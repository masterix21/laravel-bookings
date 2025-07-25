<?php

use Carbon\Carbon;
use Masterix21\Bookings\Actions\CheckBookingOverlaps;
use Masterix21\Bookings\Enums\UnbookableReason;
use Masterix21\Bookings\Events\BookingChangeFailed;
use Masterix21\Bookings\Events\BookingFailed;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Tests\TestClasses\Product;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

it('returns true when no overlaps exist', function () {
    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 1,
    ]);

    $periods = PeriodCollection::make(
        Period::make(
            Carbon::now()->addHour(),
            Carbon::now()->addHours(2),
            Precision::HOUR()
        )
    );

    $result = (new CheckBookingOverlaps)->run(
        periods: $periods,
        bookableResource: $bookableResource,
    );

    expect($result)->toBeTrue();
});

it('returns false when overlaps exist and max is exceeded', function () {
    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 1,
    ]);

    $booking = Booking::factory()->create();

    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking->id,
        'starts_at' => now(),
        'ends_at' => now()->addHours(2),
    ]);

    // Try to book overlapping period
    $periods = PeriodCollection::make(
        Period::make(
            now()->addHour(),
            now()->addHours(3),
            Precision::HOUR()
        ),
    );

    $result = (new CheckBookingOverlaps)->run(
        periods: $periods,
        bookableResource: $bookableResource,
    );

    expect($result)->toBeFalse();
});

it('returns true when overlaps exist but max is not exceeded', function () {
    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 2, // Allow 2 concurrent bookings
    ]);

    // Create existing booking
    $booking = Booking::factory()->create();
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking->id,
        'starts_at' => Carbon::now(),
        'ends_at' => Carbon::now()->addHours(2),
    ]);

    // Try to book overlapping period (should be allowed since max=2)
    $periods = PeriodCollection::make(
        Period::make(Carbon::now()->addHour(), Carbon::now()->addHours(3), Precision::HOUR())
    );

    $result = (new CheckBookingOverlaps())->run(
        periods: $periods,
        bookableResource: $bookableResource,
    );

    expect($result)->toBeTrue();
});

it('handles multiple periods correctly', function () {
    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 1,
    ]);

    // Create existing booking for first period
    $booking = Booking::factory()->create();
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking->id,
        'starts_at' => Carbon::now(),
        'ends_at' => Carbon::now()->addHour(),
    ]);

    $periods = PeriodCollection::make(
        Period::make(Carbon::now()->addMinutes(30), Carbon::now()->addMinutes(90), Precision::MINUTE()), // Overlaps
        Period::make(Carbon::now()->addHours(2), Carbon::now()->addHours(3), Precision::MINUTE()) // No overlap
    );

    $result = (new CheckBookingOverlaps())->run(
        periods: $periods,
        bookableResource: $bookableResource,
    );

    expect($result)->toBeFalse();
});

it('ignores specified booking when provided', function () {
    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 1,
    ]);

    // Create existing booking to ignore
    $bookingToIgnore = Booking::factory()->create();
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $bookingToIgnore->id,
        'starts_at' => Carbon::now(),
        'ends_at' => Carbon::now()->addHours(2),
    ]);

    // Try to book overlapping period but ignore the existing booking
    $periods = PeriodCollection::make(
        Period::make(Carbon::now()->addHour(), Carbon::now()->addHours(3), Precision::HOUR())
    );

    $result = (new CheckBookingOverlaps())->run(
        periods: $periods,
        bookableResource: $bookableResource,
        emitEvent: false,
        throw: false,
        ignoreBooking: $bookingToIgnore
    );

    expect($result)->toBeTrue();
});

it('throws exception when throw is true and overlaps exist', function () {
    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 1,
    ]);

    // Create existing booking
    $booking = Booking::factory()->create();
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking->id,
        'starts_at' => Carbon::now(),
        'ends_at' => Carbon::now()->addHours(2),
    ]);

    // Try to book overlapping period with throw=true
    $periods = PeriodCollection::make(
        Period::make(Carbon::now()->addHour(), Carbon::now()->addHours(3), Precision::HOUR())
    );

    expect(fn() => (new CheckBookingOverlaps())->run(
        periods: $periods,
        bookableResource: $bookableResource,
        emitEvent: false,
        throw: true
    ))->toThrow(BookingResourceOverlappingException::class);
});

it('emits BookingFailed event when emitEvent is true and no ignoreBooking', function () {
    Event::fake();

    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 1,
    ]);

    // Create existing booking
    $booking = Booking::factory()->create();
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking->id,
        'starts_at' => Carbon::now(),
        'ends_at' => Carbon::now()->addHours(2),
    ]);

    $periods = PeriodCollection::make(
        Period::make(Carbon::now()->addHour(), Carbon::now()->addHours(3), Precision::HOUR())
    );

    (new CheckBookingOverlaps())->run(
        periods: $periods,
        bookableResource: $bookableResource,
        emitEvent: true,
    );

    Event::assertDispatched(BookingFailed::class, function ($event) use ($bookableResource, $periods) {
        return $event->reason === UnbookableReason::PERIOD_OVERLAP
            && $event->bookableResource->id === $bookableResource->id
            && $event->periods === $periods;
    });
});

