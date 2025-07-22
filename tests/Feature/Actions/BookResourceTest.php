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
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Period;
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
});

it('can book a resource', function () {
    // Create a bookable resource
    $resource = $this->createsResources(
        fromDate: now()->startOfDay(),
        toDate: now()->addDays(7)->endOfDay(),
        resourcesCount: 1
    )->first();

    // Create a user as booker
    $user = User::factory()->create();

    // Create a product as relatable
    $product = Product::factory()->create();

    // Create a period collection
    $periods = PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDay()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d')
        )
    );

    // Book the resource
    $booking = (new BookResource())->run(
        periods: $periods,
        bookableResource: $resource,
        booker: $user,
        relatable: $product,
        label: 'Test Booking',
        note: 'Test Note',
        meta: ['test' => 'value']
    );

    // Assert the booking was created
    expect($booking)->toBeInstanceOf(Booking::class)
        ->and($booking->booker_type)->toBe(User::class)
        ->and($booking->booker_id)->toBe($user->id)
        ->and($booking->label)->toBe('Test Booking')
        ->and($booking->note)->toBe('Test Note')
        ->and($booking->meta->toArray())->toBe(['test' => 'value']);

    // Assert the booked periods were created
    expect($booking->bookedPeriods)->toHaveCount(1)
        ->and($booking->bookedPeriods->first()->bookable_resource_id)->toBe($resource->id)
        ->and($booking->bookedPeriods->first()->relatable_type)->toBe(Product::class)
        ->and($booking->bookedPeriods->first()->relatable_id)->toBe($product->id)
        ->and($booking->bookedPeriods->first()->starts_at->format('Y-m-d'))->toBe(now()->addDay()->format('Y-m-d'))
        ->and($booking->bookedPeriods->first()->ends_at->format('Y-m-d'))->toBe(now()->addDays(2)->format('Y-m-d'));

    // Assert events were dispatched
    Event::assertDispatched(BookingInProgress::class);
    Event::assertDispatched(BookingCompleted::class);
});

it('can update an existing booking', function () {
    // Create a bookable resource
    $resource = $this->createsResources(
        fromDate: now()->startOfDay(),
        toDate: now()->addDays(7)->endOfDay(),
        resourcesCount: 1
    )->first();

    // Create users and products
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();

    // Create initial periods
    $initialPeriods = PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDay()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d')
        )
    );

    // Create initial booking
    $booking = (new BookResource())->run(
        periods: $initialPeriods,
        bookableResource: $resource,
        booker: $user1,
        relatable: $product1,
        label: 'Initial Booking',
        note: 'Initial Note',
        meta: ['initial' => 'value']
    );

    // Clear events
    Event::fake();

    // Create updated periods
    $updatedPeriods = PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDays(3)->format('Y-m-d'),
            now()->addDays(4)->format('Y-m-d')
        )
    );

    // Update the booking
    $updatedBooking = (new BookResource())->run(
        periods: $updatedPeriods,
        bookableResource: $resource,
        booker: $user2,
        booking: $booking,
        relatable: $product2,
        label: 'Updated Booking',
        note: 'Updated Note',
        meta: ['updated' => 'value']
    );

    // Assert the booking was updated
    expect($updatedBooking)->toBeInstanceOf(Booking::class)
        ->and($updatedBooking->id)->toBe($booking->id)
        ->and($updatedBooking->booker_type)->toBe(User::class)
        ->and($updatedBooking->booker_id)->toBe($user2->id)
        ->and($updatedBooking->label)->toBe('Updated Booking')
        ->and($updatedBooking->note)->toBe('Updated Note')
        ->and($updatedBooking->meta->toArray())->toBe(['updated' => 'value']);

    // Assert the booked periods were updated
    expect($updatedBooking->bookedPeriods)->toHaveCount(1)
        ->and($updatedBooking->bookedPeriods->first()->bookable_resource_id)->toBe($resource->id)
        ->and($updatedBooking->bookedPeriods->first()->relatable_type)->toBe(Product::class)
        ->and($updatedBooking->bookedPeriods->first()->relatable_id)->toBe($product2->id)
        ->and($updatedBooking->bookedPeriods->first()->starts_at->format('Y-m-d'))->toBe(now()->addDays(3)->format('Y-m-d'))
        ->and($updatedBooking->bookedPeriods->first()->ends_at->format('Y-m-d'))->toBe(now()->addDays(4)->format('Y-m-d'));

    // Assert events were dispatched
    Event::assertDispatched(BookingChanging::class);
    Event::assertDispatched(BookingChanged::class);
});

