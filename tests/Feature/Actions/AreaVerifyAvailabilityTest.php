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

class AreaVerifyAvailabilityTest extends TestCase
{
    /** @test */
    public function it_returns_true_because_area_has_resources_not_booked_and_with_a_valid_timetable()
    {
        BookableArea::factory()
            ->count(1)
            ->has(BookableResource::factory()->count(3))
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
            BookableArea::first()
        );

        $this->assertTrue($result);

        $startDate = now()->subWeek()->startOf('week');

        $resources = BookableResource::query()->get();

        $resources->take(2)
            ->each(function (BookableResource $bookableResource) use (&$startDate) {
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
                                    'from_date' => $startDate->format('Y-m-d'),
                                    'to_date' => $startDate->format('Y-m-d'),
                                    'from_time' => '00:00:00',
                                    'to_time' => '23:59:59',
                                ])
                            )
                    )
                    ->create();

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

        $this->assertTrue($result);

        User::factory()
            ->count(1)
            ->has(
                Booking::factory()->count(1)
                    ->has(BookedResource::factory()->count(1)->state([
                        'bookable_area_id' => $resources->last()->bookable_area_id,
                        'bookable_resource_id' => $resources->last()->id,
                    ]))
                    ->has(
                        BookedPeriod::factory()->count(1)->state([
                            'from_date' => $startDate->format('Y-m-d'),
                            'to_date' => $startDate->format('Y-m-d'),
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
            BookableArea::first()
        );

        $this->assertFalse($result);
    }
}
