<?php

use App\Models\Resource;

test('the default court resource exists', function () {
    expect(Resource::query()->count())->toBe(1);

    $resource = Resource::query()->firstOrFail();
    expect($resource->name)->toBe('Cancha 1');
    expect($resource->kind)->toBe('court');
    expect($resource->is_active)->toBeTrue();
});
