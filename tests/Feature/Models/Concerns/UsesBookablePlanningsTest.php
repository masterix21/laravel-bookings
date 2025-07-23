<?php

use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
use Masterix21\Bookings\Exceptions\RelationsOutOfPlanningsException;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Period;
use Masterix21\Bookings\Tests\Concerns\CreatesResources;
use Spatie\Period\Period as SpatiePeriod;

uses(CreatesResources::class);

it('has bookable plannings relationship', function () {
    $this->createsResources();

    $bookableResource = BookableResource::first();

    expect($bookableResource->bookablePlannings()->count())->toBe(1)
        ->and($bookableResource->bookablePlannings()->first())->toBeInstanceOf(BookablePlanning::class);
});

it('throws an exception when no valid plannings exist', function () {
    BookableResource::factory()->count(1)->create();

    $bookableResource = BookableResource::first();
    $dates = collect([now()]);

    expect(fn () => $bookableResource->ensureHasValidPlannings(dates: $dates))
        ->toThrow(OutOfPlanningsException::class);
});

it('validates plannings successfully when valid plannings exist', function () {
    $this->createsResources();

    $bookableResource = BookableResource::first();

    $dates = Period::toDates(
        SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d')
        )
    );

    $bookableResource->ensureHasValidPlannings(dates: $dates);

    expect(true)->toBeTrue();
});

it('handles empty relations in ensureRelationsHaveValidPlannings', function () {
    $this->createsResources();

    $bookableResource = BookableResource::first();
    $dates = collect([now()]);

    // This should not throw an exception when relations is empty
    $bookableResource->ensureRelationsHaveValidPlannings(dates: $dates, relations: collect());

    expect(true)->toBeTrue();
});

it('succeeds when relations have no plannings due to whereDoesntHave logic', function () {
    // Create a resource without plannings
    $resourceWithoutPlannings = BookableResource::factory()->create();

    // Create dates for future period
    $dates = Period::toDates(
        SpatiePeriod::make(
            now()->addMonth()->startOf('week')->format('Y-m-d'),
            now()->addMonth()->endOf('week')->format('Y-m-d')
        )
    );

    // This tests lines 56-67 in UsesBookablePlannings.php
    // Resources without plannings will match the whereDoesntHave('bookablePlannings') condition
    $resourceWithoutPlannings->ensureRelationsHaveValidPlannings(
        dates: $dates,
        relations: collect([$resourceWithoutPlannings])
    );

    expect(true)->toBeTrue();
});

it('succeeds when relations have valid plannings through direct bookablePlannings', function () {
    // Create a resource with valid plannings
    $resourceWithPlannings = BookableResource::factory()
        ->has(
            BookablePlanning::factory()->state([
                'starts_at' => now()->subWeek()->startOf('week'),
                'ends_at' => now()->subWeek()->endOf('week'),
            ])
        )
        ->create();

    // Create dates that match the planning period
    $dates = Period::toDates(
        SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d')
        )
    );

    // This should not throw an exception - tests the success path in lines 56-78
    $resourceWithPlannings->ensureRelationsHaveValidPlannings(
        dates: $dates,
        relations: collect([$resourceWithPlannings])
    );

    expect(true)->toBeTrue();
});

it('handles mixed relations with some having valid plannings', function () {
    // Create one resource with plannings
    $resourceWithPlannings = BookableResource::factory()
        ->has(
            BookablePlanning::factory()->state([
                'starts_at' => now()->subWeek()->startOf('week'),
                'ends_at' => now()->subWeek()->endOf('week'),
            ])
        )
        ->create();

    // Create one resource without plannings
    $resourceWithoutPlannings = BookableResource::factory()->create();

    // Create dates that match the planning period
    $dates = Period::toDates(
        SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d')
        )
    );

    // This should succeed because at least one resource has valid plannings
    $resourceWithPlannings->ensureRelationsHaveValidPlannings(
        dates: $dates,
        relations: collect([$resourceWithPlannings, $resourceWithoutPlannings])
    );

    expect(true)->toBeTrue();
});

it('filters non-BookableResource objects from relations', function () {
    // Create a mock object that isn't a BookableResource
    $nonBookableResource = new stdClass();
    $nonBookableResource->id = 999;

    // Create dates
    $dates = collect([now()]);

    // Create a resource to call the method on
    $resource = BookableResource::factory()->create();

    // This should not throw an exception because non-BookableResource objects are filtered out
    // This tests the filter logic in line 58: $relations->filter(fn ($bookable) => $bookable instanceof BookableResource)
    $resource->ensureRelationsHaveValidPlannings(
        dates: $dates,
        relations: collect([$nonBookableResource])
    );

    expect(true)->toBeTrue();
});

it('handles resources that dont have plannings but match through whereDoesntHave', function () {
    // Create a resource without any plannings
    $resourceWithoutPlannings = BookableResource::factory()->create();

    // Create dates
    $dates = collect([now()]);

    // This tests the whereDoesntHave logic in lines 64-65
    // A resource without plannings should still pass validation in some scenarios
    $resourceWithoutPlannings->ensureRelationsHaveValidPlannings(
        dates: $dates,
        relations: collect([$resourceWithoutPlannings])
    );

    expect(true)->toBeTrue();
});

it('throws exception when no BookableResource instances found after filtering', function () {
    // Create a mock object that isn't a BookableResource but will cause the query to return 0 results
    $resource = BookableResource::factory()->create();
    
    // Delete the resource after creating it so the ID won't be found in the database
    $deletedResourceId = $resource->id;
    $resource->delete();
    
    // Create a new resource with the deleted ID to simulate the query finding no results
    $mockResource = new BookableResource();
    $mockResource->id = $deletedResourceId;
    
    // Create dates
    $dates = collect([now()]);

    // This should throw an exception because the query will find no matching resources
    expect(fn () => $resource->ensureRelationsHaveValidPlannings(
        dates: $dates,
        relations: collect([$mockResource])
    ))->toThrow(RelationsOutOfPlanningsException::class);
});
