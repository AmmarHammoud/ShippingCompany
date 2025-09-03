<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use App\Models\Shipment;
class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        // All authenticated users can view reports list
        return $user->isSuperAdmin() || $user->isCenterManager();
    }

    public function view(User $user, Report $report): bool
    {
        // Reporter or admin/staff can view
        return $user->id === $report->user_id || $user->id === $shipment->recipient_id || $user->isSuperAdmin() || $user->isCenterManager();
    }

    public function create(User $user, Shipment $shipment): bool
    {
        return $user->id === $shipment->client_id || $user->id === $shipment->recipient_id;
    }

    public function update(User $user, Report $report): bool
    {
        // Reporter can update message, staff can update status
        return $user->id === $report->user_id || $user->id === $shipment->recipient_id || $user->isSuperAdmin() || $user->isCenterManager();
    }

    public function delete(User $user, Report $report): bool
    {
        // Reporter or admin can delete
        return $user->id === $report->user_id || $user->id === $shipment->recipient_id || $user->isSuperAdmin();
    }
}