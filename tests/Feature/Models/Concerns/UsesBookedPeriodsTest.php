<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Tests\database\factories\BookedPeriodFactory;
use Masterix21\Bookings\Tests\database\factories\BookingFactory;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

uses(RefreshDatabase::class);


it('has booked periods relationship', function () {
    $booking = BookingFactory::new()->create();
    $bookedPeriod = BookedPeriodFactory::new()->create(['booking_id' => $booking->id]);

    expect($booking->bookedPeriods()->count())->toBe(1)
        ->and($booking->bookedPeriods()->first())->toBeInstanceOf(BookedPeriod::class)
        ->and($booking->bookedPeriods()->first()->id)->toBe($bookedPeriod->id);
});

it('returns empty period collection when no booked periods exist', function () {
    $booking = BookingFactory::new()->create();

    $result = $booking->getBookedPeriods();

    expect($result)->toBeInstanceOf(PeriodCollection::class)
        ->and($result->isEmpty())->toBeTrue();
});

it('returns booked periods filtered by is_excluded status', function () {
    $booking = BookingFactory::new()->create();

    // Create included periods
    BookedPeriodFactory::new()->count(2)->included()->create(['booking_id' => $booking->id]);

    // Create excluded periods
    BookedPeriodFactory::new()->count(3)->excluded()->create(['booking_id' => $booking->id]);

    // Get included periods (default behavior)
    $includedPeriods = $booking->getBookedPeriods();
    expect($includedPeriods)->toHaveCount(2);

    // Get excluded periods
    $excludedPeriods = $booking->getBookedPeriods(isExcluded: true);
    expect($excludedPeriods)->toHaveCount(3);
});

it('transforms database periods to spatie period objects with day precision', function () {
    $booking = BookingFactory::new()->create();

    $startDate = '2024-01-15';
    $endDate = '2024-01-20';

    BookedPeriodFactory::new()
        ->withDates($startDate, $endDate)
        ->included()
        ->create(['booking_id' => $booking->id]);

    $result = $booking->getBookedPeriods();

    expect($result)->toHaveCount(1);

    $periods = iterator_to_array($result);
    $period = $periods[0];
    expect($period)->toBeInstanceOf(Period::class)
        ->and($period->precision()->equals(Precision::DAY()))->toBeTrue()
        ->and($period->start()->format('Y-m-d'))->toBe($startDate)
        ->and($period->end()->format('Y-m-d'))->toBe($endDate);
});

it('merges additional periods when provided', function () {
    $booking = BookingFactory::new()->create();

    // Create one period in database
    BookedPeriodFactory::new()
        ->withDates('2024-01-15', '2024-01-20')
        ->included()
        ->create(['booking_id' => $booking->id]);

    // Create additional periods to merge
    $additionalPeriods = new PeriodCollection(
        Period::make('2024-02-01', '2024-02-05', Precision::DAY()),
        Period::make('2024-02-10', '2024-02-15', Precision::DAY())
    );

    $result = $booking->getBookedPeriods(mergePeriods: $additionalPeriods);

    expect($result)->toHaveCount(3);
});

it('does not merge empty additional periods', function () {
    $booking = BookingFactory::new()->create();

    BookedPeriodFactory::new()
        ->withDates('2024-01-15', '2024-01-20')
        ->included()
        ->create(['booking_id' => $booking->id]);

    $emptyPeriods = new PeriodCollection();

    $result = $booking->getBookedPeriods(mergePeriods: $emptyPeriods);

    expect($result)->toHaveCount(1);
});

it('returns fallback periods when no periods exist and fallback is provided', function () {
    $booking = BookingFactory::new()->create();

    $fallbackPeriods = new PeriodCollection(
        Period::make('2024-03-01', '2024-03-05', Precision::DAY()),
        Period::make('2024-03-10', '2024-03-15', Precision::DAY())
    );

    $result = $booking->getBookedPeriods(fallbackPeriods: $fallbackPeriods);

    $periods = iterator_to_array($result);
    expect($result)->toHaveCount(2)
        ->and($periods[0]->start()->format('Y-m-d'))->toBe('2024-03-01')
        ->and($periods[count($periods) - 1]->end()->format('Y-m-d'))->toBe('2024-03-15');
});

