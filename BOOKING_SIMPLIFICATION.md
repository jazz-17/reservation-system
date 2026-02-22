# Booking Simplification: Remove Booking Modes, Flexible Reservations

> **Prerequisite:** This document builds on top of [CALENDAR_REDESIGN.md](./CALENDAR_REDESIGN.md), which covers the public calendar switch to FullCalendar + event-based API. This document covers the **booking form redesign** and the **removal of booking modes** from the entire system.

## Decision

We are removing the concept of "booking modes" (`fixed_duration`, `variable_duration`, `predefined_blocks`). Reservations become fully flexible: a user picks any start time and any end time, as long as it doesn't conflict with existing reservations, blackouts, or opening hours. A reservation at 11:33 or 11:41 is equally valid.

This simplifies the entire stack — backend validation, frontend UI, admin settings, and the mental model.

---

## What Gets Removed

### The `BookingMode` Enum

**File:** `app/Models/Enums/BookingMode.php`

This enum and all references to it are deleted. There is no longer a concept of fixed vs. variable vs. predefined.

### Settings That Become Obsolete

These settings no longer serve a purpose and should be removed from `SettingsDefaults`, the admin settings form, and the `settings` table:

| Setting | Why it's removed |
|---|---|
| `booking_mode` | No more modes — always flexible |
| `slot_duration_minutes` | No fixed slot length |
| `slot_step_minutes` | No step alignment requirement — any start time is valid |
| `predefined_blocks` | No predefined blocks concept |

### Settings That Stay (Renamed for Clarity)

