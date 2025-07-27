<?php

use App\Events\TestPusherEvent;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\ShipmentDriverOfferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SuperAdminController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
    Route::get('/noway', function (){
        return response()->json('fdsfs');
    });
});

//confrim delivery by recipient
Route::get('shipments/{barcode}/confirm', [ShipmentController::class, 'confirmDelivery']);



    Route::middleware(['auth:sanctum', 'role:client'])->group( function () {
    Route::post('recipient', [ShipmentController::class, 'storeRecipient']);
    Route::post('details', [ShipmentController::class, 'storeDetails']);
    Route::post('cancel/{id}', [ShipmentController::class, 'cancel']);
    Route::post('update/{id}', [ShipmentController::class, 'update']);
    Route::get('shipmentshow/{id}', [ShipmentController::class, 'show']);
    Route::get('my-shipments', [ShipmentController::class, 'myShipments']);

    Route::controller(RatingController::class)->group(function () {
        Route::post('/ratings', 'store');
        Route::get('/ratings/{id}', 'show');
        Route::post('/ratings/{id}', 'update');
        Route::delete('/ratings/{id}', 'destroy');
    });

    Route::controller(ReportController::class)->group(function() {
        Route::post('/reports', 'store');
        Route::get('/reports/{report}', 'show');
        Route::post('/reports/{report}', 'update');
        Route::delete('/reports/{report}', 'destroy');
        Route::get('/reports', 'index');
    });

});


//super admin
Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
    Route::post('addmanger', [SuperAdminController::class, 'store']);
    Route::post('updatemanager/{id}', [SuperAdminController::class, 'update']);
    Route::delete('deletemanager/{id}', [SuperAdminController::class, 'destroy']);

    Route::get('centers', [SuperAdminController::class, 'index']);

    Route::post('storeCenter', [SuperAdminController::class, 'storeCenter']);
    Route::Post('updateCenter/{id}', [SuperAdminController::class, 'updateCenter']);
    Route::delete('deleteCenter/{id}', [SuperAdminController::class, 'deleteCenter']);


    Route::get('performance-kpis', [SuperAdminController::class, 'performanceKPIs']);



});




Route::middleware(['auth:sanctum', 'role:driver'])->group(function(){
    Route::post('offers/{shipment}/accept', [ShipmentDriverOfferController::class, 'acceptOffer']);
    Route::post('offers/{shipment}/reject', [ShipmentDriverOfferController::class, 'rejectOffer']);
    Route::get('/shipments/{barcode}/confirm-pickup', [ShipmentDriverOfferController::class, 'confirmPickupByDriver']);
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
