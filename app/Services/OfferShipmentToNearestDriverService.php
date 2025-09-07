<?php

namespace App\Services;

use App\Events\ShipmentOfferedToDriver;
use App\Models\Shipment;
use App\Models\User;
use App\Models\ShipmentDriverOffer;

class OfferShipmentToNearestDriverService
{
    /**
     * عرض الشحنة على أقرب سائق حسب المرحلة:
     * - pickup: الأقرب لموقع المرسل
     * - delivery: أقل "كلفة مسار" = مسافة (المركز→السائق) + مسافة (السائق→المستلم)
     *
     * تُعيد أول سائق لم يُعرض عليه مسبقًا (stage + shipment) وتُنشئ له عرضًا بحالة pending.
     */
    public static function offer(Shipment $shipment, string $stage = 'pickup'): ?User
    {
        $centerField = $stage === 'pickup' ? 'center_from_id' : 'center_to_id';
        $driverField = $stage === 'pickup' ? 'pickup_driver_id' : 'delivery_driver_id';

        // إذا الشحنة معيّن لها سائق لهذيك المرحلة، لا نعيد عرضها
        if ($shipment->{$driverField}) {
            return null;
        }

        // جلب السائقين ضمن نفس المركز (كما هو في كودك الأصلي) + شروط الاعتماد/التفعيل
        $drivers = User::where('role', 'driver')
            ->where('is_approved', true)
            ->where('active', true)
            ->where('center_id', $shipment->{$centerField})
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        // ===== تحديد نقاط الحساب حسب المرحلة =====
        if ($stage === 'pickup') {
            // الالتقاط: نفس منطقك القديم (من موقع المرسل إلى السائق)
            $sourceLat = $shipment->sender_lat;
            $sourceLng = $shipment->sender_lng;

            $sortedDrivers = $drivers->sortBy(function ($driver) use ($sourceLat, $sourceLng) {
                return self::calculateDistance(
                    $sourceLat,
                    $sourceLng,
                    $driver->latitude,
                    $driver->longitude
                );
            });
        } else {
            // التسليم: كلفة المسار = (المركز→السائق) + (السائق→المستلم)
            $centerLat = $shipment->centerTo?->latitude;   // قد تكون null
            $centerLng = $shipment->centerTo?->longitude;  // قد تكون null

            $recipientLat = $shipment->recipient_lat;
            $recipientLng = $shipment->recipient_lng;

            $sortedDrivers = $drivers->sortBy(function ($driver) use ($centerLat, $centerLng, $recipientLat, $recipientLng) {
                // مسافة السائق ← المستلم (ضرورية)
                $dDriverToRecipient = self::calculateDistance(
                    $driver->latitude,
                    $driver->longitude,
                    $recipientLat,
                    $recipientLng
                );

                // مسافة المركز ← السائق (اختيارية؛ قد لا تتوفر إحداثيات المركز)
                $dCenterToDriver = null;
                if (!is_null($centerLat) && !is_null($centerLng)) {
                    $dCenterToDriver = self::calculateDistance(
                        $centerLat,
                        $centerLng,
                        $driver->latitude,
                        $driver->longitude
                    );
                }

                // إن كانت إحداثيات المركز غير متوفرة، نستخدم فقط السائق→المستلم
                $cost = ($dCenterToDriver === null)
                    ? $dDriverToRecipient
                    : ($dCenterToDriver + $dDriverToRecipient);

                // لو بتحب المتوسط بدل الجمع، نفس الترتيب: $cost / 2
                return $cost;
            });
        }

        // إنشاء أول عرض لسائق لم يُعرض عليه من قبل (لنفس الشحنة والمرحلة)
        foreach ($sortedDrivers as $driver) {
            $alreadyOffered = ShipmentDriverOffer::where('shipment_id', $shipment->id)
                ->where('driver_id', $driver->id)
                ->where('stage', $stage)
                ->exists();

            if (! $alreadyOffered) {
                ShipmentDriverOffer::create([
                    'shipment_id' => $shipment->id,
                    'driver_id'   => $driver->id,
                    'stage'       => $stage,
                    'status'      => 'pending',
                ]);

                // إشعار/حدث كما في كودك الأصلي
                event(new ShipmentOfferedToDriver($shipment, $driver->id));

                return $driver;
            }
        }

        return null;
    }

    /**
     * دالة حساب المسافة بين نقطتين (Haversine) بالكيلومتر
     */
    public static function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        // حماية بسيطة لو أي إحداثية ناقصة
        if (is_null($lat1) || is_null($lng1) || is_null($lat2) || is_null($lng2)) {
            // قيمة كبيرة جدًا حتى تُقصى من الترتيب
            return INF;
        }

        $earthRadius = 6371; // كم
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
