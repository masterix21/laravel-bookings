<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Events\BookingChangeFailed;
use Masterix21\Bookings\Events\BookingFailed;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Tests\Concerns\CreatesResources;
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

    $this->periods = PeriodCollection::make(
        SpatiePeriod::make(
            now()->addDay()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d')
        )
    );
});

it('rolls back the transaction when a non-Exception throwable is thrown', function () {
    $baseLevel = DB::transactionLevel();

    try {
        (new BookResource)
            ->onBookingSaving(function () {
                throw new Error('non-exception failure');
            })
            ->run(
                periods: $this->periods,
                bookableResource: $this->resource,
                booker: $this->user,
            );

        $this->fail('An Error was expected to bubble up.');
    } catch (Error) {
    }

    expect(DB::transactionLevel())->toBe($baseLevel)
        ->and(Booking::query()->count())->toBe(0);
});

it('emits the failure event after the transaction has been rolled back', function () {
    $levelAtDispatch = null;

    Event::listen(BookingFailed::class, function () use (&$levelAtDispatch) {
        $levelAtDispatch = DB::transactionLevel();
    });

    $baseLevel = DB::transactionLevel();

    try {
        (new BookResource)
            ->onBookingSaving(function () {
                throw new RuntimeException('booking failure');
            })
            ->run(
                periods: $this->periods,
                bookableResource: $this->resource,
                booker: $this->user,
            );
    } catch (RuntimeException) {
    }

    expect($levelAtDispatch)->toBe($baseLevel);
});

it('emits BookingFailed when a creation fails after the booking has been saved', function () {
    $failed = false;
    $changeFailed = false;

    Event::listen(BookingFailed::class, function () use (&$failed) {
        $failed = true;
    });

    Event::listen(BookingChangeFailed::class, function () use (&$changeFailed) {
        $changeFailed = true;
    });

    try {
        (new BookResource)
            ->onBookingSaved(function () {
                throw new RuntimeException('failure after save');
            })
            ->run(
                periods: $this->periods,
                bookableResource: $this->resource,
                booker: $this->user,
            );
    } catch (RuntimeException) {
    }

    expect($failed)->toBeTrue()
        ->and($changeFailed)->toBeFalse();
});
