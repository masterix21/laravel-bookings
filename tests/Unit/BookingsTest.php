<?php

use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

it('generates different checksums for different periods', function () {
    $resource = BookableResource::factory()->create();

    $firstChecksum = app('bookings')->checksum(
        $resource,
        PeriodCollection::make(Period::make('2026-01-01', '2026-01-05'))
    );

    $secondChecksum = app('bookings')->checksum(
        $resource,
        PeriodCollection::make(Period::make('2026-03-10', '2026-03-20'))
    );

    expect($firstChecksum)->not->toBe($secondChecksum);
});

it('generates the same checksum for the same periods', function () {
    $resource = BookableResource::factory()->create();

    $periods = fn () => PeriodCollection::make(
        Period::make('2026-01-01', '2026-01-05'),
        Period::make('2026-02-01', '2026-02-05'),
    );

    expect(app('bookings')->checksum($resource, $periods()))
        ->toBe(app('bookings')->checksum($resource, $periods()));
});

it('generates the same checksum regardless of period order', function () {
    $resource = BookableResource::factory()->create();

    $ordered = PeriodCollection::make(
        Period::make('2026-01-01', '2026-01-05'),
        Period::make('2026-02-01', '2026-02-05'),
    );

    $reversed = PeriodCollection::make(
        Period::make('2026-02-01', '2026-02-05'),
        Period::make('2026-01-01', '2026-01-05'),
    );

    expect(app('bookings')->checksum($resource, $ordered))
        ->toBe(app('bookings')->checksum($resource, $reversed));
});

it('generates different checksums for different resources', function () {
    $firstResource = BookableResource::factory()->create();
    $secondResource = BookableResource::factory()->create();

    $periods = PeriodCollection::make(Period::make('2026-01-01', '2026-01-05'));

    expect(app('bookings')->checksum($firstResource, $periods))
        ->not->toBe(app('bookings')->checksum($secondResource, $periods));
});
