<?php

namespace App\Http\Controllers;

use App\Exports\KPIExport;
use App\Http\Requests\StoreCenterManagerRequest;
use App\Http\Requests\StoreCenterRequest;
use App\Http\Requests\SwapCenterManagersRequest;
use App\Http\Requests\UpdateCenterManagerRequest;
use App\Http\Requests\UpdateCenterRequest;
use App\Models\Center;
use App\Models\Shipment;
use App\Models\User;
use App\Services\KpiService;
use App\Services\SuperAdminService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class SuperAdminController extends Controller
{
    protected $centerService;

    public function __construct(SuperAdminService $centerService)
    {
        $this->centerService = $centerService;
    }

    public function getAllUsers() {
        return response()->json([
            'success' => true,
            'users' => User::all()
        ], 200);
    }

    public function destroyUser($user_id) {
        return response()->json([
            'success' => User::query()->where('id', $user_id)->delete(),
            'message' => 'User has been deleted successfully',
        ], 200);
    }

    public function blockUser($user_id) {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($user_id);

            $user->update([
                'active' => false,
            ]);
            if($user->role == 'driver') {
                // إلغاء أي شحنات نشطة مرتبطة بالسائق
                DB::table('shipments')
                    ->where('pickup_driver_id', $user_id)
                    ->whereIn('status', ['offered_pickup_driver', 'picked_up'])
                    ->update([
                        'pickup_driver_id' => null,
                        'status' => 'pending'
                    ]);

                DB::table('shipments')
                    ->where('delivery_driver_id', $user_id)
                    ->whereIn('status', ['offered_delivery_driver', 'out_for_delivery'])
                    ->update([
                        'delivery_driver_id' => null,
                        'status' => 'arrived_at_destination_center'
                    ]);
            }
            $user->currentAccessToken()->delete();
            DB::commit();

            return [
                'success' => true,
                'message' => 'تم حظر المستخدم بنجاح',
                'data' => [
                    'user' => $user
                ]
            ];
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('User not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => ' User not found ',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error blocking user: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء حظر المستخدم',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function unblockUser($user_id)
    {
        try {
            $user = User::findOrFail($user_id);

            $user->update([
                'active' => true,
            ]);

            return [
                'success' => true,
                'message' => 'تم إلغاء حظر المستخدم بنجاح',
                'data' => [
                    'user' => $user
                ]
            ];
        } catch (ModelNotFoundException $e) {
            Log::error('User not found: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'المستخدم غير موجود',
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Error unblocking user: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء حظر المستخدم',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function store(StoreCenterManagerRequest $request)
    {
        $manager = SuperAdminService::create($request->validated());

        return response()->json([
            'message' => 'Center manager created successfully.',
            'manager' => $manager
        ]);
    }

    public function update(UpdateCenterManagerRequest $request, $id)
    {
        $manager = User::findOrFail($id);

        if ($manager->role !== 'center_manager') {
            return response()->json(['error' => 'This user is not a center manager.'], 422);
        }

        $data = $request->all();

        $updated = SuperAdminService::update($manager, $data);

        return response()->json([
            'message' => 'Center manager updated successfully.',
            'manager' => $updated
        ]);
    }

    public function destroy($id)
    {
        $manager = User::findOrFail($id);

        SuperAdminService::delete($manager);

        return response()->json(['message' => 'Center manager deleted.']);
    }
    public function index()
    {
      $centers = Center::with('manager')->get();


        $formatted = $centers->map(function ($center) {
            return [
                'center_id' => $center->id,
                'center_name' => $center->name,
                'latitude' => $center->latitude,
                'longitude' => $center->longitude,
                'created_at' => $center->created_at,
                'updated_at' => $center->updated_at,
                'manager' => $center->manager ? [
                    'id' => $center->manager->id,
                    'name' => $center->manager->name,
                    'email' => $center->manager->email,
                    'phone' => $center->manager->phone,
                    'role' => $center->manager->role,
                    'is_approved' => $center->manager->is_approved,
                    'active' => $center->manager->active,
                    'email_verified_at' => $center->manager->email_verified_at,
                    'created_at' => $center->manager->created_at,
                    'updated_at' => $center->manager->updated_at,
                ] :null
            ];
        });

        return response()->json([
            'centers' => $formatted
        ]);
    }
    public function manger()
    {

        $managers = User::where('role', 'center_manager')
            ->with('center')
            ->get();

        $formatted = $managers->map(function ($manager) {
            return [
                'id'                => $manager->id,
                'name'              => $manager->name,
                'email'             => $manager->email,
                'phone'             => $manager->phone,
                'role'              => $manager->role,
                'is_approved'       => $manager->is_approved,
                'active'            => $manager->active,
                'email_verified_at' => $manager->email_verified_at,
                'created_at'        => $manager->created_at,
                'updated_at'        => $manager->updated_at,
                'center'            => $manager->center ? [
                    'center_id'   => $manager->center->id,
                    'center_name' => $manager->center->name,
                    'latitude'    => $manager->center->latitude,
                    'longitude'   => $manager->center->longitude
                ] : null
            ];
        });

        return response()->json([
            'managers' => $formatted
        ]);
    }

public function storeCenter(StoreCenterRequest $request)
    {
        $center = $this->centerService->createCenter($request->validated());

        return response()->json([
            'message' => 'Center created successfully.',
            'center' => $center
        ]);
    }

    public function updateCenter(UpdateCenterRequest $request, int $id)
    {
        $center = $this->centerService->updateCenter($id, $request->validated());

        return response()->json([
            'message' => 'Center updated successfully.',
            'center' => $center
        ]);
    }

    public function deleteCenter(int $id)
    {
        try {
        $this->centerService->deleteCenter($id);

          return response()->json([
        'message' => 'Center deleted successfully.'
    ]);
} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    return response()->json([
        'error' => 'Center not found.'
    ], 404);
}
    }

    public function performanceKPIs(Request $request, KpiService $kpiService)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'center_id'  => 'required|integer|min:0', // ✅ السماح بـ 0
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');
        $centerId  = (int) $request->query('center_id');

        $filters = [
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'center_id'  => $centerId,
        ];

        $data = $kpiService->getDashboardData($filters);

        $centerName = 'All Centers';
        $managerName = 'N/A';

        // ✅ فقط لو id != 0 نجلب المركز والمدير
        if ($centerId !== 0) {
            $center = Center::with('manager')->find($centerId);
            if (!$center) {
                return response()->json(['error' => 'Center not found.'], 404);
            }
            $centerName = $center->name;
            $managerName = $center->manager->name ?? 'N/A';
        }

        if ($request->query('export') === 'excel') {
            return Excel::download(new KpiExport($filters), 'kpi_report.xlsx');
        }

        if ($request->query('export') === 'pdf') {
            $pdf = Pdf::loadView('exports.kpi_report', [
                'data'         => $data,
                'centerName'   => $centerName,
                'managerName'  => $managerName,
                'startDate'    => $startDate ?? 'N/A',
                'endDate'      => $endDate ?? 'N/A',
                'generatedAt'  => now()->format('Y-m-d H:i'),
            ]);
            return $pdf->download('kpi_report.pdf');
        }

        return response()->json([
            'message' => 'KPI data retrieved successfully.',
            'data'    => $data,
            'center'  => $centerName,
            'manager' => $managerName,
        ]);
    }

    public function swapCenterManagers(SwapCenterManagersRequest $request)
    {
        SuperAdminService::swapCenterManagers($request->swaps);

        return response()->json([
            'message' => 'Managers swapped successfully'
        ]);}

}
