@extends('layouts.panel')

@section('title', 'Reportes')

@section('actions')
    @if ($errors->isEmpty())
        <a href="{{ route('panel.reportes.excel', $filters) }}" class="btn btn-success">
            <i class="ti ti-download me-1" aria-hidden="true"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('panel.reportes.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="desde">Desde</label>
                    <input id="desde" name="desde" type="date" class="form-control @error('desde') is-invalid @enderror"
                           value="{{ is_string(old('desde')) ? old('desde') : $filters['desde'] }}" required>
                    @error('desde')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="hasta">Hasta</label>
                    <input id="hasta" name="hasta" type="date" class="form-control @error('hasta') is-invalid @enderror"
                           value="{{ is_string(old('hasta')) ? old('hasta') : $filters['hasta'] }}" required>
                    @error('hasta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="tipo">Tipo de incidente</label>
                    <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror">
                        <option value="">Todos los tipos</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected(old('tipo', $filters['tipo'] ?? null) == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100">Aplicar filtros</button>
                </div>
            </form>
        </div>
    </div>

    @if ($errors->isEmpty())
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Total de incidentes', 'value' => $stats['total'], 'icon' => 'ti-list', 'color' => 'blue'])</div>
            <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Activos', 'value' => $stats['active'], 'icon' => 'ti-alert-triangle', 'color' => 'green'])</div>
            <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Resueltos', 'value' => $stats['resolved'], 'icon' => 'ti-circle-check', 'color' => 'azure'])</div>
            <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Verificados', 'value' => $stats['verified'], 'icon' => 'ti-shield-check', 'color' => 'purple'])</div>
        </div>

        @if ($stats['total'] === 0)
            <div class="alert alert-info">No hay incidentes para los filtros aplicados.</div>
        @endif
    @endif
@endsection
