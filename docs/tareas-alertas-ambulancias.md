# Tareas para alertas de ambulancias en tiempo real

## Objetivo

Avisar a un ciudadano cuando su ubicación reciente y consentida esté cerca del tramo que una ambulancia todavía debe recorrer según Mapbox Directions, y cuando la unidad pueda pasar pronto por ese punto.

En el MVP, «va por mi camino» significa que el ciudadano está cerca del tramo futuro de la ambulancia. Comparar dos rutas completas requiere que el ciudadano tenga una ruta de navegación activa y queda como una ampliación posterior.

## MVP de detección

- [x] **1. Revisar el flujo actual de despachos y ubicación.** Identificar cómo se asigna una ambulancia, de dónde sale su destino y qué estados permiten evaluar su recorrido. Revisar `DispatchService`, `TrackingService`, la actualización de ubicación de usuarios y `notifications/notify-route`. **Aceptación:** quedan identificados los datos y endpoints reutilizables y los campos que faltan para registrar precisión, antigüedad y consentimiento.
- [x] **2. Preparar PostGIS y Mapbox Directions.** Habilitar PostGIS en la base local de Podman preservando sus datos. Configurar el token de Directions en Laravel por variable de entorno, separado del token público del panel, y dejar `mapbox/driving` como perfil inicial configurable. **Aceptación:** Laravel puede consultar PostGIS y obtener una ruta de prueba sin exponer el token en el cliente ni en los logs. Revisar las condiciones de Mapbox aplicables al almacenamiento de rutas antes de fijar su retención.
- [x] **3. Agregar migraciones para guardar rutas.** Crear `dispatch_routes` con geometría LineString SRID 4326, distancia, duración, versión y estado. Usar el tipo real de la clave de despacho y garantizar una sola ruta activa por despacho. **Aceptación:** activar una nueva versión y reemplazar la anterior ocurre en una transacción, incluso ante recálculos concurrentes.
- [x] **4. Implementar `EmergencyRoutingService`.** Consultar Directions con origen GPS y destino del despacho. Solicitar geometría GeoJSON completa en orden longitud, latitud. Ejecutar el cálculo al iniciar el despacho y al confirmar un desvío persistente, con límites de frecuencia y reintentos acotados. **Aceptación:** se guarda una ruta válida y una respuesta fallida o sin ruta deja el despacho sin detecciones nuevas hasta disponer de un recorrido utilizable.
- [x] **5. Registrar el GPS de la ambulancia.** Reutilizar o extender el seguimiento autenticado para aceptar coordenadas, hora y precisión de la unidad asignada. Ignorar posiciones inválidas, antiguas o fuera de orden. Preparar Redis y un worker para conservar el estado reciente y agrupar evaluaciones por despacho activo. **Aceptación:** una unidad ajena al despacho no puede actualizarlo y las evaluaciones se detienen cuando se cancela o completa.
- [x] **6. Detectar ciudadanos en el tramo futuro.** Proyectar la posición de la ambulancia sobre la ruta activa, identificar el tramo restante y consultar ubicaciones consentidas y recientes en lote mediante PostGIS. Calcular en metros la distancia lateral y la distancia por la ruta hasta el punto proyectado del ciudadano. Estimar el tiempo de llegada con la velocidad observada o una estimación documentada. **Aceptación:** se incluyen ciudadanos delante y dentro del corredor, y se descartan quienes están detrás, fuera del corredor o fuera de la ventana de anticipación. Ante un desvío o una proyección ambigua, suspender la evaluación hasta recuperar una posición confiable sobre la ruta.
- [x] **7. Validar la detección con un recorrido simulado.** La prueba automatizada reproduce una respuesta de Mapbox y posiciones GPS sobre PostgreSQL con PostGIS. Comprueba el corredor de 100 m y la ventana de 10 a 90 s. La repetición con Directions real forma parte del piloto.

## Entrega de avisos

- [x] **8. Registrar avisos y evitar duplicados.** El aviso y el evento de salida se crean en una transacción. Hay un aviso por ciudadano y despacho, incluso si cambia la ruta. Un aviso pendiente de una versión anterior se suprime antes de enviar. Los reintentos de un fallo previo al envío conservan el mismo identificador. Un resultado incierto queda marcado para revisión y no se reenvía.
- [ ] **9. Integrar FCM y Flutter.** Reutilizar `FcmService` y el registro de tokens del usuario autenticado. Enviar un aviso de aproximación por FCM HTTP v1 y mostrarlo en Flutter con el canal de notificaciones correspondiente. Comprobar el consentimiento antes de enviar y usar un identificador opaco para el aviso. **Aceptación:** un dispositivo de prueba recibe el aviso en los estados permitidos por el sistema operativo y puede desactivar el uso de su ubicación.
- [ ] **10. Validar el flujo integral con un piloto controlado.** Probar cambios de ruta, vías paralelas, cruces, pérdida de GPS, revocación de permisos, cancelación y reintentos. Medir por separado el tiempo desde el GPS hasta la aceptación de FCM y hasta la recepción en el teléfono. **Aceptación:** se documentan los falsos positivos, retrasos y limitaciones observados y se ajustan los umbrales antes de habilitar los avisos.

