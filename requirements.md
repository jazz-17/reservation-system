
---

# Requisitos — Sistema de Reservas de Cancha (FISI)

## Control de cambios

| Versión | Fecha       | Cambios |
|--------:|------------|---------|
| 0.1     | 2026-02-20 | Reestructuración completa de requisitos: reglas configurables, estados, criterios de aceptación y escenarios E2E. |

## 1) Propósito
Automatizar y transparentar el proceso de solicitud y asignación de horarios para la reserva de la cancha de la Facultad, reduciendo conflictos de horarios, carga operativa y mejorando la experiencia de estudiantes y administradores.

## 2) Alcance

### 2.1 En alcance (MVP)
- Registro e inicio de sesión para estudiantes.
- Inicio de sesión para administrador.
- Validación de correo institucional mediante **lista de permitidos (allow-list)** administrada por el administrador (importación CSV/XLSX).
- Calendario público de disponibilidad (sin datos personales) y vistas privadas para estudiantes (mis reservas).
- Creación de solicitudes de reserva con estado **Pendiente** (aprobación obligatoria).
- Panel de administración: bandeja de solicitudes, aprobar/rechazar, historial y auditoría.
- Generación de **PDF** al aprobar y envío de notificaciones por **correo electrónico**.
- Reglas de negocio **configurables en el panel admin** (horarios, cuotas, políticas, etc.).

### 2.2 Fuera de alcance (vNext)
- Pagos, firma digital, integraciones con sistemas académicos externos.
- Mensajería SMS/WhatsApp.
- Reservas recurrentes complejas (p. ej. semanal durante un semestre).

## 3) Actores y roles
- **Estudiante:** se registra, inicia sesión, consulta disponibilidad, solicita/cancela reservas y consulta su historial.
- **Administrador:** configura reglas del sistema, gestiona allow-list, revisa solicitudes, aprueba/rechaza, consulta historial, genera PDF y envía notificaciones.
- **Visitante (no autenticado):** consulta el calendario público de disponibilidad (solo “ocupado/libre”).

## 4) Glosario
- **Reserva:** solicitud/registro que ocupa un intervalo de tiempo en la cancha.
- **Slot / Bloque horario:** unidad de tiempo seleccionable para reservar (configurable).
- **Estado:** situación de una reserva (Pendiente, Aprobada, Rechazada, Cancelada).
- **Conflicto:** solapamiento de intervalos con reservas que bloquean disponibilidad.
- **Escuela + Base:** agrupación (p. ej. “E.P. Sistemas” + “B22”) usada para aplicar cuotas.
- **Allow-list:** listado de correos institucionales habilitados para registrarse.

## 5) Stack y notas técnicas (referencial)
- Backend: **Laravel 12**.
- Autenticación: **Laravel Fortify**.
- Frontend: **Inertia.js v2 + Vue 3 + Tailwind CSS**.

## 6) Estados de reserva y transiciones (definición normativa)

### 6.1 Estados
- **Pendiente:** creada por estudiante; espera decisión del administrador; **bloquea disponibilidad**.
- **Aprobada:** confirmada por administrador; bloquea disponibilidad; genera PDF y notifica.
- **Rechazada:** decisión negativa del administrador; no bloquea disponibilidad.
- **Cancelada:** anulada por estudiante (o por admin si se habilita); no bloquea disponibilidad.

### 6.2 Transiciones permitidas
- Pendiente → Aprobada
- Pendiente → Rechazada
- Pendiente → Cancelada
- Aprobada → Cancelada (según política de cancelación y permisos)

### 6.3 Regla de bloqueo
Para validaciones de disponibilidad y para el calendario público, una reserva en estado **Pendiente** o **Aprobada** se considera “ocupada” (bloquea).

## 7) Configuración administrable (obligatoria en MVP)
Todas las reglas de negocio deben ser configurables desde un panel de administración, con valores por defecto.

