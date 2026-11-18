@extends('layouts.panel')
@php use App\Support\PanelLabels; @endphp

@section('title', 'Alertas')
@section('pretitle', 'Reportes de la ciudadanía')

@section('actions')
    <a href="{{ route('panel.alertas.create') }}" class="btn btn-danger"><i class="ti ti-plus me-1"></i> Nueva alerta</a>
@endsection

@section('content')
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Total de alertas', 'value' => $stats['total'], 'icon' => 'ti-list', 'color' => 'blue'])</div>
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Activas', 'value' => $stats['activas'], 'icon' => 'ti-alert-triangle', 'color' => 'green'])</div>
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Resueltas', 'value' => $stats['resueltas'], 'icon' => 'ti-circle-check', 'color' => 'azure'])</div>
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Vencidas / eliminadas', 'value' => $stats['cerradas'], 'icon' => 'ti-archive', 'color' => 'red'])</div>
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('panel.alertas.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="q">Buscar</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="N.º, descripción o zona">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="tipo">Tipo</label>
                    <select id="tipo" name="tipo" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected(($filters['tipo'] ?? null) == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="estado">Estado</label>
                    <select id="estado" name="estado" class="form-select">
                        <option value="">Todos</option>
                        @foreach (PanelLabels::REPORT_STATUS as $value => [$label])
                            <option value="{{ $value }}" @selected(($filters['estado'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="verificacion">Verificación</label>
                    <select id="verificacion" name="verificacion" class="form-select">
                        <option value="">Todas</option>
                        <option value="si" @selected(($filters['verificacion'] ?? null) === 'si')>Verificadas</option>
                        <option value="no" @selected(($filters['verificacion'] ?? null) === 'no')>Sin verificar</option>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label" for="desde">Desde</label>
                    <input id="desde" type="date" name="desde" value="{{ $filters['desde'] ?? '' }}" class="form-control">
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label" for="hasta">Hasta</label>
                    <input id="hasta" type="date" name="hasta" value="{{ $filters['hasta'] ?? '' }}" class="form-control">
                </div>
                <div class="col-6 col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary w-100" aria-label="Filtrar"><i class="ti ti-filter"></i></button>
                    @if (array_filter($filters))
                        <a href="{{ route('panel.alertas.index') }}" class="btn btn-icon" aria-label="Quitar filtros" title="Quitar filtros"><i class="ti ti-x"></i></a>
                    @endif
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter table-hover">
                <thead>
                <tr><th>#</th><th>Tipo</th><th>Descripción</th><th>Zona</th><th>Estado</th><th>Evidencia</th><th>Fecha</th><th class="w-1"></th></tr>
                </thead>
                <tbody>
                @forelse ($reports as $report)
                    <tr>
                        <td class="text-secondary">{{ $report->id }}</td>
                        <td class="text-nowrap">{{ $report->type?->name ?? 'Sin tipo' }}</td>
                        <td class="text-truncate" style="max-width: 260px" title="{{ $report->description }}">{{ $report->description }}</td>
                        <td class="text-secondary">{{ $report->zone }}</td>
                        <td class="text-nowrap">@include('panel.partials.report-row-badges')</td>
                        <td class="text-secondary text-nowrap">
                            <i class="ti ti-photo"></i> {{ $report->images_count }}
                            <i class="ti ti-ambulance ms-2"></i> {{ $report->dispatches_count }}
                        </td>
                        <td class="text-secondary text-nowrap">{{ $report->created_at?->timezone(config('alertas.display_timezone'))->format('d/m/Y H:i') }}</td>
                        <td><a href="{{ route('panel.alertas.show', $report->id) }}" class="btn btn-sm">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-5">No hay alertas con esos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($reports->hasPages())
            <div class="card-footer d-flex align-items-center">{{ $reports->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endsection
