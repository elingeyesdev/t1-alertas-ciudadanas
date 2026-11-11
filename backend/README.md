# backend/ — API Alertas Ciudadanas (Laravel 13 + PostgreSQL)

> Parte del repo `t1-alertas-ciudadanas`. La guía completa (backend + web + móvil) está en el `README.md` de la raíz.

Migración del backend `backend_nest_alertas` (NestJS + TypeORM) a **Laravel 13**.
Mantiene las **mismas rutas, el mismo JSON de respuesta y el mismo formato de errores**
(`{ statusCode, message, error }`), así que la web y la app móvil siguen funcionando
con solo cambiar la URL base. La excepción es el tiempo real (ver "WebSockets").

---

## 1. Requisitos

| Herramienta | Versión | Nota |
|---|---|---|
| PHP | 8.3 o superior | extensiones: `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `fileinfo`, `gd` (o `imagick`), `curl` |
| Composer | 2.x | https://getcomposer.org |
| PostgreSQL | 14 o superior | **no necesita PostGIS** |
| Redis | opcional | solo si quieres guardar los trackings ahí |

> En Windows: con **Laragon** o **XAMPP** ya tienes PHP; activa `extension=pdo_pgsql`
> y `extension=pgsql` en `php.ini`.

## 2. Instalación rápida (usando este proyecto)

```bash
cd backend
composer install
cp .env.example .env          # en Windows: copy .env.example .env
php artisan key:generate
```

Edita `.env` con tus datos de PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=alertas
DB_USERNAME=postgres
DB_PASSWORD=123
JWT_SECRET=una_clave_larga_de_al_menos_32_caracteres
```

Crea la base de datos y las tablas:

```bash
psql -U postgres -c "CREATE DATABASE alertas;"
php artisan migrate --seed       # crea tablas + datos iniciales
php artisan storage:link         # para servir imágenes locales
php artisan serve                # API en http://localhost:8000/api
```

**Panel de alertas (Blade):** http://localhost:8000/alertas — tabla de reportes con fecha, tipo y estado, filtro por tipo.

**Usuario administrador inicial:** teléfono `70000000`, contraseña `admin123`
(cámbialos en `.env` con `ADMIN_PHONE` y `ADMIN_PASSWORD` antes de sembrar).

Otra opción para la base de datos: ejecutar `database/sql/alertas_postgresql.sql`
(crea tablas y datos iniciales; equivale a `migrate --seed`).

### Procesos opcionales (en otras terminales)

```bash
php artisan reverb:start      # WebSockets en ws://localhost:6001 (reportes y tracking en vivo)
php artisan schedule:work     # tarea cada 30 min que vence reportes expirados
```

### Pruebas automáticas

```bash
psql -U postgres -c "CREATE DATABASE alertas_test;"
php artisan test
```

---

## 3. Equivalencias NestJS → Laravel

| NestJS | Laravel | Carpeta |
|---|---|---|
| `models/*.entity.ts` (TypeORM) | Modelos Eloquent | `app/Models` |
| `synchronize: true` | Migraciones | `database/migrations` |
| `bootstrap/seed.ts` | Seeders | `database/seeders` |
| `http/requests/*` (DTO + class-validator) | Form Requests | `app/Http/Requests` |
| `XxxResponse.FromXxxToResponse` | API Resources | `app/Http/Resources` |
| `services/*.service.ts` | Servicios | `app/Services` |
| `http/controllers/*` | Controladores | `app/Http/Controllers` |
| `@Controller('reports')` + `@Get()` | `routes/api.php` | `routes` |
| `JwtStrategy` + `JwtGuard` | Guard `jwt` (`Auth::viaRequest`) | `app/Providers/AppServiceProvider.php` |
| `@Public()` | ruta fuera de `auth:api` | `routes/api.php` |
| `@Roles('admin')` + `RoleGuard` | middleware `role:admin` | `app/Http/Middleware/EnsureRole.php` |
| `enums/*.enum.ts` | Enums PHP | `app/Enums` |
| `@Cron('*/30 * * * *')` | `Schedule::command(...)->everyThirtyMinutes()` | `routes/console.php` |
| `@nestjs-modules/mailer` | `Mail` + Mailables | `app/Mail`, `resources/views/emails` |
| `firebase-admin` | `FcmService` (API HTTP v1) | `app/Services/FcmService.php` |
| `cloudinary` SDK | `ImageService` (API REST) | `app/Services/ImageService.php` |
| Socket.IO Gateways | Laravel Reverb + Eventos | `app/Events` |
| `config/*.config.ts` | `config/alertas.php` + `.env` | `config` |

