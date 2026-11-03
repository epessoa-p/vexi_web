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
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\PetController;
use App\Http\Controllers\Admin\SpeciesController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\WaitingRoomController;
use App\Http\Controllers\Admin\ConsultationController;
use App\Http\Controllers\ProfessionalProfileController;
use App\Http\Controllers\Admin\VaccineController;
use App\Http\Controllers\Admin\VaccinationController;
use App\Http\Controllers\Admin\DewormingController;
use App\Http\Controllers\Admin\HealthScheduleController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\StockMovementController;
use App\Http\Controllers\Admin\ExpiryController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\CashController;
use App\Http\Controllers\Admin\ReceivableController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\SalesSettingsController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\PayableController;
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

    // Perfil profesional propio: matrícula, especialidad y firma para las recetas.
    Route::get('/mi-perfil-profesional', [ProfessionalProfileController::class, 'edit'])->name('profile.professional');
    Route::put('/mi-perfil-profesional', [ProfessionalProfileController::class, 'update'])->name('profile.professional.update');

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

    /*
    |----------------------------------------------------------------------
    | Clínica: clientes, mascotas y su configuración
    |----------------------------------------------------------------------
    | Administrativos (siempre disponibles, sin plan:): son el dato maestro de
    | todos los módulos clínicos. `tenant` exige una veterinaria activa.
    */
    Route::middleware('tenant')->group(function () {

        // Clientes / propietarios
        Route::prefix('clientes')->name('clients.')->group(function () {
            Route::get('/', [ClientController::class, 'index'])->middleware('check-permission:clients.view')->name('index');
            Route::get('/create', [ClientController::class, 'create'])->middleware('check-permission:clients.create')->name('create');
            Route::get('/buscar', [ClientController::class, 'search'])->middleware('check-permission:clients.view,pets.create,pets.edit,sales.create')->name('search');
            Route::get('/{client}/mascotas', [ClientController::class, 'pets'])
                ->middleware('check-permission:pets.view,appointments.create,appointments.edit,consultations.create,sales.create')->name('pets');
            Route::post('/', [ClientController::class, 'store'])->middleware('check-permission:clients.create')->name('store');
            Route::get('/{client}', [ClientController::class, 'show'])->middleware('check-permission:clients.view')->name('show');
            Route::get('/{client}/edit', [ClientController::class, 'edit'])->middleware('check-permission:clients.edit')->name('edit');
            Route::put('/{client}', [ClientController::class, 'update'])->middleware('check-permission:clients.edit')->name('update');
            Route::delete('/{client}', [ClientController::class, 'destroy'])->middleware('check-permission:clients.delete')->name('destroy');
        });

        // Mascotas / pacientes
        Route::prefix('mascotas')->name('pets.')->group(function () {
            Route::get('/', [PetController::class, 'index'])->middleware('check-permission:pets.view')->name('index');
            Route::get('/create', [PetController::class, 'create'])->middleware('check-permission:pets.create')->name('create');
            Route::post('/', [PetController::class, 'store'])->middleware('check-permission:pets.create')->name('store');
            Route::get('/{pet}', [PetController::class, 'show'])->middleware('check-permission:pets.view')->name('show');
            Route::get('/{pet}/edit', [PetController::class, 'edit'])->middleware('check-permission:pets.edit')->name('edit');
            Route::put('/{pet}', [PetController::class, 'update'])->middleware('check-permission:pets.edit')->name('update');
            Route::delete('/{pet}', [PetController::class, 'destroy'])->middleware('check-permission:pets.delete')->name('destroy');

            // Ficha del paciente
            Route::put('/{pet}/antecedentes', [PetController::class, 'updateHistory'])->middleware('check-permission:pets.edit')->name('history.update');
            Route::post('/{pet}/pesos', [PetController::class, 'storeWeight'])->middleware('check-permission:pets.edit')->name('weights.store');
            Route::delete('/{pet}/pesos/{weight}', [PetController::class, 'destroyWeight'])->middleware('check-permission:pets.edit')->name('weights.destroy');
        });

        // Configuración clínica: especies y razas
        Route::prefix('configuracion/especies')->name('species.')->group(function () {
            Route::get('/', [SpeciesController::class, 'index'])->middleware('check-permission:species.view')->name('index');
            Route::post('/', [SpeciesController::class, 'store'])->middleware('check-permission:species.create')->name('store');
            Route::post('/restaurar', [SpeciesController::class, 'restoreDefaults'])->middleware('check-permission:species.create')->name('restore-defaults');
            Route::get('/{species}', [SpeciesController::class, 'show'])->middleware('check-permission:species.view')->name('show');
            Route::put('/{species}', [SpeciesController::class, 'update'])->middleware('check-permission:species.edit')->name('update');
            Route::delete('/{species}', [SpeciesController::class, 'destroy'])->middleware('check-permission:species.delete')->name('destroy');
            Route::post('/{species}/razas', [SpeciesController::class, 'storeBreed'])->middleware('check-permission:species.create')->name('breeds.store');
            Route::put('/{species}/razas/{breed}', [SpeciesController::class, 'updateBreed'])->middleware('check-permission:species.edit')->name('breeds.update');
            Route::delete('/{species}/razas/{breed}', [SpeciesController::class, 'destroyBreed'])->middleware('check-permission:species.delete')->name('breeds.destroy');
        });

        // Configuración: catálogo de servicios (administrativo)
        Route::prefix('configuracion/servicios')->name('services.')->group(function () {
            Route::get('/', [ServiceController::class, 'index'])->middleware('check-permission:services.view')->name('index');
            Route::get('/create', [ServiceController::class, 'create'])->middleware('check-permission:services.create')->name('create');
            Route::post('/', [ServiceController::class, 'store'])->middleware('check-permission:services.create')->name('store');
            Route::get('/{service}/edit', [ServiceController::class, 'edit'])->middleware('check-permission:services.edit')->name('edit');
            Route::put('/{service}', [ServiceController::class, 'update'])->middleware('check-permission:services.edit')->name('update');
            Route::delete('/{service}', [ServiceController::class, 'destroy'])->middleware('check-permission:services.delete')->name('destroy');
        });

        // Agenda y sala de espera: gobernadas por el módulo 'agenda' del plan.
        Route::middleware('plan:agenda')->group(function () {
            Route::prefix('agenda')->name('appointments.')->group(function () {
                Route::get('/', [AppointmentController::class, 'index'])->middleware('check-permission:appointments.view')->name('index');
                Route::get('/eventos', [AppointmentController::class, 'events'])->middleware('check-permission:appointments.view')->name('events');
                Route::get('/create', [AppointmentController::class, 'create'])->middleware('check-permission:appointments.create')->name('create');
                Route::post('/', [AppointmentController::class, 'store'])->middleware('check-permission:appointments.create')->name('store');
                Route::get('/{appointment}', [AppointmentController::class, 'show'])->middleware('check-permission:appointments.view')->name('show');
                Route::get('/{appointment}/edit', [AppointmentController::class, 'edit'])->middleware('check-permission:appointments.edit')->name('edit');
                Route::put('/{appointment}', [AppointmentController::class, 'update'])->middleware('check-permission:appointments.edit')->name('update');
                Route::patch('/{appointment}/mover', [AppointmentController::class, 'move'])->middleware('check-permission:appointments.edit')->name('move');
                Route::post('/{appointment}/estado/{action}', [AppointmentController::class, 'transition'])->middleware('check-permission:appointments.edit')->name('transition');
                Route::delete('/{appointment}', [AppointmentController::class, 'destroy'])->middleware('check-permission:appointments.delete')->name('destroy');
            });

            Route::prefix('sala-de-espera')->name('waiting-room.')->group(function () {
                Route::get('/', [WaitingRoomController::class, 'index'])->middleware('check-permission:appointments.view')->name('index');
                Route::post('/', [WaitingRoomController::class, 'walkIn'])->middleware('check-permission:appointments.create')->name('walk-in');
                Route::patch('/{appointment}/asignar', [WaitingRoomController::class, 'assign'])->middleware('check-permission:appointments.edit')->name('assign');
            });
        });

        // Consultas veterinarias: gobernadas por el módulo 'consultas' del plan.
        Route::middleware('plan:consultas')->prefix('consultas')->name('consultations.')->group(function () {
            Route::get('/', [ConsultationController::class, 'index'])->middleware('check-permission:consultations.view')->name('index');
            Route::get('/create', [ConsultationController::class, 'create'])->middleware('check-permission:consultations.create')->name('create');
            Route::post('/', [ConsultationController::class, 'store'])->middleware('check-permission:consultations.create')->name('store');
            Route::post('/desde-cita/{appointment}', [ConsultationController::class, 'startFromAppointment'])
                ->middleware('check-permission:consultations.create')->name('start-from-appointment');
            Route::get('/{consultation}', [ConsultationController::class, 'show'])->middleware('check-permission:consultations.view')->name('show');
            Route::get('/{consultation}/edit', [ConsultationController::class, 'edit'])->middleware('check-permission:consultations.edit')->name('edit');
            Route::put('/{consultation}', [ConsultationController::class, 'update'])->middleware('check-permission:consultations.edit')->name('update');
            Route::delete('/{consultation}', [ConsultationController::class, 'destroy'])->middleware('check-permission:consultations.delete')->name('destroy');
            Route::post('/{consultation}/seguimientos', [ConsultationController::class, 'storeFollowup'])->middleware('check-permission:consultations.edit')->name('followups.store');
            Route::get('/{consultation}/receta', [ConsultationController::class, 'prescription'])->middleware('check-permission:consultations.view')->name('prescription');
            Route::post('/{consultation}/receta/{item}/dispensar', [ConsultationController::class, 'dispense'])
                ->middleware(['plan:inventario', 'check-permission:inventory.create,consultations.edit'])->name('dispense');
        });

        // Ventas (módulo 'pos') y caja/gastos (módulo 'caja').
        Route::middleware('plan:pos')->group(function () {
            Route::get('/ventas/nueva', [PosController::class, 'create'])->middleware('check-permission:sales.create')->name('pos.create');
            Route::post('/ventas', [PosController::class, 'store'])->middleware('check-permission:sales.create')->name('pos.store');
            Route::get('/ventas/mascotas/{pet}/por-cobrar', [PosController::class, 'billable'])->middleware('check-permission:sales.create')->name('pos.billable');

            Route::prefix('ventas')->name('sales.')->group(function () {
                Route::get('/', [SaleController::class, 'index'])->middleware('check-permission:sales.view')->name('index');
                Route::get('/{sale}', [SaleController::class, 'show'])->middleware('check-permission:sales.view')->name('show');
                Route::get('/{sale}/comprobante', [SaleController::class, 'receipt'])->middleware('check-permission:sales.view')->name('receipt');
                Route::post('/{sale}/abonos', [SaleController::class, 'addPayment'])->middleware('check-permission:sales.create')->name('payments.store');
                Route::post('/{sale}/devolucion', [SaleController::class, 'returnItems'])->middleware('check-permission:sales.edit')->name('return');
                Route::post('/{sale}/anular', [SaleController::class, 'cancel'])->middleware('check-permission:sales.edit')->name('cancel');
            });

            Route::get('/cuentas-por-cobrar', [ReceivableController::class, 'index'])->middleware('check-permission:sales.view')->name('receivables.index');

            Route::prefix('configuracion/metodos-de-pago')->name('payment-methods.')->group(function () {
                Route::get('/', [SalesSettingsController::class, 'paymentMethods'])->middleware('check-permission:sales.view')->name('index');
                Route::post('/', [SalesSettingsController::class, 'storePaymentMethod'])->middleware('check-permission:sales.edit')->name('store');
                Route::put('/{method}', [SalesSettingsController::class, 'updatePaymentMethod'])->middleware('check-permission:sales.edit')->name('update');
            });
        });

        Route::middleware('plan:caja')->group(function () {
            Route::prefix('caja')->name('cash.')->group(function () {
                Route::get('/', [CashController::class, 'index'])->middleware('check-permission:cash.view')->name('index');
                Route::post('/{caja}/abrir', [CashController::class, 'open'])->middleware('check-permission:cash.create')->name('open');
                Route::get('/turnos/{session}', [CashController::class, 'show'])->middleware('check-permission:cash.view')->name('show');
                Route::post('/turnos/{session}/movimientos', [CashController::class, 'movement'])->middleware('check-permission:cash.create')->name('movement');
                Route::post('/turnos/{session}/cerrar', [CashController::class, 'close'])->middleware('check-permission:cash.create')->name('close');
            });

            Route::prefix('gastos')->name('expenses.')->group(function () {
                Route::get('/', [ExpenseController::class, 'index'])->middleware('check-permission:expenses.view')->name('index');
                Route::get('/create', [ExpenseController::class, 'create'])->middleware('check-permission:expenses.create')->name('create');
                Route::post('/', [ExpenseController::class, 'store'])->middleware('check-permission:expenses.create')->name('store');
                Route::get('/{expense}/edit', [ExpenseController::class, 'edit'])->middleware('check-permission:expenses.edit')->name('edit');
                Route::put('/{expense}', [ExpenseController::class, 'update'])->middleware('check-permission:expenses.edit')->name('update');
                Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->middleware('check-permission:expenses.delete')->name('destroy');
            });

            Route::prefix('configuracion/categorias-gastos')->name('expense-categories.')->group(function () {
                Route::get('/', [SalesSettingsController::class, 'expenseCategories'])->middleware('check-permission:expenses.view')->name('index');
                Route::post('/', [SalesSettingsController::class, 'storeExpenseCategory'])->middleware('check-permission:expenses.edit')->name('store');
                Route::put('/{category}', [SalesSettingsController::class, 'updateExpenseCategory'])->middleware('check-permission:expenses.edit')->name('update');
            });
        });

        // Compras y proveedores: gobernados por el módulo 'compras' del plan.
        Route::middleware('plan:compras')->group(function () {
            Route::prefix('proveedores')->name('suppliers.')->group(function () {
                Route::get('/', [SupplierController::class, 'index'])->middleware('check-permission:suppliers.view')->name('index');
                Route::get('/create', [SupplierController::class, 'create'])->middleware('check-permission:suppliers.create')->name('create');
                Route::post('/', [SupplierController::class, 'store'])->middleware('check-permission:suppliers.create')->name('store');
                Route::get('/{supplier}', [SupplierController::class, 'show'])->middleware('check-permission:suppliers.view')->name('show');
                Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->middleware('check-permission:suppliers.edit')->name('edit');
                Route::put('/{supplier}', [SupplierController::class, 'update'])->middleware('check-permission:suppliers.edit')->name('update');
                Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->middleware('check-permission:suppliers.delete')->name('destroy');
            });

            Route::prefix('compras')->name('purchases.')->group(function () {
                Route::get('/', [PurchaseController::class, 'index'])->middleware('check-permission:purchases.view')->name('index');
                Route::get('/nueva', [PurchaseController::class, 'create'])->middleware('check-permission:purchases.create')->name('create');
                Route::post('/', [PurchaseController::class, 'store'])->middleware('check-permission:purchases.create')->name('store');
                Route::get('/{purchase}', [PurchaseController::class, 'show'])->middleware('check-permission:purchases.view')->name('show');
                Route::post('/{purchase}/recepcion', [PurchaseController::class, 'receive'])->middleware('check-permission:purchases.edit')->name('receive');
                Route::post('/{purchase}/pagos', [PurchaseController::class, 'addPayment'])->middleware('check-permission:purchases.edit')->name('payments.store');
                Route::post('/{purchase}/cerrar', [PurchaseController::class, 'close'])->middleware('check-permission:purchases.edit')->name('close');
                Route::post('/{purchase}/anular', [PurchaseController::class, 'cancel'])->middleware('check-permission:purchases.edit')->name('cancel');
            });

            Route::get('/cuentas-por-pagar', [PayableController::class, 'index'])->middleware('check-permission:purchases.view')->name('payables.index');
        });

        // Farmacia e inventario: gobernados por el módulo 'inventario' del plan.
        Route::middleware('plan:inventario')->group(function () {
            Route::prefix('inventario/productos')->name('products.')->group(function () {
                Route::get('/', [ProductController::class, 'index'])->middleware('check-permission:products.view')->name('index');
                Route::get('/buscar', [ProductController::class, 'search'])
                    ->middleware('check-permission:products.view,inventory.create,consultations.edit,vaccinations.create,sales.create,purchases.create')->name('search');
                Route::get('/create', [ProductController::class, 'create'])->middleware('check-permission:products.create')->name('create');
                Route::post('/', [ProductController::class, 'store'])->middleware('check-permission:products.create')->name('store');
                Route::get('/{product}', [ProductController::class, 'show'])->middleware('check-permission:products.view')->name('show');
                Route::get('/{product}/edit', [ProductController::class, 'edit'])->middleware('check-permission:products.edit')->name('edit');
                Route::put('/{product}', [ProductController::class, 'update'])->middleware('check-permission:products.edit')->name('update');
                Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('check-permission:products.delete')->name('destroy');
            });

            Route::prefix('inventario/movimientos')->name('stock-movements.')->group(function () {
                Route::get('/', [StockMovementController::class, 'index'])->middleware('check-permission:inventory.view')->name('index');
                Route::get('/create', [StockMovementController::class, 'create'])->middleware('check-permission:inventory.create')->name('create');
                Route::post('/', [StockMovementController::class, 'store'])->middleware('check-permission:inventory.create')->name('store');
            });

            Route::get('/inventario/vencimientos', [ExpiryController::class, 'index'])->middleware('check-permission:inventory.view')->name('expiry.index');
            Route::post('/inventario/lotes/{batch}/baja', [ExpiryController::class, 'writeOff'])->middleware('check-permission:inventory.edit')->name('expiry.write-off');

            Route::prefix('configuracion/categorias-productos')->name('product-categories.')->group(function () {
                Route::get('/', [ProductCategoryController::class, 'index'])->middleware('check-permission:products.view')->name('index');
                Route::post('/', [ProductCategoryController::class, 'store'])->middleware('check-permission:products.create')->name('store');
                Route::put('/{category}', [ProductCategoryController::class, 'update'])->middleware('check-permission:products.edit')->name('update');
                Route::delete('/{category}', [ProductCategoryController::class, 'destroy'])->middleware('check-permission:products.delete')->name('destroy');
            });
        });

        // Vacunas y desparasitaciones: gobernadas por el módulo 'vacunas' del plan.
        Route::middleware('plan:vacunas')->group(function () {
            Route::get('/vacunacion', [HealthScheduleController::class, 'index'])->middleware('check-permission:vaccinations.view')->name('health.index');
            Route::get('/mascotas/{pet}/carnet', [HealthScheduleController::class, 'card'])->middleware('check-permission:vaccinations.view')->name('health.card');

            Route::prefix('vacunacion/vacunas')->name('vaccinations.')->group(function () {
                Route::get('/create', [VaccinationController::class, 'create'])->middleware('check-permission:vaccinations.create')->name('create');
                Route::post('/', [VaccinationController::class, 'store'])->middleware('check-permission:vaccinations.create')->name('store');
                Route::get('/{vaccination}/edit', [VaccinationController::class, 'edit'])->middleware('check-permission:vaccinations.edit')->name('edit');
                Route::put('/{vaccination}', [VaccinationController::class, 'update'])->middleware('check-permission:vaccinations.edit')->name('update');
                Route::delete('/{vaccination}', [VaccinationController::class, 'destroy'])->middleware('check-permission:vaccinations.delete')->name('destroy');
            });

            Route::prefix('vacunacion/desparasitaciones')->name('dewormings.')->group(function () {
                Route::get('/create', [DewormingController::class, 'create'])->middleware('check-permission:vaccinations.create')->name('create');
                Route::post('/', [DewormingController::class, 'store'])->middleware('check-permission:vaccinations.create')->name('store');
                Route::get('/{deworming}/edit', [DewormingController::class, 'edit'])->middleware('check-permission:vaccinations.edit')->name('edit');
                Route::put('/{deworming}', [DewormingController::class, 'update'])->middleware('check-permission:vaccinations.edit')->name('update');
                Route::delete('/{deworming}', [DewormingController::class, 'destroy'])->middleware('check-permission:vaccinations.delete')->name('destroy');
            });

            Route::prefix('configuracion/vacunas')->name('vaccines.')->group(function () {
                Route::get('/', [VaccineController::class, 'index'])->middleware('check-permission:vaccines.view')->name('index');
                Route::get('/create', [VaccineController::class, 'create'])->middleware('check-permission:vaccines.create')->name('create');
                Route::post('/', [VaccineController::class, 'store'])->middleware('check-permission:vaccines.create')->name('store');
                Route::get('/{vaccine}/edit', [VaccineController::class, 'edit'])->middleware('check-permission:vaccines.edit')->name('edit');
                Route::put('/{vaccine}', [VaccineController::class, 'update'])->middleware('check-permission:vaccines.edit')->name('update');
                Route::delete('/{vaccine}', [VaccineController::class, 'destroy'])->middleware('check-permission:vaccines.delete')->name('destroy');
            });
        });
    });
});

// Fallback
Route::redirect('/', '/dashboard');
