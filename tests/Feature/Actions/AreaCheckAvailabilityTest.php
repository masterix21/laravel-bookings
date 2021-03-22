<?php

namespace Masterix21\Bookings\Tests\Feature\Actions;

use Illuminate\Support\Facades\Event;
use Masterix21\Bookings\Actions\CheckAvailability;
use Masterix21\Bookings\Actions\CreateBooking;
use Masterix21\Bookings\Events\Booking\CreatedBooking;
use Masterix21\Bookings\Events\Booking\CreatingBooking;
use Masterix21\Bookings\Events\Booking\GeneratedBookedPeriods;
use Masterix21\Bookings\Events\Booking\GeneratingBookedPeriods;
use Masterix21\Bookings\Exceptions\CheckAvailability\NoSeatsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Tests\Concerns\CreatesAreasAndResources;
use Masterix21\Bookings\Tests\TestCase;
use Masterix21\Bookings\Tests\TestClasses\User;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class AreaCheckAvailabilityTest extends TestCase
{
    use CreatesAreasAndResources;

    /** @test */
    public function it_works_because_area_has_bookable_resources()
    {
        Event::fake();

        $this->createsAreasAndResources();

        $result = CheckAvailability::run(
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

                $startDate->addDay();
            });

        $result = CheckAvailability::run(
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

        $this->expectException(NoSeatsException::class);

        CheckAvailability::run(
            new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            ),
            BookableArea::first()
        );

        Event::assertDispatchedTimes(CreatingBooking::class, 3);
        Event::assertDispatchedTimes(GeneratingBookedPeriods::class, 3);
        Event::assertDispatchedTimes(GeneratedBookedPeriods::class, 3);
        Event::assertDispatchedTimes(CreatedBooking::class, 3);
    }
}
