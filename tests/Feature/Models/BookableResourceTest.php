<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Tests\database\factories\BookableResourceFactory;
use Masterix21\Bookings\Tests\database\factories\BookedPeriodFactory;
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

it('returns correct size when resource is bookable', function () {
    $bookableResource = BookableResource::factory()->create([
        'size' => 5,
        'is_bookable' => true,
    ]);

    expect($bookableResource->size())->toBe(5);
});

it('returns zero size when resource is not bookable', function () {
    $bookableResource = BookableResource::factory()->create([
        'size' => 5,
        'is_bookable' => false,
    ]);

    expect($bookableResource->size())->toBe(0);
});

it('returns actual size when ignoring unbookable flag', function () {
    $bookableResource = BookableResource::factory()->create([
        'size' => 5,
        'is_bookable' => false,
    ]);

    expect($bookableResource->size(ignoresUnbookable: true))->toBe(5);
});

it('returns actual size when bookable and ignoring unbookable flag', function () {
    $bookableResource = BookableResource::factory()->create([
        'size' => 3,
        'is_bookable' => true,
    ]);

    expect($bookableResource->size(ignoresUnbookable: true))->toBe(3);
});

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
    $bookableResource = new BookableResource();

    // Check that methods from various traits are available
    expect(method_exists($bookableResource, 'size'))->toBeTrue() // HasSizeFeatures
        ->and(method_exists($bookableResource, 'book'))->toBeTrue() // ImplementsBook
        ->and(method_exists($bookableResource, 'validatePlanningAvailability'))->toBeTrue(); // UsesBookablePlannings
});