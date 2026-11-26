@extends('layouts.panel')

@section('title', 'Mapa en vivo')
@section('pretitle', 'Monitoreo')

@push('scripts')
    @vite('resources/js/panel-map.js')
@endpush

@section('actions')
    <button type="button" class="btn" data-map-reload><i class="ti ti-refresh me-1"></i> Actualizar</button>
    <a href="{{ route('panel.alertas.create') }}" class="btn btn-danger"><i class="ti ti-plus me-1"></i> Nueva alerta</a>
@endsection

@section('content')
    <div class="row row-cards">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 map-workspace-controls">
                    <label class="visually-hidden" for="live-map-style">Estilo del mapa</label>
                    <select id="live-map-style" class="form-select">
                        <option value="streets-v12">Calles</option>
                        <option value="light-v11">Claro · destaca las alertas</option>
                        <option value="satellite-streets-v12">Satélite con calles</option>
                    </select>
                    <button type="button" class="btn ms-sm-auto" data-fit-reports disabled>
                        <i class="ti ti-focus-2 me-1" aria-hidden="true"></i> Encuadrar alertas
                    </button>
                </div>
                <div class="card-body p-0 position-relative">
                    <div id="live-map" class="live-map" role="region" aria-label="Mapa de alertas en vivo"></div>
                    <div class="map-legend card card-sm">
                        <div class="card-body py-2 small">
                            <label class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" data-layer="reports" checked>
                                <span class="form-check-label"><span class="legend-dot bg-red"></span>Alertas</span>
                            </label>
                            <label class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" data-layer="stations" checked>
                                <span class="form-check-label"><span class="legend-dot bg-blue"></span>Estaciones</span>
                            </label>
                            <label class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" data-layer="trackings" checked>
                                <span class="form-check-label"><span class="legend-dot bg-yellow"></span>Unidades en camino</span>
                            </label>
                            <label class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" data-layer="risk">
                                <span class="form-check-label"><span class="legend-dot bg-orange"></span>Zonas de riesgo</span>
                            </label>
                            <label class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" data-layer="publicAlerts" checked>
                                <span class="form-check-label"><span class="legend-dot bg-purple"></span>Avisos preventivos</span>
                            </label>
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" data-layer="missingCases" checked>
                                <span class="form-check-label"><span class="legend-dot bg-teal"></span>Desaparecidos</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            @include('panel.partials.report-weight-guide')
            <div class="card live-list-card">
                <div class="card-header">
                    <h2 class="card-title">Alertas activas</h2>
                    <div class="card-actions"><span class="badge bg-red text-white" data-count>0</span></div>
                </div>
                <div class="card-body py-2 border-bottom">
                    <label class="form-label" for="live-report-filter">Mostrar alertas</label>
                    <select id="live-report-filter" class="form-select">
                        <option value="all">Todas</option>
                        <option value="sos">Solo SOS</option>
                        <option value="verified">Verificadas</option>
                        <option value="pending">Sin verificar</option>
                    </select>
                    <label class="form-check mt-2 mb-0">
                        <input type="checkbox" class="form-check-input" data-sort-weight>
                        <span class="form-check-label">Ordenar por peso</span>
                    </label>
                </div>
                <div class="list-group list-group-flush overflow-auto" data-report-list>
                    <div class="list-group-item text-secondary">Cargando…</div>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header"><h2 class="card-title">Unidades en camino</h2></div>
                <div class="list-group list-group-flush" data-tracking-list>
                    <div class="list-group-item text-secondary">Ninguna unidad compartiendo ubicación.</div>
                </div>
            </div>
        </div>
    </div>
@endsection
