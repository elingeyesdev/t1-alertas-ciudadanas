// Mapa en vivo del panel: alertas activas, estaciones, unidades en camino y zonas de riesgo.
// Se actualiza solo con Reverb (canales "reports" y "trackings").
import { createMap, mapboxLayer, pinIcon, escapeHtml, L } from './panel/map-base.js'
import { getEcho, bindStatusIndicator } from './panel/realtime.js'

const config = window.PanelConfig
const mapEl = document.getElementById('live-map')

if (mapEl) {
    const map = createMap(mapEl)
    let baseLayer
    map.eachLayer((layer) => { baseLayer = layer })
    document.querySelector('#live-map-style').addEventListener('change', (event) => {
        baseLayer.remove()
        baseLayer = mapboxLayer(event.target.value).addTo(map)
    })
    L.control.scale({ imperial: false, position: 'bottomright' }).addTo(map)
    const layers = {
        reports: L.layerGroup().addTo(map),
        stations: L.layerGroup().addTo(map),
        trackings: L.layerGroup().addTo(map),
        risk: L.layerGroup(),
    }
    const state = { reports: new Map(), trackings: new Map(), stations: [] }
    const reportMarkers = new Map()
    const trackingMarkers = new Map()
    const stationStyle = { policia: ['blue', 'ti-shield'], bombero: ['red', 'ti-flame'], hospital: ['green', 'ti-first-aid-kit'] }
    const listEl = document.querySelector('[data-report-list]')
    const trackingListEl = document.querySelector('[data-tracking-list]')
    const countEl = document.querySelector('[data-count]')
    const reportFilter = document.querySelector('#live-report-filter')
    const sortWeight = document.querySelector('[data-sort-weight]')
    const fitReports = document.querySelector('[data-fit-reports]')

    const timeAgo = (iso) => {
        const minutes = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000))
        if (minutes < 1) return 'ahora'
        if (minutes < 60) return `hace ${minutes} min`
        return `hace ${Math.round(minutes / 60)} h`
    }
    // Las coordenadas vienen como [longitud, latitud]
    const latLng = (coordinates) => [coordinates[1], coordinates[0]]
    const reportWeight = (report) => Number(report.weight ?? 0)
    const weightLabel = (report) => `Peso: ${reportWeight(report)} pts`
    const visibleReports = () => [...state.reports.values()].filter((report) => {
        switch (reportFilter.value) {
            case 'sos': return report.is_sos
            case 'verified': return report.verified
            case 'pending': return !report.verified
            default: return true
        }
    })
    const showReportsLayer = () => {
        if (map.hasLayer(layers.reports)) return
        document.querySelector('[data-layer="reports"]').checked = true
        layers.reports.addTo(map)
    }
    const fitToReports = () => {
        const bounds = L.latLngBounds(visibleReports().map((r) => latLng(r.coordinates)))
        if (!bounds.isValid()) return
        showReportsLayer()
        map.fitBounds(bounds, { padding: [36, 36], maxZoom: 16, animate: false })
    }

    function reportPopup(r) {
        return `<strong>#${r.id} · ${escapeHtml(r.type?.name ?? 'Alerta')}</strong>
            <div class="small text-secondary">${escapeHtml(r.zone)} · ${timeAgo(r.created_at)}</div>
            <p class="my-2">${escapeHtml(r.description)}</p>
            ${r.images?.[0] ? `<img src="${escapeHtml(r.images[0].url)}" alt="" class="popup-img mb-2">` : ''}
            ${r.is_sos ? '<span class="badge bg-red text-red-fg me-1">SOS</span>' : ''}
            <span class="badge ${r.verified ? 'bg-teal-lt' : 'bg-secondary-lt'}">${r.verified ? 'Verificada' : 'Sin verificar'}</span>
            <span class="badge bg-blue-lt ms-1">${weightLabel(r)}</span>
            <a class="btn btn-sm btn-primary w-100 mt-2" href="${config.reportUrl}/${r.id}">Ver detalle</a>`
    }

    function upsertReport(r, isNew = false) {
        if (r.status && r.status !== 'activo') return removeReport(r.id)
        state.reports.set(r.id, r)
        // HU-24: el SOS se ve distinto y siempre late
        const icon = r.is_sos
            ? pinIcon('red', 'ti-sos', true)
            : pinIcon(r.verified ? 'teal' : 'red', 'ti-alert-triangle', isNew)
        let marker = reportMarkers.get(r.id)
        if (marker) {
            marker.setLatLng(latLng(r.coordinates)).setIcon(icon).setPopupContent(reportPopup(r))
            marker.setTooltipContent(weightLabel(r))
        } else {
            marker = L.marker(latLng(r.coordinates), { icon, title: r.type?.name }).bindPopup(reportPopup(r), { maxWidth: 260 })
            marker.bindTooltip(weightLabel(r), { direction: 'top', offset: [0, -12] })
            marker.addTo(layers.reports)
            reportMarkers.set(r.id, marker)
        }
    }

    function removeReport(id) {
        state.reports.delete(id)
        const marker = reportMarkers.get(id)
        if (marker) layers.reports.removeLayer(marker)
        reportMarkers.delete(id)
    }

    function renderList() {
        const reports = visibleReports().sort((a, b) =>
            (sortWeight.checked ? reportWeight(b) - reportWeight(a) : Number(b.is_sos) - Number(a.is_sos)) ||
            new Date(b.created_at) - new Date(a.created_at))
        const visibleIds = new Set(reports.map((r) => r.id))
        reportMarkers.forEach((marker, id) => {
            if (visibleIds.has(id)) layers.reports.addLayer(marker)
            else layers.reports.removeLayer(marker)
        })
        fitReports.disabled = reports.length === 0
        countEl.textContent = reports.length
        listEl.innerHTML = reports.length
            ? reports.map((r) => `
                <button type="button" class="list-group-item list-group-item-action" data-focus="${r.id}">
                    <div class="d-flex justify-content-between gap-2">
                        <strong class="text-truncate${r.is_sos ? ' text-red' : ''}">${r.is_sos ? '🚨 ' : ''}${escapeHtml(r.type?.name ?? 'Alerta')}</strong>
                        <span class="text-secondary small text-nowrap">${timeAgo(r.created_at)}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <span class="badge bg-blue-lt text-nowrap">${weightLabel(r)}</span>
                        <span class="text-secondary small text-truncate">${escapeHtml(r.zone)} · ${escapeHtml(r.description)}</span>
                    </div>
                </button>`).join('')
            : '<div class="list-group-item text-secondary">No hay alertas para este filtro.</div>'
        renderRisk()
    }

    reportFilter.addEventListener('change', renderList)
    sortWeight.addEventListener('change', renderList)
    fitReports.addEventListener('click', fitToReports)

    listEl.addEventListener('click', (event) => {
        const item = event.target.closest('[data-focus]')
        if (!item) return
        const marker = reportMarkers.get(Number(item.dataset.focus))
        if (marker) {
            showReportsLayer()
            map.setView(marker.getLatLng(), 16)
            marker.openPopup()
        }
    })

    function renderStations() {
        layers.stations.clearLayers()
        state.stations.forEach((s) => {
            const [color, icon] = stationStyle[s.installation_type] || ['secondary', 'ti-building']
            L.marker(latLng(s.coordinates), { icon: pinIcon(color, icon), title: s.name })
                .bindPopup(`<strong>${escapeHtml(s.name)}</strong>`)
                .addTo(layers.stations)
        })
    }

    function upsertTracking(t) {
        if (t.latitude == null || t.longitude == null) return
        state.trackings.set(t.id, t)
        const position = [Number(t.latitude), Number(t.longitude)]
        let marker = trackingMarkers.get(t.id)
        if (marker) {
            marker.setLatLng(position)
        } else {
            marker = L.marker(position, { icon: pinIcon('yellow', 'ti-ambulance', true), title: 'Unidad en camino' }).addTo(layers.trackings)
            trackingMarkers.set(t.id, marker)
        }
        marker.bindPopup(`<strong>Unidad en camino</strong><div class="small text-secondary">${escapeHtml(t.name || t.userName || t.id)}</div>`)
        renderTrackings()
    }

    function removeTracking(id) {
        state.trackings.delete(id)
        trackingMarkers.get(id)?.remove()
        trackingMarkers.delete(id)
        renderTrackings()
    }

    function renderTrackings() {
        const items = [...state.trackings.values()]
        trackingListEl.innerHTML = items.length
            ? items.map((t) => `<div class="list-group-item"><i class="ti ti-ambulance text-yellow me-1"></i>${escapeHtml(t.name || t.userName || 'Unidad')} <span class="text-secondary small">· ${timeAgo(t.timestamp)}</span></div>`).join('')
            : '<div class="list-group-item text-secondary">Ninguna unidad compartiendo ubicación.</div>'
    }

    // Zonas de riesgo: agrupa alertas activas en celdas de ~500 m y pinta un círculo por zona
    function renderRisk() {
        layers.risk.clearLayers()
        const cells = new Map()
        state.reports.forEach((r) => {
            const [lat, lng] = latLng(r.coordinates)
            const key = `${Math.round(lat / 0.0045)}:${Math.round(lng / 0.0045)}`
            const cell = cells.get(key) || { lat: 0, lng: 0, n: 0, weight: 0 }
            cell.lat += lat; cell.lng += lng; cell.n += 1; cell.weight += r.weight || 1
            cells.set(key, cell)
        })
        const maxWeight = Math.max(1, ...[...cells.values()].map((c) => c.weight))
        cells.forEach((c) => {
            const level = c.weight / maxWeight
            const color = level > 0.66 ? '#d63939' : level > 0.33 ? '#f76707' : '#f59f00'
            L.circle([c.lat / c.n, c.lng / c.n], { radius: 250 + c.n * 60, color, fillColor: color, fillOpacity: 0.25, weight: 1 })
                .bindTooltip(`${c.n} alerta(s) · peso ${c.weight}`)
                .addTo(layers.risk)
        })
    }

    async function load() {
        const response = await fetch(config.mapDataUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        if (!response.ok) throw new Error('No se pudo cargar el mapa')
        const data = await response.json()
        layers.reports.clearLayers(); reportMarkers.clear(); state.reports.clear()
        layers.trackings.clearLayers(); trackingMarkers.clear(); state.trackings.clear()
        data.reports.forEach((r) => upsertReport(r))
        state.stations = data.stations
        renderStations()
        data.trackings.forEach(upsertTracking)
        renderTrackings()
        renderList()
    }

    load().catch(() => { listEl.innerHTML = '<div class="list-group-item text-danger">No se pudo cargar el mapa. Recarga la página.</div>' })
    document.querySelector('[data-map-reload]')?.addEventListener('click', () => load())

    document.querySelectorAll('[data-layer]').forEach((toggle) => {
        toggle.addEventListener('change', () => {
            const layer = layers[toggle.dataset.layer]
            toggle.checked ? layer.addTo(map) : layer.remove()
        })
    })

    // Tiempo real
    try {
        const echo = getEcho()
        bindStatusIndicator(echo)
        echo.channel('reports').listen('.newReport', (report) => {
            upsertReport(report, true)
            renderList()
        })
        echo.channel('trackings')
            .listen('.tracking_started', (tracking) => upsertTracking({ ...tracking, id: tracking.id || tracking.userId }))
            .listen('.tracking_stopped', ({ userId }) => removeTracking(userId))
        // Al reconectar vuelve a pedir el estado actual
        echo.connector.pusher.connection.bind('connected', () => load().catch(() => {}))
    } catch (error) {
        console.warn('Tiempo real no disponible:', error)
    }

    // Refresco suave cada minuto para quitar alertas vencidas
    setInterval(() => load().catch(() => {}), 60000)
}
