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
use Masterix21\Bookings\Exceptions\VerifyAvailability\OutOfPlanningsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Tests\TestCase;
use Masterix21\Bookings\Tests\TestClasses\User;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class ResourceVerifyAvailabiltyTest extends TestCase
{
    /** @test */
    public function it_throws_unbookable_exception_because_the_period_has_bookings()
    {
        BookableArea::factory()
            ->count(1)
            ->has(BookableResource::factory()->count(1))
            ->has(BookablePlanning::factory()->count(1)->state([
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ]))
            ->create();

        $bookableResource = BookableResource::first();

        $user = User::factory()->count(1)->create()->first();

        Event::fake();

        CreateBooking::run(
            user: $user,
            periods: new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            ),
            bookableResource: $bookableResource
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
            $bookableResource
        );
    }

    /** @test */
    public function it_works_without_exception_because_the_period_has_no_bookings()
    {
        BookableArea::factory()
            ->count(1)
            ->has(BookableResource::factory()->count(1))
            ->has(BookablePlanning::factory()->count(1)->state([
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ]))
            ->create();

        $return = VerifyAvailability::run(
            new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            ),
            BookableResource::first()
        );

        $this->assertNull($return);
    }

    /** @test */
    public function it_throws_out_of_time_exception_because_the_periods_are_out_of_bookable_plannings()
    {
        BookableArea::factory()
            ->count(1)
            ->has(BookableResource::factory()->count(1))
            ->has(BookablePlanning::factory()->count(1)->state([
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ]))
            ->create();

        $this->expectException(OutOfPlanningsException::class);

        VerifyAvailability::run(
            new PeriodCollection(
                Period::make(
                    now()->startOf('week')->format('Y-m-d'),
                    now()->endOf('week')->format('Y-m-d'),
                )
            ),
            BookableResource::first()
        );
    }

    /** @test */
    public function it_throws_out_of_bookable_plannings_exception_because_the_periods_is_within_bookable_plannings_but_monday_isnt_included()
    {
        BookableArea::factory()
            ->count(1)
            ->has(BookableResource::factory()->count(1))
            ->has(BookablePlanning::factory()->count(1)->state([
                'monday' => false,
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ]))
            ->create();

        $this->expectException(OutOfPlanningsException::class);

        VerifyAvailability::run(
            new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            ),
            BookableResource::first()
        );
    }
}
