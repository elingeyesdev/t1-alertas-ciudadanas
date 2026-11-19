@extends('layouts.panel')

@section('title', 'Inicio')
@section('pretitle', 'Bienvenido/a, '.$user->first_name)

@section('actions')
    <a href="{{ route('panel.mapa') }}" class="btn"><i class="ti ti-map-2 me-1"></i> Ver mapa en vivo</a>
    <a href="{{ route('panel.alertas.create') }}" class="btn btn-danger"><i class="ti ti-plus me-1"></i> Nueva alerta</a>
@endsection

@section('content')
    @if ($sos->isNotEmpty())
        <div class="card card-sos mb-3">
            <div class="card-status-start bg-red"></div>
            <div class="card-header">
                <h2 class="card-title text-red"><i class="ti ti-sos me-2"></i>Botón de pánico activo ({{ $sos->count() }})</h2>
            </div>
            <div class="list-group list-group-flush">
                @foreach ($sos as $report)
                    @php $dispatch = $report->dispatches->first(); @endphp
                    <a href="{{ route('panel.alertas.show', $report->id) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between gap-2">
                            <strong>#{{ $report->id }} · {{ trim(($report->creator?->first_name ?? '').' '.($report->creator?->last_name ?? '')) ?: 'Ciudadano' }}</strong>
                            <span class="text-secondary small text-nowrap">{{ $report->created_at?->diffForHumans() }}</span>
                        </div>
                        <div class="small text-secondary">{{ $report->zone }} · {{ $report->description }}</div>
                        <div class="small mt-1">
                            @if ($dispatch && $dispatch->state?->value === 'en_curso')
                                <span class="badge bg-yellow-lt"><i class="ti ti-ambulance me-1"></i>{{ $dispatch->departed_at ? 'En camino' : 'Asignada' }}: {{ $dispatch->attendedBy?->first_name }} · {{ $dispatch->destination?->name }}</span>
                            @else
                                <span class="badge bg-red-lt"><i class="ti ti-alert-circle me-1"></i>Sin unidad asignada: despacha una a mano</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

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
