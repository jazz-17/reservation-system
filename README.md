# Reserva FISI

**Live:** [reservafisi.org.pe](https://reservafisi.org.pe)

A production-grade university resource reservation system for the Faculty of Systems Engineering (UNMSM). Students book shared resources through an interactive calendar interface while administrators manage requests, blackout periods, and quotas with full audit trails.

---

## Tech Stack

### Application

| Layer | Technologies |
|-------|-------------|
| **Backend** | PHP 8.4, Laravel 12, Inertia.js v2 |
| **Frontend** | Vue 3, TypeScript, Tailwind CSS v4 |
| **UI** | shadcn-vue (reka-ui), FullCalendar v6, Lucide Icons |
| **Auth** | Laravel Fortify (2FA, email verification, password reset) |
| **Database** | PostgreSQL 16 with exclusion constraints for conflict prevention |
| **Cache/Sessions** | Redis 7 |
| **Email** | Amazon SES (transactional emails) |
| **Permissions** | Spatie Laravel Permission (roles & permissions) |

### Infrastructure

| Component | Technology |
|-----------|------------|
| **Hosting** | Oracle Cloud Infrastructure (OCI) |
| **CDN/Proxy** | Cloudflare (SSL termination, DDoS protection, caching) |
| **Containerization** | Docker with multi-stage builds |
| **Web Server** | Nginx (Alpine) with Cloudflare origin protection |

---

## Architecture Highlights

### Multi-Stage Docker Build

The `Dockerfile` uses a 6-stage build process for optimal image size and build caching:

```
composer → base → php-build → vendor → wayfinder → node-build → prod → nginx
```

- **Wayfinder stage** generates TypeScript route functions at build time
- **Node build** compiles Vue/TypeScript assets with Vite
- **Production image** ships only runtime dependencies (~150MB)
- **Nginx image** serves static assets directly, proxies PHP to FPM

### Database Integrity

- **PostgreSQL exclusion constraint** prevents overlapping approved reservations at the database level
- **Immutable datetimes** (`CarbonImmutable`) prevent accidental mutations
- **UTC storage** with timezone-aware display (`America/Lima`)
- **Spatie Period** library for precise overlap detection with minute precision

### Service Architecture

```
docker-compose.prod.yml
├── app (PHP-FPM)        # Main application
├── nginx                # Reverse proxy + static files
├── postgres             # Primary database
├── redis                # Sessions + cache + rate limiting
└── queue                # Background job worker
```

---

## Features

### Student Portal
- Interactive monthly calendar with availability visualization
- Flexible time booking (any start/end within opening hours)
- Real-time validation for duration, lead time, and conflicts
- Reservation history with status tracking
- Email notifications for status changes

### Admin Dashboard
- Request queue with approve/reject workflow
- Conflict detection when multiple pending requests overlap
- Blackout period management (holidays, maintenance)
- Configurable quotas (per user, per school, weekly limits)
- Email allow-list for registration control
- Complete audit trail of all actions
- User and role management (Spatie Permission)

### Technical Features
- **Type-safe routing** — Wayfinder generates TypeScript functions for all Laravel routes
- **Actions pattern** — Business logic in single-responsibility action classes
- **Form Requests** — Validation extracted into dedicated request classes
- **Queue workers** — PDF generation and emails processed asynchronously
- **Health checks** — `/healthz` endpoint for container orchestration
- **33 Pest tests** covering reservation rules, availability, and workflows

---

## Project Structure

```
app/
├── Actions/
│   ├── Reservations/    # ReservationService, RulesService, AvailabilityService
│   ├── Settings/        # SettingsService (configurable opening hours, limits)
│   └── Audit/           # Audit trail recording
├── Http/Controllers/
│   ├── Admin/           # Admin panel (requests, history, settings, users)
│   ├── Api/             # JSON endpoints for Vue components
│   └── Student/         # Student booking flow
├── Models/              # Eloquent models with relationships & scopes
├── Jobs/                # SendReservationEmail (queued)
└── Policies/            # Authorization rules

resources/js/
├── pages/
│   ├── admin/           # Requests, History, Settings, Users, Audit...
│   ├── calendar/        # Public calendar (FullCalendar dayGridMonth)
│   └── reservations/    # Create booking (FullCalendar timeGridDay)
├── components/ui/       # shadcn-vue components
└── layouts/             # App, Auth, Public layouts

docker/
├── nginx/               # Nginx config + Cloudflare real-ip
├── php/                 # PHP-FPM production config (OPcache)
└── entrypoint.sh        # Config/route caching on startup

scripts/cloudflare/
├── update.sh            # Fetch CF IPs, update ipset + nginx
└── systemd/             # Timer for daily allowlist updates
```

---

## Local Development

```bash
# Start PostgreSQL, Redis, and Mailpit
docker compose up -d

# Install dependencies
composer install && npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations with seed data
php artisan migrate --seed

# Start dev server (Laravel + Vite + Queue + Pail logs)
composer run dev
```

Access the app at `http://localhost:8000` and Mailpit at `http://localhost:8025`.

## Production Deployment

```bash
# Build and start all services
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build

# Run migrations
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Set up Cloudflare origin protection (requires root)
sudo bash scripts/cloudflare/update.sh
```

## Testing

```bash
# Run all 33 tests
php artisan test

# Run with coverage
php artisan test --coverage

# Filter specific tests
php artisan test --filter=ReservationRulesTest
```

---

Built with Laravel 12 and Vue 3. Deployed on Oracle Cloud with Cloudflare.
