<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentCallbackController;

Route::get('/', function () {
    return view('welcome');
});

// Paystack webhook route - temporary placement
Route::post('/payments/callback', [PaymentCallbackController::class, 'handlePaymentCallback']);
Route::post('/api/v1/payments/callback', [PaymentCallbackController::class, 'handlePaymentCallback']);
