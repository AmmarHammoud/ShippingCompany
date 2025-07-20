<?php

use App\Events\TestPusherEvent;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\ShipmentDriverOfferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SuperAdminController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'role:dr'])->group(function () {
    Route::get('/noway', function (){
        return response()->json('fdsfs');
    });
});

Route::middleware(['auth:sanctum'])->group(function () {

});




Route::middleware(['auth:sanctum', 'role:client'])->group( function () {

Route::post('recipient', [ShipmentController::class, 'storeRecipient']);
Route::post('details', [ShipmentController::class, 'storeDetails']);
Route::post('cancel/{id}', [ShipmentController::class, 'cancel']);
Route::put('update/{id}', [ShipmentController::class, 'update']);
Route::get('shipments/{id}', [ShipmentController::class, 'show']);
Route::get('my-shipments', [ShipmentController::class, 'myShipments']);

    //rating the shipment by recipient
Route::post('shipment/ratings', [ShipmentController::class, 'rating']);

});


//super admin

Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
    Route::post('addmanger', [SuperAdminController::class, 'store']);
    Route::put('center-managers/{id}', [SuperAdminController::class, 'update']);
    Route::delete('center-managers/{id}', [SuperAdminController::class, 'destroy']);
    
    Route::get('center-managers', [SuperAdminController::class, 'index']);

    Route::post('storeCenter', [SuperAdminController::class, 'storeCenter']);
    Route::put('updateCenter/{id}', [SuperAdminController::class, 'updateCenter']);
    Route::delete('deleteCenter/{id}', [SuperAdminController::class, 'deleteCenter']);


    Route::get('dashboard/performance-kpis', [SuperAdminController::class, 'performanceKPIs']);



});


Route::get('shipments/{barcode}/confirm', [ShipmentController::class, 'confirmDelivery']);


Route::middleware(['auth:sanctum', 'role:driver'])->group(function(){
Route::post('offers/{shipment}/accept', [ShipmentDriverOfferController::class, 'acceptOffer']);
Route::post('offers/{shipment}/reject', [ShipmentDriverOfferController::class, 'rejectOffer']);
Route::get('offers', [ShipmentDriverOfferController::class, 'offersByStatus']);
 Route::post('shipments/{id}/hand-over-to-center', [ShipmentDriverOfferController::class, 'confirmHandOverToCenter']);
});

Route::controller(AuthController::class)->group(function () {
    Route::post('signup', 'signUp')->name('user.sign_up');
    Route::post('signin', 'signIn')->name('user.sign_in');
    Route::get('signout', 'signOut')->middleware('auth:sanctum');
});

Route::controller(ResetPasswordController::class)->group(function () {
    Route::post('forgotPassword', 'forgotPassword')->name('check.email_password');
    Route::post('checkCode', 'checkCode')->name('check.email_password');
    Route::post('resetPassword', 'resetPassword')->name('check.email_password');
});

Route::controller(EmailVerificationController::class)->group(function () {
    Route::post('verifyEmail', 'verifyEmail')->name('check.email_password');
    Route::post('resendVerificationCode', 'resendVerificationCode')->name('check.email_password');
});

Route::controller(AuthController::class)->group(function () {
    Route::post('signup', 'signUp')->name('user.sign_up');
    Route::post('signin', 'signIn')->name('user.sign_in');
});

Route::controller(ResetPasswordController::class)->group(function () {
    Route::post('forgotPassword', 'forgotPassword')->name('check.email_password');
    Route::post('checkCode', 'checkCode')->name('check.email_password');
    Route::post('resetPassword', 'resetPassword')->name('check.email_password');
});

Route::controller(EmailVerificationController::class)->group(function () {
    Route::post('verifyEmail', 'verifyEmail')->name('check.email_password');
    Route::post('resendVerificationCode', 'resendVerificationCode')->name('check.email_password');
});
