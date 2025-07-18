<?php

use App\Events\TestPusherEvent;
use App\Http\Controllers\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('shipments/{barcode}/confirm', [ShipmentController::class, 'confirmDelivery']);
