<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Tests\database\factories\BookingFactory;
use Masterix21\Bookings\Tests\TestClasses\Product;

uses(RefreshDatabase::class);

it('has morphTo resource relationship', function () {
    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
    ]);

    expect($bookableResource->resource())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class)
        ->and($bookableResource->resource)->toBeInstanceOf(Product::class)
        ->and($bookableResource->resource->id)->toBe($product->id);
});

it('has bookableRelations hasMany relationship', function () {
    $bookableResource = BookableResource::factory()->create();

    expect($bookableResource->bookableRelations())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('has bookedPeriods hasMany relationship', function () {
    $bookableResource = BookableResource::factory()->create();

    expect($bookableResource->bookedPeriods())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('can create booked periods through relationship', function () {
    $bookableResource = BookableResource::factory()->create();
    $booking = BookingFactory::new()->create();

    $bookedPeriod = $bookableResource->bookedPeriods()->create([
        'booking_id' => $booking->id,
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'is_excluded' => false,
    ]);

    expect($bookedPeriod)->toBeInstanceOf(BookedPeriod::class)
        ->and($bookedPeriod->bookable_resource_id)->toBe($bookableResource->id)
        ->and($bookableResource->bookedPeriods)->toHaveCount(1);
});

it('returns correct size based on bookable status and ignore flag', function (
    int $size,
    bool $isBookable,
    bool $ignoresUnbookable,
    int $expected
) {
    $resource = BookableResource::factory()->create([
        'size' => $size,
        'is_bookable' => $isBookable,
    ]);

    expect($resource->size(ignoresUnbookable: $ignoresUnbookable))->toBe($expected);
})->with([
    'bookable without ignore' => [5, true, false, 5],
    'not bookable without ignore' => [5, false, false, 0],
    'not bookable with ignore' => [5, false, true, 5],
    'bookable with ignore' => [3, true, true, 3],
]);

it('casts is_visible and is_bookable to boolean', function () {
    $bookableResource = BookableResource::factory()->create([
        'is_visible' => 1,
        'is_bookable' => 0,
    ]);

    expect($bookableResource->is_visible)->toBeTrue()
        ->and($bookableResource->is_bookable)->toBeFalse();
});

it('allows mass assignment for all attributes', function () {
    $attributes = [
        'code' => 'TEST-001',
        'size' => 10,
        'is_visible' => true,
        'is_bookable' => true,
        'resource_type' => Product::class,
        'resource_id' => 1,
    ];

    $bookableResource = new BookableResource($attributes);

    expect($bookableResource->code)->toBe('TEST-001')
        ->and($bookableResource->size)->toBe(10)
        ->and($bookableResource->is_visible)->toBeTrue()
        ->and($bookableResource->is_bookable)->toBeTrue()
        ->and($bookableResource->resource_type)->toBe(Product::class)
        ->and($bookableResource->resource_id)->toBe(1);
});

it('uses HasFactory trait', function () {
    expect(BookableResource::factory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Factories\Factory::class);
});

it('uses multiple concerns and traits', function () {
    $bookableResource = new BookableResource;

    // Check that methods from various traits are available
    expect(method_exists($bookableResource, 'size'))->toBeTrue() // HasSizeFeatures
        ->and(method_exists($bookableResource, 'book'))->toBeTrue() // ImplementsBook
        ->and(method_exists($bookableResource, 'validatePlanningAvailability'))->toBeTrue(); // UsesBookablePlannings
});

it('scopeAvailableSlotForPeriod returns resources with available slots', function () {
    $startTime = \Carbon\Carbon::parse('2024-03-15 14:00:00');
    $endTime = \Carbon\Carbon::parse('2024-03-15 16:00:00');
    $period = \Spatie\Period\Period::make($startTime, $endTime, \Spatie\Period\Precision::HOUR());

    $availableResource = BookableResource::factory()->create([
        'is_bookable' => true,
        'max' => 2,
    ]);

    $unavailableResource = BookableResource::factory()->create([
        'is_bookable' => false,
        'max' => 2,
    ]);

    $fullyBookedResource = BookableResource::factory()->create([
        'is_bookable' => true,
        'max' => 1,
    ]);

    $booking = BookingFactory::new()->create();
    $fullyBookedResource->bookedPeriods()->create([
        'booking_id' => $booking->id,
        'starts_at' => '2024-03-15 14:00:00',
        'ends_at' => '2024-03-15 17:00:00',
        'is_excluded' => false,
    ]);

    $results = BookableResource::availableSlotForPeriod($period)->get();

    expect($results->pluck('id'))->toContain($availableResource->id)
        ->and($results->pluck('id'))->not->toContain($unavailableResource->id)
        ->and($results->pluck('id'))->not->toContain($fullyBookedResource->id);
});

it('scopeAvailableSlotForPeriod considers overlapping periods correctly', function () {
    $period = \Spatie\Period\Period::make(now()->addDay(), now()->addDay()->addHours(2), \Spatie\Period\Precision::HOUR());

    $resource = BookableResource::factory()->create([
        'is_bookable' => true,
        'max' => 2,
    ]);

    $booking = BookingFactory::new()->create();

    $resource->bookedPeriods()->create([
        'booking_id' => $booking->id,
        'starts_at' => now()->addDay()->subHour(),
        'ends_at' => now()->addDay()->addHour(),
        'is_excluded' => false,
    ]);

    $results = BookableResource::availableSlotForPeriod($period)->get();

    expect($results->pluck('id'))->toContain($resource->id);
});

it('scopeAvailableForPeriod returns resources with available slots and valid planning', function () {
    $startDate = \Carbon\Carbon::parse('2024-01-15 09:00:00');
    $endDate = \Carbon\Carbon::parse('2024-01-17 18:00:00');
    $period = \Spatie\Period\Period::make($startDate, $endDate);

    $resourceWithValidPlanning = BookableResource::factory()->create([
        'is_bookable' => true,
        'max' => 2,
    ]);

    $resourceWithValidPlanning->bookablePlannings()->create([
        'monday' => true,
        'tuesday' => true,
        'wednesday' => true,
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    $resourceWithoutPlanning = BookableResource::factory()->create([
        'is_bookable' => true,
        'max' => 2,
    ]);

    $results = BookableResource::availableForPeriod($period)->get();

    expect($results->pluck('id'))->toContain($resourceWithValidPlanning->id)
        ->and($results->pluck('id'))->not->toContain($resourceWithoutPlanning->id);
});

it('scopeAvailableForPeriod excludes resources with invalid weekdays in planning', function () {
    $startDate = \Carbon\Carbon::parse('2024-01-15 09:00:00');
    $endDate = \Carbon\Carbon::parse('2024-01-17 18:00:00');
    $period = \Spatie\Period\Period::make($startDate, $endDate);

    $resource = BookableResource::factory()->create([
        'is_bookable' => true,
        'max' => 2,
    ]);

    $resource->bookablePlannings()->create([
        'monday' => true,
        'tuesday' => true,
        'wednesday' => false,
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    $results = BookableResource::availableForPeriod($period)->get();

    expect($results->pluck('id'))->not->toContain($resource->id);
});

it('scopeAvailableForPeriod excludes resources with planning outside period', function () {
    $startDate = \Carbon\Carbon::parse('2024-02-15 09:00:00');
    $endDate = \Carbon\Carbon::parse('2024-02-17 18:00:00');
    $period = \Spatie\Period\Period::make($startDate, $endDate);

    $resource = BookableResource::factory()->create([
        'is_bookable' => true,
        'max' => 2,
    ]);

    $resource->bookablePlannings()->create([
        'monday' => true,
        'tuesday' => true,
        'wednesday' => true,
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    $results = BookableResource::availableForPeriod($period)->get();

    expect($results->pluck('id'))->not->toContain($resource->id);
});

it('scopeAvailableForPeriod handles resources with multiple plannings', function () {
    $startDate = \Carbon\Carbon::parse('2024-01-15 09:00:00');
    $endDate = \Carbon\Carbon::parse('2024-01-17 18:00:00');
    $period = \Spatie\Period\Period::make($startDate, $endDate);

    $resource = BookableResource::factory()->create([
        'is_bookable' => true,
        'max' => 2,
    ]);

    $resource->bookablePlannings()->create([
        'monday' => false,
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    $resource->bookablePlannings()->create([
        'monday' => true,
        'tuesday' => true,
        'wednesday' => true,
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    $results = BookableResource::availableForPeriod($period)->get();

    expect($results->pluck('id'))->toContain($resource->id);
});

it('scopeAvailableForPeriod excludes fully booked resources even with valid planning', function () {
    $startDate = \Carbon\Carbon::parse('2024-01-15 09:00:00');
    $endDate = \Carbon\Carbon::parse('2024-01-17 18:00:00');
    $period = \Spatie\Period\Period::make($startDate, $endDate);

    $resource = BookableResource::factory()->create([
        'is_bookable' => true,
        'max' => 1,
    ]);

    $resource->bookablePlannings()->create([
        'monday' => true,
        'tuesday' => true,
        'wednesday' => true,
        'starts_at' => '2024-01-01 00:00:00',
        'ends_at' => '2024-01-31 23:59:59',
    ]);

    $booking = BookingFactory::new()->create();
    $resource->bookedPeriods()->create([
        'booking_id' => $booking->id,
        'starts_at' => $startDate,
        'ends_at' => $endDate,
        'is_excluded' => false,
    ]);

    $results = BookableResource::availableForPeriod($period)->get();

    expect($results->pluck('id'))->not->toContain($resource->id);
});
