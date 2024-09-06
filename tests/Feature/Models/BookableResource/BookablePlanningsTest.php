<?php

use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Period;
use Spatie\Period\Period as SpatiePeriod;
use Masterix21\Bookings\Tests\Concerns\CreatesAreasAndResources;

uses(CreatesAreasAndResources::class);

it('throws an exception because it has no plannings', function () {
    BookableArea::factory()->count(1)
        ->has(BookableResource::factory()->count(1))
        ->create();

    $bookableResource = BookableResource::first();

    expect(fn() => $bookableResource->ensureHasValidPlannings(dates: collect([now()])))
        ->toThrow(OutOfPlanningsException::class);
});

it('works because it has a valid planning', function () {
    $this->createsAreasAndResources();

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
