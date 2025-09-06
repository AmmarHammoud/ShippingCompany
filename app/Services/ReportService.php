<?php

namespace App\Services;

use App\Models\Report;
use App\Models\Shipment;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;

class ReportService
{
    public function createReport(array $data): Report
    {
        // Authorization is now handled by controller policy

        if(Report::query()->where('shipment_id', $data['shipment_id'])){
            throw new \Exception('Shipment already reported.', 403);
        }

        // Simply create the report
        return Report::create([
            'shipment_id' => $data['shipment_id'],
            'user_id' => Auth::id(),
            'message' => $data['message']
        ]);
    }

    public function getAllReports(array $filters = []): Paginator
    {
        $query = Report::with(['user', 'shipment']);

        // For non-admins, only show their own reports
        if (!Auth::user()->isAdmin()) {
            $query->where('user_id', Auth::id());
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(10);
    }

    public function updateReport(Report $report, array $data): Report
    {
        // Authorization is handled by controller policy
        // Only update allowed fields based on user role

        $updates = [];

        if (isset($data['message'])) {
            $updates['message'] = $data['message'];
        }

        if (isset($data['status']) && !Auth::user()->isClient() && !Auth::user()->isDriver()) {
            $updates['status'] = $data['status'];
        }

        $report->update($updates);

        return $report->fresh();
    }

    public function deleteReport(Report $report): bool
    {
        return $report->delete();
    }

}
