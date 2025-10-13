<?php

use Illuminate\Support\Facades\Event;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Enums\UnbookableReason;
use Masterix21\Bookings\Events\BookingChanged;
use Masterix21\Bookings\Events\BookingChangeFailed;
use Masterix21\Bookings\Events\BookingChanging;
use Masterix21\Bookings\Events\BookingCompleted;
use Masterix21\Bookings\Events\BookingFailed;
use Masterix21\Bookings\Events\BookingInProgress;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Tests\Concerns\CreatesResources;
use Masterix21\Bookings\Tests\TestClasses\Product;
use Masterix21\Bookings\Tests\TestClasses\User;
use Spatie\Period\Period as SpatiePeriod;
use Spatie\Period\PeriodCollection;
use Spatie\TestTime\TestTime;

uses(CreatesResources::class);

beforeEach(function () {
    TestTime::freeze();
    Event::fake();

    // Create common test data used in most tests
    $this->resource = $this->createsResources(
        startsAt: now()->startOfDay(),
        endsAt: now()->addDays(7)->endOfDay(),
        resourcesCount: 1
    )->first();

    $this->user = User::factory()->create();
    $this->product = Product::factory()->create();

    // Helper to create period collections
    $this->createPeriod = fn($startDay, $endDay) => PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDays($startDay)->format('Y-m-d'),
            now()->addDays($endDay)->format('Y-m-d')
        )
    );
});

it('can book a resource', function () {
    $booking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        relatable: $this->product,
        label: 'Test Booking',
        note: 'Test Note',
        meta: ['test' => 'value']
    );

    expect($booking)->toBeInstanceOf(Booking::class)
        ->and($booking->booker_type)->toBe(User::class)
        ->and($booking->booker_id)->toBe($this->user->id)
        ->and($booking->label)->toBe('Test Booking')
        ->and($booking->note)->toBe('Test Note')
        ->and($booking->meta->toArray())->toBe(['test' => 'value']);

    expect($booking->bookedPeriods)->toHaveCount(1)
        ->and($booking->bookedPeriods->first()->bookable_resource_id)->toBe($this->resource->id)
        ->and($booking->bookedPeriods->first()->relatable_type)->toBe(Product::class)
        ->and($booking->bookedPeriods->first()->relatable_id)->toBe($this->product->id)
        ->and($booking->bookedPeriods->first()->starts_at->format('Y-m-d'))->toBe(now()->addDay()->format('Y-m-d'))
        ->and($booking->bookedPeriods->first()->ends_at->format('Y-m-d'))->toBe(now()->addDays(2)->format('Y-m-d'));

    Event::assertDispatched(BookingInProgress::class);
    Event::assertDispatched(BookingCompleted::class);
});

it('can update an existing booking', function () {
    $user2 = User::factory()->create();
    $product2 = Product::factory()->create();

    $booking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        relatable: $this->product,
        label: 'Initial Booking',
        note: 'Initial Note',
        meta: ['initial' => 'value']
    );

    Event::fake();

    $updatedBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $this->resource,
        booker: $user2,
        booking: $booking,
        relatable: $product2,
        label: 'Updated Booking',
        note: 'Updated Note',
        meta: ['updated' => 'value']
    );

    expect($updatedBooking)->toBeInstanceOf(Booking::class)
        ->and($updatedBooking->id)->toBe($booking->id)
        ->and($updatedBooking->booker_type)->toBe(User::class)
        ->and($updatedBooking->booker_id)->toBe($user2->id)
        ->and($updatedBooking->label)->toBe('Updated Booking')
        ->and($updatedBooking->note)->toBe('Updated Note')
        ->and($updatedBooking->meta->toArray())->toBe(['updated' => 'value']);

    expect($updatedBooking->bookedPeriods)->toHaveCount(1)
        ->and($updatedBooking->bookedPeriods->first()->bookable_resource_id)->toBe($this->resource->id)
        ->and($updatedBooking->bookedPeriods->first()->relatable_type)->toBe(Product::class)
        ->and($updatedBooking->bookedPeriods->first()->relatable_id)->toBe($product2->id)
        ->and($updatedBooking->bookedPeriods->first()->starts_at->format('Y-m-d'))->toBe(now()->addDays(3)->format('Y-m-d'))
        ->and($updatedBooking->bookedPeriods->first()->ends_at->format('Y-m-d'))->toBe(now()->addDays(4)->format('Y-m-d'));

    Event::assertDispatched(BookingChanging::class);
    Event::assertDispatched(BookingChanged::class);
});

it('fails when booking overlapping periods', function () {
    $resource = $this->createsResources(
        startsAt: now()->startOfDay(),
        endsAt: now()->addDays(7)->endOfDay(),
        resourcesCount: 1,
        resourcesStates: ['max' => 1]
    )->first();

    $periods = ($this->createPeriod)(1, 2);

    $booking = (new BookResource)->run(
        periods: $periods,
        bookableResource: $resource,
        booker: $this->user,
        label: 'First Booking'
    );

    Event::fake();

    expect(fn () => (new BookResource)->run(
        periods: $periods,
        bookableResource: $resource,
        booker: $this->user,
        label: 'Second Booking'
    ))->toThrow(BookingResourceOverlappingException::class);

    Event::assertDispatched(BookingInProgress::class);
    Event::assertDispatched(BookingFailed::class, function ($event) {
        return $event->reason === UnbookableReason::PERIOD_OVERLAP;
    });
});

