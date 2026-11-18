{{-- Selector de ubicación: clic en el mapa llena latitude/longitude. Variables: $lat, $lng --}}
<div class="mb-3">
    <label class="form-label required">Ubicación</label>
    <div class="form-hint mb-2">Haz clic en el mapa para marcar el punto exacto. Puedes arrastrar el marcador.</div>
    <div class="picker-map rounded border @error('latitude') border-danger @enderror" data-location-picker role="application" aria-label="Mapa para elegir la ubicación"></div>
    <div class="row g-2 mt-1">
        <div class="col-6">
            <label class="form-label small text-secondary mb-1" for="latitude">Latitud</label>
            <input id="latitude" name="latitude" type="text" inputmode="decimal" class="form-control form-control-sm @error('latitude') is-invalid @enderror" value="{{ old('latitude', $lat) }}" required data-picker-lat>
        </div>
        <div class="col-6">
            <label class="form-label small text-secondary mb-1" for="longitude">Longitud</label>
            <input id="longitude" name="longitude" type="text" inputmode="decimal" class="form-control form-control-sm @error('longitude') is-invalid @enderror" value="{{ old('longitude', $lng) }}" required data-picker-lng>
        </div>
    </div>
</div>
