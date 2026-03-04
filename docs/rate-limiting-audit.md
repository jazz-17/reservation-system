# Rate Limiting Audit (Reservation System)

This document inventories every rate limit enforced by the application and provides an opinionated audit (too strict / too lenient / risks) based on typical threat models for a university-facing reservation system.

## What “rate limiting” means in this app

Laravel applies rate limits via the `throttle` middleware:

- **Named limiters**: `throttle:<name>` uses `RateLimiter::for('<name>', ...)`.
- **Inline limits**: `throttle:<max>,<minutes>` (example: `throttle:6,1`).

Important detail (Laravel core behavior):

- For `throttle:<max>,<minutes>`, when the request is **authenticated**, the bucket key is the **user id**; otherwise it is **domain + IP**.
  - See `vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php` (`resolveRequestSignature`).

The limiter store is configured in `config/cache.php`:

- `CACHE_STORE` defaults to `database` (general cache).
- `CACHE_LIMITER` defaults to `redis` (rate limiting / throttling).
  - This is good for correctness in multi-instance deployments, assuming Redis is available.

## Inventory: every enforced rate limit

### Public calendar availability API

**Route**

- `GET /api/public/availability` (`api.public.availability`)
  - `routes/web.php:156`

**Middleware**

- `throttle:public-availability` (`routes/web.php:158`)

**Limiter definition**

- `RateLimiter::for('public-availability', ...)` in `app/Providers/AppServiceProvider.php:97`

**Limits (both apply)**

- Per **session**: `config('rate-limiting.public_availability.per_session_per_minute')` = **120 / minute**
- Per **IP**: `config('rate-limiting.public_availability.per_ip_per_minute')` = **1200 / minute**
  - Values come from `config/rate-limiting.php:14`

**Bucket key**

- If session cookie exists: `session:<sessionId>`
- Otherwise: `ip-sessionless:<ip>`
- Plus an additional IP bucket: `ip:<ip>`

**Other relevant protection**

- Request range is capped to `config('rate-limiting.public_availability_max_days')` = **60 days** via validation (`app/Http/Requests/Api/PublicAvailabilityRequest.php:28`, `config/rate-limiting.php:19`).

**Audit**

- Good: uses both per-session and per-IP buckets; protects an expensive endpoint (`AvailabilityService` hits multiple DB queries).
- Potentially too lenient for scraping: `1200/min` per IP is high; a single client can still create sustained DB load (especially across multiple IPs).
- Risk: if Redis is unavailable (but `CACHE_LIMITER=redis`), throttling and other rate-limiter-backed operations may error under load; ensure Redis is provisioned in all environments that serve traffic.

**Recommendation**

- If you have seen load issues or scraping, consider lowering per-IP (e.g. 300–600/min) while keeping per-session at 120/min to preserve UX.
- Consider enabling Redis-backed throttle middleware in `bootstrap/app.php` via `$middleware->throttleWithRedis()` if Redis is the limiter store (perf + atomicity improvement).

---

### Authentication (Fortify)

Fortify’s built-in routes are disabled (`Fortify::ignoreRoutes()` in `app/Providers/FortifyServiceProvider.php:26`) and re-registered in `routes/fortify.php`. Rate limiting is applied there using `config('fortify.limiters.*')` and custom `RateLimiter::for(...)` definitions.

#### Login

**Route**

- `POST /login` (`login.store`) (`routes/fortify.php:49`)

**Middleware**

- `throttle:login` (`routes/fortify.php:50`)
  - Config mapping: `config/fortify.php:117`

**Limiter definition**

- `RateLimiter::for('login', ...)` in `app/Providers/FortifyServiceProvider.php:115`

**Limit**

- **5 / minute** per **email + IP** (throttle key is normalized + transliterated).

**Audit**

- Good: standard anti-bruteforce for a single account and IP.
- Risk (too lenient for credential stuffing): because the limiter key includes the email, an attacker from one IP can attempt **5/minute per account**, scaling attempts across many accounts.

**Recommendation**

- Add a second per-IP limiter to the `login` limiter (Laravel supports returning multiple limits). This keeps the current per-account protection while limiting “many accounts from one IP”.

#### Two-factor challenge

**Route**

- `POST /two-factor-challenge` (`two-factor.login.store`) (`routes/fortify.php:153`)

**Middleware**

- `throttle:two-factor` (`routes/fortify.php:154`)
  - Config mapping: `config/fortify.php:117`

**Limiter definition**

- `RateLimiter::for('two-factor', ...)` in `app/Providers/FortifyServiceProvider.php:115`

**Limit**

- **5 / minute** keyed by session `login.id`.

**Audit**

