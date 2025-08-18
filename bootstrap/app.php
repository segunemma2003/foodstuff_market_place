<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'agent' => \App\Http\Middleware\AgentMiddleware::class,
            'cors' => \App\Http\Middleware\CorsMiddleware::class,
        ]);

        $middleware->web([
            // Web middleware stack
        ]);

        $middleware->api([
            \App\Http\Middleware\CorsMiddleware::class,
        ]);

        // Apply CORS globally to all routes
        $middleware->append(\App\Http\Middleware\CorsMiddleware::class);

        // Exempt webhook URLs from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'webhook/paystack',
            'api/webhook/paystack',
            'api/v1/webhook/paystack',
            'payments/callback',
            'api/payments/callback',
            'api/v1/payments/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
