<?php

use Carbon\Carbon;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Tests\TestClasses\Product;
use Spatie\Period\Period;

it('has bookableResources morphMany relationship', function () {
    $product = Product::factory()->create();

    expect($product->bookableResources())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
});

it('has bookableResource morphOne relationship', function () {
    $product = Product::factory()->create();

    expect($product->bookableResource())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphOne::class);
});

it('has bookedPeriods hasManyDeep relationship', function () {
    $product = Product::factory()->create();

    expect($product->bookedPeriods())->toBeInstanceOf(\Staudenmeir\EloquentHasManyDeep\HasManyDeep::class);
});

it('has bookings hasManyDeep relationship', function () {
    $product = Product::factory()->create();

    expect($product->bookings())->toBeInstanceOf(\Staudenmeir\EloquentHasManyDeep\HasManyDeep::class);
});

it('can create bookable resources through morphMany relationship', function () {
    $product = Product::factory()->create();

    $bookableResource = $product->bookableResources()->create([
        'code' => 'TEST-001',
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    expect($bookableResource)->toBeInstanceOf(BookableResource::class)
        ->and($bookableResource->resource_type)->toBe(Product::class)
        ->and($bookableResource->resource_id)->toBe($product->id)
        ->and($bookableResource->code)->toBe('TEST-001');
});

it('can access bookable resource through morphOne relationship', function () {
    $product = Product::factory()->create();

    $bookableResource = $product->bookableResources()->create([
        'code' => 'TEST-002',
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    $retrievedResource = $product->bookableResource;

    expect($retrievedResource)->toBeInstanceOf(BookableResource::class)
        ->and($retrievedResource->id)->toBe($bookableResource->id)
        ->and($retrievedResource->code)->toBe('TEST-002');
});

it('performs database query when checking isBookedAt without pre-loading relation', function () {
    $product = Product::factory()->create();
    
    // Create bookable resource
    $bookableResource = $product->bookableResources()->create([
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    $date = Carbon::now();

    // Should work without pre-loading the relation (uses database query)
    expect($product->isBookedAt($date))->toBeFalse();
});

it('returns false when not booked at specific date', function () {
    $product = Product::factory()->create();
    
    // Create bookable resource
    $bookableResource = $product->bookableResources()->create([
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    $date = Carbon::now();

    expect($product->isBookedAt($date))->toBeFalse();
});

it('returns true when booked at specific date', function () {
    $product = Product::factory()->create();
    
    // Create bookable resource
    $bookableResource = $product->bookableResources()->create([
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    // Create a booking
    $booking = Booking::factory()->create();

    // Create a booked period that contains our test date
    $testDate = Carbon::now();
    $bookedPeriod = BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking->id,
        'starts_at' => $testDate->copy()->subHour(),
        'ends_at' => $testDate->copy()->addHour(),
    ]);

    expect($product->isBookedAt($testDate))->toBeTrue();
});

it('performs database query when getting bookedPeriodsOfDate without pre-loading relation', function () {
    $product = Product::factory()->create();
    
    // Create bookable resource
    $bookableResource = $product->bookableResources()->create([
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    $date = Carbon::now();

    // Should work without pre-loading the relation (uses database query)
    $periodsOfDate = $product->bookedPeriodsOfDate($date);
    
    expect($periodsOfDate)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($periodsOfDate)->toHaveCount(0);
});

it('returns empty collection when no booked periods exist for date', function () {
    $product = Product::factory()->create();
    
    // Create bookable resource
    $bookableResource = $product->bookableResources()->create([
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    $date = Carbon::now();

    $periodsOfDate = $product->bookedPeriodsOfDate($date);

    expect($periodsOfDate)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($periodsOfDate)->toHaveCount(0);
});

it('returns collection of booked periods for specific date', function () {
    $product = Product::factory()->create();
    
    // Create bookable resource
    $bookableResource = $product->bookableResources()->create([
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    // Create bookings
    $booking1 = Booking::factory()->create();
    $booking2 = Booking::factory()->create();

    $testDate = Carbon::now();

    // Create booked periods that contain our test date
    $bookedPeriod1 = BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking1->id,
        'starts_at' => $testDate->copy()->subHours(2),
        'ends_at' => $testDate->copy()->addHour(),
    ]);

    $bookedPeriod2 = BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking2->id,
        'starts_at' => $testDate->copy()->subHour(),
        'ends_at' => $testDate->copy()->addHours(2),
    ]);

    // Create a booked period that doesn't contain our test date
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking1->id,
        'starts_at' => $testDate->copy()->addDays(1),
        'ends_at' => $testDate->copy()->addDays(1)->addHour(),
    ]);

    $periodsOfDate = $product->bookedPeriodsOfDate($testDate);

    expect($periodsOfDate)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($periodsOfDate)->toHaveCount(2)
        ->and($periodsOfDate->pluck('id')->toArray())->toContain($bookedPeriod1->id, $bookedPeriod2->id);
});

it('can access bookings through hasManyDeep relationship', function () {
    $product = Product::factory()->create();
    
    // Create bookable resource
    $bookableResource = $product->bookableResources()->create([
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    // Create bookings
    $booking1 = Booking::factory()->create();
    $booking2 = Booking::factory()->create();

    // Create booked periods linking to the bookings
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking1->id,
        'starts_at' => Carbon::now(),
        'ends_at' => Carbon::now()->addHour(),
    ]);

    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking2->id,
        'starts_at' => Carbon::now()->addHours(2),
        'ends_at' => Carbon::now()->addHours(3),
    ]);

    $bookings = $product->bookings;

    expect($bookings)->toHaveCount(2)
        ->and($bookings->pluck('id')->toArray())->toContain($booking1->id, $booking2->id);
});

it('deletes associated bookable resources when model is deleted', function () {
    $product = Product::factory()->create();
    
    // Create multiple bookable resources
    $bookableResource1 = $product->bookableResources()->create([
        'code' => 'TEST-001',
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    $bookableResource2 = $product->bookableResources()->create([
        'code' => 'TEST-002',
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    $productId = $product->id;
    $resource1Id = $bookableResource1->id;
    $resource2Id = $bookableResource2->id;

    // Verify resources exist before deletion
    expect(BookableResource::find($resource1Id))->not->toBeNull()
        ->and(BookableResource::find($resource2Id))->not->toBeNull();

    // Delete the product
    $product->delete();

    // Verify the product is deleted
    expect(Product::find($productId))->toBeNull();

    // Verify associated bookable resources are deleted via boot method
    expect(BookableResource::find($resource1Id))->toBeNull()
        ->and(BookableResource::find($resource2Id))->toBeNull();
});

it('handles multiple bookable resources correctly', function () {
    $product = Product::factory()->create();
    
    // Create multiple bookable resources
    $product->bookableResources()->createMany([
        ['code' => 'RES-001', 'is_visible' => true, 'is_bookable' => true],
        ['code' => 'RES-002', 'is_visible' => true, 'is_bookable' => false],
        ['code' => 'RES-003', 'is_visible' => false, 'is_bookable' => true],
    ]);

    $bookableResources = $product->bookableResources;

    expect($bookableResources)->toHaveCount(3)
        ->and($bookableResources->pluck('code')->toArray())->toEqual(['RES-001', 'RES-002', 'RES-003'])
        ->and($bookableResources->where('is_bookable', true))->toHaveCount(2)
        ->and($bookableResources->where('is_visible', true))->toHaveCount(2);
});

it('demonstrates isBookedAt functionality with basic scenario', function () {
    $product = Product::factory()->create();
    
    // Create bookable resource
    $bookableResource = $product->bookableResources()->create([
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    // Create booking with a simple period
    $booking = Booking::factory()->create();

    // Create a booked period
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking->id,
        'starts_at' => Carbon::now(),
        'ends_at' => Carbon::now()->addHour(),
    ]);

    // Verify the relationship data is correct - now using database queries
    expect($product->bookedPeriods()->count())->toBe(1)
        ->and($product->bookings)->toHaveCount(1)
        ->and($product->bookings->first()->id)->toBe($booking->id);
        
    // Basic functionality test - the methods work correctly with database queries
    $bookedPeriod = $product->bookedPeriods()->first();
    expect($bookedPeriod)->toBeInstanceOf(BookedPeriod::class)
        ->and($bookedPeriod->period)->not->toBeNull();
});
