---
name: levantar-panel-tabler
description: Identifica y levanta el panel web administrativo correcto del proyecto t1-alertas-ciudadanas, implementado con Laravel Blade y Tabler.
---

# Levantar el panel web de t1-alertas-ciudadanas

El proyecto web que se debe levantar para este repositorio es el **panel administrativo Blade + Tabler que vive dentro de `backend/` (Laravel)**. Su URL local es `http://localhost:8080/panel/login`.

No confundas ese panel con `web/`: `web/` es una aplicación React independiente y no es el panel Laravel/Tabler solicitado.

## Inicio local en Windows con Podman

Desde la raíz del repositorio, usa el entorno local ya configurado en `.env.docker.local` y los archivos Compose del proyecto:

```powershell
podman compose --project-name t1-alertas-ciudadanas --env-file .env.docker.local -f compose.yaml -f compose.local.yaml up -d
```

Comprueba los servicios y los logs si el panel no responde:

```powershell
podman compose --project-name t1-alertas-ciudadanas --env-file .env.docker.local -f compose.yaml -f compose.local.yaml ps
podman compose --project-name t1-alertas-ciudadanas --env-file .env.docker.local -f compose.yaml -f compose.local.yaml logs --tail 100 app vite db redis
```

- Panel: `http://localhost:8080/panel/login`
- Vista de alertas: `http://localhost:8080/alertas`
- Vite para recursos de desarrollo: `http://localhost:5173`

Si es una instalación nueva y PostgreSQL no tiene el esquema, ejecuta las migraciones con `exec -T app php artisan migrate --force`; aplica seeders solo cuando se necesiten los datos iniciales de demostración. No uses `migrate:fresh` ni elimines volúmenes persistentes para levantar el panel.

La configuración de `.env.docker.local` contiene secretos locales; no los muestres ni los publiques.
