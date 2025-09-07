<?php

namespace App\Services;

use App\Events\ShipmentHandedToCenter;
use App\Events\ShipmentOfferResponded;
use App\Models\Shipment;
use App\Models\ShipmentDriverOffer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Services\OfferShipmentToNearestDriverService;
use Illuminate\Validation\ValidationException;

class ShipmentDriverOfferService
{
    /**
     * قبول عرض (يدعم المرحلتين: pickup | delivery)
     * - pickup: نعَيّن pickup_driver_id (وتأكيد الـ QR لاحقًا عبر confirmPickupByBarcode)
     * - delivery: نعَيّن delivery_driver_id ونحوّل الحالة إلى out_for_delivery
     */
    public static function accept(int $shipmentId, string $stage = 'pickup'): Shipment
    {
        $driver = Auth::user();

        $offer = ShipmentDriverOffer::where('shipment_id', $shipmentId)
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->where('stage', $stage)
            ->firstOrFail();

        $shipment = Shipment::findOrFail($shipmentId);

        if ($stage === 'pickup') {
            if ($shipment->pickup_driver_id) {
                throw new \Exception('Pickup already assigned.');
            }

            // يمكن إبقاء الحالة كما هي (offered_pickup_driver) والانتقال إلى picked_up بعد QR
            $shipment->update([
                'pickup_driver_id' => $driver->id,
            ]);
        }

        if ($stage === 'delivery') {
            if ($shipment->delivery_driver_id) {
                throw new \Exception('Delivery already assigned.');
            }

            // تأكد أن الشحنة في حالة تسمح بالتسليم
            if (! in_array($shipment->status, ['offered_delivery_driver', 'arrived_at_destination_center'])) {
                throw new \Exception('Shipment is not ready for delivery assignment.');
            }

            $shipment->update([
                'delivery_driver_id' => $driver->id,
                'status'             => 'out_for_delivery',
            ]);
        }

        $offer->update(['status' => 'accepted']);
        event(new ShipmentOfferResponded($shipment, 'accepted'));

        // ارفض باقي العروض المعلّقة لنفس المرحلة
        ShipmentDriverOffer::where('shipment_id', $shipmentId)
            ->where('driver_id', '!=', $driver->id)
            ->where('stage', $stage)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        return $shipment->fresh();
    }

    /**
     * رفض عرض:
     * - نحترم المرحلة الفعلية للعرض (stage) الذي وجّدناه للسائق الحالي.
     * - إذا لم تُعيَّن الشحنة بعد في تلك المرحلة، نعيد عرضها على أقرب سائق تالي.
     */
    public static function reject(int $shipmentId): ?object
    {
        $driver = Auth::user();

        $offer = ShipmentDriverOffer::where('shipment_id', $shipmentId)
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->orderByDesc('id') // احتياطًا لو كان في عروض متعدّدة
            ->firstOrFail();

        $stage = $offer->stage ?? 'pickup';

        $offer->update(['status' => 'rejected']);

        $shipment = Shipment::findOrFail($shipmentId);
        event(new ShipmentOfferResponded($shipment, 'rejected'));

        // إن لم تُعيَّن بعد في نفس المرحلة، أعِد العرض بحسب المرحلة
        $notAssigned = $stage === 'pickup'
            ? is_null($shipment->pickup_driver_id)
            : is_null($shipment->delivery_driver_id);

        if ($notAssigned) {
            return OfferShipmentToNearestDriverService::offer($shipment, $stage);
        }

        return null;
    }

    /**
     * تأكيد الاستلام بالباركود (pickup)
     */
    public static function confirmPickupByBarcode(string $barcode, User $driver): Shipment
    {
        $shipment = Shipment::where('barcode', $barcode)->first();

        if (! $shipment) {
            throw ValidationException::withMessages(['barcode' => ['Shipment not found.']]);
        }

        if ($shipment->pickup_driver_id !== $driver->id) {
            throw ValidationException::withMessages(['driver' => ['Unauthorized driver for this shipment.']]);
        }

        if ($shipment->status !== 'offered_pickup_driver') {
            throw ValidationException::withMessages(['status' => ['Shipment is not ready for pickup confirmation.']]);
        }

        $shipment->update(['status' => 'picked_up']);

        return $shipment->fresh();
    }

