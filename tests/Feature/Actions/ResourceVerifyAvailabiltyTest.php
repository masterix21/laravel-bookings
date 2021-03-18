<?php

namespace Masterix21\Bookings\Tests\Feature\Actions;

use Masterix21\Bookings\Actions\VerifyAvailability;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookableTimetable;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\BookedResource;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Tests\TestCase;
use Masterix21\Bookings\Tests\TestClasses\User;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class ResourceVerifyAvailabiltyTest extends TestCase
{
    /** @test */
    public function it_returns_false_because_the_period_has_bookings()
    {
        BookableArea::factory()
            ->count(1)
            ->has(BookableResource::factory()->count(1))
            ->has(BookableTimetable::factory()->count(1)->state([
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ]))
            ->create();

        $bookableResource = BookableResource::first();

        User::factory()
            ->count(1)
            ->has(
                Booking::factory()->count(1)
                    ->has(BookedResource::factory()->count(1)->state([
                        'bookable_area_id' => $bookableResource->bookable_area_id,
                        'bookable_resource_id' => $bookableResource->id,
                    ]))
                    ->has(
                        BookedPeriod::factory()->count(1)->state([
                            'from_date' => now()->subMonth()->startOf('week')->format('Y-m-d'),
                            'to_date' => now()->endOf('week')->format('Y-m-d'),
                            'from_time' => '00:00:00',
                            'to_time' => '23:59:59',
                        ])
                    )
            )
            ->create();

        $result = VerifyAvailability::run(
            new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            ),
            $bookableResource
        );

        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_true_because_the_period_has_no_bookings()
    {
        BookableArea::factory()
            ->count(1)
            ->has(BookableResource::factory()->count(1))
            ->has(BookableTimetable::factory()->count(1)->state([
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ]))
            ->create();

        $result = VerifyAvailability::run(
            new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            ),
            BookableResource::first()
        );

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_true_because_the_period_is_in_the_available_timetable()
    {
        BookableArea::factory()
            ->count(1)
            ->has(BookableResource::factory()->count(1))
            ->has(BookableTimetable::factory()->count(1)->state([
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ]))
            ->create();

        $result = VerifyAvailability::run(
            new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->addDays(2)->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            ),
            BookableResource::first()
        );

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_because_the_periods_are_out_of_timetable()
    {
        BookableArea::factory()
            ->count(1)
            ->has(BookableResource::factory()->count(1))
            ->has(BookableTimetable::factory()->count(1)->state([
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ]))
            ->create();

        $result = VerifyAvailability::run(
            new PeriodCollection(
                Period::make(
                    now()->startOf('week')->format('Y-m-d'),
                    now()->endOf('week')->format('Y-m-d'),
                )
            ),
            BookableResource::first()
        );

        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_false_because_the_periods_is_within_timetable_but_monday_isnt_included()
    {
        BookableArea::factory()
            ->count(1)
            ->has(BookableResource::factory()->count(1))
            ->has(BookableTimetable::factory()->count(1)->state([
                'monday' => false,
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ]))
            ->create();

        $result = VerifyAvailability::run(
            new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            ),
            BookableResource::first()
        );

        $this->assertFalse($result);
    }
}
