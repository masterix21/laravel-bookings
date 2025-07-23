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

it('throws exception when relations have no plannings', function () {
    // Create a resource without plannings
    $resourceWithoutPlannings = BookableResource::factory()->create();

    // Create dates for future period
    $dates = Period::toDates(
        SpatiePeriod::make(
            now()->addMonth()->startOf('week')->format('Y-m-d'),
            now()->addMonth()->endOf('week')->format('Y-m-d')
        )
    );

    // With improved business logic, resources without plannings should fail validation
    expect(fn () => $resourceWithoutPlannings->ensureRelationsHaveValidPlannings(
        dates: $dates,
        relations: collect([$resourceWithoutPlannings])
    ))->toThrow(RelationsOutOfPlanningsException::class);
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

it('throws exception when mixed relations have some without valid plannings', function () {
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

    // With improved business logic, ALL resources must have valid plannings
    expect(fn () => $resourceWithPlannings->ensureRelationsHaveValidPlannings(
        dates: $dates,
        relations: collect([$resourceWithPlannings, $resourceWithoutPlannings])
    ))->toThrow(RelationsOutOfPlanningsException::class);
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

it('handles resources that dont have plannings by throwing exception', function () {
    // Create a resource without any plannings
    $resourceWithoutPlannings = BookableResource::factory()->create();

    // Create dates
    $dates = collect([now()]);

    // With improved business logic, resources without plannings should fail validation
    expect(fn () => $resourceWithoutPlannings->ensureRelationsHaveValidPlannings(
        dates: $dates,
        relations: collect([$resourceWithoutPlannings])
    ))->toThrow(RelationsOutOfPlanningsException::class);
});

it('succeeds when all relations have valid plannings', function () {
    // Create two resources both with valid plannings for the same period
    $resource1 = BookableResource::factory()
        ->has(
            BookablePlanning::factory()->state([
                'starts_at' => now()->subWeek()->startOf('week'),
                'ends_at' => now()->subWeek()->endOf('week'),
            ])
        )
        ->create();

    $resource2 = BookableResource::factory()
        ->has(
            BookablePlanning::factory()->state([
                'starts_at' => now()->subWeek()->startOf('week'),
                'ends_at' => now()->subWeek()->endOf('week'),
            ])
        )
        ->create();

    // Create dates that match both planning periods
    $dates = Period::toDates(
        SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d')
        )
    );

    // This should succeed because ALL resources have valid plannings
    $resource1->ensureRelationsHaveValidPlannings(
        dates: $dates,
        relations: collect([$resource1, $resource2])
    );

    expect(true)->toBeTrue();
});

it('throws exception when no BookableResource instances found after filtering', function () {
    // Create a mock object that isn't a BookableResource but will cause the query to return 0 results
    $resource = BookableResource::factory()->create();
    
    // Delete the resource after creating it so the ID won't be found in the database
    $deletedResourceId = $resource->id;
    $resource->delete();
    
    // Create a new resource with the deleted ID to simulate the query finding no resources
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

it('validates input parameters and throws exception for empty dates', function () {
    $resource = BookableResource::factory()->create();

    expect(fn () => $resource->validatePlanningAvailability(collect()))
        ->toThrow(InvalidArgumentException::class, 'Dates collection cannot be empty');
});

it('validates input parameters and throws exception for non-model relations', function () {
    $resource = BookableResource::factory()->create();
    $dates = collect([now()]);
    $invalidRelation = new stdClass();

    expect(fn () => $resource->validatePlanningAvailability($dates, collect([$invalidRelation])))
        ->toThrow(InvalidArgumentException::class, 'All relation items must be Model instances');
});

it('converts non-Carbon dates to Carbon instances automatically', function () {
    $this->createsResources();
    
    $bookableResource = BookableResource::first();
    
    // Use string dates that match the planning period (last week)
    $dates = collect([
        now()->subWeek()->startOf('week')->format('Y-m-d'),
        now()->subWeek()->startOf('week')->addDay()->format('Y-m-d')
    ]);
    
    // This should work because the method converts strings to Carbon instances
    $bookableResource->validatePlanningAvailability($dates);
    
    expect(true)->toBeTrue();
});

it('handles batch processing for large collections', function () {
    // Create many resources with plannings
    $resources = BookableResource::factory()->count(150)
        ->has(
            BookablePlanning::factory()->state([
                'starts_at' => now()->subWeek()->startOf('week'),
                'ends_at' => now()->subWeek()->endOf('week'),
            ])
        )
        ->create();

    $dates = Period::toDates(
        SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d')
        )
    );

    // This should work with batching since we have more than 100 resources
    $resources->first()->validatePlanningAvailability($dates, $resources);

    expect(true)->toBeTrue();
});

it('provides detailed exception messages with resource IDs and dates', function () {
    // Create one resource without plannings
    $resourceWithoutPlannings = BookableResource::factory()->create();
    $dates = collect([now()]);

    try {
        $resourceWithoutPlannings->validateRelationsPlanningAvailability($dates, collect([$resourceWithoutPlannings]));
        expect(false)->toBeTrue(); // Should not reach here
    } catch (RelationsOutOfPlanningsException $e) {
        expect($e->getMessage())
            ->toContain("Resources with IDs [{$resourceWithoutPlannings->id}]")
            ->toContain('do not have valid plannings for the requested dates');
    }
});
