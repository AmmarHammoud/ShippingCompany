<?php

namespace App\Services;

use App\Models\Rating;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class RatingService
{
    public function createRating(array $data): Rating
    {
        $shipment = Shipment::findOrFail($data['shipment_id']);

        // Verify client owns the shipment
        if ($shipment->client_id !== Auth::id()) {
            abort(403, 'You can only rate your own shipments');
        }

        // Check if shipment is deliverable
        if (!$shipment->is_delivered) {
            abort(422, 'Shipment must be delivered before rating');
        }

        // Prevent duplicate ratings
        if (Rating::where('shipment_id', $data['shipment_id'])
                ->where('user_id', Auth::id())
                ->exists()) {
            abort(409, 'You have already rated this shipment');
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
        $rating = Rating::with(['shipment', 'user'])
            ->where('user_id', Auth::id())
            ->find($ratingId);

        if (!$rating) {
            throw new ModelNotFoundException('Rating not found or access denied', 404);
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
            ->find($ratingId);

        if (!$rating) {
            throw new ModelNotFoundException('Rating not found or access denied', 404);
        }

        return $rating->delete();
    }
}