| Setting (clave) | Descripción | Valor por defecto | Observaciones |
|---|---|---:|---|
| `timezone` | Zona horaria del sistema | `America/Lima` | Se usa para reglas semanales, horarios y cortes. |
| `opening_hours` | Horarios de atención por día | L–D: 08:00–22:00 | Debe soportar excepciones/feriados (blackout). |
| `booking_mode` | Modo de selección de horario | `fixed_duration` | Modos: `fixed_duration`, `variable_duration`, `predefined_blocks`. |
| `slot_duration_minutes` | Duración del slot (si aplica) | 60 | Para `fixed_duration`. |
| `slot_step_minutes` | Granularidad de selección | 30 | Para iniciar slots en múltiplos del step. |
| `min_duration_minutes` | Duración mínima (si aplica) | 60 | Para `variable_duration`. |
| `max_duration_minutes` | Duración máxima (si aplica) | 120 | Para `variable_duration`. |
| `lead_time_min_hours` | Anticipación mínima para reservar | 2 | Evita reservas de último minuto (configurable). |
| `lead_time_max_days` | Anticipación máxima | 30 | Límite de ventana futura. |
| `max_active_reservations_per_user` | Máximo de reservas activas por estudiante | 1 | Activas = Pendiente + Aprobada (configurable). |
| `weekly_quota_per_school_base` | Máximo semanal por Escuela+Base | 2 | Semana definida por configuración (p. ej. lunes-domingo). |
| `pending_expiration_hours` | Expiración automática de Pendiente | 24 | Al expirar, cambia a Rechazada/Cancelada automática (definir mensaje). |
| `cancel_cutoff_hours` | Horas mínimas antes del inicio para cancelar | 2 | Aplica a Cancelada por estudiante. |
| `notify_admin_emails` | Destinatarios admin | lista | Permitir múltiples + CC/BCC opcional. |
| `notify_student_on_approval` | Notificar al estudiante al aprobar | true | También aplica a rechazo/cancelación. |
| `pdf_template` | Plantilla PDF activa | `default` | Debe versionarse y ser seleccionable por admin. |

## 8) Reglas de conflicto y disponibilidad (definición normativa)
- Una reserva ocupa un intervalo **[inicio, fin)** en la zona horaria configurada.
- Existe **conflicto** si el intervalo solicitado se solapa con cualquier reserva **Pendiente** o **Aprobada**.
- La validación de disponibilidad debe ejecutarse al:
  - visualizar disponibilidad para el calendario,
  - crear una solicitud,
  - aprobar una solicitud.

## 9) Requisitos funcionales (RF)

### RF1 — Gestión de usuarios
- **RF1.1 Registro de estudiante:** el sistema permitirá registrarse capturando: nombres y apellidos, escuela profesional, base, número telefónico y correo institucional.
- **RF1.2 Validación por allow-list:** el sistema validará que el correo institucional exista en la allow-list vigente. Si no existe, el registro será rechazado con un mensaje claro.
- **RF1.3 Autenticación:** el sistema permitirá inicio y cierre de sesión para estudiantes y administrador.
- **RF1.4 Autorización:** las rutas y acciones privadas deberán requerir autenticación; el panel admin solo accesible para rol administrador.

**Criterios de aceptación (RF1):**
- Dado un correo en allow-list, cuando el estudiante se registra, entonces puede iniciar sesión.
- Dado un correo fuera de allow-list, cuando el estudiante intenta registrarse, entonces el sistema bloquea el registro y registra el intento (para auditoría).

### RF2 — Allow-list (correos permitidos)
- **RF2.1 Importación:** el administrador podrá importar allow-list mediante archivo **CSV/XLSX**.
- **RF2.2 Validación de importación:** el sistema validará formato, duplicados y correos inválidos, mostrando reporte de errores y cantidad importada.
- **RF2.3 Estrategia de actualización:** la importación deberá soportar modo **reemplazar** y modo **fusionar** (configurable o seleccionable por el admin al importar).

