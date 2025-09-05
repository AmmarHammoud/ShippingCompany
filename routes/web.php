<?php

use App\Events\TestPusherEvent;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\RatingController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::get('shipments/{barcode}/confirm', [ShipmentController::class, 'confirmDelivery']);
Route::get('dashboard/performance-kpis', [SuperAdminController::class, 'performanceKPIs']);
Route::get('rating/{ratingId}', [RatingController::class, 'show']);
Route::get('nothing', function () {
    echo 'hello';
});


Route::get('/refresh-database', function() {
    try {
        Artisan::call('migrate:refresh --seed');
        return 'Database refreshed and seeded successfully!';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
