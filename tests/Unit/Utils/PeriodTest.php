<?php

use Illuminate\Support\Carbon;
use Masterix21\Bookings\Period;
use Spatie\Period\Period as SpatiePeriod;
use Spatie\Period\PeriodCollection;

it('converts a single period to a collection of dates', function () {
    // Create a period for 3 days
    $period = SpatiePeriod::make(
        '2025-07-22',
        '2025-07-24'
    );

    // Convert to dates
    $dates = Period::toDates($period);

    // Assert we have 3 dates (22, 23, 24)
    expect($dates)->toHaveCount(3);

    // Assert the dates are correct
    expect($dates->map(fn ($date) => $date->format('Y-m-d'))->toArray())
        ->toBe([
            '2025-07-22',
            '2025-07-23',
            '2025-07-24',
        ]);
});

it('converts a collection of periods to a collection of dates', function () {
    // Create a collection with two periods
    $periods = PeriodCollection::make(
        SpatiePeriod::make('2025-07-22', '2025-07-23'),
        SpatiePeriod::make('2025-07-25', '2025-07-26')
    );

    // Convert to dates
    $dates = Period::toDates($periods);

    // Assert we have 4 dates (22, 23, 25, 26)
    expect($dates)->toHaveCount(4);

    // Assert the dates are correct
    expect($dates->map(fn ($date) => $date->format('Y-m-d'))->toArray())
        ->toBe([
            '2025-07-22',
            '2025-07-23',
            '2025-07-25',
            '2025-07-26',
        ]);
});

it('removes duplicate dates when periods overlap and removeDuplicates is true', function () {
    // Create a collection with two overlapping periods
    $periods = PeriodCollection::make(
        SpatiePeriod::make('2025-07-22', '2025-07-24'),
        SpatiePeriod::make('2025-07-23', '2025-07-25')
    );

    // Convert to dates with removeDuplicates = true (default)
    $dates = Period::toDates($periods);

    // Assert we have 4 unique dates (22, 23, 24, 25)
    expect($dates)->toHaveCount(4);

    // Assert the dates are correct and duplicates are removed
    // Use values() to reset the keys to sequential integers
    expect($dates->map(fn ($date) => $date->format('Y-m-d'))->values()->toArray())
        ->toBe([
            '2025-07-22',
            '2025-07-23',
            '2025-07-24',
            '2025-07-25',
        ]);
});

it('keeps duplicate dates when periods overlap and removeDuplicates is false', function () {
    // Create a collection with two overlapping periods
    $periods = PeriodCollection::make(
        SpatiePeriod::make('2025-07-22', '2025-07-24'),
        SpatiePeriod::make('2025-07-23', '2025-07-25')
    );

    // Convert to dates with removeDuplicates = false
    $dates = Period::toDates($periods, false);

    // Assert we have 6 dates (22, 23, 24, 23, 24, 25)
    expect($dates)->toHaveCount(6);

    // Assert the dates include duplicates
    $formattedDates = $dates->map(fn ($date) => $date->format('Y-m-d'))->toArray();

    // Count occurrences of each date
    $dateCounts = array_count_values($formattedDates);

    // Assert 2025-07-23 and 2025-07-24 appear twice (in both periods)
    expect($dateCounts['2025-07-22'])->toBe(1);
    expect($dateCounts['2025-07-23'])->toBe(2);
    expect($dateCounts['2025-07-24'])->toBe(2);
    expect($dateCounts['2025-07-25'])->toBe(1);
});

it('handles a period with a single day', function () {
    // Create a period for a single day
    $period = SpatiePeriod::make(
        '2025-07-22',
        '2025-07-22'
    );

    // Convert to dates
    $dates = Period::toDates($period);

    // Assert we have 1 date
    expect($dates)->toHaveCount(1);

    // Assert the date is correct
    expect($dates->first()->format('Y-m-d'))->toBe('2025-07-22');
});

it('handles an empty period collection', function () {
    // Create an empty period collection
    $periods = PeriodCollection::make();

    // Convert to dates
    $dates = Period::toDates($periods);

    // Assert we have 0 dates
    expect($dates)->toHaveCount(0);
    expect($dates)->toBeEmpty();
});
