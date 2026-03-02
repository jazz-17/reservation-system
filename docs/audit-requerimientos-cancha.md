# Auditoría / Code Review — Requerimientos “Sistema de Registro de Solicitudes de la Cancha”

Fecha: 2026-03-01  
Repositorio: `reservation-system` (Laravel 12 + Inertia v2 + Vue 3)  
Commit (HEAD): `ce3834b`  
Alcance de la revisión: código fuente, rutas, esquema de BD, y pruebas automatizadas (Pest).

## Resumen ejecutivo

En general, el sistema **cubre la mayoría** de los requisitos funcionales (RF) y varios no funcionales (RNF) a nivel de **seguridad de rutas, reglas de negocio, flujos de aprobación y trazabilidad**.

Principales hallazgos:

- **RF1 (Usuarios):** registro con validación por allow-list y dominio institucional implementado; escuela/base se asignan automáticamente desde la allow-list. El **teléfono es opcional** (el RF lo pide como dato del registro).
- **RF2 (Disponibilidad):** calendario público y vistas privadas implementadas. La “actualización dinámica” es **por recarga/refetch**, no por tiempo real (sin sockets/push).
- **RF3 (Registro de reservas):** validación robusta (horario de atención, duración, anticipación, blackouts, conflictos con reservas aprobadas, límite por usuario y cuota semanal por escuela/base). La UI **no selecciona el rango directamente en el calendario** (se ingresa por inputs de hora).
- **RF4 (Admin):** bandeja de solicitudes, aprobar/rechazar, historial, PDF por plantilla y cola de “artifacts” implementados. El envío de correos al admin depende de destinatarios configurados (`notify_admin_emails`).
- **RF5 (Notificaciones):** implementadas vía jobs + mail; no existe “mensaje interno” (solo correo).
- **RNF (No funcionales):** buena base de mantenibilidad, concurrencia y rendimiento. Falta cumplir explícitamente el punto de “**cifrado de correos**” (los emails se almacenan en `citext` sin cifrado de aplicación).

Leyenda de estado:

- **Cumple**: implementado y verificable en código/pruebas.
- **Parcial**: implementado con diferencias relevantes vs. el texto del requisito o depende de configuración.
- **No cumple**: no hay implementación.
- **No evaluable**: depende de operación/infra (SLA/uptime, etc.).

## Evidencias (mapa rápido del sistema)

- Rutas principales: `routes/web.php` (público/estudiante/admin) y prefijo `api` para endpoints JSON usados por FullCalendar y panel admin.
- Validación y reglas de negocio: `app/Actions/Reservations/ReservationRulesService.php`.
- Creación/aprobación/rechazo/cancelación + colas (PDF/email): `app/Actions/Reservations/ReservationService.php`, `app/Jobs/GenerateReservationPdf.php`, `app/Jobs/SendReservationEmail.php`.
- Calendario y disponibilidad: `app/Actions/Reservations/AvailabilityService.php`, `app/Http/Controllers/Api/PublicAvailabilityController.php`.
- Registro con allow-list: `app/Actions/Fortify/CreateNewUser.php`, `allow_list_entries` (tabla).
- Pruebas relevantes (Pest): `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/ReservationWorkflowTest.php`, `tests/Feature/AdminAllowListAndBlackoutsTest.php`, `tests/Feature/AdminUserManagementTest.php`, `tests/Feature/Mail/Smtp2GoTransportTest.php`, `tests/Feature/ReservationPdfDownloadTest.php`.

## Requisitos funcionales (RF)

### 1) Gestión de Usuarios

| ID | Requisito | Estado | Evidencia | Observaciones |
|---|---|---|---|---|
| RF1.1 | Registro de estudiantes con nombres/apellidos, escuela, base, teléfono, correo institucional | **Parcial** | `app/Actions/Fortify/CreateNewUser.php`, `resources/js/pages/auth/Register.vue`, `users` (columnas `first_name`, `last_name`, `professional_school_id`, `base_year`, `phone`, `email`) | Escuela/base **se asignan automáticamente** desde allow-list (no se piden en el formulario). Teléfono está marcado como **opcional** en UI y backend. |
| RF1.2 | Validar que el correo exista en base del administrador | **Cumple** | `app/Actions/Fortify/CreateNewUser.php` (consulta a `AllowListEntry`), pruebas en `tests/Feature/ReservationWorkflowTest.php` (registro bloqueado si no está en allow-list) | La allow-list se administra por UI admin o importación por CSV (padrón). |
| RF1.3 | Inicio de sesión de usuarios registrados (ruta privada) | **Cumple** | Middleware `auth` + `verified` en `routes/web.php`; Fortify routes (`/login`, `/register`) | Las rutas principales de estudiantes requieren email verificado. |
| RF1.4 | Admin inicia sesión para funciones de gestión | **Cumple** | Grupo `Route::prefix('admin')` con `permission:admin.panel.access` en `routes/web.php` | Autenticación misma base (Fortify) + autorización por roles/permisos (Spatie). |

