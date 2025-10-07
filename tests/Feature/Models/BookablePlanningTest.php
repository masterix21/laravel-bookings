<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Tests\database\factories\BookablePlanningFactory;
use Masterix21\Bookings\Tests\database\factories\BookableResourceFactory;

uses(RefreshDatabase::class);

it('has bookableResource belongsTo relationship', function () {
    $bookableResource = BookableResourceFactory::new()->create();
    $planning = BookablePlanningFactory::new()->create(['bookable_resource_id' => $bookableResource->id]);

    expect($planning->bookableResource())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($planning->bookableResource)->toBeInstanceOf(BookableResource::class)
        ->and($planning->bookableResource->id)->toBe($bookableResource->id);
});

it('casts weekday boolean properties correctly', function () {
    $planning = BookablePlanningFactory::new()->create([
        'monday' => 1,
        'tuesday' => 0,
        'wednesday' => 1,
        'thursday' => 0,
        'friday' => 1,
        'saturday' => 0,
        'sunday' => 1,
    ]);

    expect($planning->monday)->toBeTrue()
        ->and($planning->tuesday)->toBeFalse()
        ->and($planning->wednesday)->toBeTrue()
        ->and($planning->thursday)->toBeFalse()
        ->and($planning->friday)->toBeTrue()
        ->and($planning->saturday)->toBeFalse()
        ->and($planning->sunday)->toBeTrue();
});

it('casts datetime properties correctly', function () {
    $startDate = '2024-01-15 10:00:00';
    $endDate = '2024-01-15 18:00:00';

    $planning = BookablePlanningFactory::new()->create([
        'starts_at' => $startDate,
        'ends_at' => $endDate,
    ]);

    expect($planning->starts_at)->toBeInstanceOf(Carbon::class)
        ->and($planning->ends_at)->toBeInstanceOf(Carbon::class)
        ->and($planning->starts_at->format('Y-m-d H:i:s'))->toBe($startDate)
        ->and($planning->ends_at->format('Y-m-d H:i:s'))->toBe($endDate);
});

it('scopes whereWeekdaysDates with single date string', function () {
    // Create plannings for different weekdays
    $mondayPlanning = BookablePlanningFactory::new()->create(['monday' => true, 'tuesday' => false]);
    $tuesdayPlanning = BookablePlanningFactory::new()->create(['monday' => false, 'tuesday' => true]);

    // Monday date
    $mondayDate = '2024-01-15'; // This is a Monday
    $results = BookablePlanning::whereWeekdaysDates($mondayDate)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($mondayPlanning->id);
});

it('scopes whereWeekdaysDates with array of dates', function () {
    // Create planning that covers both weekdays
    $mondayWednesdayPlanning = BookablePlanningFactory::new()->create(['monday' => true, 'wednesday' => true]);
    $mondayOnlyPlanning = BookablePlanningFactory::new()->create(['monday' => true, 'wednesday' => false]);
    $fridayPlanning = BookablePlanningFactory::new()->create(['monday' => false, 'friday' => true]);

    // Monday and Wednesday dates - should find planning that covers BOTH days
    $dates = ['2024-01-15', '2024-01-17']; // Monday and Wednesday
    $results = BookablePlanning::whereWeekdaysDates($dates)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($mondayWednesdayPlanning->id);
});

it('scopes whereWeekdaysDates with collection of dates', function () {
    // Create planning for Friday
    $fridayPlanning = BookablePlanningFactory::new()->create(['friday' => true]);
    BookablePlanningFactory::new()->create(['monday' => true, 'friday' => false]);

    // Friday date as collection
    $dates = collect(['2024-01-19']); // Friday
    $results = BookablePlanning::whereWeekdaysDates($dates)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($fridayPlanning->id);
});