it('emits BookingChangeFailed event when emitEvent is true and ignoreBooking is provided', function () {
    Event::fake();

    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 1,
    ]);

    // Create existing booking
    $existingBooking = Booking::factory()->create();
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $existingBooking->id,
        'starts_at' => Carbon::now(),
        'ends_at' => Carbon::now()->addHours(2),
    ]);

    // Create another booking that would conflict
    $anotherBooking = Booking::factory()->create();
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $anotherBooking->id,
        'starts_at' => Carbon::now()->addMinutes(30),
        'ends_at' => Carbon::now()->addMinutes(90),
    ]);

    $periods = PeriodCollection::make(
        Period::make(Carbon::now()->addHour(), Carbon::now()->addHours(3), Precision::HOUR())
    );

    (new CheckBookingOverlaps())->run(
        periods: $periods,
        bookableResource: $bookableResource,
        emitEvent: true,
        ignoreBooking: $existingBooking
    );

    Event::assertDispatched(BookingChangeFailed::class, function ($event) use ($existingBooking, $bookableResource, $periods) {
        return $event->booking->id === $existingBooking->id
            && $event->reason === UnbookableReason::PERIOD_OVERLAP
            && $event->bookableResource->id === $bookableResource->id
            && $event->periods === $periods;
    });
});

it('does not emit events when emitEvent is false', function () {
    Event::fake();

    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 1,
    ]);

    // Create existing booking
    $booking = Booking::factory()->create();
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking->id,
        'starts_at' => Carbon::now(),
        'ends_at' => Carbon::now()->addHours(2),
    ]);

    $periods = PeriodCollection::make(
        Period::make(Carbon::now()->addHour(), Carbon::now()->addHours(3), Precision::HOUR())
    );

    (new CheckBookingOverlaps())->run(
        periods: $periods,
        bookableResource: $bookableResource,
    );

    Event::assertNotDispatched(BookingFailed::class);
    Event::assertNotDispatched(BookingChangeFailed::class);
});

it('handles complex overlapping scenarios with multiple periods', function () {
    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 2,
    ]);

    // Create existing bookings
    $booking1 = Booking::factory()->create();
    $booking2 = Booking::factory()->create();

    // First existing booking: 10-12
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking1->id,
        'starts_at' => Carbon::now()->setTime(10, 0),
        'ends_at' => Carbon::now()->setTime(12, 0),
    ]);

    // Second existing booking: 11-13
    BookedPeriod::factory()->create([
        'bookable_resource_id' => $bookableResource->id,
        'booking_id' => $booking2->id,
        'starts_at' => Carbon::now()->setTime(11, 0),
        'ends_at' => Carbon::now()->setTime(13, 0),
    ]);

    // Try to book multiple periods:
    // - 09-10: No overlap (should be OK)
    // - 11:30-12:30: Overlaps with both existing bookings (max=2, so should fail)
    // - 14-15: No overlap (should be OK)
    $periods = PeriodCollection::make(
        Period::make(Carbon::now()->setTime(9, 0), Carbon::now()->setTime(10, 0), Precision::HOUR()),
        Period::make(Carbon::now()->setTime(11, 30), Carbon::now()->setTime(12, 30), Precision::MINUTE()),
        Period::make(Carbon::now()->setTime(14, 0), Carbon::now()->setTime(15, 0), Precision::HOUR())
    );

    $result = (new CheckBookingOverlaps())->run(
        periods: $periods,
        bookableResource: $bookableResource,
    );

    expect($result)->toBeFalse();
});

it('works correctly when max is greater than existing bookings', function () {
    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 5,
    ]);

    // Create 3 existing overlapping bookings
    for ($i = 0; $i < 3; $i++) {
        $booking = Booking::factory()->create();
        BookedPeriod::factory()->create([
            'bookable_resource_id' => $bookableResource->id,
            'booking_id' => $booking->id,
            'starts_at' => Carbon::now()->addMinutes($i * 10),
            'ends_at' => Carbon::now()->addHours(2)->addMinutes($i * 10),
        ]);
    }

    // Try to book overlapping period (should be allowed since 3 < 5)
    $periods = PeriodCollection::make(
        Period::make(Carbon::now()->addHour(), Carbon::now()->addHours(3), Precision::HOUR())
    );

    $result = (new CheckBookingOverlaps())->run(
        periods: $periods,
        bookableResource: $bookableResource,
    );

    expect($result)->toBeTrue();
});

it('handles empty periods collection', function () {
    $product = Product::factory()->create();
    $bookableResource = BookableResource::factory()->create([
        'resource_type' => Product::class,
        'resource_id' => $product->id,
        'max' => 1,
    ]);

    $periods = new PeriodCollection();

    $result = (new CheckBookingOverlaps())->run(
        periods: $periods,
        bookableResource: $bookableResource,
    );

    expect($result)->toBeTrue();
});
