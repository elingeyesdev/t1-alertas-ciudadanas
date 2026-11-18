@extends('layouts.panel')

@section('title', 'Inicio')
@section('pretitle', 'Bienvenido/a, '.$user->first_name)

@section('actions')
    <a href="{{ route('panel.mapa') }}" class="btn"><i class="ti ti-map-2 me-1"></i> Ver mapa en vivo</a>
    <a href="{{ route('panel.alertas.create') }}" class="btn btn-danger"><i class="ti ti-plus me-1"></i> Nueva alerta</a>
@endsection

@section('content')
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Alertas activas', 'value' => $stats['activas'], 'icon' => 'ti-alert-triangle', 'color' => 'red', 'href' => route('panel.alertas.index', ['estado' => 'activo'])])</div>
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Por verificar', 'value' => $stats['sinVerificar'], 'icon' => 'ti-eye-check', 'color' => 'orange', 'href' => route('panel.alertas.index', ['estado' => 'activo', 'verificacion' => 'no'])])</div>
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Unidades en camino', 'value' => $stats['despachos'], 'icon' => 'ti-ambulance', 'color' => 'yellow'])</div>
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Alertas resueltas', 'value' => $stats['resueltas'], 'icon' => 'ti-circle-check', 'color' => 'green', 'href' => route('panel.alertas.index', ['estado' => 'resuelto'])])</div>
        <div class="col-sm-6 col-lg-4">@include('panel.partials.stat', ['label' => 'Ciudadanos registrados', 'value' => $stats['ciudadanos'], 'icon' => 'ti-users', 'color' => 'purple', 'href' => route('panel.usuarios.index', ['rol' => 'usuario'])])</div>
        <div class="col-sm-6 col-lg-4">@include('panel.partials.stat', ['label' => 'Autoridades', 'value' => $stats['autoridades'], 'icon' => 'ti-shield', 'color' => 'blue', 'href' => route('panel.usuarios.index', ['rol' => 'autoridad'])])</div>
        <div class="col-sm-12 col-lg-4">@include('panel.partials.stat', ['label' => 'Estaciones de emergencia', 'value' => $stats['estaciones'], 'icon' => 'ti-building-hospital', 'color' => 'azure', 'href' => route('panel.estaciones.index')])</div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Últimas alertas</h2>
                    <div class="card-actions"><a href="{{ route('panel.alertas.index') }}">Ver todas</a></div>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>#</th><th>Tipo</th><th>Zona</th><th>Estado</th><th>Hace</th></tr></thead>
                        <tbody>
                        @forelse ($latest as $report)
                            <tr>
                                <td class="text-secondary">{{ $report->id }}</td>
                                <td><a href="{{ route('panel.alertas.show', $report->id) }}">{{ $report->type?->name ?? 'Sin tipo' }}</a></td>
                                <td class="text-secondary">{{ $report->zone }}</td>
                                <td>@include('panel.partials.report-row-badges')</td>
                                <td class="text-secondary text-nowrap">{{ $report->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-4">Todavía no hay alertas.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Alertas por tipo (30 días)</h2></div>
                <div class="card-body">
                    @php $max = max(1, $byType->max('total') ?? 1); @endphp
                    @forelse ($byType as $row)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>{{ $row->type?->name ?? 'Sin tipo' }}</span><strong>{{ $row->total }}</strong>
                            </div>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-red" style="width: {{ round($row->total / $max * 100) }}%"
                                     role="progressbar" aria-valuenow="{{ $row->total }}" aria-valuemin="0" aria-valuemax="{{ $max }}"
                                     aria-label="{{ $row->type?->name }}"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-secondary mb-0">Sin alertas en los últimos 30 días.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
