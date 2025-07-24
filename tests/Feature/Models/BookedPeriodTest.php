<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Tests\database\factories\BookableResourceFactory;
use Masterix21\Bookings\Tests\database\factories\BookedPeriodFactory;
use Masterix21\Bookings\Tests\database\factories\BookingFactory;
use Masterix21\Bookings\Tests\TestClasses\Product;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

uses(RefreshDatabase::class);

it('has booking belongsTo relationship', function () {
    $booking = BookingFactory::new()->create();
    $bookedPeriod = BookedPeriodFactory::new()->create(['booking_id' => $booking->id]);

    expect($bookedPeriod->booking())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($bookedPeriod->booking)->toBeInstanceOf(Booking::class)
        ->and($bookedPeriod->booking->id)->toBe($booking->id);
});

it('has parent belongsTo relationship', function () {
    $parentPeriod = BookedPeriodFactory::new()->create();
    $childPeriod = BookedPeriodFactory::new()->create(['parent_id' => $parentPeriod->id]);

    expect($childPeriod->parent())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($childPeriod->parent)->toBeInstanceOf(BookedPeriod::class)
        ->and($childPeriod->parent->id)->toBe($parentPeriod->id);
});

it('has children hasMany relationship', function () {
    $parentPeriod = BookedPeriodFactory::new()->create();
    $childPeriod1 = BookedPeriodFactory::new()->create(['parent_id' => $parentPeriod->id]);
    $childPeriod2 = BookedPeriodFactory::new()->create(['parent_id' => $parentPeriod->id]);

    expect($parentPeriod->children())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($parentPeriod->children)->toHaveCount(2)
        ->and($parentPeriod->children->pluck('id')->toArray())->toContain($childPeriod1->id, $childPeriod2->id);
});

it('has bookableResource belongsTo relationship', function () {
    $bookableResource = BookableResourceFactory::new()->create();
    $bookedPeriod = BookedPeriodFactory::new()->create(['bookable_resource_id' => $bookableResource->id]);

    expect($bookedPeriod->bookableResource())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($bookedPeriod->bookableResource)->toBeInstanceOf(BookableResource::class)
        ->and($bookedPeriod->bookableResource->id)->toBe($bookableResource->id);
});

it('has relatable morphTo relationship', function () {
    $product = Product::factory()->create();
    $bookedPeriod = BookedPeriodFactory::new()->create([
        'relatable_type' => Product::class,
        'relatable_id' => $product->id,
    ]);

    expect($bookedPeriod->relatable())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class)
        ->and($bookedPeriod->relatable)->toBeInstanceOf(Product::class)
        ->and($bookedPeriod->relatable->id)->toBe($product->id);
});

it('creates period attribute from starts_at and ends_at', function () {
    $startDate = Carbon::create(2024, 1, 15, 10, 0, 0);
    $endDate = Carbon::create(2024, 1, 18, 15, 0, 0);
    
    $bookedPeriod = BookedPeriodFactory::new()->create([
        'starts_at' => $startDate,
        'ends_at' => $endDate,
    ]);

    expect($bookedPeriod->period)->toBeInstanceOf(Period::class)
        ->and($bookedPeriod->period->start()->format('Y-m-d'))->toBe($startDate->format('Y-m-d'))
        ->and($bookedPeriod->period->end()->format('Y-m-d'))->toBe($endDate->format('Y-m-d'));
});

it('casts dates and boolean properly', function () {
    $bookedPeriod = BookedPeriodFactory::new()->create([
        'is_excluded' => 1,
        'starts_at' => '2024-01-15 10:00:00',
        'ends_at' => '2024-01-15 15:00:00',
    ]);

    expect($bookedPeriod->is_excluded)->toBeTrue()
        ->and($bookedPeriod->starts_at)->toBeInstanceOf(Carbon::class)
        ->and($bookedPeriod->ends_at)->toBeInstanceOf(Carbon::class);
});

it('uses soft deletes', function () {
    $bookedPeriod = BookedPeriodFactory::new()->create();
    
    $bookedPeriod->delete();
    
    expect($bookedPeriod->deleted_at)->not->toBeNull()
        ->and(BookedPeriod::withTrashed()->find($bookedPeriod->id))->not->toBeNull()
        ->and(BookedPeriod::find($bookedPeriod->id))->toBeNull();
});

