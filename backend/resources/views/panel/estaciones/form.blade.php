@extends('layouts.panel')
@php use App\Support\PanelLabels; @endphp

@section('title', $station ? 'Editar estación' : 'Nueva estación')
@section('pretitle')
    <a href="{{ route('panel.estaciones.index') }}" class="text-reset"><i class="ti ti-arrow-left"></i> Estaciones</a>
@endsection

@push('scripts')
    @vite('resources/js/panel-picker.js')
@endpush

@section('content')
    <form method="POST" action="{{ $station ? route('panel.estaciones.update', $station->id) : route('panel.estaciones.store') }}" class="card">
        @csrf
        @if ($station) @method('PATCH') @endif
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="mb-3">
                        <label class="form-label required" for="name">Nombre</label>
                        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $station?->name) }}" required maxlength="255" placeholder="Ej.: EPI 3 Plan 3000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Tipo de estación</label>
                        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column">
                            @foreach (PanelLabels::STATION_TYPES as $value => [$label, $color, $icon])
                                <label class="form-selectgroup-item flex-fill">
                                    <input type="radio" name="installation_type" value="{{ $value }}" class="form-selectgroup-input" @checked(old('installation_type', $station?->installation_type?->value) === $value) required>
                                    <div class="form-selectgroup-label d-flex align-items-center p-3">
                                        <span class="avatar bg-{{ $color }}-lt me-3"><i class="ti {{ $icon }} fs-2"></i></span>
                                        <span>{{ $label }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    @include('panel.partials.location-picker', ['lat' => $station?->latitude, 'lng' => $station?->longitude])
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('panel.estaciones.index') }}" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary">{{ $station ? 'Guardar cambios' : 'Crear estación' }}</button>
        </div>
    </form>
@endsection
