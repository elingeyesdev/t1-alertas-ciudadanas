# Levantar la web del backend con Podman

Esta guía inicia el backend Laravel, su panel web, PostgreSQL/PostGIS, Redis y Vite en el entorno local de Podman.

## Requisitos

- Podman Desktop abierto y la máquina de Podman en ejecución.
- `podman compose` disponible. En Windows, Podman delega Compose a un proveedor externo.
- Ejecutar los comandos desde la raíz del repositorio.

Comprueba Podman y Compose:

```powershell
podman version
podman compose version
```

## Primera ejecución

Construye las imágenes e inicia los servicios:

```powershell
podman compose --env-file .env.docker.local -f compose.yaml -f compose.local.yaml up --build -d
```

Espera a que la base de datos esté saludable y ejecuta las migraciones y datos iniciales:

```powershell
podman compose --env-file .env.docker.local -f compose.yaml -f compose.local.yaml exec -T app php artisan migrate --force
podman compose --env-file .env.docker.local -f compose.yaml -f compose.local.yaml exec -T app php artisan db:seed --force
```

Los seeders crean los tipos y roles, la cuenta administradora inicial, estaciones y algunos reportes de demostración. Las credenciales se configuran con `ADMIN_PHONE` y `ADMIN_PASSWORD` en el entorno Laravel; no se documentan aquí.

## Abrir la web

- **Panel administrativo:** <http://localhost:8080/panel/login>
- **Vista de alertas:** <http://localhost:8080/alertas>
- **Vite (recursos de desarrollo):** <http://localhost:5173>

La ruta raíz `http://localhost:8080/` es un índice JSON de la API. Vite sirve recursos del frontend y no es la página del panel.

## Verificar servicios

```powershell
podman compose --env-file .env.docker.local -f compose.yaml -f compose.local.yaml ps
```

Para revisar registros del backend y Vite:

```powershell
podman compose --env-file .env.docker.local -f compose.yaml -f compose.local.yaml logs --tail 100 app vite
```

## Siguientes ejecuciones

Si los contenedores ya fueron creados, basta con iniciarlos:

```powershell
podman compose --env-file .env.docker.local -f compose.yaml -f compose.local.yaml up -d
```

Para detenerlos sin eliminar la base de datos:

```powershell
podman compose --env-file .env.docker.local -f compose.yaml -f compose.local.yaml down
```
