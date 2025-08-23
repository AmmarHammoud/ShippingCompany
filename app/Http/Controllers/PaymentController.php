<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
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
        
        $shipment = Shipment::find($request->shipment_id);
        $user = auth()->user(); 

        if (Payment::where('shipment_id', $shipment->id)
                    ->where('status', 'succeeded')
                    ->exists()) {
            return response()->json([
                'error' => 'Shipment already paid'
            ], 400);
        }
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Shipment #' . $shipment->id,
                        ],
                        'unit_amount' => 0, //$shipment->delivery_price,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('payment.success') . '?sessionId={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payment.cancel'),
                'metadata' => [
                    'shipment_id' => $shipment->id,
                    'user_id' => $user->id
                ]
            ]);

            return response()->json([
                'session_id' => $session->id,
                'url' => $session->url
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe error: ' . $e->getMessage());
            // For debugging, return error message in development
            if (env('APP_DEBUG')) {
                return response()->json([
                    'error' => 'Payment session creation failed',
                    'message' => $e->getMessage()
                ], 500);
            }
        }
         return response()->json(['error' => 'Payment session creation failed'], 500);
    }

    // public function handleWebhook(Request $request)
    // {
    //     $payload = $request->getContent();
    //     $sigHeader = $request->header('Stripe-Signature');
    //     $event = null;

    //     try {
    //         $event = \Stripe\Webhook::constructEvent(
    //             $payload,
    //             $sigHeader,
    //             env('STRIPE_WEBHOOK_SECRET')
    //         );
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Invalid signature'], 403);
    //     }

    //     // Handle successful payment
    //     if ($event->type == 'checkout.session.completed') {
    //         $session = $event->data->object;

    //         Payment::create([
    //             'user_id' => $session->metadata->user_id,
    //             'shipment_id' => $session->metadata->shipment_id,
    //             'stripe_payment_id' => $session->payment_intent,
    //             'amount' => $session->amount_total,
    //             'currency' => $session->currency,
    //             'status' => $session->payment_status,
    //         ]);

    //          Shipment::find($session->metadata->shipment_id)->update(['status' => 'paid']);
    //     }

    //     return response()->json(['status' => 'success']);
    // }

    public function success(Request $request)
    {
        $sessionId = $request->sessionId;
        Stripe::setApiKey(config('services.stripe.secret'));
        $session = Session::retrieve($sessionId);
        
        Payment::create([
            'user_id' => $session->metadata->user_id,
            'shipment_id' => $session->metadata->shipment_id,
            'stripe_payment_id' => $session->payment_intent,
            'amount' => $session->amount_total / 100,
            'currency' => $session->currency,
            'status' => $session->payment_status,
        ]);
        Shipment::find($session->metadata->shipment_id)->update(['status' => 'paid']);

        return Response::success('payment has been done successfully.', []);
    }
     public function cancel()
    {
        return Response::success('payment has been canceled successfully.', []);
    }
}