**Criterios de aceptación (RF2):**
- Al subir un archivo con 100 correos válidos, el sistema confirma importación y permite registrar esos correos.
- Al subir un archivo con filas inválidas, el sistema no “rompe” la importación; reporta filas con error.

### RF3 — Calendario y disponibilidad
- **RF3.1 Calendario público:** el sistema mostrará un calendario interactivo en ruta pública con slots ocupados/libres (sin datos personales).
- **RF3.2 Calendario privado del estudiante:** el estudiante autenticado podrá ver sus reservas: activas (Pendiente/Aprobada) y pasadas (histórico).
- **RF3.3 Actualización:** al aprobar/rechazar/cancelar/expirar una reserva, el calendario reflejará cambios.
- **RF3.4 Filtros:** el calendario deberá permitir filtrar por fecha y visualizar disponibilidad por día/semana (según UI).

**Criterios de aceptación (RF3):**
- Una reserva Pendiente aparece como ocupada en el calendario público.
- El calendario público nunca muestra nombre, teléfono ni correo del estudiante.

### RF4 — Creación y gestión de solicitudes de reserva (estudiante)
- **RF4.1 Crear solicitud:** el estudiante autenticado podrá solicitar una reserva seleccionando fecha y horario disponibles, según el modo de reservas configurado (`booking_mode`).
- **RF4.2 Validación de disponibilidad:** antes de registrar, el sistema validará conflictos y políticas (anticipación, duración, horarios de atención, blackout).
- **RF4.3 Límite por usuario:** el sistema limitará reservas activas por estudiante según configuración.
- **RF4.4 Cuota semanal por Escuela+Base:** el sistema limitará el número de reservas por semana para el grupo (Escuela+Base) según configuración.
- **RF4.5 Estado inicial:** toda solicitud inicia en estado **Pendiente**.
- **RF4.6 Cancelación:** el estudiante podrá cancelar una reserva Pendiente o Aprobada según la política configurada (cutoff).

**Criterios de aceptación (RF4):**
- Si el slot ya está ocupado (Pendiente/Aprobada), la solicitud se rechaza con mensaje “Horario no disponible”.
- Si el estudiante ya tiene el máximo de reservas activas, el sistema bloquea la nueva solicitud e indica el motivo.
- Si la cuota semanal por Escuela+Base está completa, el sistema bloquea la solicitud e indica el motivo.

### RF5 — Gestión de solicitudes (administrador)
- **RF5.1 Bandeja:** el administrador podrá ver una bandeja de solicitudes Pendientes con datos del solicitante y el intervalo solicitado.
- **RF5.2 Decisión:** el administrador podrá **aprobar** o **rechazar** solicitudes Pendientes, registrando motivo opcional.
- **RF5.3 Validación al aprobar:** al aprobar, el sistema revalidará conflictos y políticas (para evitar “carreras”).
- **RF5.4 Historial:** el administrador podrá consultar historial por estados y por rango de fechas.

**Criterios de aceptación (RF5):**
- Si al momento de aprobar existe conflicto (por otra reserva creada después), el sistema impide la aprobación y solicita resolver.
- Toda decisión queda registrada en auditoría (quién, cuándo, qué cambió).

### RF6 — PDF y notificaciones
- **RF6.1 Generación de PDF:** al aprobar, el sistema generará un PDF usando la plantilla activa, con: datos del estudiante (según política de privacidad), fecha/hora, estado, código/ID de reserva y condiciones de uso.
- **RF6.2 Envío de correos:** el sistema enviará correos al administrador y opcionalmente al estudiante (configurable) al aprobar/rechazar/cancelar.
- **RF6.3 Manejo de fallos:** si falla el envío de correo o generación de PDF, el sistema registrará el error y permitirá reintento desde el panel admin sin perder el estado de la reserva.

