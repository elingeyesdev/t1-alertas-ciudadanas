@extends('layouts.panel')
@php use App\Models\MissingCase; @endphp

@section('title', 'Publicar caso')
@section('pretitle')
    <a href="{{ route('panel.desaparecidos.index') }}" class="text-reset"><i class="ti ti-arrow-left"></i> Desaparecidos</a>
@endsection
@push('scripts')
    @vite('resources/js/panel-picker.js')
@endpush

@section('content')
    <form class="card" method="POST" action="{{ route('panel.desaparecidos.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="card-body row g-4">
            <div class="col-lg-6">
                <div class="mb-3">
                    <label for="kind" class="form-label required">Tipo</label>
                    <select class="form-select @error('kind') is-invalid @enderror" id="kind" name="kind" required>
                        @foreach (MissingCase::KINDS as $value => $label)
                            <option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('kind')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @foreach (['name' => ['Nombre', 'text', 120], 'place' => ['Lugar de desaparición', 'text', 180], 'missing_on' => ['Fecha de desaparición', 'date', null], 'reference_phone' => ['Teléfono de referencia', 'tel', 22]] as $name => [$label, $type, $max])
                    <div class="mb-3">
                        <label for="{{ $name }}" class="form-label required">{{ $label }}</label>
                        <input class="form-control @error($name) is-invalid @enderror" type="{{ $type }}" id="{{ $name }}" name="{{ $name }}" value="{{ old($name) }}" @if ($max) maxlength="{{ $max }}" @endif @if ($type === 'date') max="{{ now('America/La_Paz')->toDateString() }}" @endif required>
                        @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
                <div class="mb-3">
                    <label for="description" class="form-label required">Descripción</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" maxlength="2000" rows="4" required>{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="photos" class="form-label required">Fotos</label>
                    <input class="form-control" id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required>
                    <div class="form-hint">De una a tres imágenes JPEG, PNG o WebP de hasta 5 MB por foto.</div>
                    @error('photos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @foreach ($errors->get('photos.*') as $messages)
                        <div class="invalid-feedback d-block">{{ $messages[0] }}</div>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-6">@include('panel.desaparecidos.zone')</div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('panel.desaparecidos.index') }}" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary">Publicar y avisar a la zona</button>
        </div>
    </form>
@endsection
