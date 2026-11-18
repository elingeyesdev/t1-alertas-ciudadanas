@extends('layouts.panel')
@php use App\Support\PanelLabels; @endphp

@section('title', 'Estaciones')
@section('pretitle', 'Policía, bomberos y hospitales')

@section('actions')
    <a href="{{ route('panel.estaciones.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Nueva estación</a>
@endsection

@section('content')
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Total de estaciones', 'value' => $stats['total'], 'icon' => 'ti-building', 'color' => 'secondary'])</div>
        @foreach (PanelLabels::STATION_TYPES as $value => [$label, $color, $icon])
            <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => $label, 'value' => $stats[$value], 'icon' => $icon, 'color' => $color, 'href' => route('panel.estaciones.index', ['tipo' => $value])])</div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="q">Buscar</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nombre de la estación">
                    </div>
                </div>
                <div class="col-8 col-md-4">
                    <label class="form-label" for="tipo">Tipo</label>
                    <select id="tipo" name="tipo" class="form-select">
                        <option value="">Todos</option>
                        @foreach (PanelLabels::STATION_TYPES as $value => [$label])
                            <option value="{{ $value }}" @selected(($filters['tipo'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-4 col-md-2 d-flex gap-1">
                    <button class="btn btn-primary w-100" aria-label="Filtrar"><i class="ti ti-filter"></i></button>
                    @if (array_filter($filters))
                        <a href="{{ route('panel.estaciones.index') }}" class="btn btn-icon" aria-label="Quitar filtros"><i class="ti ti-x"></i></a>
                    @endif
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>#</th><th>Nombre</th><th>Tipo</th><th>Ubicación</th><th>Despachos</th><th class="w-1"></th></tr></thead>
                <tbody>
                @forelse ($stations as $station)
                    <tr>
                        <td class="text-secondary">{{ $station->id }}</td>
                        <td>{{ $station->name }}</td>
                        <td>{!! PanelLabels::badge(PanelLabels::STATION_TYPES, $station->installation_type?->value) !!}</td>
                        <td class="text-secondary small">{{ number_format($station->latitude, 5) }}, {{ number_format($station->longitude, 5) }}</td>
                        <td class="text-secondary">{{ $station->dispatches_count }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('panel.estaciones.edit', $station->id) }}" class="btn btn-sm"><i class="ti ti-edit me-1"></i>Editar</a>
                            <form method="POST" action="{{ route('panel.estaciones.destroy', $station->id) }}" class="d-inline"
                                  data-confirm="Se eliminará «{{ $station->name }}»." data-confirm-title="¿Eliminar estación?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-ghost-danger" aria-label="Eliminar {{ $station->name }}"><i class="ti ti-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-5">No hay estaciones con esos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($stations->hasPages())
            <div class="card-footer d-flex align-items-center">{{ $stations->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endsection
