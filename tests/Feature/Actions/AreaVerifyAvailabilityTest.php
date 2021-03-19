<?php

namespace Masterix21\Bookings\Tests\Feature\Actions;

use Illuminate\Support\Facades\Event;
use Masterix21\Bookings\Actions\CreateBooking;
use Masterix21\Bookings\Actions\VerifyAvailability;
use Masterix21\Bookings\Events\Booking\RefreshedBookedPeriods;
use Masterix21\Bookings\Events\Booking\RefreshedBooking;
use Masterix21\Bookings\Events\Booking\RefreshingBookedPeriods;
use Masterix21\Bookings\Events\Booking\RefreshingBooking;
use Masterix21\Bookings\Exceptions\VerifyAvailability\NoSeatsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Tests\TestCase;
use Masterix21\Bookings\Tests\TestClasses\User;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class AreaVerifyAvailabilityTest extends TestCase
{
    /** @test */
    public function it_works_because_area_has_bookable_resources()
    {
        Event::fake();

        BookableArea::factory()
            ->count(1)
            ->has(BookableResource::factory()->count(3))
            ->has(BookablePlanning::factory()->count(1)->state([
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ]))
            ->create();

        $result = VerifyAvailability::run(
            periods: new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            ),
            bookable: BookableArea::first(),
        );

        $this->assertNull($result);

        $startDate = now()->subWeek()->startOf('week');

        $resources = BookableResource::query()->get();

        $resources->take(2)
            ->each(function (BookableResource $bookableResource) use (&$startDate) {
                $user = User::factory()->count(1)->create()->first();

                CreateBooking::run(
                    user: $user,
                    periods: new PeriodCollection(
                        Period::make($startDate->format('Y-m-d') .' 00:00:00', $startDate->format('Y-m-d') . ' 23:59:59', Precision::SECOND())
                    ),
                    bookableResource: $bookableResource
                );

                Event::assertDispatched(RefreshingBooking::class);
                Event::assertDispatched(RefreshingBookedPeriods::class);
                Event::assertDispatched(RefreshedBooking::class);
                Event::assertDispatched(RefreshedBookedPeriods::class);

                $startDate->addDay();
            });

        $result = VerifyAvailability::run(
            new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            ),
            BookableArea::first()
        );

        $this->assertNull($result);

        $user = User::factory()->count(1)->create()->first();

        CreateBooking::run(
            user: $user,
            periods: new PeriodCollection(
                Period::make($startDate->format('Y-m-d') .' 00:00:00', $startDate->format('Y-m-d') . ' 23:59:59', Precision::SECOND())
            ),
            bookableResource: $resources->last()
        );

        Event::assertDispatched(RefreshingBooking::class);
        Event::assertDispatched(RefreshingBookedPeriods::class);
        Event::assertDispatched(RefreshedBooking::class);
        Event::assertDispatched(RefreshedBookedPeriods::class);

        $this->expectException(NoSeatsException::class);

        VerifyAvailability::run(
            new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            ),
            BookableArea::first()
        );
    }
}