---

## 4. Cómo se creó paso a paso (para hacerlo desde cero)

1. **Crear el proyecto**
   ```bash
   composer create-project laravel/laravel backend
   cd backend
   composer require firebase/php-jwt laravel/reverb
   ```
2. **Configurar PostgreSQL** en `.env` (sección 2) y crear la BD `alertas`.
3. **Enums**: crear `app/Enums/ProfileType.php`, `InstallationType.php`, `StateReport.php`, `StateType.php`.
4. **Migraciones** (una por tabla, en orden de dependencias):
   ```bash
   php artisan make:migration create_roles_table
   php artisan make:migration create_users_table          # uuid como PK
   php artisan make:migration create_authority_profiles_table
   php artisan make:migration create_report_types_table
   php artisan make:migration create_reports_table
   php artisan make:migration create_images_table
   php artisan make:migration create_comments_table
   php artisan make:migration create_emergency_stations_table
   php artisan make:migration create_dispatches_table
   ```
5. **Modelos** con sus relaciones (`belongsTo`, `hasMany`, `hasOne`), `SoftDeletes`
   donde Nest tenía `@DeleteDateColumn` y `HasUuids` en `User`:
   `php artisan make:model Report` (etc.).
6. **Autenticación JWT**: `app/Services/JwtService.php` firma los tokens; en
   `AppServiceProvider` se registra el guard `jwt`; en `config/auth.php` el guard `api`
   usa ese driver. Las rutas protegidas usan `->middleware('auth:api')`.
7. **Roles**: middleware `EnsureRole` registrado como alias `role` en `bootstrap/app.php`.
8. **Validaciones**: `php artisan make:request CreateReportRequest` (etc.).
9. **Respuestas**: `php artisan make:resource ReportResource` (etc.).
   `JsonResource::withoutWrapping()` evita el `{ "data": ... }`.
10. **Servicios y controladores**: lógica en `app/Services`, controladores delgados.
11. **Rutas** en `routes/api.php` (Laravel les pone el prefijo `/api`).
12. **Errores con formato NestJS** en `bootstrap/app.php` (`withExceptions`).
13. **Seeders**: roles, tipos, admin, estaciones y reportes demo → `php artisan migrate --seed`.
14. **Tarea programada**: comando `reports:expire` + `routes/console.php`.
15. **Tiempo real**: eventos `ShouldBroadcastNow` + `php artisan reverb:start`.
16. **Probar**: `php artisan test` y la colección de Postman en `docs/`.

---

## 5. Endpoints (prefijo `/api`)

🔓 = público · 🔒 = requiere `Authorization: Bearer <access_token>`

| Método | Ruta | Acceso |
|---|---|---|
| POST | `/auth/register` | 🔓 |
| POST | `/auth/login` | 🔓 |
| POST | `/auth/refresh` | 🔓 |
| GET | `/auth/me` | 🔒 |
| POST | `/auth/logout` | 🔒 |
| GET | `/` | 🔒 admin |
| POST | `/users/authority` | 🔒 admin |
| GET | `/users` · `/users/{id}` | 🔒 |
| PATCH | `/users/{id}` · `/{id}/fcm-token` · `/{id}/location` · `/{id}/password` | 🔒 |
| DELETE | `/users/{id}` | 🔒 admin |
| PATCH | `/authority-profile/{userId}` | 🔒 |
| GET / POST / DELETE | `/roles` · `/roles/{id}` | 🔒 (POST/DELETE admin) |
| GET / POST / DELETE | `/report-types` · `/report-types/{id}` | 🔒 (POST/DELETE admin) |
| POST | `/reports` (multipart: `image`, `type`, `description`, `latitude`, `longitude`, `userId`, `zone?`) | 🔒 |
| GET | `/reports` · `/reports/with-deleted` · `/reports/{id}` · `/reports/user/{userId}` | 🔒 |
| GET | `/reports/nearby?latitude=&longitude=&radius=` | 🔒 |
| GET | `/reports/similars?userId=&type=&latitude=&longitude=` | 🔒 |
| POST / GET | `/reports/{id}/comments` | 🔒 |
| POST | `/reports/{id}/images/{userId}` (multipart: `image`) | 🔒 |
| PATCH | `/reports/{id}/verify` | 🔒 admin, autoridad |
| PATCH | `/reports/{id}/resolve` · `/reports/{id}/reactivate` | 🔒 |
| DELETE | `/reports/{id}` | 🔒 |
| POST / GET | `/comments/{id}/replies` | 🔒 |
| DELETE | `/images/{id}` | 🔒 admin |
| GET / POST / PATCH / DELETE | `/emergency-stations` · `/emergency-stations/{id}` | 🔒 |
| POST | `/dispatches` · PATCH `/dispatches/{id}/state` | 🔒 |
| POST | `/mail/{userId}` · `/mail/{userId}/resend-credentials` | 🔒 |
| POST | `/notifications/notify-route` | 🔒 autoridad, admin |
| GET | `/trackings` · POST `/trackings/start` · POST `/trackings/stop` | 🔒 (nuevo) |

