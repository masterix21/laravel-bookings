<?php

use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Period;
use Masterix21\Bookings\Tests\Concerns\CreatesResources;
use Spatie\Period\Period as SpatiePeriod;

uses(CreatesResources::class);

it('throws an exception because it has no plannings', function () {
    BookableResource::factory()->count(1)->create();

    $bookableResource = BookableResource::first();

    expect(fn () => $bookableResource->ensureHasValidPlannings(dates: collect([now()])))
        ->toThrow(OutOfPlanningsException::class);
});

it('works because it has a valid planning', function () {
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
