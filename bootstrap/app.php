<?php

use App\Http\Middleware\EnforceHttps;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\EnforceSystemLockdown;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordHasBeenChanged;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RequireRecentApiActivity;
use App\Http\Middleware\SecurityHeaders;
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
        $middleware->prepend([EnforceHttps::class, SecurityHeaders::class]);
        $middleware->web(append: [EnsureAccountIsActive::class, EnforceIdleTimeout::class, EnforceSystemLockdown::class]);
        $middleware->statefulApi();
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureAccountIsActive::class,
            'password.changed' => EnsurePasswordHasBeenChanged::class,
            'api.idle' => RequireRecentApiActivity::class,
            'system.lockdown' => EnforceSystemLockdown::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
