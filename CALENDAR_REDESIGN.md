# Calendar Redesign: High-Level Plan

## The Problem

The current public calendar pre-computes every possible time slot on the server and sends them all to the frontend. With the default settings (60-min slots, 30-min steps, 08:00-22:00 opening hours) this produces **27 overlapping slots per day**. A 7-day request returns **189 slot objects**, almost all marked "free" — meaning the vast majority of the payload is noise.

The current API response looks like this:

```json
{
  "days": [
    {
      "date": "2026-02-21",
      "slots": [
        { "start": "...", "end": "...", "status": "free" },
        { "start": "...", "end": "...", "status": "free" },
        // ... 25 more "free" slots
      ]
    }
    // ... 6 more days
  ]
}
```

This design has several issues:

1. **Wasted data** — sending slot objects just to say "nothing is happening" is backwards. Only actual events carry information.
2. **Doesn't scale to a month view** — a month would produce ~810 slot objects.
3. **Server does unnecessary work** — generating, iterating, and overlap-checking every possible slot when only a few are occupied.
4. **Tightly coupled to booking mode** — the API response shape changes depending on `fixed_duration` / `variable_duration` / `predefined_blocks`, forcing the frontend to handle three different structures.
5. **Overlapping slots are confusing** — 08:00-09:00 and 08:30-09:30 both appear, but a single reservation marks multiple slots as occupied.

---

## The Solution

Flip the model from **"here are all possible slots and their status"** to **"here are the events that actually exist."** The public calendar becomes an event-based display (like Google Calendar), not a slot picker.

### Core Principle

The API should only return data that carries information: existing reservations and blackouts. Free time is implicit — if nothing is booked, the time is available. The frontend renders a month-grid calendar and places events on the days where they exist.

---

## New Architecture

### Frontend: FullCalendar