El primer entregable termina en la tarea 7. Debe mostrar qué ciudadanos resultan candidatos en un recorrido reproducible. Las tareas 8 a 10 convierten esa detección en avisos para el móvil.

## Flujo actual y preparación de las primeras tres tareas

| Dato | Fuente actual | Trabajo posterior |
| --- | --- | --- |
| Despacho y responsable | Flutter llama a `POST /api/dispatches` con el reporte, la estación elegida y el usuario. Laravel guarda un ID entero para el despacho y el UUID del responsable en `attended_by`. | Vincular las posiciones con ese despacho y verificar que pertenezcan a la unidad asignada. |
| Destino | `report_id` identifica el incidente y sus coordenadas. `destination_id` identifica una estación y sus coordenadas. | Al calcular la ruta, usar el incidente para el tramo hacia la emergencia y la estación para el traslado hacia ella. |
| Estado | `dispatches.state` admite `en_curso`, `completado` y `cancelado`. Flutter actualiza el estado mediante el endpoint de despachos. | Evaluar coincidencias solo durante `en_curso`. |
| Posición de la unidad | Flutter publica posición y datos de ruta en `POST /api/trackings/start`. `TrackingService` mantiene el estado reciente en caché bajo el identificador recibido. | Validar hora de captura, precisión e identidad de la unidad en el flujo asociado al despacho. |
| Posición del ciudadano | `PATCH /api/users/{id}/location` guarda `last_latitude` y `last_longitude` en `users`. El aviso por ruta existente tiene su propio endpoint. | Registrar hora, precisión y consentimiento explícito antes de reutilizar la posición para avisos de ambulancias. |

### Variables y migraciones

- Configura `MAPBOX_DIRECTIONS_TOKEN` en `.env.docker.local` con un token autorizado para solicitudes de Directions desde Laravel. El token del mapa del panel sigue en `MAPBOX_ACCESS_TOKEN`. El perfil inicial es `MAPBOX_DIRECTIONS_PROFILE=mapbox/driving`.
- Antes de actualizar un entorno existente, respalda PostgreSQL y confirma que el volumen contiene PostgreSQL 17. Construye el servicio `db` con `podman compose @compose build db`. La imagen añade PostGIS sobre PostgreSQL 17 en Debian Bookworm y reutiliza el volumen y su punto de montaje. En bases externas, el servidor necesita PostGIS y la cuenta que migra debe tener permiso para habilitarlo, o la extensión debe estar activa previamente.
- Ejecuta las migraciones después de iniciar el servidor con PostGIS. Revertir la migración de rutas elimina su tabla y conserva la extensión.
- `DispatchRouteStore::replace()` recibe un ID de despacho y una ruta de Directions con `geometry`, `distance` y `duration`. Devuelve la versión guardada. El worker usa esta operación tras calcular o recalcular la ruta.

Cuando tengas el token de Directions, recarga las variables del contenedor y comprueba la conexión desde la raíz del proyecto.

```powershell
$compose = @('--project-name', 'app-local', '--env-file', '.env.docker.local', '-f', 'compose.yaml', '-f', 'compose.local.yaml')
podman compose @compose up -d --no-build app
podman compose @compose exec -T app php artisan mapbox:check-directions
```

### GPS y detección disponibles en esta etapa

