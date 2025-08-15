<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\GeolocationController;
use App\Http\Controllers\Api\StatsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('v1')->group(function () {

    // WhatsApp Bot API
    Route::post('/whatsapp/webhook', [WhatsAppController::class, 'webhook']);
    Route::post('/whatsapp/send-message', [WhatsAppController::class, 'sendMessage']);

    // Market and Product APIs
    Route::get('/markets/nearby', [MarketController::class, 'getNearbyMarkets']);
    Route::get('/markets/{market}/products', [MarketController::class, 'getProducts']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/categories', [ProductController::class, 'getCategories']);

    // Order APIs
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::put('/orders/{order}/items', [OrderController::class, 'updateItems']);
    Route::post('/orders/{order}/checkout', [OrderController::class, 'checkout']);

    // Payment APIs
    Route::post('/payments/initialize', [PaymentController::class, 'initialize']);
    Route::post('/payments/verify', [PaymentController::class, 'verify']);
    Route::post('/payments/callback', [PaymentController::class, 'callback']);

    // Geolocation APIs
    Route::get('/geolocation/search', [GeolocationController::class, 'search']);
    Route::get('/geolocation/reverse', [GeolocationController::class, 'reverse']);

    // Stats APIs
    Route::get('/stats/dashboard', [StatsController::class, 'dashboard']);
    Route::get('/stats/markets', [StatsController::class, 'marketStats']);
    Route::get('/stats/agents', [StatsController::class, 'agentStats']);
    Route::get('/stats/orders', [StatsController::class, 'orderStats']);
    Route::get('/stats/products', [StatsController::class, 'productStats']);
    Route::get('/stats/earnings', [StatsController::class, 'earningsStats']);
    Route::get('/stats/recent-activity', [StatsController::class, 'recentActivity']);
    Route::get('/stats/performance', [StatsController::class, 'performanceMetrics']);

    // Admin APIs (protected)
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Market management
        Route::apiResource('markets', AdminController::class);
        Route::put('/markets/{market}/toggle-status', [AdminController::class, 'toggleMarketStatus']);

        // Agent management
        Route::apiResource('agents', AdminController::class);
        Route::put('/agents/{agent}/suspend', [AdminController::class, 'suspendAgent']);
        Route::put('/agents/{agent}/activate', [AdminController::class, 'activateAgent']);
        Route::put('/agents/{agent}/reset-password', [AdminController::class, 'resetAgentPassword']);

        // Order management
        Route::get('/orders', [AdminController::class, 'getOrders']);
        Route::put('/orders/{order}/assign-agent', [AdminController::class, 'assignAgent']);
        Route::put('/orders/{order}/status', [AdminController::class, 'updateOrderStatus']);

        // Product management
        Route::apiResource('products', AdminController::class);
        Route::apiResource('categories', AdminController::class);
    });

    // Agent APIs (protected)
    Route::middleware(['auth:sanctum', 'agent'])->prefix('agent')->group(function () {
        Route::get('/dashboard', [AgentController::class, 'dashboard']);
        Route::get('/orders', [AgentController::class, 'getOrders']);
        Route::put('/orders/{order}/status', [AgentController::class, 'updateOrderStatus']);
        Route::get('/earnings', [AgentController::class, 'getEarnings']);

        // Product management
        Route::get('/products', [AgentController::class, 'getProducts']);
        Route::post('/products', [AgentController::class, 'addProduct']);
        Route::put('/products/{marketProduct}', [AgentController::class, 'updateProduct']);
        Route::delete('/products/{marketProduct}', [AgentController::class, 'removeProduct']);

        // Profile management
        Route::get('/profile', [AgentController::class, 'getProfile']);
        Route::put('/profile', [AgentController::class, 'updateProfile']);
        Route::put('/change-password', [AgentController::class, 'changePassword']);
    });

    // Authentication routes
    Route::post('/admin/login', [AdminController::class, 'login']);
    Route::post('/agent/login', [AgentController::class, 'login']);
    Route::post('/logout', [AdminController::class, 'logout'])->middleware('auth:sanctum');
});
