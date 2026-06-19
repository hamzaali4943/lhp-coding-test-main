<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\CityResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const TYPES = ['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition'];

    private const STATUSES = ['draft', 'published', 'cancelled', 'sold_out'];

    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'filters' => [
                'status' => $request->status,
                'from' => $request->input('from', '2023-01-01'),
            ],
            'statuses' => self::STATUSES,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$events, $stats] = $this->loadListing($request);

        return response()->json([
            'data' => $events->items(),
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'stats' => $stats,
        ]);
    }

    public function show(Event $event): Response
    {
        $event->load('user')->loadCount('attendees');

        return Inertia::render('Events/Show', [
            'event' => $event,
            'card' => $this->toCard($event),
        ]);
    }

    /**
     * Visual 1 — image-forward card grid. The page renders the filter shell and
     * lazy-loads cards from the JSON feed below.
     */
    public function visualOne(Request $request): Response
    {
        return Inertia::render('Events/VisualOne', [
            'facets' => $this->facets(),
            'filters' => $this->filterState($request),
        ]);
    }

    /** Paginated card feed for Visual 1 (and the Visual 2 city panel). */
    public function gridData(Request $request): JsonResponse
    {
        $start = microtime(true);

        $events = $this->filtered($request)
            ->orderBy('created_time')
            ->paginate(24)
            ->withQueryString();

        $cards = array_map(fn (Event $e) => $this->toCard($e), $events->items());

        return response()->json([
            'data' => $cards,
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'stats' => ['ms' => (int) round((microtime(true) - $start) * 1000)],
        ]);
    }

    /**
     * Visual 2 — interactive map. The page renders the shell; markers and the
     * city event list are fetched from the endpoints below.
     */
    public function visualTwo(Request $request): Response
    {
        return Inertia::render('Events/VisualTwo', [
            'facets' => $this->facets(),
            'filters' => $this->filterState($request),
        ]);
    }

    /**
     * One aggregate row per city (count of matching events), used to draw the
     * map markers. A single GROUP BY keeps this fast over the full dataset.
     */
    public function mapData(Request $request): JsonResponse
    {
        $start = microtime(true);

        // Bare, city-only aggregate. Selecting just city + COUNT keeps the query
        // index-only against events_map_aggregate_index (no row/payload reads);
        // country and coordinates come from the resolver, since each city maps to
        // exactly one of them.
        $rows = $this->applyFilters(Event::query(), $request)
            ->whereNotNull('city')
            ->selectRaw('city, COUNT(*) as count')
            ->groupBy('city')
            ->get();

        $cities = collect(CityResolver::all())->keyBy('city');

        $points = $rows->map(function (Event $row) use ($cities) {
            $city = $cities->get($row->city);

            return [
                'city' => $row->city,
                'country' => $city['country'] ?? null,
                'count' => (int) $row->getAttribute('count'),
                'lat' => $city['lat'] ?? null,
                'lng' => $city['lng'] ?? null,
            ];
        })->filter(fn ($p) => $p['lat'] !== null)->values();

        return response()->json([
            'data' => $points,
            'total' => $points->sum('count'),
            'stats' => ['ms' => (int) round((microtime(true) - $start) * 1000)],
        ]);
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * Original lazy-loading listing used by the plain Events table page.
     *
     * @return array{0: LengthAwarePaginator<int, Event>, 1: array{ms: int, bytes: int}}
     */
    private function loadListing(Request $request): array
    {
        $start = microtime(true);

        $events = Event::with('user')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_time')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'ms' => (int) round((microtime(true) - $start) * 1000),
            'bytes' => strlen((string) json_encode($events->items())),
        ];

        return [$events, $stats];
    }

    /**
     * The card-feed query: the shared filters plus the eager loads the cards
     * need (organiser + attendee count).
     *
     * @return Builder<Event>
     */
    private function filtered(Request $request): Builder
    {
        return $this->applyFilters(
            Event::query()->with('user')->withCount('attendees'),
            $request,
        );
    }

    /**
     * Apply the shared filter set (date range, location, status, type) used by
     * both visualisations. All filtered columns are indexed.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));
        $query->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')));
        $query->when($request->filled('city'), fn ($q) => $q->where('city', $request->string('city')));
        $query->when($request->filled('country'), fn ($q) => $q->where('country', $request->string('country')));

        // Free-text location search. Rather than a LIKE over 1.25M rows (which
        // can't use the indexes and full-scans the table), resolve the term
        // against the known city/country list in memory and filter by an exact,
        // indexed `whereIn(city)`. No match => no results.
        if ($request->filled('q')) {
            $term = mb_strtolower(trim((string) $request->string('q')));
            $cities = collect(CityResolver::all())
                ->filter(fn ($c) => str_contains(mb_strtolower($c['city']), $term)
                    || str_contains(mb_strtolower($c['country']), $term))
                ->pluck('city')
                ->all();

            $query->whereIn('city', $cities);
        }

        // Date range on the event start (created_time holds the unix start).
        [$from, $to] = $this->dateRange($request);
        $query->when($from !== null, fn ($q) => $q->where('created_time', '>=', $from));
        $query->when($to !== null, fn ($q) => $q->where('created_time', '<=', $to));

        return $query;
    }

    /**
     * Resolve the from/to date filters into unix timestamps. Defaults to
     * "upcoming" (from today) so browsing leads with events that haven't passed.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function dateRange(Request $request): array
    {
        $from = $request->filled('from')
            ? (int) Carbon::parse($request->string('from'))->startOfDay()->timestamp
            : (int) Carbon::now()->startOfDay()->timestamp;

        $to = $request->filled('to')
            ? (int) Carbon::parse($request->string('to'))->endOfDay()->timestamp
            : null;

        return [$from, $to];
    }

    /**
     * The currently-applied filter values, echoed back to the page.
     *
     * @return array<string, mixed>
     */
    private function filterState(Request $request): array
    {
        return [
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'city' => $request->input('city'),
            'country' => $request->input('country'),
            'q' => $request->input('q'),
            'status' => $request->input('status'),
            'type' => $request->input('type'),
        ];
    }

    /**
     * Filter option lists for the UI.
     *
     * @return array<string, mixed>
     */
    private function facets(): array
    {
        return [
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
            'cities' => CityResolver::all(),
        ];
    }

    /**
     * Flatten an event into the compact shape the frontend cards consume.
     *
     * @return array<string, mixed>
     */
    private function toCard(Event $event): array
    {
        $payload = $event->payload ?? [];

        return [
            'id' => $event->id,
            'name' => $payload['name'] ?? 'Untitled event',
            'description' => $payload['description'] ?? null,
            'type' => $event->type,
            'status' => $event->status,
            'address' => $event->address,
            'city' => $event->city,
            'country' => $event->country,
            'timezone' => $event->timezone,
            'starts_at_iso' => $event->starts_at_iso,
            'starts_at_unix' => $event->created_time,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'venue' => $payload['venue']['name'] ?? null,
            'price' => $payload['pricing']['min_price'] ?? null,
            'currency' => $payload['pricing']['currency'] ?? 'USD',
            'images' => $event->images,
            'attendees_count' => $event->attendees_count ?? 0,
        ];
    }
}