- El primer GPS válido del paramédico asignado activa el cálculo hacia el incidente. Una vez activa la ruta, tres muestras distintas fuera del corredor de 65 m provocan un recálculo, con un mínimo de 45 s entre solicitudes a Directions por despacho. La evaluación se suspende mientras la posición está fuera de ruta o su proyección es ambigua.
- Flutter envía el GPS del paramédico a `POST /api/dispatches/{dispatch}/ambulance-position`. El servidor comprueba identidad, asignación, estado, precisión y hora de captura. Redis conserva la última muestra y el servicio `ambulance-worker` procesa la cola.
- La API para el piloto acepta `PUT /api/ambulance-alerts/consent` con `{"enabled":true}` o `{"enabled":false}`. Una vez habilitado, `POST /api/ambulance-alerts/position` recibe `latitude`, `longitude`, `accuracy_m` y `captured_at` en ISO 8601. Revocar el consentimiento elimina la posición. Esta API requiere una sesión de ciudadano.
- PostGIS mide la distancia al tramo que falta por recorrer y la distancia por la ruta hasta el ciudadano. El corredor inicial es de 100 m y la ventana es de 10 a 90 s. El ETA usa la velocidad media estimada por Directions. El worker conserva temporalmente el total de candidatos y, si el piloto está habilitado, registra los avisos en PostgreSQL.
- La comprobación con Directions real requiere `MAPBOX_DIRECTIONS_TOKEN`. El worker local usa Redis. En un entorno sin Redis, las conexiones por defecto de este módulo usan base de datos y requieren un worker para la cola `ambulance`.

## Piloto de avisos en dispositivos

Con un despacho en curso y GPS reciente, un ciudadano que haya dado su consentimiento también recibe un aviso inmediato si está a 25 m o menos de la ambulancia. Ese caso incluye dos posiciones GPS iguales y conserva un aviso por ciudadano y despacho.

Los envíos se habilitan en un entorno de prueba con `AMBULANCE_NOTIFICATIONS_ENABLED=true`. Configura `MAPBOX_DIRECTIONS_TOKEN` y la cuenta de servicio de Firebase en `backend/storage/app/firebase-service-account.json` o en la ruta que hayas configurado con `FIREBASE_CREDENTIALS`. Flutter y Laravel deben apuntar al mismo proyecto de Firebase. En Android, instala una compilación con Firebase e inicia sesión para registrar el canal `ambulance_alerts_sound_v3`, que reproduce el archivo de sirena incluido en el APK con el volumen de alarma configurado en Android. Para probar iOS, configura el registro de la app, APNs y la firma de la compilación. En iOS el aviso usa el sonido predeterminado. [Referencia de mensajes de FCM](https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages)

```powershell
$compose = @('--project-name', 'app-local', '--env-file', '.env.docker.local', '-f', 'compose.yaml', '-f', 'compose.local.yaml')
podman compose @compose exec -T app php artisan migrate --force
podman compose @compose up -d app ambulance-worker ambulance-scheduler
podman compose @compose exec -T app php artisan mapbox:check-directions
```

1. Usa dos cuentas y dos dispositivos de prueba. Asigna un despacho a un paramédico y activa **Avisos de ambulancias** en Configuración de la cuenta ciudadana. Ese dispositivo publica muestras mientras la app está en primer plano.
2. Reproduce el recorrido hacia el incidente. Comprueba que un ciudadano delante reciba un solo aviso y que uno detrás, fuera del corredor o con GPS vencido quede excluido. Incluye cruces y vías paralelas. Si dos vías están dentro del mismo corredor, anota un posible falso positivo, porque la distancia por sí sola no identifica la vía del ciudadano.
3. Cambia la ruta, cancela el despacho y revoca el permiso de ubicación o el consentimiento. Comprueba que los avisos pendientes obsoletos queden suprimidos. Prueba primer plano, segundo plano y aplicación cerrada con una muestra todavía vigente. Registra por separado si el sistema operativo mostró el aviso y si el usuario lo abrió.
4. Registra los casos esperados, avisos observados, falsos positivos y retrasos. Repite con los umbrales ajustados antes de ampliar el piloto.

Para medir latencia, cruza el estado de la bandeja de salida con los tiempos del aviso. `foreground_received_at` es la hora en que el servidor registró el acuse desde el móvil y, por tanto, incluye el tiempo de retorno del acuse. `opened_at` mide la apertura registrada, que es distinta de la recepción. La aceptación de FCM tampoco confirma entrega al dispositivo. [Tiempo de vida y entrega de FCM](https://firebase.google.com/docs/cloud-messaging/customize-messages/setting-message-lifespan)

```sql
SELECT o.status, count(*) AS avisos,
       avg(extract(epoch FROM a.fcm_accepted_at - a.gps_captured_at)) AS gps_a_fcm_s,
       avg(extract(epoch FROM a.foreground_received_at - a.gps_captured_at)) AS gps_a_acuse_s
FROM ambulance_alerts a
JOIN ambulance_alert_outbox o ON o.alert_id = a.id
GROUP BY o.status;
```

Las tareas 9 y 10 quedan abiertas hasta verificar la entrega con Firebase y teléfonos reales. Las pruebas automatizadas usan una ruta reproducible y respuestas simuladas de Mapbox y FCM.
