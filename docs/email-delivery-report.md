# Reporte — Puntos de envío de correo y flujo de entrega

Fecha: 2026-03-02  
Repositorio: `reservation-system`  
Objetivo: documentar **todas** las situaciones en las que la app envía correos, y **cómo** se construyen/entregan.

## 1) Configuración y transporte de correo (cómo “sale” el email)

### 1.1 Mailer (Laravel Mail)

- Config: `config/mail.php`
- Mailer por defecto: `MAIL_MAILER` (por defecto `log`)
- Toggle global de entrega: `MAIL_DELIVERY_ENABLED=true|false` (cuando `false` se suprimen todos los correos salientes)
- From global: `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`

Mailers relevantes:

- `smtp` (útil con Mailpit en local)
- `smtp2go` (transporte custom vía API)
- `log` (no entrega; escribe en logs)

### 1.2 Transporte SMTP2GO (API, no SMTP relay)

- Registro del transporte: `app/Providers/AppServiceProvider.php` (`Mail::extend('smtp2go', ...)`)
- Implementación: `app/Mail/Transports/Smtp2GoTransport.php`
- Config credenciales: `config/services.php` (`SMTP2GO_API_KEY`, `SMTP2GO_ENDPOINT`, `SMTP2GO_TIMEOUT`, `SMTP2GO_FASTACCEPT`)

Cómo funciona:

1) Laravel construye un email (Mailable o Notification → Symfony Mime Email).
2) Si `MAIL_MAILER=smtp2go`, `Smtp2GoTransport` hace `POST {endpoint}/email/send` con:
   - `sender` (From)
   - `to` / `cc` / `bcc`
   - `subject`
   - `html_body` y/o `text_body`
   - `attachments` (base64) e `inlines` (base64)
3) Si SMTP2GO responde fallas, se lanza `TransportException`.

Docs internos del proyecto:

- `docs/smtp2go/laravel-integration.md`

## 2) Correos del dominio “Reservas” (status + PDF)

Esta app envía correos transaccionales sobre reservas mediante **jobs en cola** y tracking con “artifacts”.

### 2.0 Toggle global de entrega (suprime envíos)

Si `MAIL_DELIVERY_ENABLED=false`, la aplicación suprime el envío de **todos** los correos (Mail + Notifications). Para el caso de reservas, los artifacts de correo se marcan como `skipped` (no `failed`).

### 2.1 Eventos que disparan correo de reserva

Archivo: `app/Actions/Reservations/ReservationService.php`

Se encola correo (y job) en estos puntos:

- `approve(...)` → `enqueueEmails(..., event: 'approved')`
- `reject(...)` → `enqueueEmails(..., event: 'rejected')`
- `cancel(...)` → `enqueueEmails(..., event: 'cancelled')`
- `expirePending(...)` (por comando Artisan) → `enqueueEmails(..., event: 'expired')` por cada reserva expirada

### 2.2 Destinatarios (a quién se envía)

Archivo: `app/Actions/Reservations/ReservationService.php` (`enqueueEmails`)

- **Admin (opcional por configuración):**
  - Se envía **solo si** existe al menos un destinatario en el setting `notify_admin_emails.to`.
  - Se soporta `to`, `cc`, `bcc` (array).
  - Fuente de datos: `SettingsService->get('notify_admin_emails')`
  - Defaults (hardcoded): `app/Settings/SettingsSchema.php` (vacío)
- **Estudiante (no opcional en código actual):**
  - Se envía si `reservation->user->email` está presente.
  - Siempre se encola artefacto `EmailStudent` cuando hay email válido.

### 2.3 Modelo de entrega: ReservationArtifact + Queue Jobs

Tablas:

- `reservation_artifacts` (kind/status/attempts/last_error/payload)

Kinds (alto nivel):

- `Pdf` (generación del PDF)
- `EmailAdmin` (correo al administrador)
- `EmailStudent` (correo al estudiante)

Flujo:

1) `ReservationService::enqueueEmails()` crea/actualiza un registro en `reservation_artifacts` (payload con `event` + recipients).
2) Se usa `DB::afterCommit(...)` para encolar `SendReservationEmail` (evita correr el job antes de confirmar la transacción).
3) El worker de cola ejecuta `app/Jobs/SendReservationEmail.php`.
4) El job marca attempts/errores y envía el correo real usando `Mail::to()->cc()->bcc()->send(...)`.

Reintentos:

