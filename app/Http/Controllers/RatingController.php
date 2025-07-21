<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\RatingResource;

class RatingController extends Controller
{

    private RatingService $ratingService;

    public function __construct(RatingService $ratingService)
    {
        $this->ratingService = $ratingService;
    }

    public function store(StoreRatingRequest $request)
    {
        try {
            $rating = $this->ratingService->createRating($request->validated());
            return $this->success('Rating submitted successfully', $rating);
        } catch (Throwable $th) {
            return $this->error($th->getMessage(), $th->getCode());
        }
    }

     public function show($id)
    {
        try {
            $rating = $this->ratingService->getRatingDetails($id);
            return $this->success(
                'Rating details retrieved',
                new RatingResource($rating)
            );
        } catch (Throwable $th) {
            return $this->error($th->getMessage(), $th->getCode());
        }
    }

    public function update(UpdateRatingRequest $request, $id)
    {
        try {
            $rating = $this->ratingService->updateRating($id, $request->validated());
            return $this->success(
                'Rating updated successfully',
                new RatingResource($rating)
            );
        } catch (Throwable $th) {
            return $this->error($th->getMessage(), $th->getCode());
        }
    }

    public function destroy($id)
    {
        try {
            $this->ratingService->deleteRating($id);
            return $this->success('Rating deleted successfully');
        } catch (Throwable $th) {
            return $this->error($th->getMessage(), $th->getCode());
        }
    }
}
