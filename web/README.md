# web/ — Panel de administración (React)

Panel web de Avispáme: mapa en vivo, alertas, usuarios y estaciones de emergencia.
Consume la API Laravel de `backend/` y recibe eventos en tiempo real por Laravel Reverb.

```powershell
npm install
copy .env.example .env
npm run dev          # http://localhost:8080
npm run build        # compilación de producción
```

- `/api` se reenvía a `http://localhost:8000` (ver `vite.config.ts`).
- Tiempo real: `src/lib/realtime.ts` (Laravel Echo + pusher-js). Canales `reports` y `trackings`.
- Variables: ver `.env.example`.

Guía completa en el `README.md` de la raíz del repo.