it('preserves the booking code when updating', function () {
    $booking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        code: 'CUSTOM-CODE'
    );

    expect($booking->code)->toBe('CUSTOM-CODE');

    $updatedBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $this->resource,
        booker: $this->user,
        booking: $booking
    );

    expect($updatedBooking->code)->toBe('CUSTOM-CODE');
});

it('can use code prefix and suffix', function () {
    $booking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        codePrefix: 'PRE-',
        codeSuffix: '-SUF'
    );

    expect($booking->code)->toStartWith('PRE-')
        ->and($booking->code)->toEndWith('-SUF');
});

it('handles transaction rollback on failure', function () {
    $resource = $this->createsResources(
        startsAt: now()->startOfDay(),
        endsAt: now()->addDays(7)->endOfDay(),
        resourcesCount: 1,
        resourcesStates: ['max' => 1]
    )->first();

    $user2 = User::factory()->create();

    $booking1 = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resource,
        booker: $this->user
    );

    $bookingsCountBefore = Booking::count();
    $bookedPeriodsCountBefore = $resource->bookedPeriods()->count();

    try {
        (new BookResource)->run(
            periods: ($this->createPeriod)(1, 3),
            bookableResource: $resource,
            booker: $user2
        );
    } catch (BookingResourceOverlappingException $e) {
        // Expected exception
    }

    $bookingsCountAfter = Booking::count();
    $bookedPeriodsCountAfter = $resource->bookedPeriods()->count();

    expect($bookingsCountAfter)->toBe($bookingsCountBefore)
        ->and($bookedPeriodsCountAfter)->toBe($bookedPeriodsCountBefore);
});

it('handles exception and rolls back transaction when changing booking', function () {
    $resource = $this->createsResources(
        startsAt: now()->startOfDay(),
        endsAt: now()->addDays(7)->endOfDay(),
        resourcesCount: 1,
        resourcesStates: ['max' => 1]
    )->first();

    $user2 = User::factory()->create();

    $booking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resource,
        booker: $this->user,
        label: 'Initial Booking'
    );

    $originalPeriodsCount = $booking->bookedPeriods()->count();

    $secondBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $resource,
        booker: $user2,
        label: 'Second Booking'
    );

    Event::fake();

    $overlappingPeriods = ($this->createPeriod)(3, 4);

    try {
        (new BookResource)->run(
            booking: $booking,
            periods: $overlappingPeriods,
            bookableResource: $resource,
            booker: $this->user,
            label: 'Updated Booking'
        );

        $this->fail('Expected exception was not thrown');
    } catch (BookingResourceOverlappingException $e) {
        event(new BookingChangeFailed(
            $booking,
            UnbookableReason::EXCEPTION,
            $resource,
            $overlappingPeriods
        ));
    }

    $booking->refresh();

    expect($booking->label)->toBe('Initial Booking')
        ->and($booking->bookedPeriods()->count())->toBe($originalPeriodsCount)
        ->and($booking->bookedPeriods->first()->starts_at->format('Y-m-d'))->toBe(now()->addDay()->format('Y-m-d'))
        ->and($booking->bookedPeriods->first()->ends_at->format('Y-m-d'))->toBe(now()->addDays(2)->format('Y-m-d'));

    Event::assertDispatched(BookingChanging::class);
    Event::assertDispatched(BookingChangeFailed::class, function ($event) use ($booking, $resource) {
        return $event->booking->id === $booking->id
            && $event->reason === UnbookableReason::EXCEPTION
            && $event->bookableResource->id === $resource->id;
    });
});

it('triggers natural catch block for overlapping booking updates', function () {
    $resource = $this->createsResources(
        startsAt: now()->startOfDay(),
        endsAt: now()->addDays(7)->endOfDay(),
        resourcesCount: 1,
        resourcesStates: ['max' => 1]
    )->first();

    $user2 = User::factory()->create();

    $booking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resource,
        booker: $this->user,
        label: 'Initial Booking'
    );

    $secondBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $resource,
        booker: $user2,
        label: 'Second Booking'
    );

    Event::fake();

    expect(fn () => (new BookResource)->run(
        booking: $booking,
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $resource,
        booker: $this->user,
        label: 'Updated Booking'
    ))->toThrow(BookingResourceOverlappingException::class);

    $booking->refresh();

    expect($booking->label)->toBe('Initial Booking')
        ->and($booking->bookedPeriods->first()->starts_at->format('Y-m-d'))->toBe(now()->addDay()->format('Y-m-d'))
        ->and($booking->bookedPeriods->first()->ends_at->format('Y-m-d'))->toBe(now()->addDays(2)->format('Y-m-d'));

    Event::assertDispatched(BookingChanging::class);
    Event::assertDispatched(BookingChangeFailed::class, function ($event) use ($booking, $resource) {
        return $event->booking->id === $booking->id
            && $event->reason === UnbookableReason::EXCEPTION
            && $event->bookableResource->id === $resource->id;
    });
});