**Criterios de aceptación (RF6):**
- Al aprobar una reserva, el admin recibe un correo con el PDF adjunto o enlace (según implementación).
- Si el correo falla, existe evidencia en logs/auditoría y opción de reintento.

### RF7 — Configuración del sistema (administrador)
- **RF7.1 CRUD de configuración:** el administrador podrá ver y modificar las configuraciones del sistema listadas en la sección 7.
- **RF7.2 Blackout dates:** el administrador podrá definir fechas/horas no reservables (mantenimiento, feriados, eventos).
- **RF7.3 Vista previa:** el administrador podrá previsualizar el impacto de la configuración (p. ej. horarios disponibles por día).

### RF8 — Auditoría y trazabilidad
- **RF8.1 Registro de eventos:** el sistema registrará eventos críticos: creación, aprobación, rechazo, cancelación, expiración, reintentos de notificación, importaciones de allow-list.
- **RF8.2 Consulta básica:** el administrador podrá consultar la auditoría por fecha y tipo de evento.

## 10) Requisitos no funcionales (RNF)
- **RNF1 Usabilidad:** UI responsiva (móvil/escritorio) con calendario claro, mensajes de validación y estados visibles.
- **RNF2 Seguridad:** contraseñas hasheadas; control de acceso por rol; protección CSRF; rate limiting en autenticación y creación de reservas; sanitización de entradas.
- **RNF3 Privacidad:** el calendario público no debe exponer información personal; política de retención de datos definida (configurable o documentada).
- **RNF4 Rendimiento:** disponibilidad/consulta de calendario debe responder en tiempos razonables bajo carga concurrente; evitar N+1 en consultas.
- **RNF5 Disponibilidad:** objetivo ≥ 95% de disponibilidad mensual.
- **RNF6 Mantenibilidad:** el sistema debe seguir convenciones Laravel/Inertia/Vue; configuración centralizada; pruebas automatizadas para reglas críticas.
- **RNF7 Observabilidad:** logs estructurados para fallos de notificación/importación; métricas básicas (cantidad de reservas, fallos, etc.) si aplica.

## 11) Flujo general del sistema (alto nivel)
1. Visitante consulta el calendario público (ocupado/libre).
2. Estudiante se registra (solo si su correo está en allow-list) o inicia sesión.
3. Estudiante selecciona fecha/hora disponible y crea una solicitud (Pendiente).
4. Administrador revisa la bandeja y aprueba o rechaza.
5. Si aprueba: se genera PDF y se envían correos según configuración.
6. Calendario se actualiza según el estado (Pendiente/Aprobada bloquean).

## 12) Escenarios de aceptación (end-to-end)
1. Registro con correo en allow-list → usuario puede autenticarse.
2. Registro con correo no permitido → sistema rechaza registro con mensaje y deja evidencia en auditoría.
3. Solicitud en slot libre → queda Pendiente y bloquea el calendario público.
4. Solicitud en slot ocupado (Pendiente/Aprobada) → sistema rechaza por no disponibilidad.
5. Límite de reservas activas por usuario → sistema bloquea la creación y muestra motivo.
6. Cuota semanal por Escuela+Base → sistema bloquea creación y muestra motivo.
7. Admin aprueba → pasa a Aprobada, genera PDF y notifica; auditoría registra el evento.
8. Admin rechaza → pasa a Rechazada, libera slot, notifica; auditoría registra el evento.
9. Estudiante cancela antes del cutoff → pasa a Cancelada, libera slot, notifica; auditoría registra el evento.
10. Solicitud Pendiente expira → cambia de estado automático, libera slot y registra auditoría.

## 13) Riesgos y consideraciones
- Bloqueo por Pendiente puede ser susceptible a abuso; se mitiga con expiración automática y límites por usuario.
- La importación de allow-list requiere manejo robusto de formato y errores para evitar bloqueos de registro.
- Los fallos de email/PDF no deben dejar el sistema en estados inconsistentes; se requiere reintento y trazabilidad.
