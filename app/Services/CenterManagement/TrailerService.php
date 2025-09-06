<?php

namespace App\Services\CenterManagement;

use App\Models\Center;
use App\Models\Trailer;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Resources\ShipmentResource;
use App\Http\Resources\TrailerResource;

class TrailerService
{
    public function getAvailableTrailersByCenter($centerId)
    {
        try {
            $trailers = Trailer::where('status', 'available')
                ->where('center_id', $centerId)
                ->with(['center', 'shipments' => function ($query) {
                    $query->whereIn('status', ['in_transit_between_centers', 'picked_up']);
                }])
                ->get();

            $availableTrailers = [];

            foreach ($trailers as $trailer) {
                $usedWeight = $trailer->shipments->sum('weight');
                $usedSize = $trailer->shipments->sum('size');

                $availableWeight = $trailer->capacity_kg - $usedWeight;
                $availableSize = $trailer->capacity_m3 - $usedSize;

                if ($availableWeight > 0 && $availableSize > 0) {
                    $availableTrailers[] = [
                        'trailer' => $trailer,
                        'used_weight' => $usedWeight,
                        'used_size' => $usedSize,
                        'available_weight' => $availableWeight,
                        'available_size' => $availableSize,
                        'utilization_percentage' => [
                            'weight' => round(($usedWeight / $trailer->capacity_kg) * 100, 2),
                            'size' => round(($usedSize / $trailer->capacity_m3) * 100, 2)
                        ]
                    ];
                }
            }

            return [
                'success' => true,
                'data' => $availableTrailers
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching available trailers by center: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch available trailers for center',
                'error' => $e->getMessage()
            ];
        }
    }

     public function getIncomingTrailers()
     {
         try {
             $centerId = Auth::user()->center->id;
             $incomingTrailers = Trailer::whereHas('shipments', function ($query) use ($centerId) {
                 $query->where('center_to_id', $centerId)
                     ->where('status', 'in_transit_between_centers');
             })->with(['shipments' => function ($query) use ($centerId) {
                 $query->where('center_to_id', $centerId)
                     ->where('status', 'in_transit_between_centers');
             }, 'center'])->get();

             return [
                 'success' => true,
                 'message' => 'تم جلب الشاحنات الواردة بنجاح',
                 'centerId' => $centerId,
                 'data' => [
                     'incoming_trailers' => $incomingTrailers,
                     'count' => $incomingTrailers->count(),
                     'centerId' => $centerId
                 ]
             ];
         } catch (\Exception $e) {
             Log::error('Error retrieving incoming trailers: ' . $e->getMessage());
             return [
                 'success' => false,
                 'message' => 'حدث خطأ أثناء جلب الشاحنات الواردة',
                 'error' => $e->getMessage(),
                 'status' => 500
             ];
         }
     }

    public function checkCapacity($trailerId, $shipmentId)
    {
        try {
            $trailer = Trailer::findOrFail($trailerId);
            $shipment = Shipment::findOrFail($shipmentId);

            $usedWeight = $trailer->shipments()->sum('weight');
            $usedSize = $trailer->shipments()->sum('size');

            $availableWeight = $trailer->capacity_kg - $usedWeight;
            $availableSize = $trailer->capacity_m3 - $usedSize;

            $canAdd = ($shipment->weight <= $availableWeight) &&
                ($shipment->size <= $availableSize);

            return [
                'success' => true,
                'data' => [
                    'can_add' => $canAdd,
                    'available_weight' => $availableWeight,
                    'available_size' => $availableSize,
                    'required_weight' => $shipment->weight,
                    'required_size' => $shipment->size,
                    'trailer' => $trailer->only(['id', 'name', 'capacity_kg', 'capacity_m3'])
                ]
            ];
        } catch (ModelNotFoundException $e) {
            Log::error('Model not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Trailer or shipment not found',
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Error checking capacity: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء التحقق من السعة',
                'error' => $e->getMessage()
            ];
        }
    }

