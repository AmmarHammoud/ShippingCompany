<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use App\Models\ShipmentDriverOffer;
use App\Events\BatchOfferedToDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BatchDistributionService
{
    protected $clusteringService;

    public function __construct(ShipmentClusteringService $clusteringService)
    {
        $this->clusteringService = $clusteringService;
    }

    /**
     * توزيع الشحنات الواردة على السائقين بناءً على التجميع الجغرافي
     */
    public function distributeShipmentsToDrivers(Collection $shipments, string $centerId): array
    {
        // تحديد عدد الدفعات بناءً على عدد السائقين المتاحين
        $availableDrivers = User::where('role', 'driver')
            ->where('center_id', $centerId)
            ->where('status', 'available')
            ->count();

        if ($availableDrivers === 0) {
            throw new \Exception('No available drivers at this center');
        }

        // تجميع الشحنات
        $clusters = $this->clusteringService->clusterShipmentsByDestination($shipments, $availableDrivers);

        $results = [];
        $driverIndex = 0;

        // الحصول على قائمة السائقين المتاحين
        $drivers = User::where('role', 'driver')
            ->where('center_id', $centerId)
            ->where('status', 'available')
            ->get();

        foreach ($clusters as $cluster) {
            if (empty($cluster)) {
                continue;
            }

            if ($driverIndex >= count($drivers)) {
                // إذا كان عدد الدفعات أكثر من السائقين، ندمج الدفعات المتبقية
                $this->handleRemainingClusters($clusters, $drivers, $results, $driverIndex);
                break;
            }

            $driver = $drivers[$driverIndex];
            $batchResult = $this->offerBatchToDriver($cluster, $driver, 'delivery');

            $results[] = $batchResult;
            $driverIndex++;
        }

        return $results;
    }

    /**
     * معالجة الدفعات المتبقية عندما يكون عددها أكثر من السائقين
     */
    private function handleRemainingClusters(array $clusters, Collection $drivers, array &$results, int $driverIndex): void
    {
        $remainingClusters = array_slice($clusters, $driverIndex);
        $mergedCluster = [];

        foreach ($remainingClusters as $cluster) {
            $mergedCluster = array_merge($mergedCluster, $cluster);
        }

        // توزيع الدفعة المدمجة على السائق الأقرب
        $centroid = $this->calculateClusterCentroid($mergedCluster);
        $nearestDriver = $this->findNearestDriver($centroid['lat'], $centroid['lng'], $drivers);

        if ($nearestDriver) {
            $batchResult = $this->offerBatchToDriver($mergedCluster, $nearestDriver, 'delivery');
            $results[] = $batchResult;
        }
    }

    /**
     * تقديم دفعة من الشحنات إلى سائق
     */
    public function offerBatchToDriver(array $batch, User $driver, string $stage): array
    {
        DB::beginTransaction();

        try {
            $shipmentIds = [];

            foreach ($batch as $shipmentData) {
                $shipmentId = is_array($shipmentData) ? $shipmentData['shipment_id'] : $shipmentData->id;

                // إنشاء عرض للسائق لكل شحنة في الدفعة
                ShipmentDriverOffer::create([
                    'shipment_id' => $shipmentId,
                    'driver_id' => $driver->id,
                    'stage' => $stage,
                    'status' => 'pending',
                    'offered_at' => now(),
                ]);

                $shipmentIds[] = $shipmentId;

                // تحديث حالة الشحنة
                Shipment::where('id', $shipmentId)->update([
                    'status' => 'offered_delivery_driver'
                ]);
            }

            DB::commit();

            // triggering event لإشعار السائق
            event(new BatchOfferedToDriver($driver, $shipmentIds, $stage));

            return [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'batch_size' => count($batch),
                'shipment_ids' => $shipmentIds,
                'status' => 'offered'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * حساب المركز الجغرافي لمجموعة من الشحنات
     */
    public function calculateClusterCentroid(array $cluster): array
    {
        if (empty($cluster)) {
            return ['lat' => 0, 'lng' => 0];
        }

        $totalLat = 0;
        $totalLng = 0;
        $count = count($cluster);

        foreach ($cluster as $point) {
            $totalLat += is_array($point) ? $point['lat'] : $point->recipient_lat;
            $totalLng += is_array($point) ? $point['lng'] : $point->recipient_lng;
        }

        return [
            'lat' => $totalLat / $count,
            'lng' => $totalLng / $count
        ];
    }

    /**
     * إيجاد السائق الأقرب إلى نقطة معينة
     */
    public function findNearestDriver(float $lat, float $lng, Collection $drivers): ?User
    {
        $nearestDriver = null;
        $shortestDistance = PHP_FLOAT_MAX;

        foreach ($drivers as $driver) {
            if (!$driver->current_lat || !$driver->current_lng) {
                continue;
            }

            $distance = $this->calculateDistance(
                $lat, $lng,
                $driver->current_lat, $driver->current_lng
            );

            if ($distance < $shortestDistance) {
                $shortestDistance = $distance;
                $nearestDriver = $driver;
            }
        }

        return $nearestDriver;
    }

    /**
     * حساب المسافة بين نقطتين
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // نصف قطر الأرض بالكيلومترات

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
}
