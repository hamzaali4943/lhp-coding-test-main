<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the visual pages with facets', function () {
    $this->get(route('events.visual1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Events/VisualOne')->has('facets.cities')->has('facets.types'));

    $this->get(route('events.visual2'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Events/VisualTwo')->has('facets.cities'));
});

it('returns enriched card data and filters by city', function () {
    Event::factory()->create([
        'city' => 'Berlin', 'country' => 'Germany',
        'created_time' => now()->addDays(5)->timestamp, 'status' => 'published',
    ]);
    Event::factory()->create([
        'city' => 'Paris', 'country' => 'France',
        'created_time' => now()->addDays(5)->timestamp,
    ]);

    $this->getJson(route('events.visual1.data', ['city' => 'Berlin']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.city', 'Berlin')
        ->assertJsonPath('data.0.address', 'Berlin, Germany')
        ->assertJsonStructure(['data' => [['id', 'name', 'images', 'starts_at_iso', 'attendees_count']]]);
});

it('searches by location term across city and country', function () {
    Event::factory()->create(['city' => 'Tokyo', 'country' => 'Japan', 'created_time' => now()->addDays(5)->timestamp]);
    Event::factory()->create(['city' => 'Berlin', 'country' => 'Germany', 'created_time' => now()->addDays(5)->timestamp]);

    // City term.
    $this->getJson(route('events.visual1.data', ['q' => 'Tok']))
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.city', 'Tokyo');

    // Country term.
    $this->getJson(route('events.visual1.data', ['q' => 'Germany']))
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.city', 'Berlin');

    // No match yields no rows.
    $this->getJson(route('events.visual1.data', ['q' => 'zzzzz']))
        ->assertOk()->assertJsonPath('total', 0);
});

it('excludes past events from the default upcoming feed', function () {
    Event::factory()->create(['created_time' => now()->subDays(10)->timestamp]);
    Event::factory()->create(['created_time' => now()->addDays(10)->timestamp]);

    $this->getJson(route('events.visual1.data'))
        ->assertOk()
        ->assertJsonPath('total', 1);
});

it('filters the feed by a date range', function () {
    Event::factory()->create(['created_time' => now()->addDays(3)->timestamp]);
    Event::factory()->create(['created_time' => now()->addDays(40)->timestamp]);

    $this->getJson(route('events.visual1.data', [
        'from' => now()->toDateString(),
        'to' => now()->addDays(10)->toDateString(),
    ]))->assertOk()->assertJsonPath('total', 1);
});

it('aggregates events by city for the map', function () {
    Event::factory()->count(3)->create([
        'city' => 'Berlin', 'country' => 'Germany',
        'created_time' => now()->addDays(2)->timestamp,
    ]);
    Event::factory()->create([
        'city' => 'Tokyo', 'country' => 'Japan',
        'created_time' => now()->addDays(2)->timestamp,
    ]);

    $response = $this->getJson(route('events.visual2.map'))->assertOk();

    $response->assertJsonPath('total', 4);
    $berlin = collect($response->json('data'))->firstWhere('city', 'Berlin');
    expect($berlin['count'])->toBe(3);
    expect($berlin['lat'])->not->toBeNull();
});
