<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Corren en TODA petición web. SetTenant es no-op para invitados, y
        // EnsureSubscriptionActive exime login, logout y selección de empresa.
        // Orden por request: auth → set-tenant → subscription → plan → permiso.
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\SetTenant::class,
            \App\Http\Middleware\EnsureSubscriptionActive::class,
        ]);

        // La empresa activa debe fijarse ANTES del route model binding: si no,
        // el CompanyScope corre con la empresa vacía (fail-closed) y toda ruta
        // /{modelo} de un tenant responde 404.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\SetTenant::class,
        );

        $middleware->alias([
            'set-tenant' => \App\Http\Middleware\SetTenant::class,
            'subscription' => \App\Http\Middleware\EnsureSubscriptionActive::class,
            'plan' => \App\Http\Middleware\CheckPlanModule::class,
            'check-role' => \App\Http\Middleware\CheckRole::class,
            'check-company' => \App\Http\Middleware\CheckCompany::class,
            'tenant' => \App\Http\Middleware\RequireTenant::class,
            'check-permission' => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
