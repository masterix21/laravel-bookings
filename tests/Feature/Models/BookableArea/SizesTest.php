<?php

use Masterix21\Bookings\Exceptions\RelationsHaveNoFreeSizeException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Period;
use Masterix21\Bookings\Tests\Concerns\CreatesAreasAndResources;
use Masterix21\Bookings\Tests\TestClasses\User;
use Spatie\Period\Period as SpatiePeriod;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

uses(CreatesAreasAndResources::class);

it('works because has bookable area relations with free size', function () {
    $this->createsAreasAndResources();

    $mainBookableArea = BookableArea::first();

    $bookableArea = BookableArea::factory()
        ->state(['is_bookable' => true])
        ->has(BookableResource::factory()->count(1)->state(['is_bookable' => true]))
        ->count(1)
        ->create()
        ->first();

    $dates = Period::toDates(
        SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d')
        )
    );

    try {
        $mainBookableArea->ensureHasFreeSize(
            dates: $dates,
            relations: collect([$bookableArea])
        );

        expect(true)->toBeTrue();
    } catch (Exception $e) {
        $this->fail($e->getMessage());
    }
});

it('throws exception because bookable area has no free size', function () {
    $this->createsAreasAndResources();

    $mainBookableArea = BookableArea::first();

    $bookableArea = BookableArea::factory()
        ->state(['is_bookable' => true])
        ->has(BookableResource::factory()->count(1)->state(['is_bookable' => true]))
        ->count(1)
        ->create()
        ->first();

    BookableRelation::factory()
        ->state([
            'parent_bookable_area_id' => $mainBookableArea->id,
            'parent_bookable_resource_id' => $mainBookableArea->bookableResources()->first()->id,
            'bookable_area_id' => $bookableArea->id,
            'bookable_resource_id' => $bookableArea->bookableResources()->first()->id,
        ])
        ->count(1)
        ->create();

    $user = User::factory()->count(1)->create()->first();

    $bookingPeriods = new PeriodCollection(
        SpatiePeriod::make(
            now()->subWeek()->startOf('week'),
            now()->subWeek()->endOf('week'),
            Precision::SECOND()
        )
    );

    $booking = $mainBookableArea->bookableResources()->first()->reserve(
        periods: $bookingPeriods,
        user: $user,
        relations: BookableRelation::get()
    );

    expect($booking::class)->toEqual(Booking::class);

    $dates = Period::toDates(
        SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d')
        )
    );

    expect(fn () => $mainBookableArea->ensureHasFreeSize(
        dates: $dates,
        relations: collect([$bookableArea])
    ))->toThrow(RelationsHaveNoFreeSizeException::class);
});

it('works because has bookable resources relations with free size', function () {
    $this->createsAreasAndResources();

    $mainBookableArea = BookableArea::first();

    $bookableArea = BookableArea::factory()
        ->state(['is_bookable' => true])
        ->has(BookableResource::factory()->count(1)->state(['is_bookable' => true]))
        ->count(1)
        ->create()
        ->first();

    $dates = Period::toDates(
        SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d')
        )
    );

    $bookableResource = $bookableArea->bookableResources()->first();

    try {
        $mainBookableArea->ensureHasFreeSize(
            dates: $dates,
            relations: collect([$bookableResource])
        );

        expect(true)->toBeTrue();
    } catch (Exception $e) {
        $this->fail($e->getMessage());
    }
});
