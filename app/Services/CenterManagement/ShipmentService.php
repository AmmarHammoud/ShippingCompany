<?php

namespace App\Http\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

class ShipmentService
{
    public static function cancel(int $shipmentId, $isAdmin = false): Shipment
    {
        $query = Shipment::where('id', $shipmentId);
        
        if (!$isAdmin) {
            $query->where('client_id', Auth::id());
        }
        
        $shipment = $query->firstOrFail();

        if (!in_array($shipment->status, ['pending', 'offered_pickup_driver'])) {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel shipment after it has been picked up.']
            ]);
        }

        $shipment->update([
            'status' => 'cancelled',
        ]);

        return $shipment;
    }

    public function getShipmentsForCenterManager($centerManagerId, $filters = [])
    {
        try {
            $centerManager = User::with('center')->findOrFail($centerManagerId);
            
            if (!$centerManager->center_id) {
                return [
                    'success' => false,
                    'message' => 'لم يتم تعيين مركز لهذا المدير',
                    'status' => 400
                ];
            }

            $centerId = $centerManager->center_id;

            // بناء الاستعلام
            $query = Shipment::with([
                'client', 
                'recipient', 
                'centerFrom', 
                'centerTo', 
                'pickupDriver', 
                'deliveryDriver', 
                'trailer'
            ])->where(function(Builder $q) use ($centerId) {
                $q->where('center_from_id', $centerId)
                  ->orWhere('center_to_id', $centerId);
            });

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['start_date'])) {
                $query->where('created_at', '>=', $filters['start_date']);
            }

            if (!empty($filters['end_date'])) {
                $query->where('created_at', '<=', $filters['end_date']);
            }

            if (!empty($filters['shipment_type'])) {
                $query->where('shipment_type', $filters['shipment_type']);
            }

            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function(Builder $q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%")
                      ->orWhereHas('client', function(Builder $clientQuery) use ($search) {
                          $clientQuery->where('name', 'like', "%{$search}%")
                                     ->orWhere('email', 'like', "%{$search}%")
                                     ->orWhere('phone', 'like', "%{$search}%");
                      })
                      ->orWhereHas('recipient', function(Builder $recipientQuery) use ($search) {
                          $recipientQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%")
                                        ->orWhere('phone', 'like', "%{$search}%");
                      });
                });
            }

            // الترتيب والتقسيم
            $perPage = $filters['per_page'] ?? 15;
            $shipments = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // إحصائيات إضافية
            $stats = [
                'total' => Shipment::where(function(Builder $q) use ($centerId) {
                    $q->where('center_from_id', $centerId)
                      ->orWhere('center_to_id', $centerId);
                })->count(),
                
                'by_status' => Shipment::where(function(Builder $q) use ($centerId) {
                    $q->where('center_from_id', $centerId)
                      ->orWhere('center_to_id', $centerId);
                })->select('status', DB::raw('count(*) as count'))
                  ->groupBy('status')
                  ->get()
                  ->pluck('count', 'status'),
                  
                'revenue' => Shipment::where(function(Builder $q) use ($centerId) {
                    $q->where('center_from_id', $centerId)
                      ->orWhere('center_to_id', $centerId);
                })->sum('delivery_price')
            ];

            return [
                'success' => true,
                'data' => [
                    'shipments' => $shipments,
                    'stats' => $stats,
                    'center' => $centerManager->center,
                    'filters' => $filters
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error getting shipments for center manager: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الشحنات',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function getCenterShipmentStats($centerManagerId, $period = 'month')
    {
        try {
            $centerManager = User::findOrFail($centerManagerId);
            
            if (!$centerManager->center_id) {
                return [
                    'success' => false,
                    'message' => 'لم يتم تعيين مركز لهذا المدير',
                    'status' => 400
                ];
            }

            $centerId = $centerManager->center_id;
            $startDate = match($period) {
                'day' => now()->subDay(),
                'week' => now()->subWeek(),
                'month' => now()->subMonth(),
                'year' => now()->subYear(),
                default => now()->subMonth()
            };

            $stats = [
                'total_shipments' => Shipment::where(function(Builder $q) use ($centerId) {
                    $q->where('center_from_id', $centerId)
                      ->orWhere('center_to_id', $centerId);
                })->count(),
                
                'recent_shipments' => Shipment::where(function(Builder $q) use ($centerId) {
                    $q->where('center_from_id', $centerId)
                      ->orWhere('center_to_id', $centerId);
                })->where('created_at', '>=', $startDate)->count(),
                
                'revenue' => Shipment::where(function(Builder $q) use ($centerId) {
                    $q->where('center_from_id', $centerId)
                      ->orWhere('center_to_id', $centerId);
                })->where('created_at', '>=', $startDate)->sum('delivery_price'),
                
                'by_status' => Shipment::where(function(Builder $q) use ($centerId) {
                    $q->where('center_from_id', $centerId)
                      ->orWhere('center_to_id', $centerId);
                })->where('created_at', '>=', $startDate)
                  ->select('status', DB::raw('count(*) as count'))
                  ->groupBy('status')
                  ->get()
                  ->pluck('count', 'status')
            ];

            return [
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'period' => $period,
                    'start_date' => $startDate,
                    'end_date' => now()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error getting center shipment stats: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب إحصائيات الشحنات',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }
}