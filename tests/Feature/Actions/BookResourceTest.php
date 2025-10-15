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
    $this->createPeriod = fn ($startDay, $endDay) => PeriodCollection::make(
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

it('can create a booking with a parent', function () {
    $parentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        label: 'Parent Booking'
    );

    Event::fake();

    $childBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        parent: $parentBooking,
        label: 'Child Booking'
    );

    expect($childBooking)->toBeInstanceOf(Booking::class)
        ->and($childBooking->parent_booking_id)->toBe($parentBooking->id)
        ->and($childBooking->parentBooking->id)->toBe($parentBooking->id)
        ->and($childBooking->label)->toBe('Child Booking');

    $parentBooking->refresh();

    expect($parentBooking->childBookings)->toHaveCount(1)
        ->and($parentBooking->childBookings->first()->id)->toBe($childBooking->id);

    Event::assertDispatched(BookingInProgress::class);
    Event::assertDispatched(BookingCompleted::class);
});

it('can create multiple child bookings linked to same parent', function () {
    $resources = $this->createsResources(
        startsAt: now()->startOfDay(),
        endsAt: now()->addDays(7)->endOfDay(),
        resourcesCount: 3
    );

    $parentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resources->first(),
        booker: $this->user,
        label: 'Parent Booking'
    );

    $childBooking1 = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resources->get(1),
        booker: $this->user,
        parent: $parentBooking,
        label: 'Child Booking 1'
    );

    $childBooking2 = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resources->get(2),
        booker: $this->user,
        parent: $parentBooking,
        label: 'Child Booking 2'
    );

    expect($childBooking1->parent_booking_id)->toBe($parentBooking->id)
        ->and($childBooking2->parent_booking_id)->toBe($parentBooking->id);

    $parentBooking->refresh();

    expect($parentBooking->childBookings)->toHaveCount(2)
        ->and($parentBooking->childBookings->pluck('id')->toArray())
        ->toContain($childBooking1->id, $childBooking2->id);
});

it('can update a booking to add a parent relationship', function () {
    $parentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        label: 'Parent Booking'
    );

    $booking = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $this->resource,
        booker: $this->user,
        label: 'Independent Booking'
    );

    expect($booking->parent_booking_id)->toBeNull();

    Event::fake();

    $updatedBooking = (new BookResource)->run(
        booking: $booking,
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $this->resource,
        booker: $this->user,
        parent: $parentBooking,
        label: 'Now Child Booking'
    );

    expect($updatedBooking->id)->toBe($booking->id)
        ->and($updatedBooking->parent_booking_id)->toBe($parentBooking->id)
        ->and($updatedBooking->parentBooking->id)->toBe($parentBooking->id)
        ->and($updatedBooking->label)->toBe('Now Child Booking');

    Event::assertDispatched(BookingChanging::class);
    Event::assertDispatched(BookingChanged::class);
});

it('can update a booking to change parent relationship', function () {
    $firstParent = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        label: 'First Parent'
    );

    $secondParent = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $this->resource,
        booker: $this->user,
        label: 'Second Parent'
    );

    $childBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(5, 6),
        bookableResource: $this->resource,
        booker: $this->user,
        parent: $firstParent,
        label: 'Child Booking'
    );

    expect($childBooking->parent_booking_id)->toBe($firstParent->id);

    Event::fake();

    $updatedBooking = (new BookResource)->run(
        booking: $childBooking,
        periods: ($this->createPeriod)(5, 6),
        bookableResource: $this->resource,
        booker: $this->user,
        parent: $secondParent,
        label: 'Child Booking'
    );

    expect($updatedBooking->id)->toBe($childBooking->id)
        ->and($updatedBooking->parent_booking_id)->toBe($secondParent->id)
        ->and($updatedBooking->parentBooking->id)->toBe($secondParent->id);

    $firstParent->refresh();
    $secondParent->refresh();

    expect($firstParent->childBookings)->toHaveCount(0)
        ->and($secondParent->childBookings)->toHaveCount(1)
        ->and($secondParent->childBookings->first()->id)->toBe($updatedBooking->id);

    Event::assertDispatched(BookingChanging::class);
    Event::assertDispatched(BookingChanged::class);
});

