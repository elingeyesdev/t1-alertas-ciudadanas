import { type LiveTracking } from "../domain/tracking";
import { httpClient } from "../api/httpClient";
import { getEcho, onConnectionChange } from "@/lib/realtime";

/**
 * Unidades de emergencia en vivo.
 * Antes: socket.io (getTrackings / trackings / tracking_started / tracking_stopped).
 * Ahora: GET /api/trackings + canal Reverb "trackings" con eventos
 * ".tracking_started" y ".tracking_stopped".
 */

class TrackingSocketService {
  private static instance: TrackingSocketService | null = null;
  private isConnected = false;
  private unbindConnection: (() => void) | null = null;
  private pollTimer: ReturnType<typeof setInterval> | null = null;
  private activeTrackings: LiveTracking[] = [];
  private listeners: Set<(trackings: LiveTracking[]) => void> = new Set();
  private connectionListeners: Set<(connected: boolean) => void> = new Set();

  private constructor() {}

  public static getInstance(): TrackingSocketService {
    if (!this.instance) {
      this.instance = new TrackingSocketService();
    }
    return this.instance;
  }

  public connect() {
    if (this.isConnected) return;
    this.isConnected = true;

    getEcho()
      .then((echo) => {
        this.unbindConnection = onConnectionChange(echo, (connected) => {
          this.notifyConnectionListeners(connected);
          // Al (re)conectar se pide la lista actual por HTTP (antes: emit "getTrackings")
          if (connected) void this.loadTrackings();
        });

        echo
          .channel("trackings")
          .listen(".tracking_started", (data: any) => {
            try {
              if (data && (data.id || data.userId)) {
                const parsed = this.parseBackendTracking(data);
                const index = this.activeTrackings.findIndex((t) => t.id === parsed.id);
                if (index !== -1) {
                  this.activeTrackings[index] = parsed;
                } else {
                  this.activeTrackings.push(parsed);
                }
                this.notifyListeners();
              }
            } catch (e) {
              console.error("Error procesando tracking_started:", e);
            }
          })
          .listen(".tracking_stopped", (data: { userId: string }) => {
            if (data && data.userId) {
              this.activeTrackings = this.activeTrackings.filter((t) => t.id !== data.userId);
              this.notifyListeners();
            }
          });

        void this.loadTrackings();
      })
      .catch((e) => {
        this.isConnected = false;
        console.error("No se pudo conectar al tiempo real de tracking:", e);
      });

    // Respaldo: refresca la lista cada 30 s (descarta unidades que dejaron de enviar)
    this.pollTimer = setInterval(() => void this.loadTrackings(), 30000);
  }

  /** GET /api/trackings: lista de unidades activas. */
  private async loadTrackings() {
    try {
      const data = await httpClient.get<any[]>("/trackings");
      if (Array.isArray(data)) {
        this.activeTrackings = data.map((t) => this.parseBackendTracking(t));
        this.notifyListeners();
      }
    } catch (e) {
      console.error("Error obteniendo trackings:", e);
    }
  }

  private parseBackendTracking(data: any): LiveTracking {
    return {
      id: data.id || data.userId || "",
      latitude: Number(data.latitude) || 0,
      longitude: Number(data.longitude) || 0,
      incidentLatitude: data.incidentLatitude !== undefined ? Number(data.incidentLatitude) : undefined,
      incidentLongitude: data.incidentLongitude !== undefined ? Number(data.incidentLongitude) : undefined,
      reportId: data.reportId !== undefined ? Number(data.reportId) : undefined,
      type: data.type || "",
      description: data.description || "",
      route: Array.isArray(data.route)
        ? data.route.map((p: any) => ({
            lat: Number(p.lat ?? p.latitude ?? 0),
            lng: Number(p.lng ?? p.longitude ?? 0),
          }))
        : [],
      status: data.status || "",
      profileType: data.profileType || "",
    };
  }

  public subscribe(callback: (trackings: LiveTracking[]) => void) {
    this.listeners.add(callback);
    callback([...this.activeTrackings]);
    return () => {
      this.listeners.delete(callback);
    };
  }

  public subscribeConnection(callback: (connected: boolean) => void) {
    this.connectionListeners.add(callback);
    callback(this.isConnected);
    return () => {
      this.connectionListeners.delete(callback);
    };
  }

  private notifyListeners() {
    this.listeners.forEach((listener) => listener([...this.activeTrackings]));
  }

  private notifyConnectionListeners(connected: boolean) {
    this.connectionListeners.forEach((listener) => listener(connected));
  }

  public disconnect() {
    if (this.isConnected) {
      getEcho()
        .then((echo) => echo.leave("trackings"))
        .catch(() => {});
    }
    this.unbindConnection?.();
    this.unbindConnection = null;
    if (this.pollTimer) clearInterval(this.pollTimer);
    this.pollTimer = null;
    this.isConnected = false;
    this.activeTrackings = [];
    this.listeners.clear();
    this.connectionListeners.clear();
  }
}

export const trackingSocketService = TrackingSocketService.getInstance();
