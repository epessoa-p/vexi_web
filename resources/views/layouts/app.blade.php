@extends('layouts.base')

@section('content')
@php
    $authUser = auth()->user();
    // $tenantCompany y $globalMode los comparte el middleware SetTenant.
    $currentCompany = $tenantCompany ?? null;
    $activeCompanies = $authUser->activeCompanies()->get();
    $subscription = $currentCompany?->subscription;

    $can = fn (string $permission) => $authUser->hasPermissionInCompany($permission, $currentCompany);
    $hasModule = fn (string $module) => $authUser->is_super_admin || (bool) $currentCompany?->planAllows($module);
@endphp

<div class="app-shell d-flex">
    <aside class="app-sidebar">
        <div class="sidebar-brand">
            <button class="btn btn-link p-0 me-2 d-lg-none text-dark" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebarMobile" aria-controls="appSidebarMobile">
                <i class="bi bi-list fs-4"></i>
            </button>
            <div class="brand-icon"><i class="bi bi-heart-pulse"></i></div>
            <div>
                <div class="brand-title">VEXI</div>
                <small class="text-muted">{{ $currentCompany?->name ?? 'Plataforma' }}</small>
            </div>
        </div>

        <div class="sidebar-section-title">Principal</div>
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a class="nav-link app-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="bi bi-house"></i> Dashboard
                </a>
            </li>
        </ul>

        {{-- Los módulos clínicos son de UNA veterinaria: en Modo Global no se muestran. --}}
        @php
            $canAgenda = $currentCompany && $can('appointments.view') && $hasModule('agenda');
            $canConsultations = $currentCompany && $can('consultations.view') && $hasModule('consultas');
            $canHealth = $currentCompany && $can('vaccinations.view') && $hasModule('vacunas');
            $canVaccineCatalog = $currentCompany && $can('vaccines.view') && $hasModule('vacunas');
            $canProducts = $currentCompany && $can('products.view') && $hasModule('inventario');
            $canInventory = $currentCompany && $can('inventory.view') && $hasModule('inventario');
            $canSales = $currentCompany && $can('sales.view') && $hasModule('pos');
            $canSell = $currentCompany && $can('sales.create') && $hasModule('pos');
            $canCash = $currentCompany && $can('cash.view') && $hasModule('caja');
            $canExpenses = $currentCompany && $can('expenses.view') && $hasModule('caja');
            $canPurchases = $currentCompany && $can('purchases.view') && $hasModule('compras');
            $canSuppliers = $currentCompany && $can('suppliers.view') && $hasModule('compras');
        @endphp
        @if($currentCompany && ($can('clients.view') || $can('pets.view') || $canAgenda || $canConsultations || $canHealth))
            <div class="sidebar-section-title mt-4">Clínica</div>
            <ul class="nav flex-column gap-1">
                @if($canAgenda)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('appointments.*') ? 'active' : '' }}" href="{{ route('appointments.index') }}">
                            <i class="bi bi-calendar-week"></i> Agenda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('waiting-room.*') ? 'active' : '' }}" href="{{ route('waiting-room.index') }}">
                            <i class="bi bi-hourglass-split"></i> Sala de espera
                        </a>
                    </li>
                @endif
                @if($canConsultations)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('consultations.*') ? 'active' : '' }}" href="{{ route('consultations.index') }}">
                            <i class="bi bi-clipboard2-pulse"></i> Consultas
                        </a>
                    </li>
                @endif
                @if($canHealth)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('health.*', 'vaccinations.*', 'dewormings.*') ? 'active' : '' }}" href="{{ route('health.index') }}">
                            <i class="bi bi-shield-plus"></i> Vacunación
                        </a>
                    </li>
                @endif
                @if($can('clients.view'))
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('clients.*') ? 'active' : '' }}" href="{{ route('clients.index') }}">
                            <i class="bi bi-people"></i> Clientes
                        </a>
                    </li>
                @endif
                @if($can('pets.view'))
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('pets.*') ? 'active' : '' }}" href="{{ route('pets.index') }}">
                            <i class="bi bi-heart-pulse"></i> Mascotas
                        </a>
                    </li>
                @endif
            </ul>
        @endif

        @if($authUser->is_super_admin)
            <div class="sidebar-section-title mt-4">Plataforma</div>
            <ul class="nav flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link app-link {{ request()->routeIs('companies.*') ? 'active' : '' }}" href="{{ route('companies.index') }}">
                        <i class="bi bi-building"></i> Empresas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link app-link {{ request()->routeIs('plans.*') ? 'active' : '' }}" href="{{ route('plans.index') }}">
                        <i class="bi bi-box-seam"></i> Planes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link app-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}">
                        <i class="bi bi-shield-lock"></i> Roles
                    </a>
                </li>
            </ul>
        @endif

        @if($canSales || $canCash || $canExpenses)
            <div class="sidebar-section-title mt-4">Ventas y caja</div>
            <ul class="nav flex-column gap-1">
                @if($canSell)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.create') }}">
                            <i class="bi bi-cart-plus"></i> Nueva venta
                        </a>
                    </li>
                @endif
                @if($canSales)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.index') }}">
                            <i class="bi bi-receipt"></i> Ventas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('receivables.*') ? 'active' : '' }}" href="{{ route('receivables.index') }}">
                            <i class="bi bi-hourglass"></i> Cuentas por cobrar
                        </a>
                    </li>
                @endif
                @if($canCash)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('cash.*') ? 'active' : '' }}" href="{{ route('cash.index') }}">
                            <i class="bi bi-cash-coin"></i> Caja
                        </a>
                    </li>
                @endif
                @if($canExpenses)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}" href="{{ route('expenses.index') }}">
                            <i class="bi bi-wallet2"></i> Gastos
                        </a>
                    </li>
                @endif
            </ul>
        @endif

        @if($canProducts || $canInventory || $canPurchases || $canSuppliers)
            <div class="sidebar-section-title mt-4">Inventario y compras</div>
            <ul class="nav flex-column gap-1">
                @if($canProducts)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">
                            <i class="bi bi-box-seam"></i> Productos
                        </a>
                    </li>
                @endif
                @if($canInventory)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('stock-movements.*') ? 'active' : '' }}" href="{{ route('stock-movements.index') }}">
                            <i class="bi bi-arrow-left-right"></i> Movimientos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('expiry.*') ? 'active' : '' }}" href="{{ route('expiry.index') }}">
                            <i class="bi bi-hourglass-bottom"></i> Vencimientos
                        </a>
                    </li>
                @endif
                @if($canPurchases)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}" href="{{ route('purchases.index') }}">
                            <i class="bi bi-bag"></i> Compras
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('payables.*') ? 'active' : '' }}" href="{{ route('payables.index') }}">
                            <i class="bi bi-hourglass-split"></i> Cuentas por pagar
                        </a>
                    </li>
                @endif
                @if($canSuppliers)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">
                            <i class="bi bi-truck"></i> Proveedores
                        </a>
                    </li>
                @endif
            </ul>
        @endif

        <div class="sidebar-section-title mt-4">Administracion</div>
        <ul class="nav flex-column gap-1">
            @if($can('users.view'))
                <li class="nav-item">
                    <a class="nav-link app-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                        <i class="bi bi-person-gear"></i> Usuarios
                    </a>
                </li>
            @endif
            @if($can('cargos.view'))
                <li class="nav-item">
                    <a class="nav-link app-link {{ request()->routeIs('cargos.*') ? 'active' : '' }}" href="{{ route('cargos.index') }}">
                        <i class="bi bi-briefcase"></i> Cargos
                    </a>
                </li>
            @endif
            @if($can('personal.view'))
                <li class="nav-item">
                    <a class="nav-link app-link {{ request()->routeIs('personal.*') ? 'active' : '' }}" href="{{ route('personal.index') }}">
                        <i class="bi bi-person-vcard"></i> Personal
                    </a>
                </li>
            @endif
            @if($can('branches.view'))
                <li class="nav-item">
                    <a class="nav-link app-link {{ request()->routeIs('branches.*') ? 'active' : '' }}" href="{{ route('branches.index') }}">
                        <i class="bi bi-diagram-2"></i> Sucursales
                    </a>
                </li>
            @endif
            {{-- Cajas depende del módulo 'caja' del plan (ver Plan::PERMISSION_MODULE_FEATURES);
                 la ruta lo exige con plan:caja, aquí solo se oculta el enlace. --}}
            @if($can('cajas.view') && $hasModule('caja'))
                <li class="nav-item">
                    <a class="nav-link app-link {{ request()->routeIs('cajas.*') ? 'active' : '' }}" href="{{ route('cajas.index') }}">
                        <i class="bi bi-safe"></i> Cajas
                    </a>
                </li>
            @endif
        </ul>

        @if($currentCompany && ($can('species.view') || $can('services.view') || $canVaccineCatalog || $canProducts || $canSales || $canExpenses))
            <div class="sidebar-section-title mt-4">Configuración</div>
            <ul class="nav flex-column gap-1">
                @if($can('services.view'))
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('services.*') ? 'active' : '' }}" href="{{ route('services.index') }}">
                            <i class="bi bi-card-checklist"></i> Servicios
                        </a>
                    </li>
                @endif
                @if($canVaccineCatalog)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('vaccines.*') ? 'active' : '' }}" href="{{ route('vaccines.index') }}">
                            <i class="bi bi-capsule"></i> Vacunas
                        </a>
                    </li>
                @endif
                @if($canProducts)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('product-categories.*') ? 'active' : '' }}" href="{{ route('product-categories.index') }}">
                            <i class="bi bi-bookmarks"></i> Categorías de productos
                        </a>
                    </li>
                @endif
                @if($canSales)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('payment-methods.*') ? 'active' : '' }}" href="{{ route('payment-methods.index') }}">
                            <i class="bi bi-credit-card"></i> Métodos de pago
                        </a>
                    </li>
                @endif
                @if($canExpenses)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('expense-categories.*') ? 'active' : '' }}" href="{{ route('expense-categories.index') }}">
                            <i class="bi bi-tags"></i> Categorías de gastos
                        </a>
                    </li>
                @endif
                @if($can('species.view'))
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs('species.*') ? 'active' : '' }}" href="{{ route('species.index') }}">
                            <i class="bi bi-tags"></i> Especies y razas
                        </a>
                    </li>
                @endif
            </ul>
        @endif
    </aside>

    <main class="app-main">
        <nav class="navbar navbar-expand-lg app-topbar mb-4">
            <div class="container-fluid px-0">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebarMobile" aria-controls="appSidebarMobile">
                        <i class="bi bi-list"></i>
                    </button>
                    <span class="topbar-label">Overview</span>
                    <span class="topbar-separator">|</span>
                    <span class="text-muted small">
                        {{ ($globalMode ?? false) ? 'Modo Global' : ($currentCompany?->name ?? 'Sin empresa activa') }}
                    </span>

                    @if($subscription)
                        @php
                            $badge = match (true) {
                                $subscription->isBlocked() => 'danger',
                                $subscription->inGrace() => 'warning',
                                $subscription->onTrial() => 'info',
                                $subscription->isCurrent() => 'success',
                                default => 'danger',
                            };
                        @endphp
                        <span class="badge bg-{{ $badge }}" title="Plan {{ $subscription->plan?->name }}">
                            {{ $subscription->statusLabel() }}
                        </span>
                    @endif

                    @if(!auth()->user()->is_super_admin && $activeCompanies->count() > 1)
                        <div class="dropdown">
                            <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-buildings"></i> Empresa
                            </button>
                            <ul class="dropdown-menu shadow border-0">
                                @foreach($activeCompanies as $company)
                                    <li>
                                        <form action="{{ route('set-company', $company->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center">
                                                <span>{{ $company->name }}</span>
                                                @if($currentCompany && $currentCompany->id === $company->id)
                                                    <i class="bi bi-check-lg text-success"></i>
                                                @endif
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <button class="btn btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li><span class="dropdown-item-text text-muted">{{ auth()->user()->name }}</span></li>
                            <li><span class="dropdown-item-text text-muted small">{{ auth()->user()->email }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('profile.professional') }}"><i class="bi bi-person-badge"></i> Mi perfil profesional</a></li>
                        </ul>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                        @csrf
                        <button class="btn btn-logout" type="submit">
                            <i class="bi bi-box-arrow-right"></i> Cerrar sesion
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        @if(isset($breadcrumbs))
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    @foreach($breadcrumbs as $label => $url)
                        @if($loop->last)
                            <li class="breadcrumb-item active">{{ $label }}</li>
                        @else
                            <li class="breadcrumb-item"><a href="{{ $url }}" class="text-decoration-none">{{ $label }}</a></li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif

        @if($globalMode ?? false)
            <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
                <div>
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Modo Global</strong> — estás viendo datos de <strong>todas</strong> las empresas.
                </div>
                <a href="{{ route('companies.index') }}" class="btn btn-sm btn-outline-dark">
                    Entrar como empresa…
                </a>
            </div>
        @elseif($authUser->is_super_admin && $currentCompany)
            <div class="alert alert-info d-flex justify-content-between align-items-center" role="alert">
                <div>
                    <i class="bi bi-eye-fill"></i>
                    Estás viendo la plataforma como <strong>{{ $currentCompany->name }}</strong>.
                </div>
                <form action="{{ route('exit-company') }}" method="POST" class="m-0">
                    @csrf
                    <button class="btn btn-sm btn-outline-dark" type="submit">Salir del modo empresa</button>
                </form>
            </div>
        @endif

        @if($message = session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> {{ $message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($message = session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> {{ $message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i>
                @if($errors->count() === 1)
                    {{ $errors->first() }}
                @else
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('page')
    </main>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="appSidebarMobile" aria-labelledby="appSidebarMobileLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="appSidebarMobileLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="p-3">
            <ul class="nav flex-column gap-1">
                <li><a class="nav-link app-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a></li>
                @if($canAgenda)
                    <li><a class="nav-link app-link {{ request()->routeIs('appointments.*') ? 'active' : '' }}" href="{{ route('appointments.index') }}">Agenda</a></li>
                    <li><a class="nav-link app-link {{ request()->routeIs('waiting-room.*') ? 'active' : '' }}" href="{{ route('waiting-room.index') }}">Sala de espera</a></li>
                @endif
                @if($canConsultations)
                    <li><a class="nav-link app-link {{ request()->routeIs('consultations.*') ? 'active' : '' }}" href="{{ route('consultations.index') }}">Consultas</a></li>
                @endif
                @if($canHealth)
                    <li><a class="nav-link app-link {{ request()->routeIs('health.*', 'vaccinations.*', 'dewormings.*') ? 'active' : '' }}" href="{{ route('health.index') }}">Vacunación</a></li>
                @endif
                @if($currentCompany && $can('clients.view'))
                    <li><a class="nav-link app-link {{ request()->routeIs('clients.*') ? 'active' : '' }}" href="{{ route('clients.index') }}">Clientes</a></li>
                @endif
                @if($currentCompany && $can('pets.view'))
                    <li><a class="nav-link app-link {{ request()->routeIs('pets.*') ? 'active' : '' }}" href="{{ route('pets.index') }}">Mascotas</a></li>
                @endif
                @if($authUser->is_super_admin)
                    <li><a class="nav-link app-link {{ request()->routeIs('companies.*') ? 'active' : '' }}" href="{{ route('companies.index') }}">Empresas</a></li>
                    <li><a class="nav-link app-link {{ request()->routeIs('plans.*') ? 'active' : '' }}" href="{{ route('plans.index') }}">Planes</a></li>
                    <li><a class="nav-link app-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}">Roles</a></li>
                @endif
                @if($can('users.view'))
                    <li><a class="nav-link app-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">Usuarios</a></li>
                @endif
                @if($can('cargos.view'))
                    <li><a class="nav-link app-link {{ request()->routeIs('cargos.*') ? 'active' : '' }}" href="{{ route('cargos.index') }}">Cargos</a></li>
                @endif
                @if($can('personal.view'))
                    <li><a class="nav-link app-link {{ request()->routeIs('personal.*') ? 'active' : '' }}" href="{{ route('personal.index') }}">Personal</a></li>
                @endif
                @if($can('branches.view'))
                    <li><a class="nav-link app-link {{ request()->routeIs('branches.*') ? 'active' : '' }}" href="{{ route('branches.index') }}">Sucursales</a></li>
                @endif
                @if($can('cajas.view') && $hasModule('caja'))
                    <li><a class="nav-link app-link {{ request()->routeIs('cajas.*') ? 'active' : '' }}" href="{{ route('cajas.index') }}">Cajas</a></li>
                @endif
                @if($currentCompany && $can('services.view'))
                    <li><a class="nav-link app-link {{ request()->routeIs('services.*') ? 'active' : '' }}" href="{{ route('services.index') }}">Servicios</a></li>
                @endif
                @if($canVaccineCatalog)
                    <li><a class="nav-link app-link {{ request()->routeIs('vaccines.*') ? 'active' : '' }}" href="{{ route('vaccines.index') }}">Vacunas</a></li>
                @endif
                @if($canSell)
                    <li><a class="nav-link app-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.create') }}">Nueva venta</a></li>
                @endif
                @if($canSales)
                    <li><a class="nav-link app-link {{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.index') }}">Ventas</a></li>
                    <li><a class="nav-link app-link {{ request()->routeIs('receivables.*') ? 'active' : '' }}" href="{{ route('receivables.index') }}">Cuentas por cobrar</a></li>
                @endif
                @if($canCash)
                    <li><a class="nav-link app-link {{ request()->routeIs('cash.*') ? 'active' : '' }}" href="{{ route('cash.index') }}">Caja</a></li>
                @endif
                @if($canExpenses)
                    <li><a class="nav-link app-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}" href="{{ route('expenses.index') }}">Gastos</a></li>
                @endif
                @if($canProducts)
                    <li><a class="nav-link app-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">Productos</a></li>
                @endif
                @if($canInventory)
                    <li><a class="nav-link app-link {{ request()->routeIs('stock-movements.*') ? 'active' : '' }}" href="{{ route('stock-movements.index') }}">Movimientos</a></li>
                    <li><a class="nav-link app-link {{ request()->routeIs('expiry.*') ? 'active' : '' }}" href="{{ route('expiry.index') }}">Vencimientos</a></li>
                @endif
                @if($canPurchases)
                    <li><a class="nav-link app-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}" href="{{ route('purchases.index') }}">Compras</a></li>
                    <li><a class="nav-link app-link {{ request()->routeIs('payables.*') ? 'active' : '' }}" href="{{ route('payables.index') }}">Cuentas por pagar</a></li>
                @endif
                @if($canSuppliers)
                    <li><a class="nav-link app-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">Proveedores</a></li>
                @endif
                @if($currentCompany && $can('species.view'))
                    <li><a class="nav-link app-link {{ request()->routeIs('species.*') ? 'active' : '' }}" href="{{ route('species.index') }}">Especies y razas</a></li>
                @endif
            </ul>
        </nav>
    </div>
</div>

@endsection
