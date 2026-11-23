@php
    $panelUser = auth('web')->user();
    $menu = [
        ['panel.index', 'panel.index', 'ti-layout-dashboard', 'Inicio'],
        ['panel.mapa', 'panel.mapa*', 'ti-map-2', 'Mapa en vivo'],
        ['panel.alertas.index', 'panel.alertas.*', 'ti-alert-triangle', 'Alertas'],
        ['panel.reportes.index', 'panel.reportes.*', 'ti-report-analytics', 'Reportes'],
        ['panel.usuarios.index', 'panel.usuarios.*', 'ti-users', 'Usuarios'],
        ['panel.estaciones.index', 'panel.estaciones.*', 'ti-building-hospital', 'Estaciones'],
    ];
    $panelConfig = [
        'mapboxToken' => config('alertas.panel.mapbox_token'),
        'center' => config('alertas.panel.map_center'),
        'reverb' => config('alertas.panel.reverb'),
        'mapDataUrl' => route('panel.mapa.datos'),
        'reportUrl' => url('/panel/alertas'),
    ];
@endphp
<!doctype html>
<html lang="es" data-bs-navbar-position="vertical">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · Alertas Ciudadanas</title>
    <script>window.PanelConfig = @json($panelConfig);</script>
    @vite(['resources/css/panel.css', 'resources/js/panel.js'])
    @stack('scripts')
</head>
<body class="panel-page">
<a class="skip-link" href="#main-content">Saltar al contenido</a>
<div class="page">
    <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
                    aria-controls="sidebar-menu" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand navbar-brand-autodark" href="{{ route('panel.index') }}">
                <span class="brand-mark" aria-hidden="true">AC</span>
                <span class="brand-title">Alertas Ciudadanas</span>
            </a>
            <div class="navbar-nav flex-row d-lg-none">
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Menú de usuario">
                        <span class="avatar avatar-sm">{{ mb_substr($panelUser->first_name, 0, 1) }}{{ mb_substr($panelUser->last_name, 0, 1) }}</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                        <span class="dropdown-header">{{ $panelUser->first_name }} {{ $panelUser->last_name }}</span>
                        <form method="POST" action="{{ route('panel.logout') }}">@csrf
                            <button type="submit" class="dropdown-item"><i class="ti ti-logout me-2"></i>Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="collapse navbar-collapse" id="sidebar-menu">
                <ul class="navbar-nav pt-lg-3">
                    @foreach ($menu as [$route, $pattern, $icon, $label])
                        <li class="nav-item {{ request()->routeIs($pattern) ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti {{ $icon }}"></i></span>
                                <span class="nav-link-title">{{ $label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-auto p-3 d-none d-lg-block">
                    <a href="{{ route('panel.alertas.create') }}" class="btn btn-danger w-100">
                        <i class="ti ti-plus me-1"></i> Nueva alerta
                    </a>
                </div>
            </div>
        </div>
    </aside>

    <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none">
        <div class="container-xl">
            <div class="navbar-nav flex-row order-md-last">
                <span class="realtime-status me-3" data-realtime-status hidden>
                    <span class="status-dot"></span><span data-realtime-text>Conectando…</span>
                </span>
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Menú de usuario">
                        <span class="avatar avatar-sm">{{ mb_substr($panelUser->first_name, 0, 1) }}{{ mb_substr($panelUser->last_name, 0, 1) }}</span>
                        <div class="ps-2">
                            <div>{{ $panelUser->first_name }} {{ $panelUser->last_name }}</div>
                            <div class="mt-1 small text-secondary">Administración</div>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                        <form method="POST" action="{{ route('panel.logout') }}">@csrf
                            <button type="submit" class="dropdown-item"><i class="ti ti-logout me-2"></i>Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <div class="page-pretitle">@yield('pretitle', 'Panel de administración')</div>
                        <h1 class="page-title">@yield('title', 'Panel')</h1>
                    </div>
                    <div class="col-auto ms-auto d-print-none">
                        <div class="btn-list">@yield('actions')</div>
                    </div>
                </div>
            </div>
        </div>
        <main id="main-content" class="page-body" tabindex="-1">
            <div class="container-xl">
                @include('panel.partials.flash')
                @yield('content')
            </div>
        </main>
        <footer class="footer footer-transparent d-print-none">
            <div class="container-xl text-secondary small d-flex justify-content-between flex-wrap gap-2">
                <span>App de Seguridad Ciudadana</span><span>Panel de administración</span>
            </div>
        </footer>
    </div>
</div>
@include('panel.partials.confirm-modal')
</body>
</html>