it('returns database periods when both periods exist and fallback is provided', function () {
    $booking = BookingFactory::new()->create();

    // Create period in database
    BookedPeriodFactory::new()
        ->withDates('2024-01-15', '2024-01-20')
        ->included()
        ->create(['booking_id' => $booking->id]);

    $fallbackPeriods = new PeriodCollection(
        Period::make('2024-03-01', '2024-03-05', Precision::DAY())
    );

    $result = $booking->getBookedPeriods(fallbackPeriods: $fallbackPeriods);

    // Should return database periods, not fallback
    $periods = iterator_to_array($result);
    expect($result)->toHaveCount(1)
        ->and($periods[0]->start()->format('Y-m-d'))->toBe('2024-01-15');
});

it('combines database periods with merge periods and ignores fallback when database has results', function () {
    $booking = BookingFactory::new()->create();

    // Create period in database
    BookedPeriodFactory::new()
        ->withDates('2024-01-15', '2024-01-20')
        ->included()
        ->create(['booking_id' => $booking->id]);

    $mergePeriods = new PeriodCollection(
        Period::make('2024-02-01', '2024-02-05', Precision::DAY())
    );

    $fallbackPeriods = new PeriodCollection(
        Period::make('2024-03-01', '2024-03-05', Precision::DAY())
    );

    $result = $booking->getBookedPeriods(
        mergePeriods: $mergePeriods,
        fallbackPeriods: $fallbackPeriods
    );

    // Should have database period + merged period, but not fallback
    expect($result)->toHaveCount(2);

    $periods = iterator_to_array($result);
    $dates = collect($periods)->map(fn ($period) => $period->start()->format('Y-m-d'))->sort()->values();
    expect($dates->toArray())->toBe(['2024-01-15', '2024-02-01']);
});

it('handles complex scenario with all parameters', function () {
    $booking = BookingFactory::new()->create();

    // Create included and excluded periods
    BookedPeriodFactory::new()
        ->withDates('2024-01-15', '2024-01-20')
        ->included()
        ->create(['booking_id' => $booking->id]);

    BookedPeriodFactory::new()
        ->withDates('2024-01-25', '2024-01-30')
        ->excluded()
        ->create(['booking_id' => $booking->id]);

    $mergePeriods = new PeriodCollection(
        Period::make('2024-02-01', '2024-02-05', Precision::DAY())
    );

    $fallbackPeriods = new PeriodCollection(
        Period::make('2024-03-01', '2024-03-05', Precision::DAY())
    );

    // Test with excluded periods
    $excludedResult = $booking->getBookedPeriods(
        isExcluded: true,
        mergePeriods: $mergePeriods,
        fallbackPeriods: $fallbackPeriods
    );

    expect($excludedResult)->toHaveCount(2); // 1 excluded period + 1 merged period

    // Test with included periods
    $includedResult = $booking->getBookedPeriods(
        isExcluded: false,
        mergePeriods: $mergePeriods,
        fallbackPeriods: $fallbackPeriods
    );

    expect($includedResult)->toHaveCount(2); // 1 included period + 1 merged period
});

it('returns fallback when no database periods match filter', function () {
    $booking = BookingFactory::new()->create();

    // Create only excluded periods
    BookedPeriodFactory::new()
        ->withDates('2024-01-15', '2024-01-20')
        ->excluded()
        ->create(['booking_id' => $booking->id]);

    $fallbackPeriods = new PeriodCollection(
        Period::make('2024-03-01', '2024-03-05', Precision::DAY())
    );

    // Ask for included periods (should find none, return fallback)
    $result = $booking->getBookedPeriods(
        isExcluded: false,
        fallbackPeriods: $fallbackPeriods
    );

    $periods = iterator_to_array($result);
    expect($result)->toHaveCount(1)
        ->and($periods[0]->start()->format('Y-m-d'))->toBe('2024-03-01');
});
