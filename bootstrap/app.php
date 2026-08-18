<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));
            Route::middleware('web')
                ->group(base_path('routes/instructor.php'));
            Route::middleware('web')
                ->group(base_path('routes/connector.php'));
            Route::middleware(['web', 'auth'])
                ->get('/suspension-status', [\App\Http\Controllers\Moderation\SuspensionStatusController::class, 'show'])
                ->name('moderation.suspension-status');
            Route::middleware(['web', 'auth'])
                ->get('/suspensions/{userSuspension}/appeals/create', [\App\Http\Controllers\Moderation\SuspensionAppealController::class, 'create'])
                ->name('moderation.appeals.create');
            Route::middleware(['web', 'auth'])
                ->post('/suspensions/{userSuspension}/appeals', [\App\Http\Controllers\Moderation\SuspensionAppealController::class, 'store'])
                ->name('moderation.appeals.store');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'premium'          => \App\Http\Middleware\CheckPremiumStatus::class,
            'role'             => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'       => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'profile.completed' => \App\Http\Middleware\EnsureProfileCompleted::class,
            'guardian.verified' => \App\Http\Middleware\EnsureGuardianVerified::class,
            'suspension.guard' => \App\Http\Middleware\CheckUserSuspensionStatus::class,
            // PayMongo webhook HMAC signature verification
            'paymongo.webhook' => \App\Http\Middleware\VerifyPayMongoWebhook::class,
        ]);

        $middleware->appendToGroup('web', \App\Http\Middleware\CheckUserSuspensionStatus::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
