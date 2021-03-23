<?php

namespace Masterix21\Bookings\Tests\Feature\Actions\Checks;

use Masterix21\Bookings\Actions\Checks\BookableHasAvailableSeats;
use Masterix21\Bookings\Actions\CreateBooking;
use Masterix21\Bookings\Exceptions\CheckAvailability\RelationsHaveNoSeatsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Tests\Concerns\CreatesAreasAndResources;
use Masterix21\Bookings\Tests\TestCase;
use Masterix21\Bookings\Tests\TestClasses\User;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class BookableHasAvailableSeatsTest extends TestCase
{
    use CreatesAreasAndResources;

    /** @test */
    public function it_works_because_bookable_area_has_bookable_area_relations_with_available_seats()
    {
        $this->createsAreasAndResources();

        $mainBookableArea = BookableArea::first();

        /** @var BookableArea $bookableArea */
        $bookableArea = BookableArea::factory()
            ->state(['is_bookable' => true])
            ->has(BookableResource::factory()->count(1)->state(['is_bookable' => true]))
            ->count(1)
            ->create()
            ->first();

        $result = BookableHasAvailableSeats::run(
            dates: app('bookings')->periodsToDates(periods: new PeriodCollection(Period::make(
                now()->subWeek()->startOf('week')->format('Y-m-d'),
                now()->subWeek()->endOf('week')->format('Y-m-d'),
            ))),
            bookable: $mainBookableArea,
            relations: collect([ $bookableArea ])
        );

        $this->assertNull($result);
    }

    /** @test */
    public function it_throw_exception_because_bookable_area_has_bookable_area_with_no_seats()
    {
        $this->createsAreasAndResources();

        $mainBookableArea = BookableArea::first();

        /** @var BookableArea $bookableArea */
        $bookableArea = BookableArea::factory()
            ->state(['is_bookable' => true])
            ->has(BookableResource::factory()->count(1)->state(['is_bookable' => true]))
            ->count(1)
            ->create()
            ->first();

        BookableRelation::factory()
            ->state([
                'parent_bookable_area_id' => $mainBookableArea->id,
                'parent_bookable_resource_id' => $mainBookableArea->bookableResources()->first()->id,
                'bookable_area_id' => $bookableArea->id,
                'bookable_resource_id' => $bookableArea->bookableResources()->first()->id,
            ])
            ->count(1)
            ->create();

        $user = User::factory()->count(1)->create()->first();

        CreateBooking::run(
            user: $user,
            periods: new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week'),
                    now()->subWeek()->endOf('week'),
                    Precision::SECOND()
                )
            ),
            bookableResource: $mainBookableArea->bookableResources()->first(),
            relations: BookableRelation::get(),
        );

        $this->expectException(RelationsHaveNoSeatsException::class);

        BookableHasAvailableSeats::run(
            dates: app('bookings')->periodsToDates(periods: new PeriodCollection(Period::make(
                now()->subWeek()->startOf('week')->format('Y-m-d'),
                now()->subWeek()->endOf('week')->format('Y-m-d'),
            ))),
            bookable: $mainBookableArea,
            relations: collect([ $bookableArea ])
        );
    }

    /** @test */
    public function it_works_because_bookable_area_has_bookable_resources_relations_with_available_seats()
    {
        $this->createsAreasAndResources();

        $mainBookableArea = BookableArea::first();

        /** @var BookableArea $bookableArea */
        $bookableArea = BookableArea::factory()
            ->state(['is_bookable' => true])
            ->has(BookableResource::factory()->count(1)->state(['is_bookable' => true]))
            ->count(1)
            ->create()
            ->first();

        $bookableResource = $bookableArea->bookableResources()->first();

        $result = BookableHasAvailableSeats::run(
            dates: app('bookings')->periodsToDates(periods: new PeriodCollection(Period::make(
                now()->subWeek()->startOf('week')->format('Y-m-d'),
                now()->subWeek()->endOf('week')->format('Y-m-d'),
            ))),
            bookable: $mainBookableArea,
            relations: collect([ $bookableResource ])
        );

        $this->assertNull($result);
    }
}