Replace the custom Vue slot-grid with [FullCalendar](https://fullcalendar.io/docs/vue) (`@fullcalendar/vue3`).

**Packages to install:**

- `@fullcalendar/core` — engine
- `@fullcalendar/vue3` — Vue 3 component
- `@fullcalendar/daygrid` — month grid view
- `@fullcalendar/interaction` — click/select handling

**Why FullCalendar:**

- Battle-tested month/week/day grid views out of the box
- Built-in event source system — automatically calls the API with `start`/`end` params when the user navigates months
- Handles event rendering, overlapping events, multi-day events, background events (for blackouts)
- Locale support (Spanish) built in
- Customizable event display via CSS / render hooks
- No need to build month navigation, day grid layout, or event placement from scratch

**How it works with our API:**

FullCalendar's [event source](https://fullcalendar.io/docs/events-json-feed) system makes a GET request to our API endpoint whenever the visible date range changes (e.g., user navigates to March). It sends `start` and `end` query parameters automatically. The API returns an array of event objects in FullCalendar's format, and they're rendered on the grid.

**User interaction:**

- Month view shows events as colored bars on each day
- Blackouts render as background events (shaded full days or partial ranges)
- Clicking a day could navigate to the booking form (future enhancement)

---

### Backend: Lean Event-Based API

#### New Endpoint

Refactor `PublicAvailabilityController` (or create a new one) to return events in FullCalendar's expected format:

```
GET /api/public/availability?start=2026-02-01&end=2026-03-01
```

**Response:**

```json
[
  {
    "title": "Ocupado",
    "start": "2026-02-05T10:00:00-05:00",
    "end": "2026-02-05T11:33:00-05:00",
    "color": "#f59e0b",
    "extendedProps": { "type": "reservation" }
  },
  {
    "title": "Bloqueado - Feriado",
    "start": "2026-02-10",
    "end": "2026-02-11",
    "display": "background",
    "color": "#64748b",
    "extendedProps": { "type": "blackout" }
  }
]
```

This is just a direct query of the `reservations` and `blackouts` tables for the given date range, mapped to FullCalendar event objects. No slot generation.

**Privacy:** The public endpoint does not expose who made the reservation or any personal data. Events just show "Ocupado" (occupied) or "Bloqueado" (blocked).

#### What Gets Removed from `AvailabilityService`

The following methods become unnecessary for the calendar:

- `buildFixedDurationSlots()`
- `buildPredefinedBlocks()`
- `buildVariableStartTimes()`
- `buildOptionsForDay()`
- `overlapsAny()` (the PHP-side slot overlap loop)

The `availabilityForRange()` method gets simplified to: query reservations + blackouts, format as events.

> **Note:** If the existing slot-generation logic is used by other parts of the app (e.g., the student booking form's "Create" page), it can be kept as a separate method or moved to the booking flow. The calendar no longer needs it.

---

### Backend Validation: `spatie/period`

Install [`spatie/period`](https://github.com/spatie/period) to replace the hand-rolled date overlap checks in `ReservationRulesService`.

**What it provides:**

- `Period::make($start, $end, Precision::MINUTE())` — creates a period object
- `$requested->overlapsWith($existing)` — clean overlap detection
- `$openingPeriod->contains($requested)` — check if a booking falls within opening hours
- `PeriodCollection::make(...)->gaps()` — find free time ranges (useful if needed later)
- Configurable boundary handling — whether touching periods (10:00-11:00 and 11:00-12:00) should count as overlapping

**Where it replaces current code:**

| Current code (ReservationRulesService) | With spatie/period |
|---|---|
| Manual `$start < $end` comparisons in `validateOpeningHours()` | `$openingPeriod->contains($requestedPeriod)` |
| Manual SQL overlap check in `validateBlackouts()` | Query blackouts, then `$requested->overlapsWith($blackoutPeriod)` |
| Manual SQL overlap check in `validateConflicts()` | Query reservations, then `$requested->overlapsWith($reservationPeriod)` |
| `overlapsAny()` loop in `AvailabilityService` | No longer needed for calendar (and `overlapsWith` for booking) |

The SQL-level filtering (`WHERE starts_at < ? AND ends_at > ?`) should still be used to narrow down candidates from the database — `spatie/period` then handles the precise comparison in PHP. This is especially important for edge cases like boundary precision.

---

## What Changes, What Stays

### Changes

| Component | Before | After |
|---|---|---|
| `Public.vue` | Custom slot-grid with vue-query | FullCalendar month view with built-in event fetching |
| `PublicAvailabilityController` | Returns all-slots-with-status payload | Returns FullCalendar event array (reservations + blackouts) |
| `AvailabilityService` | Generates slots, checks overlaps per slot | Simple query + format (for calendar). Slot logic removed or isolated to booking flow only. |
| `ReservationRulesService` | Hand-rolled Carbon comparisons for overlap/containment | Uses `spatie/period` for period comparisons |
| Dependencies | None for dates | `spatie/period` (composer), `@fullcalendar/*` (npm) |

### Stays the Same

- **`reservations` table schema** — no changes
- **`blackouts` table schema** — no changes
- **`settings` table and `SettingsService`** — no changes
- **`ReservationService`** (create, cancel, approve, reject) — no changes to business logic
- **`StoreReservationRequest`** — no changes
- **All admin pages** — no changes
- **Route structure** — the public calendar route stays at `/calendario`, the API stays at `/api/public/availability`
- **Opening hours, booking mode, quotas, lead times** — all settings remain, enforced at booking time

---

## Data Flow: Before and After

### Before

```
User picks date range
  -> GET /api/public/availability?from=2026-02-21&to=2026-02-27
  -> Server generates 27 slots/day x 7 days = 189 objects
  -> Checks each slot against reservations + blackouts
  -> Returns massive JSON with mostly "free" slots
  -> Frontend renders flat list of slots per day
```

### After

```
FullCalendar renders February 2026
  -> GET /api/public/availability?start=2026-02-01&end=2026-03-01
  -> Server queries reservations (pending+approved) and blackouts in range
  -> Returns only the events that exist (e.g., 3 reservations + 1 blackout = 4 objects)
  -> FullCalendar places them on the month grid
```

---

## Implementation Steps (High Level)

1. **Install dependencies**
   - `composer require spatie/period`
   - `npm install @fullcalendar/core @fullcalendar/vue3 @fullcalendar/daygrid @fullcalendar/interaction`

2. **Refactor the API endpoint**
   - Update `PublicAvailabilityController` to return FullCalendar-formatted events
   - Simplify `AvailabilityService` — new method that queries reservations + blackouts and maps to event objects
   - Keep `start`/`end` query params (FullCalendar sends these automatically)

3. **Rebuild the frontend**
   - Replace `Public.vue` content with FullCalendar component
   - Configure: month view, Spanish locale, event colors, click handlers
   - Remove vue-query usage for this page (FullCalendar handles fetching)

4. **Refactor validation with spatie/period**
   - Update `ReservationRulesService` to use `Period` objects for overlap and containment checks
   - Keep SQL-level pre-filtering for performance, use `spatie/period` for precise comparison

5. **Clean up dead code**
   - Remove slot-generation methods from `AvailabilityService` if no other consumer exists
   - Remove old TypeScript types (`Slot`, `StartTime`, etc.) from `Public.vue`

6. **Tests**
   - Update existing tests for the availability endpoint to match new response format
   - Update/add tests for `ReservationRulesService` with `spatie/period`
   - Test FullCalendar integration (event source URL, correct params)

---

## Open Questions for Implementation

- **Should the booking form also use FullCalendar?** Or keep it as a separate, simpler form where the user picks date + time directly? (Recommendation: keep booking form separate — the calendar is for viewing, the form is for creating.)
- **Day click behavior:** Should clicking a day on the public calendar navigate to the booking form with that date pre-filled? Or just show a detail popover?
- **Event detail on hover/click:** Should clicking an "Ocupado" event show any detail (e.g., time range), or just the color bar?
