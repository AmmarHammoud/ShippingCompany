<?php
namespace App\Services;

use App\Models\Center;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiService
{
    public function getDashboardData(array $filters = []): array
    {
        $query = Shipment::query();

        // ✅ فلترة المراكز (تجاهل إذا كان center_id = 0)
        if (!empty($filters['center_id']) && $filters['center_id'] != 0) {
            $query->where(function ($q) use ($filters) {
                $q->where('center_from_id', $filters['center_id'])
                    ->orWhere('center_to_id', $filters['center_id']);
            });
        }

        // ✅ فلترة التاريخ
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $total = (clone $query)->count();
        $delivered = (clone $query)->where('status', 'delivered')->count();
        $cancelled = (clone $query)->where('status', 'cancelled')->count();

        $avgDeliveryTime = (clone $query)
            ->whereNotNull('delivered_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at)) as avg_time'))
            ->value('avg_time');

        $trend = (clone $query)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        return [
            'summary' => [
                'total_shipments'     => $total,
                'delivered_shipments' => $delivered,
                'cancelled_shipments' => $cancelled,
                'avg_delivery_time'   => round($avgDeliveryTime ?? 0, 2),
            ],
            'trend' => $trend,
        ];
    }
}
