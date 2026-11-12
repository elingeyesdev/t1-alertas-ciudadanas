<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · Alertas Ciudadanas</title>
    @vite(['resources/css/panel.css', 'resources/js/panel.js'])
</head>
<body class="panel-page">
    <a class="skip-link" href="#main-content">Saltar al contenido</a>
    <header class="navbar navbar-expand-md panel-navbar">
        <div class="container-xl">
            <a class="navbar-brand p-0" href="{{ route('panel.index') }}" aria-label="Inicio del panel">
                <span class="brand-mark" aria-hidden="true">AC</span>
                <span class="brand-title">Alertas Ciudadanas</span>
            </a>
            <div class="navbar-nav flex-row order-md-last align-items-center gap-3">
                <span class="identity-label"><span class="identity-name">{{ $user->first_name }} {{ $user->last_name }}</span><span class="identity-role">Administración</span></span>
                <form method="POST" action="{{ route('panel.logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </header>
    <main id="main-content" class="container-xl panel-main" tabindex="-1">
        @yield('content')
    </main>
    <footer class="container-xl panel-footer"><span>Alertas Ciudadanas</span><span>Panel de administración</span></footer>
</body>
</html>
