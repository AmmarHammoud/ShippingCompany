<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use App\Models\Shipment;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use App\Http\Responses\Response;

class PaymentController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
        ]);

        $shipment = Shipment::findOrFail($request->shipment_id);
        $user = auth()->user();

        if ($shipment->client_id !== $user->id) {
            return response()->json([
                'error' => 'Unauthorized access to shipment'
            ], 403);
        }

        if (Payment::where('shipment_id', $shipment->id)
            ->where('status', 'succeeded')
            ->exists()) {
            return response()->json([
                'error' => 'Shipment already paid'
            ], 400);
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $isMobile = $request->header('X-Platform') === 'mobile' || $request->input('platform') === 'mobile';

            if ($isMobile) {
                // 🔹 Create PaymentIntent for mobile flow
                $paymentIntent = PaymentIntent::create([
                    'amount' => $shipment->delivery_price * 100, // amount in cents
                    'currency' => 'usd',
                    'metadata' => [
                        'shipment_id' => $shipment->id,
                        'user_id' => $user->id,
                    ],
                    'receipt_email' => $user->email,
                    'automatic_payment_methods' => [
                        'enabled' => true, // supports Apple Pay, Google Pay, etc.
                    ],
                ]);

                // Save in DB
                Payment::create([
                    'user_id' => $user->id,
                    'shipment_id' => $shipment->id,
                    'stripe_payment_id' => $paymentIntent->id,
                    'amount' => $shipment->delivery_price,
                    'currency' => 'usd',
                    'status' => 'pending',
                ]);

                return response()->json([
                    'payment_intent_id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret,
                    'publishable_key' => env('STRIPE_KEY'),
                    'amount' => $shipment->delivery_price,
                    'currency' => 'usd',
                ]);
            } else {
                // 🔹 Keep Checkout Session for web flow
                $session = Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'Shipment #' . $shipment->id,
                                'description' => 'Shipment delivery service',
                            ],
                            'unit_amount' => $shipment->delivery_price * 100,
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'success_url' => route('payment.success') . '?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('payment.cancel') . '?shipment_id=' . $shipment->id,
                    'metadata' => [
                        'shipment_id' => $shipment->id,
                        'user_id' => $user->id
                    ],
                    'customer_email' => $user->email,
                ]);

                Payment::create([
                    'user_id' => $user->id,
                    'shipment_id' => $shipment->id,
                    'stripe_session_id' => $session->id,
                    'amount' => $shipment->delivery_price,
                    'currency' => 'usd',
                    'status' => 'pending',
                ]);

                return response()->json([
                    'session_id' => $session->id,
                    'url' => $session->url,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Stripe error: ' . $e->getMessage());
            Log::error('Stripe error trace: ', ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'error' => 'Payment session creation failed',
                'message' => env('APP_DEBUG') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid Stripe webhook payload: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid Stripe webhook signature: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;

                if ($session->payment_status === 'paid') {
                    $this->handleSuccessfulPayment($session);
                }
                break;
            case 'checkout.session.async_payment_succeeded':
                $session = $event->data->object;
                $this->handleSuccessfulPayment($session);
                break;
            case 'checkout.session.async_payment_failed':
                $session = $event->data->object;
                $this->handleFailedPayment($session);
                break;
        }

        return response()->json(['status' => 'success']);
    }

    protected function handleSuccessfulPayment($session)
    {
        $payment = Payment::where('stripe_session_id', $session->id)->first();

        if ($payment && $payment->status !== 'succeeded') {
            $payment->update([
                'stripe_payment_id' => $session->payment_intent,
                'status' => 'succeeded',
                'amount' => $session->amount_total / 100,
            ]);

            Shipment::where('id', $session->metadata->shipment_id)
                ->update(['status' => 'paid']);

            Log::info('Payment succeeded for session: ' . $session->id);
        }
    }

    protected function handleFailedPayment($session)
    {
        $payment = Payment::where('stripe_session_id', $session->id)->first();

        if ($payment) {
            $payment->update([
                'status' => 'failed',
            ]);

            Log::error('Payment failed for session: ' . $session->id);
        }
    }

    public function checkPaymentStatus(Request $request)
    {
        $request->validate([
            'session_id' => 'required'
        ]);

        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $session = Session::retrieve($request->session_id);
            $payment = Payment::where('stripe_session_id', $request->session_id)->first();

            if (!$payment) {
                return response()->json(['error' => 'Payment not found'], 404);
            }

            return response()->json([
                'status' => $payment->status,
                'payment_intent' => $session->payment_intent,
                'payment_status' => $session->payment_status
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving payment status: ' . $e->getMessage());
            return response()->json(['error' => 'Unable to retrieve payment status'], 500);
        }
    }

    public function success(Request $request)
    {
        $request->validate([
            'session_id' => 'required'
        ]);

        $payment = Payment::where('stripe_session_id', $request->session_id)->first();

        if (!$payment) {
            return Response::error('Payment not found', [], 404);
        }
        $payment->update(['status' => 'paid']);

        return Response::success('Payment has been processed successfully.', [
            'status' => $payment->status,
            'shipment_id' => $payment->shipment_id
        ]);
    }

    public function cancel(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id'
        ]);

        $payment = Payment::where('shipment_id', $request->shipment_id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($payment) {
            $payment->update(['status' => 'canceled']);
        }

        return Response::success('Payment has been canceled.', []);
    }
}
