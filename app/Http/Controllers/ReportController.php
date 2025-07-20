<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use app\Services\ReportService;

class ReportController extends Controller
{

    private ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }
    
    public function store(StoreReportRequest $request)
    {
        try {
            $shipment = Shipment::findOrFail($request->shipment_id);
            $this->authorize('create', [Report::class, $shipment]);
            $report = $this->reportService->createReport($request->validated());
            return $this->success('Report submitted successfully', $report);
        } catch (Throwable $th) {
            return $this->error($th->getMessage(), $th->getCode());
        }
    }

    public function index(Request $request)
    {
        try {
            $this->authorize('viewAny', Report::class);
            $reports = $this->reportService->getAllReports($request);
            return $this->success('Reports retrieved successfully', $reports);
        } catch (Throwable $th) {
            return $this->error($th->getMessage(), $th->getCode());
        }
    }
    
    public function show(Report $report)
    {
        try {
            $this->authorize('view', $report);
            return $this->success('Report details retrieved', $report);
        } catch (Throwable $th) {
            return $this->error($th->getMessage(), $th->getCode());
        }
    }

    public function update(Request $request, Report $report)
    {
        try {
            $this->authorize('update', $report);
            $updatedReport = $this->reportService->updateReport($report, $request);
            return $this->success('Report updated successfully', $updatedReport);
        } catch (Throwable $th) {
            return $this->error($th->getMessage(), $th->getCode());
        }
    }

    public function destroy(Report $report)
    {
        try {
            $this->authorize('delete', $report);
            $this->reportService->deleteReport($report);
            return $this->success('Report deleted successfully');
        } catch (Throwable $th) {
            return $this->error($th->getMessage(), $th->getCode());
        }
    }
}
