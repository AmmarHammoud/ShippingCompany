<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\RatingResource;
use App\Services\RatingService;
use App\Http\Requests\StoreRatingRequest;
use App\Http\Requests\UpdateRatingRequest;
use App\Http\Responses\Response;
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
            return Response::success('Rating submitted successfully', $rating);
        } catch (Throwable $th) {
            return Response::error($th->getMessage(), $th->getCode());
        }
    }

     public function show($id)
    {
        try {
            $rating = $this->ratingService->getRatingDetails($id);
            return Response::success(
                'Rating details retrieved', 
                new RatingResource($rating)
            );
        } catch (Throwable $th) {
            return Response::error($th->getMessage(), $th->getCode());
        }
    }

    public function update(UpdateRatingRequest $request, $id)
    {
        try {
            $rating = $this->ratingService->updateRating($id, $request->validated());
            return Response::success(
                'Rating updated successfully', 
                new RatingResource($rating)
            );
        } catch (Throwable $th) {
            return Response::error($th->getMessage(), $th->getCode());
        }
    }

    public function destroy($id)
    {
        try {
            $this->ratingService->deleteRating($id);
            return Response::success('Rating deleted successfully');
        } catch (Throwable $th) {
            return Response::error($th->getMessage(), $th->getCode());
        }
    }
}
