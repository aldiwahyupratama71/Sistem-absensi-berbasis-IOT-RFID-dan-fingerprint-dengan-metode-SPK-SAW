<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stateful API untuk SPA (Sanctum cookie-based — opsional, kita pakai token Bearer)
        // $middleware->statefulApi();

        $middleware->alias([
            'device.auth' => \App\Http\Middleware\AuthenticateDevice::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pastikan semua error di /api selalu dikembalikan sebagai JSON
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