### 2) Visualización de Disponibilidad

| ID | Requisito | Estado | Evidencia | Observaciones |
|---|---|---|---|---|
| RF2.1 | Calendario interactivo público con horarios reservados | **Cumple** | `GET /calendario` (`PublicCalendarController`), `resources/js/pages/calendar/Public.vue`, `GET /api/public/availability` | El feed de eventos muestra **aprobadas** como “Ocupado” y “Blackouts” como bloqueos de fondo. |
| RF2.2 | Autenticados ven reservas activas y pasadas (vista privada) | **Cumple** | `GET /reservas` + `GET /api/student/reservations`, `resources/js/pages/reservations/Index.vue` | La vista separa “activas” vs “historial” en frontend. |
| RF2.3 | Calendario se actualiza dinámicamente al aprobar/denegar | **Parcial** | `GET /api/public/availability` consulta en tiempo real al servidor; admin invalida queries internas | No existe push/sockets. El calendario **refleja cambios al refetch** (recarga, cambio de rango, o re-entrada). “Denegar” no cambia disponibilidad porque pendientes no se publican. |

### 3) Registro de Reservas

| ID | Requisito | Estado | Evidencia | Observaciones |
|---|---|---|---|---|
| RF3.1 | Estudiante solicita reserva eligiendo fecha/hora disponible en el calendario, limitado a 1 por cuenta | **Parcial** | Límite: setting `max_active_reservations_per_user` + `ReservationRulesService::validateUserActiveLimit()`; UI: `resources/js/pages/reservations/Create.vue` | Límite **cumple** por regla de negocio. La UI no selecciona el rango “dibujando” en el calendario (se ingresan horas por inputs). |
| RF3.2 | Verificar disponibilidad antes de registrar | **Cumple** | `ReservationRulesService::validateConflicts()` (contra aprobadas) + blackouts + horario de atención | El sistema permite múltiples pendientes sobre el mismo slot (decisión de producto). Evita el conflicto real al aprobar (y por constraint en BD). |
| RF3.3 | Limitar a 2 reservas por semana por escuela+base | **Cumple** | setting `weekly_quota_per_school_base`, `ReservationRulesService::validateWeeklyQuota()`, prueba “weekly quota” en `tests/Feature/ReservationWorkflowTest.php` | Se cuenta `pending + approved` (scope `blocking()`), lo cual reduce spam por grupo. |
| RF3.4 | Registrar solicitudes en estado “Pendiente” hasta revisión | **Cumple** | `ReservationService::createPending()` crea `ReservationStatus::Pending` | UI lo comunica explícitamente en `reservations/Create.vue`. |
| RF3.5 | Cancelar reserva pendiente o aprobada antes de su fecha de uso | **Parcial** | `ReservationService::cancel()` + `ReservationRulesService::validateCancellation()` + ruta `POST /reservas/{reservation}/cancelar` | Hay una política de corte `cancel_cutoff_hours` (default 2h). Si el requisito era “en cualquier momento antes de la fecha”, hoy es **más restrictivo**. |

### 4) Gestión de Solicitudes por el Administrador

| ID | Requisito | Estado | Evidencia | Observaciones |
|---|---|---|---|---|
| RF4.1 | Bandeja de solicitudes con info del solicitante y horario | **Cumple** | `GET /admin/solicitudes` + `GET /api/admin/requests`, `resources/js/pages/admin/Requests.vue` | Incluye nombre, email, escuela/base y teléfono (si existe). |
| RF4.2 | Aceptar o denegar solicitudes pendientes | **Cumple** | `ReservationRequestController@approve/@reject`, `ReservationService::approve()/reject()` | Autorización por permiso `admin.reservas.solicitudes.decide`. |
| RF4.3 | Si se acepta: generar documento (PDF) desde plantilla | **Cumple** | `GenerateReservationPdf` usa `barryvdh/laravel-dompdf` con vista `pdfs.reservation.default` | El PDF se guarda en `storage` (disk local) y se trackea con `reservation_artifacts`. |
| RF4.4 | Enviar documento por correo al admin y opcional al estudiante | **Parcial** | `SendReservationEmail` adjunta PDF cuando `event === 'approved'`; `ReservationService::enqueueEmails()` crea artifacts `EmailAdmin` (si hay destinatarios en `notify_admin_emails`) y `EmailStudent` | No existe “opcional” por usuario: el correo al estudiante se encola siempre que tenga email. La entrega depende de `MAIL_MAILER`/SMTP configurado. |
| RF4.5 | Admin consulta historial (aceptadas, rechazadas, canceladas) | **Cumple** | `GET /admin/historial` + `GET /api/admin/history` | Filtra por estado y rango de fechas (limit 500). |

