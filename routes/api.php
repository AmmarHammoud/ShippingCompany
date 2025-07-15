<?php

use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentDriverOfferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->prefix('shipments')->group(function () {
    Route::post('/recipient', [ShipmentController::class, 'storeRecipient']);
    Route::post('/details', [ShipmentController::class, 'storeDetails']);
});
Route::post('/offers/{shipment}/accept', [ShipmentDriverOfferController::class, 'acceptOffer']);
Route::post('/offers/{shipment}/reject', [ShipmentDriverOfferController::class, 'rejectOffer']);
Route::get('/offers/my-pending', [ShipmentDriverOfferController::class, 'myPendingOffers']);
Route::get('/offers', [ShipmentDriverOfferController::class, 'offersByStatus']);
