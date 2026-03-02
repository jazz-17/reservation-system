# SMTP2GO + Laravel (this project)

This project sends email via **Laravel Mail**. We added a custom mail transport (`smtp2go`) so existing Mailables / Notifications (Fortify, etc.) keep working without rewriting app code.

## Mail transports in this repo

### Local development (recommended): Mailpit (SMTP)

- Mailer: `MAIL_MAILER=smtp`
- SMTP: `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`
- Inbox UI: `http://localhost:8025`

Mailpit is defined in `docker-compose.yml`. It’s great for previewing email content without sending real email.

### Real sending: SMTP2GO (API)

- Mailer: `MAIL_MAILER=smtp2go`
- Configure SMTP2GO credentials:
  - `SMTP2GO_API_KEY`
  - `SMTP2GO_ENDPOINT` (default `https://api.smtp2go.com/v3`)

SMTP2GO uses the API endpoint `POST /v3/email/send` (not SMTP relay).

## Required sender configuration (SMTP2GO dashboard)

SMTP2GO requires a verified sender.

- If you have a **verified sender domain** (recommended), you can use **any** From address on that domain:
  - Example: `MAIL_FROM_ADDRESS=no-reply@reservafisi.org.pe`
- If you do **not** have the domain verified, you must add a **single sender email** in SMTP2GO and use that address.

Also set:
- `MAIL_FROM_NAME="ReservaFISI"`

## Logging SMTP2GO responses (debugging delivery)

To see what SMTP2GO returned (request id, email id, failures), enable SMTP2GO logging:

```bash
SMTP2GO_LOG=true
SMTP2GO_LOG_CHANNEL=smtp2go
SMTP2GO_LOG_LEVEL=debug
SMTP2GO_LOG_RESPONSE=true
```

Optional (temporarily; can include sensitive content / recipients):

```bash
SMTP2GO_LOG_PAYLOAD=true
```

Logs go to:
- `storage/logs/smtp2go.log`

Tail:

```bash
tail -f storage/logs/smtp2go.log
```

## Email verification (Fortify)

Email verification is enabled via Fortify:

- `config/fortify.php` includes `Features::emailVerification()`
- `app/Models/User.php` implements `Illuminate\Contracts\Auth\MustVerifyEmail`
- The verify-email Inertia page is `resources/js/pages/auth/VerifyEmail.vue`

Behavior:
- After registration, users are redirected to `verification.notice` (`/email/verify`) until verified.
- App routes that should require verification use the `verified` middleware (see `routes/web.php`).

## Implementation notes (swap-ready abstraction)

- Transport implementation: `app/Mail/Transports/Smtp2GoTransport.php`
- Registration: `Mail::extend('smtp2go', ...)` in `app/Providers/AppServiceProvider.php`
- Config:
  - `config/mail.php` defines the `smtp2go` mailer
  - `config/services.php` holds SMTP2GO settings

Switching providers later (e.g. SES) should be a config change:
- Set `MAIL_MAILER=ses` (and configure AWS credentials / dependencies as needed).

## Common “it says sent but I got nothing” checklist

1) Confirm you are using SMTP2GO, not Mailpit:
   - `MAIL_MAILER=smtp2go`
2) Confirm your From address is allowed by SMTP2GO:
   - `MAIL_FROM_ADDRESS` is within a verified sender domain (or is a verified single sender)
3) Clear config cache if you changed env values:
   - `php artisan config:clear`
4) Enable SMTP2GO logs (`SMTP2GO_LOG=true`) and inspect `storage/logs/smtp2go.log`
5) Check SMTP2GO “Activity” using the `email_id` logged by the transport.

