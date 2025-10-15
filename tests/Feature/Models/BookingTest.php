<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Tests\database\factories\BookedPeriodFactory;
use Masterix21\Bookings\Tests\database\factories\BookingFactory;
use Masterix21\Bookings\Tests\TestClasses\User;

uses(RefreshDatabase::class);

it('has booker morphTo relationship', function () {
    $user = User::factory()->create();
    $booking = BookingFactory::new()->create([
        'booker_type' => User::class,
        'booker_id' => $user->id,
    ]);

    expect($booking->booker())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class)
        ->and($booking->booker)->toBeInstanceOf(User::class)
        ->and($booking->booker->id)->toBe($user->id);
});

it('has bookedPeriod hasOne relationship', function () {
    $booking = BookingFactory::new()->create();
    $bookedPeriod = BookedPeriodFactory::new()->create(['booking_id' => $booking->id]);

    expect($booking->bookedPeriod())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class)
        ->and($booking->bookedPeriod)->toBeInstanceOf(BookedPeriod::class)
        ->and($booking->bookedPeriod->id)->toBe($bookedPeriod->id);
});

it('has bookedPeriods hasMany relationship', function () {
    $booking = BookingFactory::new()->create();
    $bookedPeriod1 = BookedPeriodFactory::new()->create(['booking_id' => $booking->id]);
    $bookedPeriod2 = BookedPeriodFactory::new()->create(['booking_id' => $booking->id]);

    expect($booking->bookedPeriods())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($booking->bookedPeriods)->toHaveCount(2)
        ->and($booking->bookedPeriods->pluck('id')->toArray())->toContain($bookedPeriod1->id, $bookedPeriod2->id);
});

it('casts meta to ArrayObject', function () {
    $meta = ['key1' => 'value1', 'key2' => 'value2'];
    $booking = BookingFactory::new()->create(['meta' => $meta]);

    expect($booking->meta)->toBeInstanceOf(ArrayObject::class)
        ->and($booking->meta['key1'])->toBe('value1')
        ->and($booking->meta['key2'])->toBe('value2');
});

it('allows mass assignment for all attributes', function () {
    $user = User::factory()->create();
    $attributes = [
        'code' => 'BOOK-001',
        'booker_type' => User::class,
        'booker_id' => $user->id,
        'meta' => ['custom' => 'data'],
    ];

    $booking = new Booking($attributes);

    expect($booking->code)->toBe('BOOK-001')
        ->and($booking->booker_type)->toBe(User::class)
        ->and($booking->booker_id)->toBe($user->id)
        ->and($booking->meta)->toBeInstanceOf(ArrayObject::class)
        ->and($booking->meta['custom'])->toBe('data');
});

it('uses HasFactory trait', function () {
    expect(Booking::factory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Factories\Factory::class);
});

it('uses UsesBookedPeriods trait', function () {
    $booking = new Booking;

    expect(method_exists($booking, 'getBookedPeriods'))->toBeTrue();
});

it('uses UsesGenerateBookedPeriods trait', function () {
    $booking = new Booking;

    // Check that the trait is used (method should exist)
    expect(in_array('Masterix21\Bookings\Models\Concerns\UsesGenerateBookedPeriods', class_uses($booking)))->toBeTrue();
});

it('creates booking with different booker types', function () {
    $user = User::factory()->create();

    $booking = Booking::create([
        'code' => 'TEST-001',
        'booker_type' => User::class,
        'booker_id' => $user->id,
    ]);

    expect($booking->exists)->toBeTrue()
        ->and($booking->booker_type)->toBe(User::class)
        ->and($booking->booker_id)->toBe($user->id)
        ->and($booking->booker)->toBeInstanceOf(User::class);
});

it('persists meta data correctly', function () {
    $user = User::factory()->create();
    $metaData = [
        'preferences' => ['theme' => 'dark'],
        'notes' => 'Special requirements',
        'tags' => ['vip', 'priority'],
    ];

    $booking = Booking::create([
        'code' => 'META-001',
        'booker_type' => User::class,
        'booker_id' => $user->id,
        'meta' => $metaData,
    ]);

    $retrieved = Booking::find($booking->id);

    expect($retrieved->meta)->toBeInstanceOf(ArrayObject::class)
        ->and($retrieved->meta['preferences'])->toBe(['theme' => 'dark'])
        ->and($retrieved->meta['notes'])->toBe('Special requirements')
        ->and($retrieved->meta['tags'])->toBe(['vip', 'priority']);
});

it('handles null meta data', function () {
    $booking = BookingFactory::new()->create(['meta' => null]);

    expect($booking->meta)->toBeNull();
});

it('handles empty meta data', function () {
    $booking = BookingFactory::new()->create(['meta' => []]);

    expect($booking->meta)->toBeInstanceOf(ArrayObject::class)
        ->and(count($booking->meta))->toBe(0);
});

it('has parentBooking belongsTo relationship', function () {
    $parentBooking = BookingFactory::new()->create();
    $childBooking = BookingFactory::new()->create([
        'parent_booking_id' => $parentBooking->id,
    ]);

    expect($childBooking->parentBooking())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($childBooking->parentBooking)->toBeInstanceOf(Booking::class)
        ->and($childBooking->parentBooking->id)->toBe($parentBooking->id);
});