    /**
     * تسليم الشحنة إلى المركز (بعد الالتقاط)
     */
    public static function confirmHandOverToCenter(Shipment $shipment, User $driver)
    {
        if ($shipment->pickup_driver_id !== $driver->id) {
            throw ValidationException::withMessages(['driver' => ['Unauthorized driver for this shipment.']]);
        }

        if ($shipment->status !== 'picked_up') {
            throw ValidationException::withMessages(['status' => ['Shipment status does not allow handover.']]);
        }

        $shipment->status = 'pending_at_center';
        $shipment->save();

        return $shipment->fresh();
    }

    /**
     * (اختياري) تأكيد خروج الشحنة للتسليم عبر باركود (delivery)
     * تُستخدم إذا كنت تسمح بالانتقال من offered_delivery_driver إلى out_for_delivery عبر مسح.
     * حالياً accept(stage='delivery') يجعلها out_for_delivery مباشرة، لكن نخليها idempotent.
     */
    public static function confirmOutForDeliveryByBarcode(string $barcode, User $driver): Shipment
    {
        $shipment = Shipment::where('barcode', $barcode)->first();

        if (! $shipment) {
            throw ValidationException::withMessages(['barcode' => ['Shipment not found.']]);
        }

        if ($shipment->delivery_driver_id !== $driver->id) {
            throw ValidationException::withMessages(['driver' => ['Unauthorized driver for this shipment.']]);
        }

        if (! in_array($shipment->status, ['offered_delivery_driver', 'out_for_delivery'])) {
            throw ValidationException::withMessages(['status' => ['Shipment is not in a deliverable state.']]);
        }

        if ($shipment->status === 'offered_delivery_driver') {
            $shipment->update(['status' => 'out_for_delivery']);
        }

        return $shipment->fresh();
    }

    /**
     * (اختياري) تأكيد التسليم للمستلم (يدعم OTP/توقيع/لوكيشن)
     */
    public static function confirmHandOverToRecipient(
        Shipment $shipment,
        User $driver,
        ?string $otp = null,
        array $meta = []
    ): Shipment {
        if ($shipment->delivery_driver_id !== $driver->id) {
            throw ValidationException::withMessages(['driver' => ['Unauthorized driver for this shipment.']]);
        }

        if ($shipment->status !== 'out_for_delivery') {
            throw ValidationException::withMessages(['status' => ['Shipment is not out for delivery.']]);
        }

        // تحقق OTP (إن كنت تستخدمه في جدول الشحنات)
        if ($otp !== null) {
            if (empty($shipment->delivery_otp_code) || $shipment->delivery_otp_code !== $otp) {
                throw ValidationException::withMessages(['otp' => ['Invalid OTP code.']]);
            }
        }

        $updates = [
            'status'       => 'delivered',
            'delivered_at' => now(),
        ];

        if (isset($meta['signed_url']))   $updates['recipient_signature_url'] = $meta['signed_url'];
        if (isset($meta['delivered_lat'])) $updates['delivered_lat'] = $meta['delivered_lat'];
        if (isset($meta['delivered_lng'])) $updates['delivered_lng'] = $meta['delivered_lng'];

        $shipment->update($updates);

        return $shipment->fresh();
    }

    /**
     * جلب العروض حسب الحالة، مع خيار تحديد المرحلة (بدون كسر التواقيع القديمة)
     */
    public static function getOffersByStatus(string $status, ?string $stage = null)
    {
        $driver = Auth::user();

        $q = ShipmentDriverOffer::with('shipment')
            ->where('driver_id', $driver->id)
            ->where('status', $status)
            ->latest();

        if ($stage !== null) {
            $q->where('stage', $stage);
        }

        return $q->get();
    }
}