- Existe página admin de artifacts fallidos: `GET /admin/artifacts`
- Reintento manual: `POST /admin/artifacts/{artifact}/retry`
- Controller: `app/Http/Controllers/Admin/ReservationArtifactController.php`

### 2.4 Contenido del correo de reservas

Mailable:

- `app/Mail/ReservationStatusMail.php`
- Subject por evento:
  - `approved` → “Reserva aprobada”
  - `rejected` → “Reserva rechazada”
  - `cancelled` → “Reserva cancelada”
  - `expired` → “Reserva expirada”

Vista:

- HTML: `resources/views/emails/reservation-status.blade.php`

### 2.5 Adjuntos (PDF)

Generación:

- Job: `app/Jobs/GenerateReservationPdf.php`
- Vista: `resources/views/pdfs/reservation/default.blade.php`
- Guardado: `Storage::disk('local')->put("reservations/{id}/reservation.pdf", ...)`

Adjunto al email:

- Job: `app/Jobs/SendReservationEmail.php`
- Solo adjunta si `event === 'approved'` y existe un artifact `Pdf` con `status = Sent` y `payload.path` existente en `Storage`.
- El adjunto se pasa a `ReservationStatusMail` como `attachmentPath` y se nombra `reserva.pdf`.

## 3) Correos de autenticación / cuenta (Fortify / Laravel Notifications)

Estos correos se envían vía **Notifications** (no el sistema de artifacts de reservas).

### 3.1 Verificación de correo (VerifyEmail)

Prerequisitos:

- `app/Models/User.php` implementa `MustVerifyEmail`
- Fortify habilita la feature de verificación
- Tras registro, se redirige a `verification.notice` (`/email/verify`): `app/Http/Responses/Fortify/RegisterResponse.php`

Puntos donde se envía:

- Automático al registrarse (listener de Laravel para usuarios `MustVerifyEmail`).
- Reenvío por el usuario:
  - Ruta: `POST /email/verification-notification` (`verification.send`)
  - UI: `resources/js/pages/auth/VerifyEmail.vue` y “reenviar” en `resources/js/pages/settings/Profile.vue`
- Reenvío por admin:
  - Ruta: `POST /admin/usuarios/{user}/email-verification` (`admin.users.email-verification.store`)
  - Código: `app/Http/Controllers/Admin/UserManagementController.php` (`sendEmailVerification()`)

Notas:

- La app tiene plantillas `resources/views/emails/verify-email.blade.php` y `resources/views/emails/verify-email-text.blade.php`, pero **no** hay override explícito de `VerifyEmail::toMailUsing(...)` en `app/Providers/*`, así que hoy la notificación usa el template default de Laravel (a menos que se haya publicado/overriden vistas en otro lugar).

### 3.2 Restablecimiento de contraseña (ResetPassword)

Puntos donde se envía:

- Flujo público “Olvidé mi contraseña” (Fortify):
  - Ruta: `POST /forgot-password` (`password.email`)
- Envío por admin:
  - Ruta: `POST /admin/usuarios/{user}/password-reset` (`admin.users.password-reset.store`)
  - Código: `app/Http/Controllers/Admin/UserManagementController.php` (`sendPasswordReset()`, usa `Password::sendResetLink(...)`)

Notas:

- Estas notificaciones se envían por el canal `mail` y dependen de `MAIL_MAILER`.
- No se registran en `reservation_artifacts` (es un flujo separado).

## 4) Pruebas y verificación

Cobertura relevante:

- Transporte SMTP2GO: `tests/Feature/Mail/Smtp2GoTransportTest.php`
- Envío de VerifyEmail/ResetPassword por admin: `tests/Feature/AdminUserManagementTest.php`
- Flujo end-to-end de approve/reject/cancel + artifacts + queue: `tests/Feature/ReservationWorkflowTest.php`

## 5) Observaciones / gaps detectados

- “Opcionalmente al estudiante” (requerimiento RF4.4): en el código actual, el correo al estudiante se encola siempre que exista `user.email`. Si se necesita opcionalidad, hace falta un setting/flag y lógica en `ReservationService::enqueueEmails()`.
- Las plantillas `resources/views/emails/verify-email*.blade.php` existen pero no están conectadas explícitamente a `VerifyEmail` hoy.
- Los correos de autenticación (VerifyEmail / ResetPassword) no usan el sistema de artifacts (no hay historial/reintentos en la UI admin, salvo reenvío manual).
