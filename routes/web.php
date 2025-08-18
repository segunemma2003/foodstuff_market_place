<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentCallbackController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::get('/', function () {
    return view('welcome');
});

// Paystack webhook route - following exact documentation
Route::post('/webhook/paystack', [PaymentCallbackController::class, 'handleWebhook']) ->withoutMiddleware([VerifyCsrfToken::class]);
Route::post('/payment-callback', [PaymentCallbackController::class, 'handleWebhook']);
Route::post('/payments/callback', [PaymentCallbackController::class, 'handleWebhook']);
Route::post('/api/v1/payments/callback', [PaymentCallbackController::class, 'handleWebhook']);

