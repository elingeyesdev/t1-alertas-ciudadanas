import { type Report } from "../domain/types";
import { getEcho } from "@/lib/realtime";

/**
 * Nuevos reportes en tiempo real.
 * Antes: socket.io, namespace "reports", evento "newReport".
 * Ahora: Laravel Reverb, canal público "reports", evento ".newReport".
 */
class ReportsSocketService {
  private static instance: ReportsSocketService | null = null;
  private isConnected = false;
  private listeners: Set<(report: Report) => void> = new Set();

  private constructor() {}

  public static getInstance(): ReportsSocketService {
    if (!this.instance) {
      this.instance = new ReportsSocketService();
    }
    return this.instance;
  }

  public connect() {
    if (this.isConnected) return;
    this.isConnected = true;

    getEcho()
      .then((echo) => {
        echo.channel("reports").listen(".newReport", (data: Report) => {
          try {
            if (data) {
              this.listeners.forEach((listener) => listener(data));
            }
          } catch (e) {
            console.error("Error procesando nuevo reporte en tiempo real:", e);
          }
        });
        console.log("Escuchando nuevos reportes (Laravel Reverb)");
      })
      .catch((e) => {
        this.isConnected = false;
        console.error("No se pudo conectar al tiempo real de reportes:", e);
      });
  }

  public subscribe(callback: (report: Report) => void) {
    this.listeners.add(callback);
    return () => {
      this.listeners.delete(callback);
    };
  }

  public disconnect() {
    if (this.isConnected) {
      getEcho()
        .then((echo) => echo.leave("reports"))
        .catch(() => {});
    }
    this.isConnected = false;
    this.listeners.clear();
  }
}

export const reportsSocketService = ReportsSocketService.getInstance();