    public function assignToTrailer($trailerId, $shipmentId)
    {
        try {
            DB::beginTransaction();

            $trailer = Trailer::findOrFail($trailerId);
            $shipment = Shipment::findOrFail($shipmentId);

            if ($shipment->status !== 'arrived_at_center') {
                return [
                    'success' => false,
                    'message' => 'لا يمكن إضافة الشحنة إلى الشاحنة. يجب أن تكون حالة الشحنة "arrived_at_center"',
                    'status' => 400
                ];
            }

            if($trailer->status !== 'available') {
                return [
                    'success' => false,
                    'message' => 'هذه الشاحنة تحت الصيانة، لا يمكن إضافة الشحنة إليها.',
                    'status' => 400
                ];
            }

            if($trailer->center_to_id !== null && $trailer->center_to_id != $shipment->center_to_id) {
                return [
                    'success' => false,
                    'message' => 'لا يمكن إضافة هذه الشحنة لهذه الشاحنة، لأن المركز المتوجه إليه مختلف',
                    'status' => 400
                ];
            }

            $usedWeight = $trailer->shipments()->sum('weight');
            $usedSize = $trailer->shipments()->sum('size');

            if (($usedWeight + $shipment->weight > $trailer->capacity_kg) ||
                ($usedSize + $shipment->size > $trailer->capacity_m3)) {
                return [
                    'success' => false,
                    'message' => 'لا توجد سعة كافية في الشاحنة',
                    'data' => [
                        'available_weight' => $trailer->capacity_kg - $usedWeight,
                        'available_size' => $trailer->capacity_m3 - $usedSize,
                        'required_weight' => $shipment->weight,
                        'required_size' => $shipment->size
                    ],
                    'status' => 400
                ];
            }
            $trailer->center_to_id = $shipment->center_to_id;
            $trailer->save();

            $shipment->trailer_id = $trailerId;
            $shipment->status = 'assigned_to_trailer';
            $shipment->save();

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم إضافة الشحنة إلى الشاحنة بنجاح',
                'data' => [
                    'trailer' => $trailer,
                    'remaining_capacity' => [
                        'weight' => $trailer->capacity_kg - ($usedWeight + $shipment->weight),
                        'size' => $trailer->capacity_m3 - ($usedSize + $shipment->size)
                    ]
                ]
            ];
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Model not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'الشاحنة أو الشحنة غير موجودة',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning to trailer: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة الشحنة إلى الشاحنة',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function transferTrailer($trailerId)
    {
        try {
            DB::beginTransaction();

            $trailer = Trailer::findOrFail($trailerId);

            if ($trailer->shipments()->count() === 0) {
                return [
                    'success' => false,
                    'message' => 'لا يمكن نقل شاحنة فارغة',
                    'status' => 400
                ];
            }

            $trailer->shipments()->update([
                'status' => 'in_transit_between_centers',
            ]);

            $trailer->center_to_id = $trailer->shipments[0]->center_to_id;
            $trailer->save();

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم إرسال الشاحنة إلى المركز الجديد بنجاح',
                'data' => [
                    'trailer' => $trailer->load('shipments', 'center')
                ]
            ];
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Model not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'الشاحنة غير موجودة',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error transferring trailer: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إرسال الشاحنة',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function arrivedTrailer($trailerId)
    {
        try {
            DB::beginTransaction();

            $trailer = Trailer::findOrFail($trailerId);

            $trailer->center_id = $trailer->shipments[0]->center_to_id;
            $trailer->save();

            $trailer->shipments()->update([
                'status' => 'arrived_at_destination_center',
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم استلام الشحنة بنجاح',
                'data' => [
                    'trailer' => $trailer->load('shipments', 'center')
                ]
            ];
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Model not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'الشاحنة غير موجودة',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error transferring trailer: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء نقل الشاحنة',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function removeFromTrailer(Request $request, $trailerId, $shipmentId)
    {
        try {
            DB::beginTransaction();

            $trailer = Trailer::findOrFail($trailerId);
            $shipment = Shipment::findOrFail($shipmentId);

            if ($shipment->trailer_id != $trailerId) {
                return [
                    'success' => false,
                    'message' => 'الشحنة غير موجودة في هذه الشاحنة',
                    'status' => 404
                ];
            }

            $shipment->trailer_id = null;
            $shipment->status = $request->new_status ?? 'arrived_at_destination_center';
            $shipment->save();

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم إزالة الشحنة من الشاحنة بنجاح',
                'data' => [
                    'shipment' => $shipment,
                    'trailer' => $trailer->load('shipments')
                ]
            ];
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Model not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'الشاحنة أو الشحنة غير موجودة',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error removing from trailer: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إزالة الشحنة من الشاحنة',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function getTrailerShipments($trailerId)
    {
        try {
            $trailer = Trailer::with(['shipments', 'center'])->findOrFail($trailerId);

            $usedWeight = $trailer->shipments->sum('weight');
            $usedSize = $trailer->shipments->sum('size');
            $shipments = $trailer->shipments;
            return [
                'success' => true,
                'data' => [
                    'trailer' => TrailerResource::make($trailer),
                    'shipments' => ShipmentResource::collection($shipments),
                    'capacity_usage' => [
                        'weight' => $usedWeight,
                        'size' => $usedSize,
                        'remaining_weight' => $trailer->capacity_kg - $usedWeight,
                        'remaining_size' => $trailer->capacity_m3 - $usedSize,
                        'weight_percentage' => round(($usedWeight / $trailer->capacity_kg) * 100, 2),
                        'size_percentage' => round(($usedSize / $trailer->capacity_m3) * 100, 2)
                    ],
                ]
            ];
        } catch (ModelNotFoundException $e) {
            Log::error('Trailer not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'الشاحنة غير موجودة',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Error getting trailer shipments: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب بيانات الشاحنة',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }
}
