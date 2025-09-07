<?php

namespace App\Services\CenterManagement;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DriverService
{
    public function createDriver(array $data)
    {
        try {
            DB::beginTransaction();

            // إنشاء المستخدم (السائق)
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'driver',
                'center_id' => Auth::user()->center->id,
                'email_verified_at' => now(),
                'is_approved' => $data['is_approved'] ?? true,
                'active' => $data['active'] ?? true,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);

            $user->assignRole('driver');


            DB::commit();

            return [
                'success' => true,
                'message' => 'تم إنشاء السائق بنجاح',
                'data' => [
                    'driver' => $user->load('roles')
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating driver: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء السائق',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function updateDriver($driverId, array $data)
    {
        try {
            DB::beginTransaction();

            $driver = User::role('driver')->findOrFail($driverId);

            // تحديث بيانات السائق
            $updateData = [];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (isset($data['email'])) $updateData['email'] = $data['email'];
            if (isset($data['phone'])) $updateData['phone'] = $data['phone'];
            if (isset($data['center_id'])) $updateData['center_id'] = $data['center_id'];
            if (isset($data['is_approved'])) $updateData['is_approved'] = $data['is_approved'];
            if (isset($data['active'])) $updateData['active'] = $data['active'];
            if (isset($data['latitude'])) $updateData['latitude'] = $data['latitude'];
            if (isset($data['longitude'])) $updateData['longitude'] = $data['longitude'];

            if (isset($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $driver->update($updateData);

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم تحديث بيانات السائق بنجاح',
                'data' => [
                    'driver' => $driver->load('roles', 'center')
                ]
            ];
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Driver not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'السائق غير موجود',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating driver: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث بيانات السائق',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function deleteDriver($driverId)
    {
        try {
            DB::beginTransaction();

            $driver = User::role('driver')->findOrFail($driverId);

            // التحقق من عدم وجود شحنات مرتبطة بالسائق
            $hasPickupShipments = DB::table('shipments')->where('pickup_driver_id', $driverId)->exists();
            $hasDeliveryShipments = DB::table('shipments')->where('delivery_driver_id', $driverId)->exists();

            if ($hasPickupShipments || $hasDeliveryShipments) {
                return [
                    'success' => false,
                    'message' => 'لا يمكن حذف السائق لأنه مرتبط بشحنات',
                    'status' => 400
                ];
            }

            $driver->delete();

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم حذف السائق بنجاح'
            ];
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Driver not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'السائق غير موجود',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting driver: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف السائق',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function blockDriver($driverId)
    {
        try {
            DB::beginTransaction();

            $driver = User::role('driver')->findOrFail($driverId);

            $driver->update([
                'active' => false,
            ]);

            // إلغاء أي شحنات نشطة مرتبطة بالسائق
            DB::table('shipments')
                ->where('pickup_driver_id', $driverId)
                ->whereIn('status', ['offered_pickup_driver', 'picked_up'])
                ->update([
                    'pickup_driver_id' => null,
                    'status' => 'pending'
                ]);

            DB::table('shipments')
                ->where('delivery_driver_id', $driverId)
                ->whereIn('status', ['offered_delivery_driver', 'out_for_delivery'])
                ->update([
                    'delivery_driver_id' => null,
                    'status' => 'arrived_at_destination_center'
                ]);

            $driver->currentAccessToken()?->delete();

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم حظر السائق بنجاح',
                'data' => [
                    'driver' => $driver
                ]
            ];
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Driver not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'السائق غير موجود',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error blocking driver: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء حظر السائق',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function unblockDriver($driverId)
    {
        try {
            $driver = User::role('driver')->findOrFail($driverId);

            $driver->update([
                'active' => true,
            ]);

            return [
                'success' => true,
                'message' => 'تم إلغاء حظر السائق بنجاح',
                'data' => [
                    'driver' => $driver
                ]
            ];
        } catch (ModelNotFoundException $e) {
            Log::error('Driver not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'السائق غير موجود',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Error unblocking driver: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء حظر السائق',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function approveDriver($driverId)
    {
        try {
            $driver = User::role('driver')->findOrFail($driverId);

            $driver->update([
                'is_approved' => true
            ]);

            return [
                'success' => true,
                'message' => 'تم الموافقة على السائق بنجاح',
                'data' => [
                    'driver' => $driver
                ]
            ];
        } catch (ModelNotFoundException $e) {
            Log::error('Driver not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'السائق غير موجود',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Error approving driver: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء الموافقة على السائق',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function getDriverDetails($driverId)
    {
        try {
            $driver = User::role('driver')->with(['center', 'permissions'])->findOrFail($driverId);

            // إحصائيات السائق
            $stats = [
                'total_pickups' => DB::table('shipments')->where('pickup_driver_id', $driverId)->count(),
                'total_deliveries' => DB::table('shipments')->where('delivery_driver_id', $driverId)->count(),
                'completed_shipments' => DB::table('shipments')
                    ->where(function($query) use ($driverId) {
                        $query->where('pickup_driver_id', $driverId)
                              ->orWhere('delivery_driver_id', $driverId);
                    })
                    ->where('status', 'delivered')
                    ->count(),
                'active_shipments' => DB::table('shipments')
                    ->where(function($query) use ($driverId) {
                        $query->where('pickup_driver_id', $driverId)
                              ->orWhere('delivery_driver_id', $driverId);
                    })
                    ->whereIn('status', ['offered_pickup_driver', 'picked_up', 'offered_delivery_driver', 'out_for_delivery'])
                    ->count()
            ];

            return [
                'success' => true,
                'data' => [
                    'driver' => $driver,
                    'stats' => $stats
                ]
            ];
        } catch (ModelNotFoundException $e) {
            Log::error('Driver not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'السائق غير موجود',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Error getting driver details: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تفاصيل السائق',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function getAllDrivers($filters = [])
    {
        try {
            $query = User::role('driver')->with(['center', 'permissions']);

            if (!empty($filters['center_id'])) {
                $query->where('center_id', $filters['center_id']);
            }

            if (!empty($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $query->where('active', true);
                } elseif ($filters['status'] === 'blocked') {
                    $query->where('active', false);
                }
            }

            if (!empty($filters['approved'])) {
                $query->where('is_approved', filter_var($filters['approved'], FILTER_VALIDATE_BOOLEAN));
            }

            $drivers = $query->get();

            return [
                'success' => true,
                'data' => [
                    'drivers' => $drivers,
                    'count' => $drivers->count(),
                    'stats' => [
                        'total' => User::role('driver')->count(),
                        'active' => User::role('driver')->where('active', true)->count(),
                        'blocked' => User::role('driver')->where('active', false)->count(),
                        'approved' => User::role('driver')->where('is_approved', true)->count(),
                        'pending_approval' => User::role('driver')->where('is_approved', false)->count()
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error getting all drivers: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب قائمة السائقين',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }
}
