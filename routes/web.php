<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaystackWebhookController;

Route::get('/', function () {
    return view('welcome');
});

// Paystack webhook route - following exact documentation
Route::post('/webhook/paystack', [PaystackWebhookController::class, 'handle']);
// Route::post('/payments/callback', [PaystackWebhookController::class, 'handle']);
// Route::post('/api/v1/payments/callback', [PaystackWebhookController::class, 'handle']);