it('preserves parent_booking_id when updating without parent parameter', function () {
    $parentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        label: 'Parent Booking'
    );

    $childBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $this->resource,
        booker: $this->user,
        parent: $parentBooking,
        label: 'Child Booking'
    );

    expect($childBooking->parent_booking_id)->toBe($parentBooking->id);

    $updatedBooking = (new BookResource)->run(
        booking: $childBooking,
        periods: ($this->createPeriod)(5, 6),
        bookableResource: $this->resource,
        booker: $this->user,
        label: 'Updated Child Booking'
    );

    expect($updatedBooking->parent_booking_id)->toBe($parentBooking->id)
        ->and($updatedBooking->parentBooking->id)->toBe($parentBooking->id);
});

it('persists parent_booking_id correctly to database', function () {
    $parentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        label: 'Parent Booking'
    );

    $childBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $this->resource,
        booker: $this->user,
        parent: $parentBooking,
        label: 'Child Booking'
    );

    $retrievedChild = Booking::find($childBooking->id);

    expect($retrievedChild->parent_booking_id)->toBe($parentBooking->id)
        ->and($retrievedChild->parentBooking)->toBeInstanceOf(Booking::class)
        ->and($retrievedChild->parentBooking->id)->toBe($parentBooking->id);
});

it('does not create orphaned parent relationships on transaction rollback', function () {
    $resource = $this->createsResources(
        startsAt: now()->startOfDay(),
        endsAt: now()->addDays(7)->endOfDay(),
        resourcesCount: 1,
        resourcesStates: ['max' => 1]
    )->first();

    $parentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resource,
        booker: $this->user,
        label: 'Parent Booking'
    );

    $existingBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $resource,
        booker: $this->user,
        label: 'Existing Booking'
    );

    $bookingsCountBefore = Booking::count();

    try {
        (new BookResource)->run(
            periods: ($this->createPeriod)(3, 4),
            bookableResource: $resource,
            booker: $this->user,
            parent: $parentBooking,
            label: 'Overlapping Child Booking'
        );
    } catch (BookingResourceOverlappingException $e) {
        // Expected exception
    }

    $bookingsCountAfter = Booking::count();
    $parentBooking->refresh();

    expect($bookingsCountAfter)->toBe($bookingsCountBefore)
        ->and($parentBooking->childBookings)->toHaveCount(0);
});

it('can query parent and children together after booking', function () {
    $parentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        label: 'Parent Booking'
    );

    $childBooking1 = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $this->resource,
        booker: $this->user,
        parent: $parentBooking,
        label: 'Child Booking 1'
    );

    $childBooking2 = (new BookResource)->run(
        periods: ($this->createPeriod)(5, 6),
        bookableResource: $this->resource,
        booker: $this->user,
        parent: $parentBooking,
        label: 'Child Booking 2'
    );

    $loadedParent = Booking::with('childBookings')->find($parentBooking->id);
    $loadedChild = Booking::with('parentBooking')->find($childBooking1->id);

    expect($loadedParent->relationLoaded('childBookings'))->toBeTrue()
        ->and($loadedParent->childBookings)->toHaveCount(2)
        ->and($loadedChild->relationLoaded('parentBooking'))->toBeTrue()
        ->and($loadedChild->parentBooking->id)->toBe($parentBooking->id);
});

it('allows child booking to access parent data', function () {
    $parentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $this->resource,
        booker: $this->user,
        label: 'Parent Booking',
        meta: ['parent_data' => 'important']
    );

    $childBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $this->resource,
        booker: $this->user,
        parent: $parentBooking,
        label: 'Child Booking'
    );

    expect($childBooking->parentBooking->label)->toBe('Parent Booking')
        ->and($childBooking->parentBooking->meta['parent_data'])->toBe('important')
        ->and($childBooking->parentBooking->booker->id)->toBe($this->user->id);
});

