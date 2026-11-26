@php use App\Http\Requests\MissingCaseRequest; @endphp
@include('panel.partials.location-picker', ['lat' => $lat ?? null, 'lng' => $lng ?? null])
<div>
    <label for="radius_m" class="form-label required">Radio del aviso</label>
    <select id="radius_m" name="radius_m" class="form-select @error('radius_m') is-invalid @enderror" data-picker-radius required>
        @foreach (MissingCaseRequest::RADII as $meters => $label)
            <option value="{{ $meters }}" @selected((int) old('radius_m', 2000) === $meters)>{{ $label }}</option>
        @endforeach
    </select>
    @error('radius_m')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-hint">Marca el lugar de desaparición. Se avisará a los ciudadanos de la zona que compartan su ubicación.</div>
</div>
