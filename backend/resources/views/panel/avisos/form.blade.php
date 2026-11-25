@extends('layouts.panel')
@php use App\Support\PanelLabels; @endphp

@section('title', 'Nuevo aviso preventivo')
@section('pretitle')
    <a href="{{ route('panel.avisos.index') }}" class="text-reset"><i class="ti ti-arrow-left"></i> Avisos preventivos</a>
@endsection

@push('scripts')
    @vite('resources/js/panel-picker.js')
@endpush

@section('content')
    <form method="POST" action="{{ route('panel.avisos.store') }}" class="card">
        @csrf
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="mb-3">
                        <label class="form-label required">Tipo de aviso</label>
                        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column">
                            @foreach (PanelLabels::PUBLIC_ALERT_CATEGORIES as $value => [$label, $color, $icon])
                                <label class="form-selectgroup-item flex-fill">
                                    <input type="radio" name="category" value="{{ $value }}" class="form-selectgroup-input" @checked(old('category') === $value) required>
                                    <div class="form-selectgroup-label d-flex align-items-center p-3">
                                        <span class="avatar bg-{{ $color }}-lt me-3"><i class="ti {{ $icon }} fs-2"></i></span>
                                        <span>{{ $label }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('category')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="title">Título</label>
                        <input id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required maxlength="120" placeholder="Ej.: Alerta naranja por lluvias intensas">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="message">Mensaje</label>
                        <textarea id="message" name="message" rows="4" class="form-control @error('message') is-invalid @enderror" required maxlength="500" placeholder="Qué pasa y qué debe hacer la población">{{ old('message') }}</textarea>
                        <div class="form-hint">Hasta 500 caracteres. La notificación muestra los primeros 180.</div>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label required" for="radius_m">Zona (radio)</label>
                            <select id="radius_m" name="radius_m" class="form-select @error('radius_m') is-invalid @enderror" required data-picker-radius>
                                @foreach ($radii as $meters => $label)
                                    <option value="{{ $meters }}" @selected((int) old('radius_m', 2000) === $meters)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label required" for="hours">Vigente por</label>
                            <select id="hours" name="hours" class="form-select @error('hours') is-invalid @enderror" required>
                                @foreach ($durations as $hours => $label)
                                    <option value="{{ $hours }}" @selected((int) old('hours', 24) === $hours)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="ti ti-info-circle me-1"></i>
                        Al publicarlo llega una notificación a los ciudadanos que están dentro de la zona y comparten su ubicación.
                        El aviso se ve en el mapa de la app y del panel hasta que termine su vigencia.
                    </div>
                </div>
                <div class="col-lg-7">
                    @include('panel.partials.location-picker', ['lat' => null, 'lng' => null])
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('panel.avisos.index') }}" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="ti ti-send me-1"></i>Publicar aviso</button>
        </div>
    </form>
@endsection
