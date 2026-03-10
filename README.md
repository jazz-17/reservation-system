# Reserva FISI

**Live:** [reservafisi.org.pe](https://reservafisi.org.pe)

A modern university resource reservation system built for the Faculty of Systems Engineering. Students can book shared resources (labs, meeting rooms, equipment) through an intuitive calendar interface, while administrators manage requests, blackout periods, and quotas.

## Tech Stack

| Layer | Technologies |
|-------|-------------|
| **Backend** | PHP 8.4, Laravel 12, PostgreSQL, Redis |
| **Frontend** | Vue 3, TypeScript, Inertia.js v2, Tailwind CSS v4 |
| **UI Components** | shadcn-vue (reka-ui), Lucide Icons |
| **Calendar** | FullCalendar v6 |
| **Auth** | Laravel Fortify (2FA, email verification) |
| **Infrastructure** | Docker, SMTP2GO (transactional email) |

## Features

### For Students
- **Interactive Calendar** — Browse availability in a monthly calendar view
- **Flexible Booking** — Any start/end time within opening hours (no fixed slots)
- **Real-time Validation** — Instant feedback on duration limits, lead time, and conflicts
- **Reservation Management** — View, track, and cancel pending/approved bookings

### For Administrators
- **Request Queue** — Approve or reject pending reservations with conflict detection
- **Blackout Management** — Block dates for maintenance, holidays, or special events
- **Quota System** — Configurable limits per user, per school, and per week
- **Allow-list** — Restrict registration to authorized university emails
- **Audit Trail** — Complete history of all system actions
- **PDF Artifacts** — Auto-generated reservation confirmations

### Technical Highlights
- **UTC Storage, Local Display** — All datetimes stored in UTC, converted to `America/Lima` for users
- **Overlap Prevention** — Database-level exclusion constraint prevents double-booking
- **Actions Pattern** — Business logic organized into single-responsibility action classes
- **Type-safe Routes** — Wayfinder generates TypeScript functions for all Laravel routes
- **Queue Workers** — PDF generation and email notifications run asynchronously

## Architecture

```
app/
├── Actions/           # Business logic (Reservations, Settings, Audit)
├── Http/Controllers/
│   ├── Admin/         # Admin panel endpoints
│   ├── Api/           # JSON API for frontend
│   └── Student/       # Student-facing endpoints
├── Models/            # Eloquent models with relationships
├── Policies/          # Authorization rules
└── Jobs/              # Async tasks (PDF, Email)

resources/js/
├── pages/             # Inertia page components
│   ├── admin/         # Admin views (Requests, History, Settings...)
│   ├── calendar/      # Public calendar with FullCalendar
│   └── reservations/  # Student booking flow
├── components/ui/     # shadcn-vue components
└── layouts/           # App, Auth, Public layouts
```

## Screenshots

*Coming soon*

## Local Development

```bash
# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate --seed

# Start development server (runs Laravel, Vite, Queue, and Pail concurrently)
composer run dev
```

## Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=ReservationTest
```

---

Built with Laravel and Vue.
