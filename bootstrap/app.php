<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'can_access' => \App\Http\Middleware\EnsureCanAccess::class,
        ]);

        // Railway's edge proxy terminates HTTPS and forwards plain HTTP
        // internally; without this, Laravel thinks every request is HTTP,
        // generating http:// form actions/redirects behind an https:// proxy.
        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('support-chat:prune')->daily();
        // Hourly, not dailyAt() — the command itself checks Settings for whether it's enabled
        // and which hour to actually run at, so the admin panel's toggle/time dropdown take
        // effect immediately instead of needing a redeploy to change a hardcoded schedule time.
        $schedule->command('backup:run')->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
