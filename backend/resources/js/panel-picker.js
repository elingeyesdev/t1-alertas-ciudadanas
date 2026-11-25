// Mapas pequeños de formularios: selector de ubicación y mapa estático del detalle.
import { createMap, pinIcon, DEFAULT_CENTER, L } from './panel/map-base.js'

// Selector: clic o arrastre llena los campos latitud/longitud
document.querySelectorAll('[data-location-picker]').forEach((element) => {
    const form = element.closest('form')
    const latInput = form.querySelector('[data-picker-lat]')
    const lngInput = form.querySelector('[data-picker-lng]')
    const initial = latInput.value && lngInput.value ? [Number(latInput.value), Number(lngInput.value)] : null
    const map = createMap(element, { center: initial || DEFAULT_CENTER, zoom: initial ? 16 : 13 })
    let marker = null
    // HU-32: si el formulario tiene un radio (avisos preventivos) se dibuja la zona
    const radiusInput = form.querySelector('[data-picker-radius]')
    let circle = null
    const drawZone = () => {
        if (!radiusInput || !marker) return
        const radius = Number(radiusInput.value) || 0
        if (!circle) {
            circle = L.circle(marker.getLatLng(), { radius, color: '#ae3ec9', fillColor: '#ae3ec9', fillOpacity: 0.15, weight: 2 }).addTo(map)
        } else {
            circle.setLatLng(marker.getLatLng()).setRadius(radius)
        }
        map.fitBounds(circle.getBounds(), { padding: [20, 20], maxZoom: 16 })
    }
    radiusInput?.addEventListener('change', drawZone)

    const place = (latlng, pan = false) => {
        if (!marker) {
            marker = L.marker(latlng, { draggable: true, icon: pinIcon('red', 'ti-map-pin') }).addTo(map)
            marker.on('dragend', () => { write(marker.getLatLng()); drawZone() })
        } else {
            marker.setLatLng(latlng)
        }
        if (radiusInput) drawZone()
        else if (pan) map.setView(latlng, Math.max(map.getZoom(), 15))
    }
    const write = ({ lat, lng }) => {
        latInput.value = lat.toFixed(6)
        lngInput.value = lng.toFixed(6)
        latInput.classList.remove('is-invalid'); lngInput.classList.remove('is-invalid')
    }

    if (initial) place(initial)
    map.on('click', (event) => { place(event.latlng); write(event.latlng) })
    ;[latInput, lngInput].forEach((input) => input.addEventListener('change', () => {
        const lat = Number(latInput.value), lng = Number(lngInput.value)
        if (Number.isFinite(lat) && Number.isFinite(lng) && latInput.value && lngInput.value) place([lat, lng], true)
    }))
})

// Mapa estático de solo lectura (detalle de alerta)
document.querySelectorAll('[data-static-map]').forEach((element) => {
    const position = [Number(element.dataset.lat), Number(element.dataset.lng)]
    const map = createMap(element, { center: position, zoom: 16, scrollWheelZoom: false })
    L.marker(position, { icon: pinIcon('red') }).addTo(map).bindTooltip(element.dataset.label || 'Alerta')
})
