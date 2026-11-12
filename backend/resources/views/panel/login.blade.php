<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar sesión · Alertas Ciudadanas</title>
    @vite(['resources/css/panel.css', 'resources/js/panel.js'])
</head>
<body class="panel-login-page">
    <main class="login-shell">
        <section class="login-brand" aria-labelledby="login-heading">
            <a class="brand-lockup" href="{{ route('panel.login') }}" aria-label="Alertas Ciudadanas, inicio del panel">
                <span class="brand-mark" aria-hidden="true">AC</span>
                <span>Alertas Ciudadanas</span>
            </a>
            <div class="login-intro">
                <p class="eyebrow">Acceso institucional</p>
                <h1 id="login-heading">Gestiona la atención de alertas</h1>
                <p>Ingresa con tu cuenta de administración para continuar.</p>
            </div>
            <p class="login-footnote">Acceso exclusivo para personal autorizado.</p>
        </section>
        <section class="login-form-panel" aria-label="Formulario de inicio de sesión">
            <div class="login-card">
                <div class="login-card-heading">
                    <p class="eyebrow">Panel de administración</p>
                    <h2>Iniciar sesión</h2>
                    <p>Usa el teléfono asociado a tu cuenta.</p>
                </div>
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert" aria-live="assertive">
                        <span>{{ $errors->first('phone') ?: 'Revisá los datos ingresados.' }}</span>
                    </div>
                @endif
                <form method="POST" action="{{ route('panel.login.store') }}" class="login-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="phone">Teléfono</label>
                        <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="username"
                            class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}"
                            required maxlength="20" autofocus @error('phone') aria-describedby="phone-error" @enderror>
                        @error('phone')<div id="phone-error" class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Contraseña</label>
                        <input id="password" name="password" type="password" autocomplete="current-password"
                            class="form-control @error('password') is-invalid @enderror" required maxlength="255"
                            @error('password') aria-describedby="password-error" @enderror>
                        @error('password')<div id="password-error" class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <label class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span class="form-check-label">Recordarme en este dispositivo</span>
                    </label>
                    <button type="submit" class="btn btn-primary w-100">Entrar al panel</button>
                </form>
                <p class="login-help">¿Necesitas ayuda? Contacta al equipo administrador.</p>
            </div>
        </section>
    </main>
</body>
</html>


