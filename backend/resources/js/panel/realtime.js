// Conexión en tiempo real con Laravel Reverb (los mismos canales que la web React y la app).
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

let echo = null

export function getEcho() {
    if (echo) return echo
    const reverb = (window.PanelConfig || {}).reverb || {}
    window.Pusher = Pusher
    const secure = reverb.scheme === 'https'
    echo = new Echo({
        broadcaster: 'reverb',
        key: reverb.key,
        wsHost: window.location.hostname,
        wsPort: reverb.port,
        wssPort: reverb.port,
        forceTLS: secure,
        enabledTransports: secure ? ['wss'] : ['ws'],
        disableStats: true,
    })
    return echo
}

/** Muestra en la barra superior si el tiempo real está conectado. */
export function bindStatusIndicator(echoInstance) {
    const badge = document.querySelector('[data-realtime-status]')
    const text = document.querySelector('[data-realtime-text]')
    const pusher = echoInstance.connector?.pusher
    if (!badge || !pusher) return
    badge.hidden = false
    const update = (state) => {
        const online = state === 'connected'
        badge.classList.toggle('is-online', online)
        text.textContent = online ? 'Tiempo real activo' : 'Sin tiempo real (¿Reverb apagado?)'
    }
    update(pusher.connection.state)
    pusher.connection.bind('state_change', ({ current }) => update(current))
}