it('scopes whereAllDatesAreWithinPeriods for included periods', function () {
    $periods = new PeriodCollection(
        Period::make('2024-01-01', '2024-01-05', Precision::DAY())
    );

    // Create period that overlaps with the search period
    $matchingPeriod = BookedPeriodFactory::new()->create([
        'starts_at' => '2024-01-02 00:00:00',
        'ends_at' => '2024-01-04 23:59:59',
        'is_excluded' => false,
    ]);

    // Create periods that don't match
    BookedPeriodFactory::new()->create([
        'starts_at' => '2024-02-01 00:00:00',
        'ends_at' => '2024-02-05 23:59:59',
        'is_excluded' => false,
    ]);

    BookedPeriodFactory::new()->create([
        'starts_at' => '2024-01-02 00:00:00',
        'ends_at' => '2024-01-04 23:59:59',
        'is_excluded' => true, // excluded
    ]);

    $results = BookedPeriod::whereAllDatesAreWithinPeriods($periods)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($matchingPeriod->id);
});

it('scopes whereAllDatesAreWithinPeriods for excluded periods', function () {
    $periods = new PeriodCollection(
        Period::make('2024-01-01', '2024-01-05', Precision::DAY())
    );

    $excludedPeriod = BookedPeriodFactory::new()->create([
        'starts_at' => '2024-01-02 00:00:00',
        'ends_at' => '2024-01-04 23:59:59',
        'is_excluded' => true,
    ]);

    BookedPeriodFactory::new()->create([
        'starts_at' => '2024-01-02 00:00:00',
        'ends_at' => '2024-01-04 23:59:59',
        'is_excluded' => false,
    ]);

    $results = BookedPeriod::whereAllDatesAreWithinPeriods($periods, excluded: true)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($excludedPeriod->id);
});

it('scopes whereDatesAreWithinPeriods for included periods', function () {
    $periods = new PeriodCollection(
        Period::make('2024-01-01', '2024-01-05', Precision::DAY()),
        Period::make('2024-01-10', '2024-01-15', Precision::DAY())
    );

    // Create periods that overlap with any of the periods
    $overlappingPeriod1 = BookedPeriodFactory::new()->create([
        'starts_at' => '2024-01-03 00:00:00',
        'ends_at' => '2024-01-07 23:59:59', // overlaps first period
        'is_excluded' => false,
    ]);
    
    $overlappingPeriod2 = BookedPeriodFactory::new()->create([
        'starts_at' => '2024-01-08 00:00:00',
        'ends_at' => '2024-01-12 23:59:59', // overlaps second period
        'is_excluded' => false,
    ]);

    // Create period that doesn't overlap
    BookedPeriodFactory::new()->create([
        'starts_at' => '2024-02-01 00:00:00',
        'ends_at' => '2024-02-05 23:59:59',
        'is_excluded' => false,
    ]);

    $results = BookedPeriod::whereDatesAreWithinPeriods($periods)->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id')->toArray())->toContain($overlappingPeriod1->id, $overlappingPeriod2->id);
});

it('scopes whereDatesAreWithinPeriods for excluded periods', function () {
    $periods = new PeriodCollection(
        Period::make('2024-01-01', '2024-01-05', Precision::DAY())
    );

    $excludedOverlapping = BookedPeriodFactory::new()->create([
        'starts_at' => '2024-01-02 00:00:00',
        'ends_at' => '2024-01-06 23:59:59',
        'is_excluded' => true,
    ]);

    BookedPeriodFactory::new()->create([
        'starts_at' => '2024-01-02 00:00:00',
        'ends_at' => '2024-01-06 23:59:59',
        'is_excluded' => false,
    ]);

    $results = BookedPeriod::whereDatesAreWithinPeriods($periods, excluded: true)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($excludedOverlapping->id);
});

it('allows mass assignment for all attributes', function () {
    $attributes = [
        'booking_id' => 1,
        'bookable_resource_id' => 1,
        'parent_id' => 1,
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'is_excluded' => true,
        'relatable_type' => Product::class,
        'relatable_id' => 1,
    ];

    $bookedPeriod = new BookedPeriod($attributes);

    expect($bookedPeriod->booking_id)->toBe(1)
        ->and($bookedPeriod->bookable_resource_id)->toBe(1)
        ->and($bookedPeriod->parent_id)->toBe(1)
        ->and($bookedPeriod->is_excluded)->toBeTrue()
        ->and($bookedPeriod->relatable_type)->toBe(Product::class)
        ->and($bookedPeriod->relatable_id)->toBe(1);
});

it('uses HasFactory trait', function () {
    expect(BookedPeriod::factory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Factories\Factory::class);
});
