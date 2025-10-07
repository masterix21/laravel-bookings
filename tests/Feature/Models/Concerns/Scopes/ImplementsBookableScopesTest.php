<?php

use Masterix21\Bookings\Models\BookableResource;

it('filters bookable resources with bookable scope', function () {
    // Create bookable resources with different is_bookable values
    $bookableResource1 = BookableResource::factory()->create([
        'is_bookable' => true,
        'is_visible' => true,
    ]);

    $bookableResource2 = BookableResource::factory()->create([
        'is_bookable' => true,
        'is_visible' => false,
    ]);

    $unbookableResource = BookableResource::factory()->create([
        'is_bookable' => false,
        'is_visible' => true,
    ]);

    // Test bookable scope
    $bookableResults = BookableResource::bookable()->get();

    expect($bookableResults)->toHaveCount(2)
        ->and($bookableResults->pluck('id')->toArray())->toContain($bookableResource1->id, $bookableResource2->id)
        ->and($bookableResults->pluck('id')->toArray())->not->toContain($unbookableResource->id);
});

it('filters unbookable resources with unbookable scope', function () {
    // Create bookable resources with different is_bookable values
    $bookableResource1 = BookableResource::factory()->create([
        'is_bookable' => true,
        'is_visible' => true,
    ]);

    $bookableResource2 = BookableResource::factory()->create([
        'is_bookable' => true,
        'is_visible' => false,
    ]);

    $unbookableResource1 = BookableResource::factory()->create([
        'is_bookable' => false,
        'is_visible' => true,
    ]);

    $unbookableResource2 = BookableResource::factory()->create([
        'is_bookable' => false,
        'is_visible' => false,
    ]);

    // Test unbookable scope
    $unbookableResults = BookableResource::unbookable()->get();

    expect($unbookableResults)->toHaveCount(2)
        ->and($unbookableResults->pluck('id')->toArray())->toContain($unbookableResource1->id, $unbookableResource2->id)
        ->and($unbookableResults->pluck('id')->toArray())->not->toContain($bookableResource1->id, $bookableResource2->id);
});

it('can chain bookable scope with other query methods', function () {
    // Create test data
    $visibleBookable = BookableResource::factory()->create([
        'is_bookable' => true,
        'is_visible' => true,
        'size' => 10,
    ]);

    $hiddenBookable = BookableResource::factory()->create([
        'is_bookable' => true,
        'is_visible' => false,
        'size' => 5,
    ]);

    $visibleUnbookable = BookableResource::factory()->create([
        'is_bookable' => false,
        'is_visible' => true,
        'size' => 20,
    ]);

    // Chain bookable scope with other conditions
    $results = BookableResource::bookable()
        ->where('is_visible', true)
        ->where('size', '>=', 8)
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($visibleBookable->id);
});

it('can chain unbookable scope with other query methods', function () {
    // Create test data
    $visibleBookable = BookableResource::factory()->create([
        'is_bookable' => true,
        'is_visible' => true,
        'size' => 10,
    ]);

    $hiddenUnbookable = BookableResource::factory()->create([
        'is_bookable' => false,
        'is_visible' => false,
        'size' => 5,
    ]);

    $visibleUnbookable = BookableResource::factory()->create([
        'is_bookable' => false,
        'is_visible' => true,
        'size' => 20,
    ]);

    // Chain unbookable scope with other conditions
    $results = BookableResource::unbookable()
        ->where('is_visible', true)
        ->orderBy('size', 'desc')
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($visibleUnbookable->id)
        ->and($results->first()->size)->toBe(20);
});

it('bookable and unbookable scopes are mutually exclusive', function () {
    // Create test data
    BookableResource::factory()->count(3)->create(['is_bookable' => true]);
    BookableResource::factory()->count(2)->create(['is_bookable' => false]);

    $bookableCount = BookableResource::bookable()->count();
    $unbookableCount = BookableResource::unbookable()->count();
    $totalCount = BookableResource::count();

    expect($bookableCount)->toBe(3)
        ->and($unbookableCount)->toBe(2)
        ->and($bookableCount + $unbookableCount)->toBe($totalCount);
});

it('scopes work with empty result sets', function () {
    // Ensure database is clean for this test
    BookableResource::query()->delete();

    $bookableResults = BookableResource::bookable()->get();
    $unbookableResults = BookableResource::unbookable()->get();

    expect($bookableResults)->toHaveCount(0)
        ->and($unbookableResults)->toHaveCount(0);
});

it('scopes work with query builder methods like exists and count', function () {
    // Create test data
    BookableResource::factory()->create(['is_bookable' => true]);
    BookableResource::factory()->create(['is_bookable' => false]);

    // Test exists()
    expect(BookableResource::bookable()->exists())->toBeTrue()
        ->and(BookableResource::unbookable()->exists())->toBeTrue();

    // Test count()
    expect(BookableResource::bookable()->count())->toBe(1)
        ->and(BookableResource::unbookable()->count())->toBe(1);

    // Test first()
    $firstBookable = BookableResource::bookable()->first();
    $firstUnbookable = BookableResource::unbookable()->first();

    expect($firstBookable)->toBeInstanceOf(BookableResource::class)
        ->and($firstBookable->is_bookable)->toBeTrue()
        ->and($firstUnbookable)->toBeInstanceOf(BookableResource::class)
        ->and($firstUnbookable->is_bookable)->toBeFalse();
});

it('scopes work correctly with table prefixes', function () {
    // This test ensures the scope uses $this->getTable() correctly
    $bookableResource = BookableResource::factory()->create(['is_bookable' => true]);
    $unbookableResource = BookableResource::factory()->create(['is_bookable' => false]);

    // The scopes should work even if there are table prefixes or aliases
    $bookableQuery = BookableResource::bookable();
    $unbookableQuery = BookableResource::unbookable();

    // Check that the queries contain the correct table reference
    $bookableSql = $bookableQuery->toSql();
    $unbookableSql = $unbookableQuery->toSql();

    // SQLite quotes the table and column names
    expect($bookableSql)->toContain('"bookable_resources"."is_bookable"')
        ->and($unbookableSql)->toContain('"bookable_resources"."is_bookable"');

    // Verify the queries return correct results
    expect($bookableQuery->count())->toBe(1)
        ->and($unbookableQuery->count())->toBe(1);
});
