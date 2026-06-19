<?php

use App\Support\CityResolver;

it('resolves coordinates to the nearest known city', function () {
    $resolver = new CityResolver;

    expect($resolver->resolve(52.52, 13.405)['city'])->toBe('Berlin');
    expect($resolver->resolve(40.71, -74.0)['city'])->toBe('New York');
    expect($resolver->resolve(35.68, 139.65))->toMatchArray([
        'city' => 'Tokyo',
        'country' => 'Japan',
        'timezone' => 'Asia/Tokyo',
    ]);
});

it('exposes the full city list for facets', function () {
    $cities = CityResolver::all();

    expect($cities)->not->toBeEmpty();
    expect($cities[0])->toHaveKeys(['city', 'country', 'lat', 'lng']);
});