it('scopes whereAllDatesAreWithinPeriods with null start and end dates', function () {
    // Planning with no date restrictions (null starts_at and ends_at)
    $openPlanning = BookablePlanningFactory::new()->create([
        'starts_at' => null,
        'ends_at' => null,
    ]);

    // Planning with date restrictions
    BookablePlanningFactory::new()->create([
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    $testDate = '2024-06-15'; // Outside the restricted period
    $results = BookablePlanning::whereAllDatesAreWithinPeriods($testDate)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($openPlanning->id);
});

it('scopes whereAllDatesAreWithinPeriods with date within period', function () {
    $planning = BookablePlanningFactory::new()->create([
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    // Create planning outside period
    BookablePlanningFactory::new()->create([
        'starts_at' => '2024-02-01 00:00:00',
        'ends_at' => '2024-02-28 23:59:59',
    ]);

    $testDate = '2024-01-15'; // Within first planning period
    $results = BookablePlanning::whereAllDatesAreWithinPeriods($testDate)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($planning->id);
});

it('scopes whereAllDatesAreWithinPeriods with multiple dates', function () {
    // Planning that covers both test dates
    $planning = BookablePlanningFactory::new()->create([
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    // Planning that only covers one test date
    BookablePlanningFactory::new()->create([
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-15 23:59:59',
    ]);

    $testDates = ['2024-01-10', '2024-01-25']; // Both within first planning
    $results = BookablePlanning::whereAllDatesAreWithinPeriods($testDates)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($planning->id);
});

it('scopes whereDatesAreWithinPeriods with single date', function () {
    // Planning that covers the test date
    $planning1 = BookablePlanningFactory::new()->create([
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    // Another planning that also covers the test date
    $planning2 = BookablePlanningFactory::new()->create([
        'starts_at' => '2024-01-10 00:00:00',
        'ends_at' => '2024-01-20 23:59:59',
    ]);

    // Planning that doesn't cover the test date
    BookablePlanningFactory::new()->create([
        'starts_at' => '2024-02-01 00:00:00',
        'ends_at' => '2024-02-28 23:59:59',
    ]);

    $testDate = '2024-01-15';
    $results = BookablePlanning::whereDatesAreWithinPeriods($testDate)->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id')->toArray())->toContain($planning1->id, $planning2->id);
});

it('scopes whereDatesAreWithinPeriods with multiple dates', function () {
    // Planning that covers first date
    $planning1 = BookablePlanningFactory::new()->create([
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-15 23:59:59',
    ]);

    // Planning that covers second date
    $planning2 = BookablePlanningFactory::new()->create([
        'starts_at' => '2024-01-20 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    // Planning that covers both dates
    $planning3 = BookablePlanningFactory::new()->create([
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    $testDates = ['2024-01-10', '2024-01-25'];
    $results = BookablePlanning::whereDatesAreWithinPeriods($testDates)->get();

    expect($results)->toHaveCount(3)
        ->and($results->pluck('id')->toArray())->toContain($planning1->id, $planning2->id, $planning3->id);
});

it('scopes whereDatesAreValids combines weekdays and period checks', function () {
    // Planning for Monday within date range
    $validPlanning = BookablePlanningFactory::new()->create([
        'monday' => true,
        'tuesday' => false,
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    // Planning for Monday but outside date range
    BookablePlanningFactory::new()->create([
        'monday' => true,
        'tuesday' => false,
        'starts_at' => '2024-02-01 00:00:00',
        'ends_at' => '2024-02-28 23:59:59',
    ]);

    // Planning within date range but wrong weekday
    BookablePlanningFactory::new()->create([
        'monday' => false,
        'tuesday' => true,
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    $mondayDate = '2024-01-15'; // Monday within first planning period
    $results = BookablePlanning::whereDatesAreValids($mondayDate)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($validPlanning->id);
});

it('allows mass assignment for all attributes', function () {
    $attributes = [
        'bookable_resource_id' => 1,
        'monday' => true,
        'tuesday' => false,
        'wednesday' => true,
        'thursday' => false,
        'friday' => true,
        'saturday' => false,
        'sunday' => true,
        'starts_at' => '2024-01-01 10:00:00',
        'ends_at' => '2024-12-31 18:00:00',
    ];

    $planning = new BookablePlanning($attributes);

    expect($planning->bookable_resource_id)->toBe(1)
        ->and($planning->monday)->toBeTrue()
        ->and($planning->tuesday)->toBeFalse()
        ->and($planning->wednesday)->toBeTrue()
        ->and($planning->thursday)->toBeFalse()
        ->and($planning->friday)->toBeTrue()
        ->and($planning->saturday)->toBeFalse()
        ->and($planning->sunday)->toBeTrue();
});

it('uses HasFactory trait', function () {
    expect(BookablePlanning::factory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Factories\Factory::class);
});

it('handles edge cases with null dates in scopes', function () {
    $planning = BookablePlanningFactory::new()->create([
        'starts_at' => null,
        'ends_at' => null,
        'monday' => true,
    ]);

    // Test with null dates - should work with open planning
    $mondayDate = '2024-01-15'; // Monday
    $results = BookablePlanning::whereDatesAreValids($mondayDate)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($planning->id);
});

it('handles unique dates in scopes correctly', function () {
    $planning = BookablePlanningFactory::new()->create([
        'monday' => true,
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    // Pass duplicate dates - should still work correctly due to unique() call
    $duplicateDates = ['2024-01-15', '2024-01-15', '2024-01-15']; // All Monday
    $results = BookablePlanning::whereDatesAreValids($duplicateDates)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($planning->id);
});