### 5) Notificaciones y Comunicaciones

| ID | Requisito | Estado | Evidencia | Observaciones |
|---|---|---|---|---|
| RF5.1 | Correos de confirmación o rechazo de reservas a los usuarios | **Parcial** | `ReservationService::enqueueEmails()` con `event: approved/rejected/expired/cancelled` + `ReservationStatusMail` | Existe envío por correo (en cola) para eventos de estado. La entrega depende de `MAIL_MAILER`/SMTP configurado. |
| RF5.2 | En caso de cancelación, notificar al estudiante por correo o mensaje interno | **Parcial** | `ReservationService::cancel()` → `enqueueEmails(..., event: 'cancelled')` | No hay “mensaje interno”. |

## Requisitos no funcionales (RNF)

| ID | Requisito | Estado | Evidencia | Observaciones |
|---|---|---|---|---|
| RNF1 | Usabilidad (UI intuitiva, calendario responsive) | **Parcial** | FullCalendar + Tailwind, vistas dedicadas (`calendar/Public.vue`, `reservations/Create.vue`) | Se percibe usable en escritorio/móvil, pero esto requiere validación UX (tests de usabilidad). |
| RNF2 | Seguridad: rutas privadas protegidas; datos personales seguros (hash/cifrado) | **Parcial** | `auth` + `verified` + permisos (Spatie) en rutas; `User` castea `password` como `hashed` | **No** hay cifrado de correo en BD (almacenado en `citext`). Contraseñas sí están hasheadas. |
| RNF3 | Rendimiento: consultas rápidas + actualizaciones en tiempo real (sockets opcional) | **Parcial** | Endpoint de disponibilidad hace consultas acotadas por rango; colas para PDF/email | No hay sockets ni polling por defecto; “tiempo real” sería por refetch manual. |
| RNF4 | Escalabilidad: concurrencia sin degradación | **Cumple (base técnica)** | Advisory locks en PG para creación/aprobación; constraint de no solape para aprobadas | Ayuda a evitar condiciones de carrera (cuotas/slots) bajo carga concurrente. |
| RNF5 | Mantenibilidad: arquitectura modular | **Cumple** | Actions + Form Requests + Jobs + Policies + tests Pest | Stack difiere de “Angular+Node”, pero cumple el objetivo de modularidad. |
| RNF6 | Disponibilidad 95% | **No evaluable** | `GET /up` existe (ruta framework) | Requiere métricas/monitoring e infraestructura (SLA). |

## Brechas y recomendaciones (prioridad)

1) **Cifrado de correos (RNF2):** hoy no se cifra `users.email`. Si el requisito es obligatorio, definir estrategia (p.ej. `email_encrypted` + `email_hash` para búsqueda/unique, o cifrado a nivel de DB/volumen).

2) **Notificaciones por correo (RF5 / RF4.4):** documentar y validar configuración de correo (`MAIL_MAILER`, `MAIL_FROM_*`). Para correo al admin, definir destinatarios en `notify_admin_emails`. Si se requiere que el correo al estudiante sea “opcional”, hoy hace falta una regla/setting adicional.

3) **Teléfono requerido (RF1.1):** el backend/UI lo trata como opcional. Decidir si debe ser obligatorio y ajustar validación y UX.

4) **“Selección en calendario” (RF3.1):** si se exige que el usuario seleccione el rango directamente en el calendario (drag/select), habilitar `selectable: true` en FullCalendar y sincronizar con el form.

5) **Actualización “dinámica” del calendario (RF2.3 / RNF3):** agregar polling (p.ej. refetch cada N segundos) o broadcasting/websockets (opcional) para que usuarios vean cambios sin recargar.

## Conclusión

El flujo principal descrito (“ver calendario público → registrar/iniciar sesión → solicitar → admin aprueba/rechaza → genera PDF y notifica”) está **implementado**. Las diferencias clave con el documento de requerimientos están en:

- notificaciones (dependen de configuración),
- notificaciones (dependen de configuración de correo y destinatarios),
- cifrado de correos (no implementado),
- y algunos detalles de UX (“selección” en calendario y “dinámico” en tiempo real).
