@extends('layouts.panel')

@section('title', 'Inicio')

@section('content')
    <section class="page-heading" aria-labelledby="page-title">
        <div>
            <p class="eyebrow">Panel de administración</p>
            <h1 id="page-title">Inicio</h1>
            <p class="text-secondary mb-0">El espacio de trabajo inicial para la gestión de alertas ciudadanas.</p>
        </div>
        <span class="status-chip"><span class="status-dot" aria-hidden="true"></span> Acceso autorizado</span>
    </section>

    <section class="welcome-card" aria-labelledby="welcome-title">
        <div class="welcome-icon" aria-hidden="true">AC</div>
        <div>
            <p class="eyebrow">Bienvenido/a</p>
            <h2 id="welcome-title">{{ $user->first_name }} {{ $user->last_name }}</h2>
            <p class="text-secondary mb-0">Tu sesión de administración está activa. Próximamente encontrarás aquí herramientas para gestionar alertas.</p>
        </div>
    </section>

    <section class="next-steps" aria-labelledby="next-steps-title">
        <h2 id="next-steps-title">Este panel está en preparación</h2>
        <p class="text-secondary mb-0">Los módulos de gestión se incorporarán por etapas. Por ahora, no hay acciones de negocio disponibles desde esta pantalla.</p>
    </section>
@endsection