| Current Setting | New Name (optional) | Purpose |
|---|---|---|
| `min_duration_minutes` | `min_duration_minutes` | Minimum booking length (e.g., can't book 5 minutes) |
| `max_duration_minutes` | `max_duration_minutes` | Maximum booking length (e.g., can't book 8 hours) |
| `lead_time_min_hours` | `lead_time_min_hours` | Must book at least N hours ahead |
| `lead_time_max_days` | `lead_time_max_days` | Can't book more than N days out |
| `max_active_reservations_per_user` | `max_active_reservations_per_user` | Limit active reservations per user |
| `weekly_quota_per_school_base` | `weekly_quota_per_school_base` | Weekly limit per school+base |
| `pending_expiration_hours` | `pending_expiration_hours` | Auto-expire unreviewed requests |
| `cancel_cutoff_hours` | `cancel_cutoff_hours` | Cancellation deadline |
| `timezone` | `timezone` | System timezone |
| `opening_hours` | `opening_hours` | Per-weekday open/close hours |
| `notify_admin_emails` | `notify_admin_emails` | Admin notification config |
| `notify_student_on_approval` | `notify_student_on_approval` | Student notification toggle |
| `pdf_template` | `pdf_template` | PDF generation template |

---

## Backend Changes

### 1. `AvailabilityService` — Gut and Simplify

**File:** `app/Actions/Reservations/AvailabilityService.php`

The entire slot-generation engine is removed. The service becomes a thin query layer:

**Remove these methods:**
- `buildOptionsForDay()`
- `buildFixedDurationSlots()`
- `buildPredefinedBlocks()`
- `buildVariableStartTimes()`
- `overlapsAny()`

**Replace `availabilityForRange()` with an event-based method:**

The new method queries reservations (pending + approved) and blackouts for a date range, then maps them to FullCalendar event objects. No computation, just a query + format. See [CALENDAR_REDESIGN.md](./CALENDAR_REDESIGN.md) for the exact response shape.

### 2. `ReservationRulesService` — Simplify Validation

**File:** `app/Actions/Reservations/ReservationRulesService.php`

**Remove `validateModeConstraints()` entirely.** This method enforced:
- Step alignment (start time must be divisible by `slot_step_minutes`) — no longer needed
- Fixed duration match — no longer needed
- Predefined block match — no longer needed

**Keep and simplify these validations in `validateForCreation()`:**

| Validation | Current | New |
|---|---|---|
| End after start | `$endsAtUtc->lessThanOrEqualTo($startsAtUtc)` | Same (or use `spatie/period`) |
| Lead time | `validateLeadTime()` | Keep as-is |
| Opening hours | `validateOpeningHours()` | Keep, refactor to use `spatie/period` |
| Blackout overlap | `validateBlackouts()` | Keep, refactor to use `spatie/period` |
| User active limit | `validateUserActiveLimit()` | Keep as-is |
| Weekly quota | `validateWeeklyQuota()` | Keep as-is |
| Conflict overlap | `validateConflicts()` | Keep, refactor to use `spatie/period` |
| **Min/max duration** | Was inside `validateModeConstraints()` | **Extract to its own check** — ensure the reservation is between `min_duration_minutes` and `max_duration_minutes` |

The new `validateForCreation()` flow:

```
1. End must be after start
2. Duration must be >= min_duration_minutes and <= max_duration_minutes
3. Lead time check (not too soon, not too far)
4. Must fall within opening hours for that weekday
5. Must not overlap any blackouts
6. User hasn't exceeded active reservation limit
7. School/base weekly quota not exceeded
8. Must not overlap any existing reservations (pending or approved)
```

### 3. `ReservationService` — Simplify Creation

**File:** `app/Actions/Reservations/ReservationService.php`

In `createPending()`, remove the booking mode switch that auto-computes `ends_at` for fixed-duration mode:

```php
// REMOVE THIS:
$mode = BookingMode::from($this->settings->getString('booking_mode'));
$computedEndsAtUtc = match ($mode) {
    BookingMode::FixedDuration => $startsAtUtc->addMinutes(...),
    default => $endsAtUtc,
};

// REPLACE WITH:
// $endsAtUtc is always required now — no auto-computation
```

Both `starts_at` and `ends_at` are always required from the client.

### 4. `StoreReservationRequest` — Both Fields Required

**File:** `app/Http/Requests/Student/StoreReservationRequest.php`

Remove the conditional `nullable` on `ends_at`:

```php
// BEFORE:
'ends_at' => [
    $mode === BookingMode::FixedDuration ? 'nullable' : 'required',
    'date',
    'after:starts_at',
],

// AFTER:
'ends_at' => ['required', 'date', 'after:starts_at'],
```

No more dependency on `BookingMode` or `SettingsService` in the form request.

### 5. `SettingsDefaults` — Remove Obsolete Keys

**File:** `app/Actions/Settings/SettingsDefaults.php`

Remove: `booking_mode`, `slot_duration_minutes`, `slot_step_minutes`, `predefined_blocks`.

Keep: `min_duration_minutes`, `max_duration_minutes`, and everything else.

### 6. `UpdateSettingsRequest` — Remove Obsolete Fields

**File:** `app/Http/Requests/Admin/UpdateSettingsRequest.php`

Remove validation rules for: `booking_mode`, `slot_duration_minutes`, `slot_step_minutes`, `predefined_blocks`, and all `predefined_blocks.*` nested rules.

### 7. Database: Clean Up Stale Settings Rows

No migration needed (settings is a key-value store), but a cleanup is recommended:

```php
// In a migration or artisan command:
Setting::query()->whereIn('key', [
    'booking_mode',
    'slot_duration_minutes',
    'slot_step_minutes',
    'predefined_blocks',
])->delete();
```

### 8. Delete `BookingMode` Enum

**File:** `app/Models/Enums/BookingMode.php` — delete entirely.

---

## Frontend Changes

### 1. `Create.vue` — New Booking Form

**File:** `resources/js/pages/reservations/Create.vue`

Replace the entire slot-based picker with a two-panel layout:

**Left panel:** FullCalendar `timeGridDay` view (read-only)
- Shows existing reservations and blackouts for the selected day as colored blocks on a vertical timeline
- Requires `@fullcalendar/timegrid` package (in addition to packages from CALENDAR_REDESIGN.md)
- Opening hours displayed via FullCalendar's `businessHours` + `slotMinTime`/`slotMaxTime`
- This is purely visual context — not interactive for selection

**Right panel:** Simple form
- Date picker (navigates the day view on the left)
- Start time input (`<input type="time">`)
- End time input (`<input type="time">`)
- Summary (duration computed client-side as display-only)
- "Enviar solicitud" button

**Data flow:**
1. User picks a date → FullCalendar day view updates, form date updates
2. User sees what's taken on the timeline
3. User types start and end time in the form
4. Submit → `POST /reservas` with `starts_at` and `ends_at`
5. Server validates everything (overlap, opening hours, blackouts, duration, lead time, quotas)
6. On error → show validation messages
7. On success → redirect to reservations index

**On mobile:** The two panels stack vertically — calendar on top, form below.

**What's removed from Create.vue:**
- All slot/block/start_time TypeScript types
- The `vue-query` availability fetch
- The slot picker UI (buttons for each slot)
- The duration dropdown for variable mode
- All booking mode conditional rendering

### 2. `Settings.vue` — Simplify Admin Form

**File:** `resources/js/pages/admin/Settings.vue`

Remove:
- `booking_mode` select dropdown
- `slot_duration_minutes` input
- `slot_step_minutes` input
- Entire "Bloques predefinidos" section (conditional on `predefined_blocks` mode)
- The `bookingModeLabel` computed
- Mode-dependent logic in `schedulePreview`
- The `PredefinedBlocks` type
- `addBlock()` / `removeBlock()` methods

Keep:
- `min_duration_minutes` input (move to always-visible, not conditional on mode)
- `max_duration_minutes` input (same)
- Opening hours section
- All notification settings
- Lead time, quotas, expiration, cancellation settings
- PDF template

Simplify `schedulePreview` — it no longer needs to compute slot counts per mode. It can just show the opening hours for each day.

### 3. `Public.vue` — Already Covered

See [CALENDAR_REDESIGN.md](./CALENDAR_REDESIGN.md). The public calendar uses FullCalendar month view.

**Addition:** clicking a day on the public calendar navigates to the booking form (`/reservas/nueva?date=2026-02-21`). The Create page reads the `date` query param to pre-select that day.

---

## New Dependencies

### Composer

| Package | Purpose |
|---|---|
| `spatie/period` | Period comparison (overlap, containment) for reservation validation |

### NPM

| Package | Purpose |
|---|---|
| `@fullcalendar/core` | FullCalendar engine |
| `@fullcalendar/vue3` | Vue 3 component |
| `@fullcalendar/daygrid` | Month grid view (public calendar) |
| `@fullcalendar/timegrid` | Day timeline view (booking form) |
| `@fullcalendar/interaction` | Click/date selection handling |

---

## Files Changed — Complete List

### Deleted

| File | Reason |
|---|---|
| `app/Models/Enums/BookingMode.php` | Booking modes removed |

### Modified (Backend)

| File | Change |
|---|---|
| `app/Actions/Reservations/AvailabilityService.php` | Remove all slot-generation methods, replace with event query |
| `app/Actions/Reservations/ReservationRulesService.php` | Remove `validateModeConstraints()`, add min/max duration check, refactor overlap checks to use `spatie/period` |
| `app/Actions/Reservations/ReservationService.php` | Remove booking mode switch in `createPending()`, require `ends_at` always |
| `app/Http/Requests/Student/StoreReservationRequest.php` | `ends_at` always required, remove `BookingMode` dependency |
| `app/Http/Requests/Admin/UpdateSettingsRequest.php` | Remove rules for `booking_mode`, `slot_duration_minutes`, `slot_step_minutes`, `predefined_blocks` |
| `app/Http/Controllers/Api/PublicAvailabilityController.php` | Update to return FullCalendar event format |
| `app/Actions/Settings/SettingsDefaults.php` | Remove `booking_mode`, `slot_duration_minutes`, `slot_step_minutes`, `predefined_blocks` |

### Modified (Frontend)

| File | Change |
|---|---|
| `resources/js/pages/calendar/Public.vue` | Replace with FullCalendar month view |
| `resources/js/pages/reservations/Create.vue` | Replace slot picker with timeGridDay + simple form |
| `resources/js/pages/admin/Settings.vue` | Remove booking mode UI, predefined blocks, slot settings |

### New (if needed)

| File | Purpose |
|---|---|
| Migration to clean stale settings | Delete obsolete settings rows from DB |

---

## Validation Rules Summary (New)

These are the server-side checks when a user submits a reservation:

| Rule | Description | Error |
|---|---|---|
| `starts_at` required, valid date | Basic input validation | "Fecha de inicio requerida" |
| `ends_at` required, valid date, after `starts_at` | Basic input validation | "Fecha de fin requerida / debe ser posterior" |
| Duration between min and max | `min_duration_minutes` <= duration <= `max_duration_minutes` | "La duración no es válida" |
| Lead time (minimum) | Start must be >= now + `lead_time_min_hours` | "Debes reservar con mayor anticipación" |
| Lead time (maximum) | Start must be <= now + `lead_time_max_days` | "La fecha supera la anticipación máxima" |
| Within opening hours | Start and end must fall within the weekday's open/close times | "Fuera del horario de atención" |
| No blackout overlap | Must not overlap any blackout period | "Fecha/hora bloqueada" |
| User active limit | User must have < `max_active_reservations_per_user` active reservations | "Máximo de reservas activas alcanzado" |
| Weekly quota | School+base must have < `weekly_quota_per_school_base` for that week | "Cuota semanal completa" |
| No conflict overlap | Must not overlap any existing pending/approved reservation | "Horario no disponible" |

---

## Implementation Order

1. **Install dependencies** — `spatie/period` + FullCalendar packages
2. **Backend: Remove booking mode** — delete enum, clean settings defaults, simplify `ReservationService`, `StoreReservationRequest`, `UpdateSettingsRequest`
3. **Backend: Simplify validation** — rewrite `ReservationRulesService` without mode constraints, add standalone min/max duration check, adopt `spatie/period`
4. **Backend: Refactor availability API** — event-based response for FullCalendar
5. **Frontend: Public calendar** — FullCalendar month view in `Public.vue`
6. **Frontend: Booking form** — timeGridDay + simple form in `Create.vue`
7. **Frontend: Admin settings** — strip mode-related fields from `Settings.vue`
8. **Database cleanup** — migration to delete stale settings rows
9. **Tests** — update all existing tests, add new ones for flexible booking validation