it('fails when booking overlapping periods', function () {
    // Create a bookable resource with max=1
    $resource = $this->createsResources(
        fromDate: now()->startOfDay(),
        toDate: now()->addDays(7)->endOfDay(),
        resourcesCount: 1,
        resourcesStates: ['max' => 1]
    )->first();

    // Create a user as booker
    $user = User::factory()->create();

    // Create a period collection
    $periods = PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDay()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d')
        )
    );

    // Book the resource first time
    $booking = (new BookResource())->run(
        periods: $periods,
        bookableResource: $resource,
        booker: $user,
        label: 'First Booking'
    );

    // Clear events
    Event::fake();

    // Try to book the same period again
    expect(fn () => (new BookResource())->run(
        periods: $periods,
        bookableResource: $resource,
        booker: $user,
        label: 'Second Booking'
    ))->toThrow(BookingResourceOverlappingException::class);

    // Assert events were dispatched
    Event::assertDispatched(BookingInProgress::class);
    Event::assertDispatched(BookingFailed::class, function ($event) {
        return $event->reason === UnbookableReason::PERIOD_OVERLAP;
    });
});

it('preserves the booking code when updating', function () {
    // Create a bookable resource
    $resource = $this->createsResources(
        fromDate: now()->startOfDay(),
        toDate: now()->addDays(7)->endOfDay(),
        resourcesCount: 1
    )->first();

    // Create a user as booker
    $user = User::factory()->create();

    // Create initial periods
    $initialPeriods = PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDay()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d')
        )
    );

    // Create initial booking with a specific code
    $booking = (new BookResource())->run(
        periods: $initialPeriods,
        bookableResource: $resource,
        booker: $user,
        code: 'CUSTOM-CODE'
    );

    // Assert the code was set
    expect($booking->code)->toBe('CUSTOM-CODE');

    // Create updated periods
    $updatedPeriods = PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDays(3)->format('Y-m-d'),
            now()->addDays(4)->format('Y-m-d')
        )
    );

    // Update the booking without specifying a code
    $updatedBooking = (new BookResource())->run(
        periods: $updatedPeriods,
        bookableResource: $resource,
        booker: $user,
        booking: $booking
    );

    // Assert the code was preserved
    expect($updatedBooking->code)->toBe('CUSTOM-CODE');
});

it('can use code prefix and suffix', function () {
    // Create a bookable resource
    $resource = $this->createsResources(
        fromDate: now()->startOfDay(),
        toDate: now()->addDays(7)->endOfDay(),
        resourcesCount: 1
    )->first();

    // Create a user as booker
    $user = User::factory()->create();

    // Create a period collection
    $periods = PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDay()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d')
        )
    );

    // Book the resource with code prefix and suffix
    $booking = (new BookResource())->run(
        periods: $periods,
        bookableResource: $resource,
        booker: $user,
        codePrefix: 'PRE-',
        codeSuffix: '-SUF'
    );

    // Assert the code has the prefix and suffix
    expect($booking->code)->toStartWith('PRE-')
        ->and($booking->code)->toEndWith('-SUF');
});

it('handles transaction rollback on failure', function () {
    // Create a bookable resource
    $resource = $this->createsResources(
        fromDate: now()->startOfDay(),
        toDate: now()->addDays(7)->endOfDay(),
        resourcesCount: 1,
        resourcesStates: ['max' => 1]
    )->first();

    // Create users
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Create overlapping periods
    $periods1 = PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDay()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d')
        )
    );

    $periods2 = PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDays(1)->format('Y-m-d'),
            now()->addDays(3)->format('Y-m-d')
        )
    );

    // Book the resource first time
    $booking1 = (new BookResource())->run(
        periods: $periods1,
        bookableResource: $resource,
        booker: $user1
    );

    // Count bookings before attempting the second booking
    $bookingsCountBefore = Booking::count();
    $bookedPeriodsCountBefore = $resource->bookedPeriods()->count();

    // Try to book overlapping period (should fail)
    try {
        (new BookResource())->run(
            periods: $periods2,
            bookableResource: $resource,
            booker: $user2
        );
    } catch (BookingResourceOverlappingException $e) {
        // Expected exception
    }

    // Count bookings after the failed attempt
    $bookingsCountAfter = Booking::count();
    $bookedPeriodsCountAfter = $resource->bookedPeriods()->count();

    // Assert no new bookings or booked periods were created
    expect($bookingsCountAfter)->toBe($bookingsCountBefore)
        ->and($bookedPeriodsCountAfter)->toBe($bookedPeriodsCountBefore);
});
