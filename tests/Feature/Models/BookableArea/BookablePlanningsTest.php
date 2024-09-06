<?php

use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
use Masterix21\Bookings\Exceptions\RelationsOutOfPlanningsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Period;
use Spatie\Period\Period as SpatiePeriod;
use Masterix21\Bookings\Tests\Concerns\CreatesAreasAndResources;

uses(CreatesAreasAndResources::class);

it('throws an exception because it has no plannings', function () {
    $bookableArea = BookableArea::factory()->count(1)
        ->has(BookableResource::factory()->count(1))
        ->create()
        ->first();

    expect(fn() => $bookableArea->ensureHasValidPlannings(dates: collect([now()])))
        ->toThrow(OutOfPlanningsException::class);
});

it('works because it has a valid planning', function () {
    $this->createsAreasAndResources();

    $bookableArea = BookableArea::first();

    $dates = Period::toDates(SpatiePeriod::make(
        now()->subWeek()->startOf('week')->format('Y-m-d'),
        now()->subWeek()->endOf('week')->format('Y-m-d'),
    ));

    try {
        $bookableArea->ensureHasValidPlannings(dates: $dates);
    } catch (Exception $e) {
        $this->fail($e->getMessage());
    }

    expect(true)->toBeTrue();
});

it('throws an exception because it has relations with invalid planning', function () {
    $this->createsAreasAndResources();

    $mainBookableArea = BookableArea::first();

    $bookableArea = BookableArea::factory()
        ->has(BookableResource::factory()->count(1))
        ->count(1)
        ->create()
        ->first();

    $bookableResource = $bookableArea->bookableResources()->first();

    BookablePlanning::factory()
        ->state([
            'bookable_area_id' => $bookableArea->id,
            'bookable_resource_id' => $bookableResource->id,
            'from_date' => now()->subYear()->format('Y-m-d'),
            'to_date' => now()->subYear()->format('Y-m-d'),
            'from_time' => '08:00:00',
            'to_time' => '08:59:59',
        ])
        ->create();

    BookableRelation::factory()
        ->state([
            'parent_bookable_area_id' => $mainBookableArea->id,
            'bookable_area_id' => $bookableArea->id,
        ])
        ->create();

    $dates = Period::toDates(SpatiePeriod::make(
        now()->subWeek()->startOf('week')->format('Y-m-d'),
        now()->subWeek()->endOf('week')->format('Y-m-d'),
    ));

    expect(fn() => $mainBookableArea->ensureHasValidPlannings(dates: $dates, relations: collect([$bookableArea])))
        ->toThrow(RelationsOutOfPlanningsException::class);

    expect(fn() => $mainBookableArea->ensureHasValidPlannings(dates: $dates, relations: $bookableArea->bookableResources))
        ->toThrow(RelationsOutOfPlanningsException::class);
});

it('works because it has relations with valid planning', function () {
    $this->createsAreasAndResources();

    $mainBookableArea = BookableArea::first();

    $bookableArea = BookableArea::factory()
        ->has(BookableResource::factory()->count(1))
        ->count(1)
        ->create()
        ->first();

    $bookableResource = $bookableArea->bookableResources()->first();

    BookablePlanning::factory()
        ->state([
            'bookable_area_id' => $bookableArea->id,
            'bookable_resource_id' => $bookableResource->id,
            'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
            'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
            'from_time' => '00:00:00',
            'to_time' => '23:59:59',
        ])
        ->create();

    BookableRelation::factory()
        ->state([
            'parent_bookable_area_id' => $mainBookableArea->id,
            'bookable_area_id' => $bookableArea->id,
        ])
        ->create();

    $dates = Period::toDates(SpatiePeriod::make(
        now()->subWeek()->startOf('week')->format('Y-m-d'),
        now()->subWeek()->endOf('week')->format('Y-m-d'),
    ));

    try {
        $mainBookableArea->ensureHasValidPlannings(dates: $dates, relations: collect([$bookableArea]));
        $mainBookableArea->ensureHasValidPlannings(dates: $dates, relations: collect([$bookableResource]));
    } catch (Exception $e) {
        $this->fail($e->getMessage());
    }

    expect(true)->toBeTrue();
});
