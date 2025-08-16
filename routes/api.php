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
    Route::post('/whatsapp/process-message', [WhatsAppController::class, 'processMessage']);
    Route::post('/whatsapp/create-order', [WhatsAppController::class, 'createOrder']);
    Route::get('/whatsapp/status', [WhatsAppController::class, 'getStatus']);
    Route::post('/whatsapp/initialize', [WhatsAppController::class, 'initialize']);

    // Order Search and Management APIs
    Route::get('/orders/search', [OrderController::class, 'search']); // Search orders by order number
    Route::get('/orders/{order}/items', [OrderController::class, 'getItems']); // Get just the items for an order
    Route::get('/orders/{order_number}/status', [OrderController::class, 'getOrderStatus']); // Get order status and send WhatsApp notification
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::put('/orders/{order}/items', [OrderController::class, 'updateItems']);
    Route::post('/orders/{order}/checkout', [OrderController::class, 'checkout']);
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    // Autocomplete and Search APIs
    Route::get('/products/autocomplete', [ProductController::class, 'autocomplete']); // Product autocomplete
    Route::get('/markets/autocomplete', [MarketController::class, 'autocomplete']); // Market autocomplete
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/categories', [ProductController::class, 'getCategories']);

    // Market and Proximity APIs
    Route::get('/markets/nearby', [MarketController::class, 'getNearbyMarkets']);
    Route::get('/markets/{market}/products', [MarketController::class, 'getProducts']);
    Route::get('/markets/{market}/prices', [MarketController::class, 'getProductPrices']); // Get prices and measurements

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
    Route::middleware(['admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Market management
        Route::get('/markets', [AdminController::class, 'getMarkets']);
        Route::post('/markets', [AdminController::class, 'store']);
        Route::get('/markets/{market}', [AdminController::class, 'show']);
        Route::put('/markets/{market}', [AdminController::class, 'update']);
        Route::delete('/markets/{market}', [AdminController::class, 'destroy']);
        Route::put('/markets/{market}/toggle-status', [AdminController::class, 'toggleMarketStatus']);

        // Agent management
        Route::get('/agents', [AdminController::class, 'getAgents']);
        Route::post('/agents', [AdminController::class, 'createAgent']);
        Route::get('/agents/{agent}', [AdminController::class, 'showAgent']);
        Route::put('/agents/{agent}', [AdminController::class, 'updateAgent']);
        Route::delete('/agents/{agent}', [AdminController::class, 'destroyAgent']);
        Route::put('/agents/{agent}/suspend', [AdminController::class, 'suspendAgent']);
        Route::put('/agents/{agent}/activate', [AdminController::class, 'activateAgent']);
        Route::put('/agents/{agent}/reset-password', [AdminController::class, 'resetAgentPassword']);
        Route::put('/agents/{agent}/switch-market', [AdminController::class, 'switchAgentMarket']); // Switch agent to different market

        // Product management
        Route::get('/products', [AdminController::class, 'getProducts']);
        Route::post('/products', [AdminController::class, 'createProduct']);
        Route::get('/products/{product}', [AdminController::class, 'showProduct']);
        Route::put('/products/{product}', [AdminController::class, 'updateProduct']);
        Route::delete('/products/{product}', [AdminController::class, 'destroyProduct']);

        // Category management
        Route::get('/categories', [AdminController::class, 'getCategories']);
        Route::post('/categories', [AdminController::class, 'createCategory']);
        Route::get('/categories/{category}', [AdminController::class, 'showCategory']);
        Route::put('/categories/{category}', [AdminController::class, 'updateCategory']);
        Route::delete('/categories/{category}', [AdminController::class, 'destroyCategory']);

        // Market Product management (Admin can manage products for any market)
        Route::get('/market-products', [AdminController::class, 'getMarketProducts']);
        Route::post('/market-products', [AdminController::class, 'createMarketProduct']);
        Route::put('/market-products/{marketProduct}', [AdminController::class, 'updateMarketProduct']);
        Route::delete('/market-products/{marketProduct}', [AdminController::class, 'destroyMarketProduct']);

        // Order management
        Route::get('/orders', [AdminController::class, 'getOrders']);
        Route::get('/orders/search', [AdminController::class, 'searchOrders']); // Admin order search
        Route::put('/orders/{order}/assign-agent', [AdminController::class, 'assignAgent']);
        Route::put('/orders/{order}/status', [AdminController::class, 'updateOrderStatus']);
        Route::put('/orders/{order}/approve-agent', [AdminController::class, 'approveAgent']); // Approve agent for order
        Route::put('/orders/{order}/switch-agent', [AdminController::class, 'switchAgent']); // Switch agent for order

        // Commission and Payment management
        Route::get('/commissions', [AdminController::class, 'getCommissions']);
        Route::put('/commissions/{commission}/approve', [AdminController::class, 'approveCommission']);
        Route::put('/commissions/{commission}/reject', [AdminController::class, 'rejectCommission']);
        Route::post('/commissions/bulk-approve', [AdminController::class, 'bulkApproveCommissions']);

        // System settings
        Route::get('/settings', [AdminController::class, 'getSettings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);

        // Bank management
        Route::get('/banks', [AdminController::class, 'getBanks']);
        Route::post('/verify-bank-account', [AdminController::class, 'verifyBankAccount']);
        Route::get('/bank-details', [AdminController::class, 'getBankDetails']);
    });

    // Agent APIs (protected)
    Route::middleware(['agent'])->prefix('agent')->group(function () {
        Route::get('/dashboard', [AgentController::class, 'dashboard']);
        Route::get('/orders', [AgentController::class, 'getOrders']);
        Route::get('/orders/search', [AgentController::class, 'searchOrders']); // Agent order search
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

        // Commission tracking
        Route::get('/commissions', [AgentController::class, 'getCommissions']);
        Route::get('/commissions/pending', [AgentController::class, 'getPendingCommissions']);
        Route::get('/commissions/paid', [AgentController::class, 'getPaidCommissions']);
    });

    // Authentication routes
    Route::post('/admin/login', [AdminController::class, 'login']);
    Route::post('/agent/login', [AgentController::class, 'login']);

    // Health check route
    Route::get('/admin/health', [AdminController::class, 'healthCheck']);
});
