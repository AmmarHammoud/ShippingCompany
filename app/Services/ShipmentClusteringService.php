<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ShipmentClusteringService
{
    /**
     * تجميع الشحنات بناءً على إحداثيات المستلمين باستخدام خوارزمية K-Means
     */
    public function clusterShipmentsByDestination(Collection $shipments, int $numberOfClusters): array
    {
        // استخراج الإحداثيات من الشحنات
        $coordinates = $shipments->map(function ($shipment) {
            return [
                'shipment_id' => $shipment->id,
                'lat' => $shipment->recipient_lat,
                'lng' => $shipment->recipient_lng,
                'weight' => $shipment->weight,
                'volume' => $shipment->volume // إذا كان لديك حقل الحجم
            ];
        })->toArray();

        // تطبيق خوارزمية K-Means المبسطة
        $clusters = $this->kMeansClustering($coordinates, $numberOfClusters);

        return $clusters;
    }

    /**
     * تنفيذ مبسط لخوارزمية K-Means
     */
    private function kMeansClustering(array $points, int $k, int $maxIterations = 100): array
    {
        if (count($points) === 0) {
            return [];
        }

        // تهيئة المراكز عشوائيًا
        $centroids = $this->initializeCentroids($points, $k);

        $clusters = [];
        $changed = false;
        $iterations = 0;

        do {
            $changed = false;
            $clusters = array_fill(0, $k, []);

            // تعيين كل نقطة لأقرب مركز
            foreach ($points as $point) {
                $minDistance = PHP_FLOAT_MAX;
                $clusterIndex = 0;

                foreach ($centroids as $index => $centroid) {
                    $distance = $this->calculateDistance(
                        $point['lat'], $point['lng'],
                        $centroid['lat'], $centroid['lng']
                    );

                    if ($distance < $minDistance) {
                        $minDistance = $distance;
                        $clusterIndex = $index;
                    }
                }

                $clusters[$clusterIndex][] = $point;
            }

            // إعادة حساب المراكز
            $newCentroids = [];
            foreach ($clusters as $cluster) {
                if (count($cluster) === 0) {
                    $newCentroids[] = ['lat' => 0, 'lng' => 0];
                    continue;
                }

                $avgLat = array_sum(array_column($cluster, 'lat')) / count($cluster);
                $avgLng = array_sum(array_column($cluster, 'lng')) / count($cluster);

                $newCentroids[] = ['lat' => $avgLat, 'lng' => $avgLng];
            }

            // التحقق إذا تغيرت المراكز
            for ($i = 0; $i < $k; $i++) {
                if ($this->calculateDistance(
                        $centroids[$i]['lat'], $centroids[$i]['lng'],
                        $newCentroids[$i]['lat'], $newCentroids[$i]['lng']
                    ) > 0.001) {
                    $changed = true;
                    break;
                }
            }

            $centroids = $newCentroids;
            $iterations++;
        } while ($changed && $iterations < $maxIterations);

        return $clusters;
    }

    /**
     * تهيئة المراكز الأولية عشوائيًا
     */
    private function initializeCentroids(array $points, int $k): array
    {
        $centroids = [];
        $keys = array_rand($points, min($k, count($points)));

        if (!is_array($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $key) {
            $centroids[] = [
                'lat' => $points[$key]['lat'],
                'lng' => $points[$key]['lng']
            ];
        }

        return $centroids;
    }

    /**
     * حساب المسافة بين نقطتين باستخدام صيغة Haversine
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

    /**
     * تجميع الشحنات مع مراعاة سعة المركبات (CVRP)
     */
    public function clusterShipmentsWithCapacity(Collection $shipments, float $maxCapacity): array
    {
        $clusters = [];
        $currentCluster = [];
        $currentLoad = 0;

        // ترتيب الشحنات حسب المنطقة أولاً
        $sortedShipments = $shipments->sortBy([
            ['recipient_lat', 'asc'],
            ['recipient_lng', 'asc']
        ]);

        foreach ($sortedShipments as $shipment) {
            $shipmentLoad = $shipment->weight; // يمكن إضافة الحجم أيضًا

            if ($currentLoad + $shipmentLoad <= $maxCapacity) {
                $currentCluster[] = $shipment;
                $currentLoad += $shipmentLoad;
            } else {
                if (!empty($currentCluster)) {
                    $clusters[] = $currentCluster;
                }

                $currentCluster = [$shipment];
                $currentLoad = $shipmentLoad;
            }
        }

        if (!empty($currentCluster)) {
            $clusters[] = $currentCluster;
        }

        return $clusters;
    }
}