it('allows parent to access all children data', function () {
    $resources = $this->createsResources(
        startsAt: now()->startOfDay(),
        endsAt: now()->addDays(7)->endOfDay(),
        resourcesCount: 3
    );

    $parentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resources->first(),
        booker: $this->user,
        label: 'Parent Booking'
    );

    $childBookings = collect([
        (new BookResource)->run(
            periods: ($this->createPeriod)(1, 2),
            bookableResource: $resources->get(1),
            booker: $this->user,
            parent: $parentBooking,
            label: 'Child 1',
            meta: ['resource_type' => 'parking']
        ),
        (new BookResource)->run(
            periods: ($this->createPeriod)(1, 2),
            bookableResource: $resources->get(2),
            booker: $this->user,
            parent: $parentBooking,
            label: 'Child 2',
            meta: ['resource_type' => 'meal']
        ),
    ]);

    $parentBooking->refresh();

    expect($parentBooking->childBookings)->toHaveCount(2)
        ->and($parentBooking->childBookings->pluck('label')->toArray())->toContain('Child 1', 'Child 2')
        ->and($parentBooking->childBookings->first()->meta['resource_type'])->toBeIn(['parking', 'meal']);
});

it('can create nested parent-child-grandchild bookings', function () {
    $resources = $this->createsResources(
        startsAt: now()->startOfDay(),
        endsAt: now()->addDays(7)->endOfDay(),
        resourcesCount: 3
    );

    $grandparentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resources->get(0),
        booker: $this->user,
        label: 'Grandparent Booking'
    );

    $parentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resources->get(1),
        booker: $this->user,
        parent: $grandparentBooking,
        label: 'Parent Booking'
    );

    $childBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resources->get(2),
        booker: $this->user,
        parent: $parentBooking,
        label: 'Child Booking'
    );

    expect($childBooking->parent_booking_id)->toBe($parentBooking->id)
        ->and($childBooking->parentBooking->id)->toBe($parentBooking->id)
        ->and($parentBooking->parent_booking_id)->toBe($grandparentBooking->id)
        ->and($parentBooking->parentBooking->id)->toBe($grandparentBooking->id);

    $grandparentBooking->refresh();
    $parentBooking->refresh();

    expect($grandparentBooking->childBookings)->toHaveCount(1)
        ->and($grandparentBooking->childBookings->first()->id)->toBe($parentBooking->id)
        ->and($parentBooking->childBookings)->toHaveCount(1)
        ->and($parentBooking->childBookings->first()->id)->toBe($childBooking->id);
});

it('preserves parent relationship when booking update fails', function () {
    $resource = $this->createsResources(
        startsAt: now()->startOfDay(),
        endsAt: now()->addDays(7)->endOfDay(),
        resourcesCount: 1,
        resourcesStates: ['max' => 1]
    )->first();

    $parentBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(1, 2),
        bookableResource: $resource,
        booker: $this->user,
        label: 'Parent Booking'
    );

    $childBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(3, 4),
        bookableResource: $resource,
        booker: $this->user,
        parent: $parentBooking,
        label: 'Child Booking'
    );

    $blockingBooking = (new BookResource)->run(
        periods: ($this->createPeriod)(5, 6),
        bookableResource: $resource,
        booker: $this->user,
        label: 'Blocking Booking'
    );

    $originalParentId = $childBooking->parent_booking_id;

    Event::fake();

    try {
        (new BookResource)->run(
            booking: $childBooking,
            periods: ($this->createPeriod)(5, 6),
            bookableResource: $resource,
            booker: $this->user,
            label: 'Updated Child Booking'
        );

        $this->fail('Expected exception was not thrown');
    } catch (BookingResourceOverlappingException $e) {
        // Expected exception
    }

    $childBooking->refresh();

    expect($childBooking->parent_booking_id)->toBe($originalParentId)
        ->and($childBooking->parentBooking->id)->toBe($parentBooking->id)
        ->and($childBooking->label)->toBe('Child Booking');

    Event::assertDispatched(BookingChanging::class);
    Event::assertDispatched(BookingChangeFailed::class);
});
