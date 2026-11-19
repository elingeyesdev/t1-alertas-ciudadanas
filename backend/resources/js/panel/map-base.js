// Utilidades de mapa compartidas (Leaflet + teselas de Mapbox).
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const config = window.PanelConfig || {}

export const DEFAULT_CENTER = config.center || [-17.7833, -63.1821]

export function createMap(element, options = {}) {
    if (!config.mapboxToken) {
        throw new Error('Falta MAPBOX_ACCESS_TOKEN para mostrar el mapa del panel.')
    }

    const map = L.map(element, { zoomControl: true, ...options }).setView(options.center || DEFAULT_CENTER, options.zoom || 13)

    mapboxLayer('streets-v12').addTo(map)

    return map
}

export function mapboxLayer(style) {
    return L.tileLayer(`https://api.mapbox.com/styles/v1/mapbox/${style}/tiles/512/{z}/{x}/{y}@2x?access_token={token}`, {
        token: config.mapboxToken,
        tileSize: 512,
        zoomOffset: -1,
        maxZoom: 19,
        attribution: '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    })
}

/** Marcador circular con ícono de Tabler. */
export function pinIcon(color, icon = 'ti-alert-triangle', pulse = false) {
    return L.divIcon({
        className: '',
        html: `<span class="map-pin bg-${color}${pulse ? ' map-pin-pulse' : ''}"><i class="ti ${icon}"></i></span>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -14],
    })
}

export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c])
}

export { L }