- Good: limits OTP guessing.
- Risk: if `login.id` were ever missing / empty for a request, buckets could collapse (multiple users sharing the same empty key). This should be unlikely if Fortify’s flow is intact, but it’s worth keeping in mind when customizing authentication.

#### Registration

**Route**

- `POST /register` (`register.store`) (`routes/fortify.php:91`)

**Middleware**

- `throttle:register` (`routes/fortify.php:92`)
  - Config mapping: `config/fortify.php:117`

**Limiter definition**

- `RateLimiter::for('register', ...)` in `app/Providers/FortifyServiceProvider.php:115`

**Limits (both apply)**

- Per **IP**: `config('rate-limiting.register.per_ip_per_minute')` = **10 / minute**
- Per **email**: `config('rate-limiting.register.per_email_per_minute')` = **3 / minute**
  - Values from `config/rate-limiting.php:4`

**Audit**

- Good: appropriate for a system that already restricts registration (this app checks an allow list in `app/Actions/Fortify/CreateNewUser.php`).
- Potentially too strict in shared networks: if you ever run mass onboarding events behind one NAT, `10/min` per IP may block legitimate users.

#### Forgot password (request reset link)

**Route**

- `POST /forgot-password` (`password.email`) (`routes/fortify.php:71`)

**Middleware**

- `throttle:forgot-password` (`routes/fortify.php:72`)
  - Config mapping: `config/fortify.php:117` uses `password-email` → `forgot-password`

**Limiter definition**

- `RateLimiter::for('forgot-password', ...)` in `app/Providers/FortifyServiceProvider.php:115`

**Limits (both apply)**

- Per **IP**: `config('rate-limiting.forgot_password.per_ip_per_minute')` = **10 / minute**
- Per **email**: `config('rate-limiting.forgot_password.per_email_per_minute')` = **2 / minute**
  - Values from `config/rate-limiting.php:9`

**Additional throttling**

- Password broker throttle: `config/auth.php:93` sets `'throttle' => 60` seconds between generating reset tokens.

**Audit**

- Good: defense-in-depth (route limiter + broker throttle).
- Potential usability concern: users can still spam the form and get 429s; but the values are reasonable.

#### Email verification (send + verify)

**Routes**

- `POST /email/verification-notification` (`verification.send`) (`routes/fortify.php:111`)
- `GET /email/verify/{id}/{hash}` (`verification.verify`) (`routes/fortify.php:107`)

**Middleware**

- Both use `throttle:<verificationLimiter>` (`routes/fortify.php:108`, `routes/fortify.php:112`)
- Default is `throttle:6,1` (`routes/fortify.php:45`) unless `fortify.limiters.verification` is set.

**Effective limit (default)**

- **6 / minute** per **user id** (because these routes require auth).

**Audit**

- Good: prevents “resend email” spam; default 6/min is sane.
- If you see legitimate users getting throttled, the usual cause is repeated clicks / retries; raising to 10/min is typically enough.

---

### Authenticated reservation / settings actions (app routes)

These routes use the inline throttle (`throttle:6,1`). Because they are behind `auth` middleware, the effective bucket key is the **user id**.

#### Create reservation request

- `POST /reservas` (`reservations.store`)
  - `routes/web.php:34` + `routes/web.php:38`
- Limit: **6 / minute** per user

**Audit**

- Good: prevents accidental double-submits and basic spam.
- If users commonly create multiple reservations quickly (or if the UI retries automatically), 6/min may feel restrictive.

#### Cancel reservation

- `POST /reservas/{reservation}/cancelar` (`reservations.cancel`)
  - `routes/web.php:42`
- Limit: **6 / minute** per user

**Audit**

- Likely fine; cancellations should be rare.

#### Change password (settings)

- `PUT /settings/password` (`user-password.update`)
  - `routes/settings.php:16` + `routes/settings.php:21`
- Limit: **6 / minute** per user

**Audit**

- Good: mitigates brute-force-y UI behavior and repeated submissions.
- Not a primary security control (password change already requires auth + current password validation in the request layer, if implemented).

## Summary judgement

Overall, the app’s rate limiting is **present and mostly reasonable**, especially around authentication and the public availability endpoint.

The main “too lenient” gap is **login credential stuffing across many accounts from one IP**, since the current login limiter is only keyed by `email + ip`. The main “too strict” risk is **public availability** if you lower it too far and FullCalendar starts 429’ing during normal navigation; however current values are generous.

## Recommended follow-up actions (prioritized)

1. Add a **second per-IP limit** to the `login` limiter (keep the current email+IP limiter, add an IP-only limiter).
2. Confirm Redis is provisioned for `CACHE_LIMITER=redis` in all deployed environments; otherwise throttling may not behave as expected under load.
3. If availability endpoint load is a concern, tune `public_availability.per_ip_per_minute` downward and consider caching responses by date range.

