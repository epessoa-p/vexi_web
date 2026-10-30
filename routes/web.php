<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubscriptionBlockedController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CajaController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\CargoController;
use App\Http\Controllers\Admin\PersonalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Orden de las capas de autorización
|--------------------------------------------------------------------------
| auth → set-tenant → subscription → plan:<mod> → check-permission:<p>
|
| `set-tenant` y `subscription` corren en todo el grupo `web` (bootstrap/app.php).
| `plan:` todavía no envuelve ninguna ruta: los módulos administrativos están
| siempre disponibles. Llegará con el dominio veterinario (agenda, consultas, pos, …).
*/

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.store');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Company selection (multi-empresa)
    Route::get('/select-company', [LoginController::class, 'selectCompany'])->name('select-company');
    Route::post('/set-company/{companyId}', [LoginController::class, 'setCompany'])->name('set-company');
    Route::post('/exit-company', [LoginController::class, 'exitCompany'])->name('exit-company');

    // Suscripción bloqueada o vencida
    Route::get('/suscripcion/bloqueada', SubscriptionBlockedController::class)->name('subscription.blocked');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Panel del operador de la plataforma (superadmin)
    |----------------------------------------------------------------------
    */
    Route::middleware('check-role:super_admin')->group(function () {

        // Empresas
        Route::prefix('admin/companies')->name('companies.')->group(function () {
            Route::get('/', [CompanyController::class, 'index'])->name('index');
            Route::get('/create', [CompanyController::class, 'create'])->name('create');
            Route::post('/', [CompanyController::class, 'store'])->name('store');
            Route::get('/{company}', [CompanyController::class, 'show'])->name('show');
            Route::get('/{company}/edit', [CompanyController::class, 'edit'])->name('edit');
            Route::put('/{company}', [CompanyController::class, 'update'])->name('update');
            Route::delete('/{company}', [CompanyController::class, 'destroy'])->name('destroy');

            // Suscripción de la empresa (plan, estado, fechas y overrides)
            Route::get('/{company}/subscription/edit', [SubscriptionController::class, 'edit'])->name('subscription.edit');
            Route::put('/{company}/subscription', [SubscriptionController::class, 'update'])->name('subscription.update');
        });

        // Planes
        Route::prefix('admin/plans')->name('plans.')->group(function () {
            Route::get('/', [PlanController::class, 'index'])->name('index');
            Route::get('/create', [PlanController::class, 'create'])->name('create');
            Route::post('/', [PlanController::class, 'store'])->name('store');
            Route::get('/{plan}/edit', [PlanController::class, 'edit'])->name('edit');
            Route::put('/{plan}', [PlanController::class, 'update'])->name('update');
            Route::delete('/{plan}', [PlanController::class, 'destroy'])->name('destroy');
        });

        // Roles: catálogo global del operador
        Route::prefix('admin/roles')->name('roles.')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->name('index');
            Route::get('/create', [RoleController::class, 'create'])->name('create');
            Route::post('/', [RoleController::class, 'store'])->name('store');
            Route::get('/{role}', [RoleController::class, 'show'])->name('show');
            Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit');
            Route::put('/{role}', [RoleController::class, 'update'])->name('update');
            Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Administración de la empresa (gobernada por permisos)
    |----------------------------------------------------------------------
    */

    // Usuarios
    Route::prefix('admin/users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->middleware('check-permission:users.view')->name('index');
        Route::get('/create', [UserController::class, 'create'])->middleware('check-permission:users.create')->name('create');
        Route::post('/', [UserController::class, 'store'])->middleware('check-permission:users.create')->name('store');
        Route::get('/{user}', [UserController::class, 'show'])->middleware('check-permission:users.view')->name('show');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->middleware('check-permission:users.edit')->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->middleware('check-permission:users.edit')->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('check-permission:users.delete')->name('destroy');
        Route::post('/{user}/assign-role/{company}/{role}', [UserController::class, 'assignRole'])
            ->middleware('check-permission:users.edit')->name('assign-role');
    });

    // Cargos
    Route::prefix('admin/cargos')->name('cargos.')->group(function () {
        Route::get('/', [CargoController::class, 'index'])->middleware('check-permission:cargos.view')->name('index');
        Route::get('/create', [CargoController::class, 'create'])->middleware('check-permission:cargos.create')->name('create');
        Route::post('/', [CargoController::class, 'store'])->middleware('check-permission:cargos.create')->name('store');
        Route::get('/role-permissions/{role}', [CargoController::class, 'rolePermissions'])
            ->middleware('check-permission:cargos.create,cargos.edit')->name('role-permissions');
        Route::get('/{cargo}/edit', [CargoController::class, 'edit'])->middleware('check-permission:cargos.edit')->name('edit');
        Route::put('/{cargo}', [CargoController::class, 'update'])->middleware('check-permission:cargos.edit')->name('update');
        Route::delete('/{cargo}', [CargoController::class, 'destroy'])->middleware('check-permission:cargos.delete')->name('destroy');
    });

    // Personal
    Route::prefix('admin/personal')->name('personal.')->group(function () {
        Route::get('/', [PersonalController::class, 'index'])->middleware('check-permission:personal.view')->name('index');
        Route::get('/create', [PersonalController::class, 'create'])->middleware('check-permission:personal.create')->name('create');
        Route::post('/', [PersonalController::class, 'store'])->middleware('check-permission:personal.create')->name('store');
        Route::get('/{personal}/edit', [PersonalController::class, 'edit'])->middleware('check-permission:personal.edit')->name('edit');
        Route::put('/{personal}', [PersonalController::class, 'update'])->middleware('check-permission:personal.edit')->name('update');
        Route::delete('/{personal}', [PersonalController::class, 'destroy'])->middleware('check-permission:personal.delete')->name('destroy');
    });

    // Sucursales
    Route::prefix('admin/branches')->name('branches.')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->middleware('check-permission:branches.view')->name('index');
        Route::get('/create', [BranchController::class, 'create'])->middleware('check-permission:branches.create')->name('create');
        Route::post('/', [BranchController::class, 'store'])->middleware('check-permission:branches.create')->name('store');
        Route::get('/{branch}', [BranchController::class, 'show'])->middleware('check-permission:branches.view')->name('show');
        Route::get('/{branch}/edit', [BranchController::class, 'edit'])->middleware('check-permission:branches.edit')->name('edit');
        Route::put('/{branch}', [BranchController::class, 'update'])->middleware('check-permission:branches.edit')->name('update');
        Route::delete('/{branch}', [BranchController::class, 'destroy'])->middleware('check-permission:branches.delete')->name('destroy');
    });

    // Cajas: primer módulo gobernado por el plan además del permiso.
    Route::middleware('plan:caja')->prefix('admin/cajas')->name('cajas.')->group(function () {
        Route::get('/', [CajaController::class, 'index'])->middleware('check-permission:cajas.view')->name('index');
        Route::get('/create', [CajaController::class, 'create'])->middleware('check-permission:cajas.create')->name('create');
        Route::post('/', [CajaController::class, 'store'])->middleware('check-permission:cajas.create')->name('store');
        Route::get('/{caja}/edit', [CajaController::class, 'edit'])->middleware('check-permission:cajas.edit')->name('edit');
        Route::put('/{caja}', [CajaController::class, 'update'])->middleware('check-permission:cajas.edit')->name('update');
        Route::delete('/{caja}', [CajaController::class, 'destroy'])->middleware('check-permission:cajas.delete')->name('destroy');
    });
});

// Fallback
Route::redirect('/', '/dashboard');
