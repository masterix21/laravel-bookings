<?php

namespace Masterix21\Bookings\Tests\Feature\Actions;

use Illuminate\Support\Facades\Event;
use Masterix21\Bookings\Actions\CreateBooking;
use Masterix21\Bookings\Actions\CheckAvailability;
use Masterix21\Bookings\Events\Booking\CreatedBooking;
use Masterix21\Bookings\Events\Booking\CreatingBooking;
use Masterix21\Bookings\Events\Booking\GeneratedBookedPeriods;
use Masterix21\Bookings\Events\Booking\GeneratingBookedPeriods;
use Masterix21\Bookings\Exceptions\CheckAvailability\NoSeatsException;
use Masterix21\Bookings\Exceptions\CheckAvailability\OutOfPlanningsException;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Tests\Concerns\CreatesAreasAndResources;
use Masterix21\Bookings\Tests\TestCase;
use Masterix21\Bookings\Tests\TestClasses\User;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class ResourceCheckAvailabiltyTest extends TestCase
{
    use CreatesAreasAndResources;

    /** @test */
    public function it_throws_no_seats_exception_because_the_period_has_bookings()
    {
        $this->createsAreasAndResources();

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

        Event::assertDispatched(CreatingBooking::class);
        Event::assertDispatched(GeneratingBookedPeriods::class);
        Event::assertDispatched(GeneratedBookedPeriods::class);
        Event::assertDispatched(CreatedBooking::class);

        $this->expectException(NoSeatsException::class);

        CheckAvailability::run(
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
        $this->createsAreasAndResources();

        $return = CheckAvailability::run(
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
        $this->createsAreasAndResources();

        $this->expectException(OutOfPlanningsException::class);

        CheckAvailability::run(
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
        $this->createsAreasAndResources(planningsStates: ['monday' => false]);

        $this->expectException(OutOfPlanningsException::class);

        CheckAvailability::run(
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
