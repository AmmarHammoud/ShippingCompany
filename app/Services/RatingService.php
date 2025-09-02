<?php

namespace App\Services;

use App\Models\Rating;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use mysql_xdevapi\Exception;

class RatingService
{
    public function createRating(array $data): Rating
    {
        $shipment = Shipment::findOrFail($data['shipment_id']);

        // Verify client owns the shipment
        if ($shipment->client_id !== Auth::id()) {
            Throw new \Exception( 'You can only rate your own shipments', 403);
        }

        // Check if shipment is deliverable
        if ($shipment->status != 'delivered') {
            Throw new \Exception('Shipment must be delivered before rating', 422);
        }

        // Prevent duplicate ratings
        if (Rating::where('shipment_id', $data['shipment_id'])
                ->where('user_id', Auth::id())
                ->exists()) {
            Throw new \Exception('You have already rated this shipment', 409);
        }

        return Rating::create([
            'shipment_id' => $data['shipment_id'],
            'user_id' => Auth::id(),
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null
        ]);
    }

    public function getRatingDetails(int $ratingId): Rating
    {
        $rating = Rating::with(['shipment', 'user'])->find($ratingId);

        if (!$rating) {
            throw new ModelNotFoundException('Rating not found', 404);
        }

        return $rating;
    }

    public function updateRating(int $ratingId, array $data): Rating
    {
        $rating = Rating::where('user_id', Auth::id())
            ->find($ratingId);

        if (!$rating) {
            throw new ModelNotFoundException('Rating not found or access denied', 404);
        }

        // Only allow updating rating and comment
        $rating->update([
            'rating' => $data['rating'] ?? $rating->rating,
            'comment' => $data['comment'] ?? $rating->comment
        ]);

        return $rating->fresh();
    }

    public function deleteRating(int $ratingId): bool
    {
        $rating = Rating::where('user_id', Auth::id())
            ->where('id', $ratingId)->first();
        if (!$rating) {
            throw new ModelNotFoundException('Rating not found or access denied', 404);
        }

        return $rating->delete();
    }
}
