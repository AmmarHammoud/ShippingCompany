<?php

namespace App\Services\CenterManagement;

use App\Models\Trailer;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TrailerService
{
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

            if ($shipment->status !== 'picked_up') {
                return [
                    'success' => false,
                    'message' => 'لا يمكن إضافة الشحنة إلى الشاحنة. يجب أن تكون حالة الشحنة "picked_up"',
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

            $shipment->trailer_id = $trailerId;
            $shipment->status = 'in_transit_between_centers';
            $shipment->save();

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم إضافة الشحنة إلى الشاحنة بنجاح',
                'data' => [
                    'trailer' => $trailer->load('shipments'),
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

    public function transferTrailer($trailerId, $destinationCenterId)
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

            $trailer->center_id = $destinationCenterId;
            $trailer->save();

            $trailer->shipments()->update([
                'status' => 'arrived_at_destination_center',
                'center_to_id' => $destinationCenterId
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم نقل الشاحنة إلى المركز الجديد بنجاح',
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

    public function removeFromTrailer($trailerId, $shipmentId)
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
            $shipment->status = 'arrived_at_destination_center';
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

            return [
                'success' => true,
                'data' => [
                    'trailer' => $trailer,
                    'capacity_usage' => [
                        'weight' => $usedWeight,
                        'size' => $usedSize,
                        'remaining_weight' => $trailer->capacity_kg - $usedWeight,
                        'remaining_size' => $trailer->capacity_m3 - $usedSize,
                        'weight_percentage' => round(($usedWeight / $trailer->capacity_kg) * 100, 2),
                        'size_percentage' => round(($usedSize / $trailer->capacity_m3) * 100, 2)
                    ]
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