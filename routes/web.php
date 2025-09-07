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



Route::get('/run-schedule', function () {
    if (request('secret') !== 'your-secret-key-here') {
        abort(403);
    }

    Artisan::call('schedule:run');

    return response()->json(['status' => 'Schedule executed successfully']);
});

Route::get('/process-queue', function () {
    if (request('secret') !== 'your-secret-key-here') {
        abort(403);
    }

    Artisan::call('queue:work', ['--stop-when-empty' => false]);

    return response()->json(['status' => 'Queue processed successfully']);
});