it('has childBookings hasMany relationship', function () {
    $parentBooking = BookingFactory::new()->create();
    $childBooking1 = BookingFactory::new()->create(['parent_booking_id' => $parentBooking->id]);
    $childBooking2 = BookingFactory::new()->create(['parent_booking_id' => $parentBooking->id]);

    expect($parentBooking->childBookings())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($parentBooking->childBookings)->toHaveCount(2)
        ->and($parentBooking->childBookings->pluck('id')->toArray())->toContain($childBooking1->id, $childBooking2->id);
});

it('returns null for parentBooking when booking has no parent', function () {
    $booking = BookingFactory::new()->create(['parent_booking_id' => null]);

    expect($booking->parentBooking)->toBeNull();
});

it('returns empty collection for childBookings when booking has no children', function () {
    $booking = BookingFactory::new()->create();

    expect($booking->childBookings)->toHaveCount(0)
        ->and($booking->childBookings)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

it('allows a booking to have multiple child bookings', function () {
    $parentBooking = BookingFactory::new()->create();
    $childBookings = collect([
        BookingFactory::new()->create(['parent_booking_id' => $parentBooking->id]),
        BookingFactory::new()->create(['parent_booking_id' => $parentBooking->id]),
        BookingFactory::new()->create(['parent_booking_id' => $parentBooking->id]),
    ]);

    $parentBooking->refresh();

    expect($parentBooking->childBookings)->toHaveCount(3)
        ->and($parentBooking->childBookings->pluck('id')->sort()->values()->toArray())
        ->toBe($childBookings->pluck('id')->sort()->values()->toArray());
});

it('sets child parent_booking_id to null when parent is deleted', function () {
    $parentBooking = BookingFactory::new()->create();
    $childBooking1 = BookingFactory::new()->create(['parent_booking_id' => $parentBooking->id]);
    $childBooking2 = BookingFactory::new()->create(['parent_booking_id' => $parentBooking->id]);

    expect($childBooking1->parent_booking_id)->toBe($parentBooking->id)
        ->and($childBooking2->parent_booking_id)->toBe($parentBooking->id);

    $parentBooking->delete();

    $childBooking1->refresh();
    $childBooking2->refresh();

    expect($childBooking1->parent_booking_id)->toBeNull()
        ->and($childBooking2->parent_booking_id)->toBeNull()
        ->and($childBooking1->exists)->toBeTrue()
        ->and($childBooking2->exists)->toBeTrue();
});

it('allows nested parent-child relationships', function () {
    $grandparentBooking = BookingFactory::new()->create();
    $parentBooking = BookingFactory::new()->create(['parent_booking_id' => $grandparentBooking->id]);
    $childBooking = BookingFactory::new()->create(['parent_booking_id' => $parentBooking->id]);

    expect($childBooking->parentBooking->id)->toBe($parentBooking->id)
        ->and($parentBooking->parentBooking->id)->toBe($grandparentBooking->id)
        ->and($grandparentBooking->childBookings)->toHaveCount(1)
        ->and($grandparentBooking->childBookings->first()->id)->toBe($parentBooking->id)
        ->and($parentBooking->childBookings)->toHaveCount(1)
        ->and($parentBooking->childBookings->first()->id)->toBe($childBooking->id);
});

it('allows mass assignment for parent_booking_id', function () {
    $parentBooking = BookingFactory::new()->create();
    $user = User::factory()->create();

    $childBooking = new Booking([
        'parent_booking_id' => $parentBooking->id,
        'code' => 'CHILD-001',
        'booker_type' => User::class,
        'booker_id' => $user->id,
    ]);

    expect($childBooking->parent_booking_id)->toBe($parentBooking->id);
});

it('can query parent and children together using eager loading', function () {
    $parentBooking = BookingFactory::new()->create();
    $childBooking1 = BookingFactory::new()->create(['parent_booking_id' => $parentBooking->id]);
    $childBooking2 = BookingFactory::new()->create(['parent_booking_id' => $parentBooking->id]);

    $loadedParent = Booking::with('childBookings')->find($parentBooking->id);
    $loadedChild = Booking::with('parentBooking')->find($childBooking1->id);

    expect($loadedParent->relationLoaded('childBookings'))->toBeTrue()
        ->and($loadedParent->childBookings)->toHaveCount(2)
        ->and($loadedChild->relationLoaded('parentBooking'))->toBeTrue()
        ->and($loadedChild->parentBooking->id)->toBe($parentBooking->id);
});

it('persists parent_booking_id correctly to database', function () {
    $parentBooking = BookingFactory::new()->create();
    $user = User::factory()->create();

    $childBooking = Booking::create([
        'parent_booking_id' => $parentBooking->id,
        'code' => 'TEST-CHILD',
        'booker_type' => User::class,
        'booker_id' => $user->id,
    ]);

    $retrieved = Booking::find($childBooking->id);

    expect($retrieved->parent_booking_id)->toBe($parentBooking->id)
        ->and($retrieved->parentBooking->id)->toBe($parentBooking->id);
});
