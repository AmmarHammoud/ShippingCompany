<?php

use App\Events\TestPusherEvent;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\RatingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated']);
})->name('login');

Route::get('shipments/{barcode}/confirm', [ShipmentController::class, 'confirmDelivery']);
Route::get('dashboard/performance-kpis', [SuperAdminController::class, 'performanceKPIs']);
Route::get('rating/{ratingId}', [RatingController::class, 'show']);