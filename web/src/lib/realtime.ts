/**
 * Conexión en tiempo real con el backend Laravel (Laravel Reverb, protocolo Pusher).
 * Reemplaza a socket.io-client. Se carga de forma diferida para que el SSR
 * de TanStack Start nunca intente abrir un WebSocket en el servidor.
 */
import type Echo from "laravel-echo";

type EchoInstance = Echo<"reverb">;

let echoPromise: Promise<EchoInstance> | null = null;

function reverbConfig() {
  const env = import.meta.env;
  const scheme = (env.VITE_REVERB_SCHEME || "http").trim();
  const host =
    env.VITE_REVERB_HOST?.trim() ||
    (typeof window !== "undefined" ? window.location.hostname : "localhost");
  const port = Number(env.VITE_REVERB_PORT || (scheme === "https" ? 443 : 6001));
  return {
    key: (env.VITE_REVERB_APP_KEY || "alertas-key").trim(),
    host,
    port,
    forceTLS: scheme === "https",
  };
}

export function getEcho(): Promise<EchoInstance> {
  if (typeof window === "undefined") {
    return Promise.reject(new Error("Echo solo funciona en el navegador"));
  }
  if (!echoPromise) {
    echoPromise = Promise.all([import("laravel-echo"), import("pusher-js")]).then(
      ([{ default: EchoClass }, { default: Pusher }]) => {
        (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;
        const cfg = reverbConfig();
        return new EchoClass({
          broadcaster: "reverb",
          key: cfg.key,
          wsHost: cfg.host,
          wsPort: cfg.port,
          wssPort: cfg.port,
          forceTLS: cfg.forceTLS,
          enabledTransports: ["ws", "wss"],
        });
      },
    );
  }
  return echoPromise;
}

/** Escucha cambios de conexión (conectado / desconectado). Devuelve una función para dejar de escuchar. */
export function onConnectionChange(echo: EchoInstance, cb: (connected: boolean) => void): () => void {
  const pusher = (echo.connector as unknown as { pusher: any }).pusher;
  const handler = (states: { current: string }) => cb(states.current === "connected");
  pusher.connection.bind("state_change", handler);
  cb(pusher.connection.state === "connected");
  return () => pusher.connection.unbind("state_change", handler);
}

/** Cierra la conexión (al cerrar sesión). */
export async function disconnectEcho() {
  if (!echoPromise) return;
  const echo = await echoPromise.catch(() => null);
  echo?.disconnect();
  echoPromise = null;
}
