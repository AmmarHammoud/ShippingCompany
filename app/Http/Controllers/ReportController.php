<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Shipment;
use Illuminate\Http\Request;
use App\Services\ReportService;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\http\Responses\Response;
use App\Policies\ReportPolicy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;

class ReportController extends Controller
{
use AuthorizesRequests;
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
            return Response::success('Report submitted successfully', $report);
        } catch (Throwable $th) {
            return Response::error($th->getMessage(), $th->getCode());
        }
    }

    public function index(Request $request)
    {
        try {
            $this->authorize('viewAny', Report::class);
            $reports = $this->reportService->getAllReports($request);
            return Response::success('Reports retrieved successfully', $reports);
        } catch (Throwable $th) {
            return Response::error($th->getMessage(), $th->getCode());
        }
    }

    public function show(Report $report)
    {
        try {
            $this->authorize('view', $report);
            return Response::success('Report details retrieved', $report);
        } catch (Throwable $th) {
            return Response::error($th->getMessage(), $th->getCode());
        }
    }

    public function update(UpdateReportRequest $request, Report $report)
    {
        try {
            $this->authorize('update', $report);
            $updatedReport = $this->reportService->updateReport($report, $request->validated());
            return Response::success('Report updated successfully', $updatedReport);
        } catch (Throwable $th) {
            return Response::error($th->getMessage(), $th->getCode());
        }
    }

    public function destroy(Report $report)
    {
        try {
            $this->authorize('delete', $report);
            $this->reportService->deleteReport($report);
            return Response::success('Report deleted successfully');
        } catch (Throwable $th) {
            return Response::error($th->getMessage(), $th->getCode());
        }
    }
}
