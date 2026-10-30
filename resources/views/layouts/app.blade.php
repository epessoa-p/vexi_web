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
            <div class="brand-icon"><i class="bi bi-scissors"></i></div>
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
            </ul>
        </nav>
    </div>
</div>

@endsection
