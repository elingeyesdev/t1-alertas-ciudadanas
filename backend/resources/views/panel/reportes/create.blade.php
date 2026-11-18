@extends('layouts.panel')

@section('title', 'Nueva alerta')
@section('pretitle')
    <a href="{{ route('panel.alertas.index') }}" class="text-reset"><i class="ti ti-arrow-left"></i> Alertas</a>
@endsection

@push('scripts')
    @vite('resources/js/panel-picker.js')
@endpush

@section('content')
    <form method="POST" action="{{ route('panel.alertas.store') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div class="card-header">
            <div>
                <h2 class="card-title">Publicar una alerta desde el panel</h2>
                <div class="text-secondary small">Se mostrará en el mapa y se avisará a los vecinos cercanos, igual que una alerta de la app.</div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="mb-3">
                        <label class="form-label required" for="type">Tipo de alerta</label>
                        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="">Elige un tipo…</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}" @selected(old('type') == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="description">Descripción</label>
                        <textarea id="description" name="description" rows="4" maxlength="250" class="form-control @error('description') is-invalid @enderror" required placeholder="¿Qué está pasando?">{{ old('description') }}</textarea>
                        <div class="form-hint">Máximo 250 caracteres.</div>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="zone">Zona o barrio</label>
                        <input id="zone" name="zone" type="text" maxlength="255" class="form-control" value="{{ old('zone') }}" placeholder="Ej.: Plan 3000, Equipetrol">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="image">Foto (opcional)</label>
                        <input id="image" name="image" type="file" accept="image/*" class="form-control @error('image') is-invalid @enderror">
                        @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-lg-7">
                    @include('panel.partials.location-picker', ['lat' => null, 'lng' => null])
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('panel.alertas.index') }}" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-danger"><i class="ti ti-send me-1"></i> Publicar alerta</button>
        </div>
    </form>
@endsection
