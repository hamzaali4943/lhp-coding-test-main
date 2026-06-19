# Event Visuals — Implementation Notes

A short write-up of the decisions behind this build. The brief: two distinct event-browsing
pages, local images, human-readable addresses, sensible timezones, date + location filtering,
attendee registration, and confirmation + reminder emails — all against the seeded **1.25M-row**
dataset.

## The two visualisations

- **Visual 1 — card grid** (`resources/js/pages/Events/VisualOne.vue`): an image-forward gallery
  with hover-zoom, status/countdown badges, multi-image dots, and infinite scroll. Filter by
  location (search + city select), date range, type and status.
- **Visual 2 — interactive map** (`resources/js/pages/Events/VisualTwo.vue`): a Leaflet map with
  one circle-marker per city, sized/coloured by event count. Click a city to open a side panel
  listing its events (paginated). Filter by date + type/status.

They share data plumbing (`useEventFeed`, `useEventTime`, `EventCard`) but are deliberately
different layouts — a browse-everything grid vs. a where-is-it map.

Leaflet's JS/CSS are bundled locally (Vite); only the OpenStreetMap raster tiles are fetched
externally. The "serve locally" rule is written about **images**, which are fully local (below).

## Key decisions

### Addresses — offline reverse geocoding
The seeder scatters events within ±0.5° of ~80 known city anchors. Rather than call a geocoding
API 1.25M times, `App\Support\CityResolver` snaps each coordinate to its nearest anchor (which
carries a city, country and IANA timezone). Resolved values are **persisted** to indexed
`city` / `country` / `timezone` columns via a one-off command (`events:backfill-locations`), so
filtering and the map aggregate stay index-only. Fully offline, deterministic, fast.

### Timezones — venue-local time
Events are global. `created_time` is a UTC unix timestamp; each event carries its venue IANA
timezone. The UI always renders the **wall-clock time at the venue** (`Intl` + the stored
timezone) with a short tz label — the most meaningful answer to "when is this event, there",
independent of where the viewer sits.

### Images — local, deterministic
Events have no real images, so `public/images/events/{category}/{1..3}.svg` holds a small pool of
locally-served category placeholders. `App\Support\EventImages` assigns each event 3 of them,
deterministically from its id (stable across reloads, reuses files, no external URLs). Exposed via
the `images` accessor on `Event`.

### Performance — built for 1.25M rows
- **Grid**: keyset-friendly `where(created_time) order by created_time limit 24`, infinite scroll.
  Defaults to *upcoming* events. ~20ms/page.
- **Map**: a single `GROUP BY city` covered by a composite index
  `(city, created_time, status, type)` — the aggregate is **index-only** (never reads the 2.5 GB of
  row data / `payload`), and groups stream in city order. ~60ms for 626k upcoming events.
  Country/coordinates come from the resolver, so the query selects only `city, COUNT(*)`.
- All filter columns are indexed; the free-text location search uses a prefix `LIKE` so the index
  still applies.

### Attendees & emails
- `attendees` table with a unique `(event_id, email)` and per-attendee
  `reminder_3d_sent_at` / `reminder_24h_sent_at` stamps.
- Registering (`POST /events/{event}/attendees`) is validated, idempotent, and queues an
  `AttendeeConfirmationMail`. The UI confirms via a flash toast.
- `events:send-reminders` (scheduled hourly in `routes/console.php`) queues reminders for events
  starting in the 3-day (24–72h out) and 24-hour (0–24h out) windows, then stamps each attendee so
  re-runs never duplicate. The windows are non-overlapping, so the last day only sends the 24h
  reminder. Mailables are queued (`ShouldQueue`); delivery uses the `log` mailer by default.

## Running it

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed            # seeds 1.25M events (~25s); SEED_ROWS=50000 for a small set
php artisan events:backfill-locations # resolve city/country/timezone (one-off)
composer run dev                      # serve + queue:listen + vite  (queue worker is needed for emails)
```

Emails are written to `storage/logs/laravel.log` (log mailer). To deliver reminders on a real
schedule, run `php artisan schedule:work` alongside a queue worker.

## Tests & quality

`composer ci:check` is green: ESLint, Prettier, `vue-tsc`, Pint, PHPStan (level 7, 0 errors), and
the Pest suite. New coverage: `CityResolverTest`, `EventVisualsTest` (grid/map filtering + shape),
`AttendeeRegistrationTest`, `EventReminderTest` (windows + no duplicates).

A few small pre-existing items were tidied along the way: a broken filter handler in the original
`Events/Index.vue` (`aplyFilters` typo), and PHPStan level-7 type issues in the provided
seeder/factory. PHPStan's `types:check` script was given `--memory-limit=512M` (larastan needs
more than the 128M default).
