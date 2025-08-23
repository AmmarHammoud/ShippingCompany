<?php

namespace App\Http\Services;

use App\Models\Shipment;
use App\Models\Driver;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Center;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportService
{
    public function getFinancialReport($filters = [])
    {
        try {
            // فلترة حسب التاريخ إذا كان موجودًا
            $query = Shipment::with(['client', 'recipient', 'centerFrom', 'centerTo']);
            
            if (!empty($filters['start_date'])) {
                $query->where('created_at', '>=', $filters['start_date']);
            }
            
            if (!empty($filters['end_date'])) {
                $query->where('created_at', '<=', $filters['end_date']);
            }
            
            if (!empty($filters['center_id'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('center_from_id', $filters['center_id'])
                      ->orWhere('center_to_id', $filters['center_id']);
                });
            }
            
            $shipments = $query->get();
            
            // حساب الإحصائيات المالية
            $totalRevenue = $shipments->sum('delivery_price');
            $totalProductValue = $shipments->sum('product_value');
            $totalAmount = $shipments->sum('total_amount');
            
            // نفترض أن التكلفة يمكن حسابها كنسبة من delivery_price أو حقل منفصل إذا كان موجودًا
            // إذا لم يكن حقل cost موجودًا، يمكننا افتراض نسبة ثابتة كتكلفة (مثال: 70% من delivery_price)
            $totalCost = $shipments->sum(function($shipment) {
                // إذا كان لديك حقل cost في الجدول، استبدل هذا بالحقل الفعلي
                return $shipment->delivery_price * 0.7;
            });
            
            $totalProfit = $totalRevenue - $totalCost;
            
            // عدد الشحنات حسب الحالة
            $shipmentsByStatus = $shipments->groupBy('status')->map->count();
            
            // الشحنات حسب النوع
            $shipmentsByType = $shipments->groupBy('shipment_type')->map->count();
            
            // الشحنات الأكثر ربحية
            $mostProfitableShipments = $shipments->sortByDesc(function($shipment) {
                $cost = $shipment->delivery_price * 0.7;
                return $shipment->delivery_price - $cost;
            })->take(10)->values();
            
            return [
                'success' => true,
                'data' => [
                    'period' => [
                        'start_date' => $filters['start_date'] ?? 'بداية التسجيلات',
                        'end_date' => $filters['end_date'] ?? 'نهاية التسجيلات'
                    ],
                    'financial_summary' => [
                        'total_revenue' => $totalRevenue,
                        'total_product_value' => $totalProductValue,
                        'total_amount' => $totalAmount,
                        'total_cost' => $totalCost,
                        'total_profit' => $totalProfit,
                        'profit_margin' => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0
                    ],
                    'shipments_count' => $shipments->count(),
                    'shipments_by_status' => $shipmentsByStatus,
                    'shipments_by_type' => $shipmentsByType,
                    'most_profitable_shipments' => $mostProfitableShipments->map(function($shipment) {
                        return [
                            'id' => $shipment->id,
                            'invoice_number' => $shipment->invoice_number,
                            'delivery_price' => $shipment->delivery_price,
                            'estimated_cost' => $shipment->delivery_price * 0.7,
                            'estimated_profit' => $shipment->delivery_price * 0.3,
                            'client' => $shipment->client->name ?? 'غير معروف',
                            'status' => $shipment->status
                        ];
                    }),
                    'filters' => $filters
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error generating financial report: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء التقرير المالي',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function getDashboardStats($period = 'month')
    {
        try {
            // تحديد الفترة الزمنية
            $startDate = match($period) {
                'day' => Carbon::now()->subDay(),
                'week' => Carbon::now()->subWeek(),
                'month' => Carbon::now()->subMonth(),
                'year' => Carbon::now()->subYear(),
                default => Carbon::now()->subMonth()
            };
            
            // إحصائيات الشحنات
            $totalShipments = Shipment::count();
            $recentShipments = Shipment::where('created_at', '>=', $startDate)->count();
            
            // إحصائيات السائقين (نفترض أن السائقين هم users لديهم role driver)
            $totalDrivers = User::where('role', 'driver')->count();
            $newDrivers = User::where('role', 'driver')->where('created_at', '>=', $startDate)->count();
            
            // إحصائيات المقطورات
            $totalTrailers = Trailer::count();
            $activeTrailers = Trailer::where('status', 'active')->count();
            
            // الشحنات حسب الحالة
            $shipmentsByStatus = Shipment::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');
            
            // الشحنات الأخيرة
            $latestShipments = Shipment::with(['centerFrom', 'centerTo'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get()
                ->map(function($shipment) {
                    return [
                        'id' => $shipment->id,
                        'invoice_number' => $shipment->invoice_number,
                        'status' => $shipment->status,
                        'delivery_price' => $shipment->delivery_price,
                        'created_at' => $shipment->created_at,
                        'origin_center' => $shipment->centerFrom->name ?? 'غير معين',
                        'destination_center' => $shipment->centerTo->name ?? 'غير معين'
                    ];
                });
            
            return [
                'success' => true,
                'data' => [
                    'period' => $period,
                    'timeframe' => [
                        'start_date' => $startDate->format('Y-m-d H:i:s'),
                        'end_date' => Carbon::now()->format('Y-m-d H:i:s')
                    ],
                    'shipments' => [
                        'total' => $totalShipments,
                        'recent' => $recentShipments,
                        'by_status' => $shipmentsByStatus
                    ],
                    'drivers' => [
                        'total' => $totalDrivers,
                        'new' => $newDrivers
                    ],
                    'trailers' => [
                        'total' => $totalTrailers,
                        'active' => $activeTrailers
                    ],
                    'latest_shipments' => $latestShipments
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error generating dashboard stats: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب إحصائيات لوحة التحكم',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function getShipmentsReport($filters = [])
    {
        try {
            $query = Shipment::with(['centerFrom', 'centerTo', 'trailer', 'client', 'recipient', 'pickupDriver', 'deliveryDriver']);
            
            // تطبيق الفلاتر
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (!empty($filters['start_date'])) {
                $query->where('created_at', '>=', $filters['start_date']);
            }
            
            if (!empty($filters['end_date'])) {
                $query->where('created_at', '<=', $filters['end_date']);
            }
            
            if (!empty($filters['center_id'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('center_from_id', $filters['center_id'])
                      ->orWhere('center_to_id', $filters['center_id']);
                });
            }
            
            if (!empty($filters['shipment_type'])) {
                $query->where('shipment_type', $filters['shipment_type']);
            }
            
            $shipments = $query->get();
            
            // تحليل البيانات
            $analysis = [
                'by_status' => $shipments->groupBy('status')->map->count(),
                'by_center' => $shipments->groupBy('center_from_id')->map(function($group, $key) {
                    return [
                        'count' => $group->count(),
                        'center_name' => Center::find($key)->name ?? 'غير معين'
                    ];
                }),
                'by_type' => $shipments->groupBy('shipment_type')->map->count(),
                'average_weight' => $shipments->avg('weight'),
                'average_size' => $shipments->avg('size'),
                'average_delivery_price' => $shipments->avg('delivery_price'),
                'revenue_by_center' => $shipments->groupBy('center_from_id')->map(function($group, $key) {
                    return [
                        'revenue' => $group->sum('delivery_price'),
                        'center_name' => Center::find($key)->name ?? 'غير معين'
                    ];
                }),
                'profit_by_center' => $shipments->groupBy('center_from_id')->map(function($group, $key) {
                    $totalRevenue = $group->sum('delivery_price');
                    $totalCost = $group->sum(function($shipment) {
                        return $shipment->delivery_price * 0.7;
                    });
                    return [
                        'profit' => $totalRevenue - $totalCost,
                        'center_name' => Center::find($key)->name ?? 'غير معين'
                    ];
                })
            ];
            
            return [
                'success' => true,
                'data' => [
                    'shipments' => $shipments->map(function($shipment) {
                        return [
                            'id' => $shipment->id,
                            'invoice_number' => $shipment->invoice_number,
                            'barcode' => $shipment->barcode,
                            'status' => $shipment->status,
                            'shipment_type' => $shipment->shipment_type,
                            'weight' => $shipment->weight,
                            'size' => $shipment->size,
                            'delivery_price' => $shipment->delivery_price,
                            'product_value' => $shipment->product_value,
                            'total_amount' => $shipment->total_amount,
                            'created_at' => $shipment->created_at,
                            'origin_center' => $shipment->centerFrom->name ?? 'غير معين',
                            'destination_center' => $shipment->centerTo->name ?? 'غير معين',
                            'client' => $shipment->client->name ?? 'غير معروف',
                            'recipient' => $shipment->recipient->name ?? 'غير معروف'
                        ];
                    }),
                    'analysis' => $analysis,
                    'filters' => $filters,
                    'summary' => [
                        'total_shipments' => $shipments->count(),
                        'total_revenue' => $shipments->sum('delivery_price'),
                        'total_product_value' => $shipments->sum('product_value'),
                        'total_amount' => $shipments->sum('total_amount'),
                        'total_profit' => $shipments->sum('delivery_price') - $shipments->sum(function($shipment) {
                            return $shipment->delivery_price * 0.7;
                        })
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error generating shipments report: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء تقرير الشحنات',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }
}