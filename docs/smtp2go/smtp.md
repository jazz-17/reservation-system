# SMTP2GO via SMTP (Laravel)

This project sends email using **Laravel Mail**. For SMTP2GO, prefer using SMTP (not the HTTP API) so switching providers later (e.g. Amazon SES) is mostly configuration.

## Recommended SMTP settings (SMTP2GO)

From the SMTP2GO dashboard, create an SMTP user per environment (e.g. `reservation-system-prod`, `reservation-system-staging`).

Typical `.env` values:

```bash
MAIL_MAILER=smtp
MAIL_HOST=mail.smtp2go.com
MAIL_PORT=587
MAIL_SCHEME=smtp   # 587 STARTTLS
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

Alternative if 587 is blocked:

```bash
MAIL_PORT=2525
MAIL_SCHEME=smtp
```

Implicit TLS:

```bash
MAIL_PORT=465
MAIL_SCHEME=smtps
```

## Verified sender requirement

SMTP2GO will reject email if the From address is not authorized.

Make sure one of these is true:
- The **domain** in `MAIL_FROM_ADDRESS` is verified in SMTP2GO (recommended), or
- The exact `MAIL_FROM_ADDRESS` is verified as a single sender email.

## Test sending

Use the built-in command:

```bash
php artisan mail:test you@example.com
```

## Queues (production)

Email verification is queued. Ensure the production `queue` worker container is running so queued notifications are processed.