---

## 6. WebSockets: lo único que cambia para el frontend

Socket.IO no existe en Laravel. Se usa **Laravel Reverb** (protocolo Pusher):

| Antes (Socket.IO) | Ahora (Reverb) |
|---|---|
| namespace `reports`, evento `newReport` | canal `reports`, evento `.newReport` |
| emit `startTracking` / `stopTracking` | `POST /api/trackings/start` / `stop` |
| emit `getTrackings` | `GET /api/trackings` |
| evento `tracking_started` / `tracking_stopped` | canal `trackings`, eventos `.tracking_started` / `.tracking_stopped` |

Cliente web (`npm i laravel-echo pusher-js`):

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: 'alertas-key',          // REVERB_APP_KEY
  wsHost: 'localhost',
  wsPort: 6001,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],
});

echo.channel('reports').listen('.newReport', (report) => console.log(report));
echo.channel('trackings')
  .listen('.tracking_started', (t) => console.log(t))
  .listen('.tracking_stopped', ({ userId }) => console.log(userId));
```

En Flutter se puede usar un cliente Pusher (por ejemplo `dart_pusher_channels`)
apuntando a `ws://<IP>:6001/app/alertas-key`.

Si Reverb no está levantado la API **sigue funcionando**; solo se registra un
aviso en `storage/logs/laravel.log`.

---

## 7. Cambios y mejoras respecto al backend NestJS

- **Sin PostGIS**: las ubicaciones son columnas `latitude` / `longitude` y las
  distancias se calculan con Haversine en SQL. Funciona en cualquier PostgreSQL.
  En las respuestas, `coordinates` sigue siendo `[longitud, latitud]`.
- **Nombres de tablas y columnas** con la convención de Laravel (`users`, `reports`,
  `creator_id`, `report_type_id`…). El JSON de la API no cambia.
- **Refresh token más seguro**: se guarda su hash SHA-256. Con bcrypt, que corta en
  72 bytes, cualquier refresh token del mismo usuario pasaba la verificación.
  Además, un refresh token ya no sirve como access token.
- **`JWT_SECRET` de mínimo 32 caracteres** (lo exige la librería para HS256).
- **Responder a una respuesta** devuelve 400 de verdad (en Nest la validación no
  funcionaba porque no cargaba `parent_comment`).
- **`PATCH /reports/{id}/verify`** devuelve el reporte con formato `ReportResponse`
  (antes devolvía la entidad completa, incluido el hash de la contraseña del creador).
- **Imágenes**: si Cloudinary dice `pendiente`, se guardan en `storage/app/public/reports`.
- **Cooldown de notificaciones por ruta** guardado en caché, así sobrevive a reinicios.
- **Borrar un rol o tipo en uso** devuelve 409 en lugar de un error 500.
- ⚠️ Igual que en Nest, `POST /auth/register` acepta `roleId` del cliente: cualquiera
  podría registrarse como admin. Se recomienda forzar `roleId = 1` en el registro.

## 8. Firebase y correo

- Copia tu `firebase-service-account.json` a `storage/app/` (no lo subas a Git).
- Gmail: usa una **contraseña de aplicación** en `MAIL_PASSWORD`.
