<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Tests\Concerns\CreatesResources;
use Masterix21\Bookings\Tests\TestClasses\Product;
use Masterix21\Bookings\Tests\TestClasses\User;
use Spatie\Period\Period as SpatiePeriod;
use Spatie\Period\PeriodCollection;
use Spatie\TestTime\TestTime;

uses(CreatesResources::class);

beforeEach(function () {
    TestTime::freeze();

    $this->resource = $this->createsResources(
        startsAt: now()->startOfDay(),
        endsAt: now()->addDays(7)->endOfDay(),
        resourcesCount: 1,
        resourcesStates: ['max' => 10]
    )->first();

    $this->user = User::factory()->create();
    $this->product = Product::factory()->create();

    Relation::morphMap([
        'user' => User::class,
        'product' => Product::class,
    ]);

    $this->periods = PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDay()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d')
        )
    );
});

afterEach(function () {
    Relation::morphMap([], false);
});

it('stores the morph alias for booker and relatable when creating', function () {
    $booking = (new BookResource)->run(
        periods: $this->periods,
        bookableResource: $this->resource,
        booker: $this->user,
        relatable: $this->product,
    );

    expect($booking->booker_type)->toBe('user')
        ->and($booking->bookedPeriods()->first()->relatable_type)->toBe('product');
});

it('stores the morph alias for booker when updating', function () {
    $booking = (new BookResource)->run(
        periods: $this->periods,
        bookableResource: $this->resource,
        booker: $this->user,
    );

    $newBooker = User::factory()->create();

    $updatedBooking = (new BookResource)->run(
        periods: $this->periods,
        bookableResource: $this->resource,
        booker: $newBooker,
        booking: $booking,
    );

    expect($updatedBooking->booker_type)->toBe('user')
        ->and($updatedBooking->booker_id)->toBe($newBooker->id);
});
