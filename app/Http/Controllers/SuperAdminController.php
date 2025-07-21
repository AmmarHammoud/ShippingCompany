<?php

namespace App\Http\Controllers;

use App\Exports\KPIExport;
use App\Http\Requests\StoreCenterManagerRequest;
use App\Http\Requests\StoreCenterRequest;
use App\Http\Requests\UpdateCenterManagerRequest;
use App\Http\Requests\UpdateCenterRequest;
use App\Models\Center;
use App\Models\Shipment;
use App\Models\User;
use App\Services\KpiService;
use App\Services\SuperAdminService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class SuperAdminController extends Controller
{
    protected $centerService;

    public function __construct(SuperAdminService $centerService)
    {
        $this->centerService = $centerService;
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
            'center_id'  => 'required|exists:centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $centerId = $request->query('center_id');

        if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            return response()->json(['error' => 'Invalid start_date format. Use YYYY-MM-DD'], 422);
        }

        if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            return response()->json(['error' => 'Invalid end_date format. Use YYYY-MM-DD'], 422);
        }

        $filters = [
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'center_id'  => $centerId,
        ];

        $data = $kpiService->getDashboardData($filters);

        $centerName = 'All Centers';
        $managerName = 'N/A';

        if (!empty($centerId)) {
            $center = Center::with('manager')->find($centerId);
            if (!$center) {
                return response()->json(['error' => 'Center not found.'], 404);
            }
            $centerName = $center->name;
            $managerName = $center->manager->name ?? 'N/A';
        }

        if ($request->query('export') === 'excel') {
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\KpiExport($filters), 'kpi_report.xlsx');
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
        ]);
    }}
