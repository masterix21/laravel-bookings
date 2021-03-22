<?php

namespace Masterix21\Bookings\Tests\Feature\Actions\Checks;

use Masterix21\Bookings\Actions\Checks\BookableHasValidPlannings;
use Masterix21\Bookings\Exceptions\CheckAvailability\OutOfPlanningsException;
use Masterix21\Bookings\Exceptions\CheckAvailability\RelationsHaveNoSeatsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Tests\Concerns\CreatesAreasAndResources;
use Masterix21\Bookings\Tests\TestCase;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class BookableHasValidPlanningsTest extends TestCase
{
    use CreatesAreasAndResources;

    /** @test */
    public function it_throws_an_exception_because_bookable_area_has_no_plannings()
    {
        $bookableArea = BookableArea::factory()->count(1)
            ->has(BookableResource::factory()->count(1))
            ->create()
            ->first();

        $this->expectException(OutOfPlanningsException::class);

        BookableHasValidPlannings::run(
            dates: collect([now()]),
            bookable: $bookableArea
        );
    }

    /** @test */
    public function it_throws_an_exception_because_bookable_resource_has_no_plannings()
    {
        BookableArea::factory()->count(1)
            ->has(BookableResource::factory()->count(1))
            ->create();

        $this->expectException(OutOfPlanningsException::class);

        BookableHasValidPlannings::run(
            dates: collect([now()]),
            bookable: BookableResource::first()
        );
    }

    /** @test */
    public function it_works_because_bookable_area_has_a_valid_planning()
    {
        $this->createsAreasAndResources();

        $return = BookableHasValidPlannings::run(
            dates: app('bookings')->periodsToDates(periods: new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            )),
            bookable: BookableArea::first()
        );

        $this->assertNull($return);
    }

    /** @test */
    public function it_works_because_bookable_resource_has_a_valid_planning()
    {
        $this->createsAreasAndResources();

        $return = BookableHasValidPlannings::run(
            dates: app('bookings')->periodsToDates(periods: new PeriodCollection(
                Period::make(
                    now()->subWeek()->startOf('week')->format('Y-m-d'),
                    now()->subWeek()->endOf('week')->format('Y-m-d'),
                )
            )),
            bookable: BookableResource::first()
        );

        $this->assertNull($return);
    }

    /** @test */
    public function it_throws_an_exception_because_bookable_area_has_bookable_resource_in_relations_with_invalid_planning()
    {
        $this->createsAreasAndResources();

        /** @var BookableArea $mainBookableArea */
        $mainBookableArea = BookableArea::first();

        /** @var BookableArea $bookableArea */
        $bookableArea = BookableArea::factory()
            ->has(BookableResource::factory()->count(1))
            ->count(1)
            ->create()
            ->first();

        $bookableResource = $bookableArea->bookableResources()->first();

        BookablePlanning::factory()
            ->state([
                'bookable_area_id' => $bookableArea->id,
                'bookable_resource_id' => $bookableResource->id,
                'from_date' => now()->subYear()->format('Y-m-d'),
                'to_date' => now()->subYear()->format('Y-m-d'),
                'from_time' => '08:00:00',
                'to_time' => '08:59:59',
            ])
            ->create();

        BookableRelation::factory()
            ->state([
                'parent_bookable_area_id' => $mainBookableArea->id,
                'bookable_area_id' => $bookableArea->id,
            ])
            ->create();

        $this->expectException(RelationsHaveNoSeatsException::class);

        BookableHasValidPlannings::run(
            dates: app('bookings')->periodsToDates(periods: new PeriodCollection(Period::make(
                now()->subWeek()->startOf('week')->format('Y-m-d'),
                now()->subWeek()->endOf('week')->format('Y-m-d'),
            ))),
            bookable: $mainBookableArea->bookableResources()->first(),
            relations: collect([ $bookableArea ])
        );
    }

    /** @test */
    public function it_works_because_bookable_area_has_bookable_resource_in_relations_with_valid_planning()
    {
        $this->createsAreasAndResources();

        /** @var BookableArea $mainBookableArea */
        $mainBookableArea = BookableArea::first();

        /** @var BookableArea $bookableArea */
        $bookableArea = BookableArea::factory()
            ->has(BookableResource::factory()->count(1))
            ->count(1)
            ->create()
            ->first();

        $bookableResource = $bookableArea->bookableResources()->first();

        BookablePlanning::factory()
            ->state([
                'bookable_area_id' => $bookableArea->id,
                'bookable_resource_id' => $bookableResource->id,
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ])
            ->create();

        BookableRelation::factory()
            ->state([
                'parent_bookable_area_id' => $mainBookableArea->id,
                'bookable_area_id' => $bookableArea->id,
            ])
            ->create();

        $result = BookableHasValidPlannings::run(
            dates: app('bookings')->periodsToDates(periods: new PeriodCollection(Period::make(
                now()->subWeek()->startOf('week')->format('Y-m-d'),
                now()->subWeek()->endOf('week')->format('Y-m-d'),
            ))),
            bookable: $mainBookableArea->bookableResources()->first(),
            relations: collect([ $bookableArea ])
        );

        $this->assertNull($result);
    }

    /** @test */
    public function it_throws_an_exception_because_bookable_area_has_bookable_area_in_relations_with_invalid_planning()
    {
        $this->createsAreasAndResources();

        /** @var BookableArea $mainBookableArea */
        $mainBookableArea = BookableArea::first();

        /** @var BookableArea $bookableArea */
        $bookableArea = BookableArea::factory()
            ->has(BookableResource::factory()->count(1))
            ->count(1)
            ->create()
            ->first();

        BookablePlanning::factory()
            ->state([
                'bookable_area_id' => $bookableArea->id,
                'from_date' => now()->subYear()->format('Y-m-d'),
                'to_date' => now()->subYear()->format('Y-m-d'),
                'from_time' => '08:00:00',
                'to_time' => '08:59:59',
            ])
            ->create();

        BookableRelation::factory()
            ->state([
                'parent_bookable_area_id' => $mainBookableArea->id,
                'bookable_area_id' => $bookableArea->id,
            ])
            ->create();

        $this->expectException(RelationsHaveNoSeatsException::class);

        BookableHasValidPlannings::run(
            dates: app('bookings')->periodsToDates(periods: new PeriodCollection(Period::make(
                now()->subWeek()->startOf('week')->format('Y-m-d'),
                now()->subWeek()->endOf('week')->format('Y-m-d'),
            ))),
            bookable: $mainBookableArea->bookableResources()->first(),
            relations: collect([ $bookableArea ])
        );
    }

    /** @test */
    public function it_works_because_bookable_area_has_bookable_area_in_relations_with_valid_planning_periods()
    {
        $this->createsAreasAndResources();

        /** @var BookableArea $mainBookableArea */
        $mainBookableArea = BookableArea::first();

        /** @var BookableArea $bookableArea */
        $bookableArea = BookableArea::factory()
            ->has(BookableResource::factory()->count(1))
            ->count(1)
            ->create()
            ->first();

        BookablePlanning::factory()
            ->state([
                'bookable_area_id' => $bookableArea->id,
                'from_date' => now()->subWeek()->startOf('week')->format('Y-m-d'),
                'to_date' => now()->subWeek()->endOf('week')->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ])
            ->create();

        BookableRelation::factory()
            ->state([
                'parent_bookable_area_id' => $mainBookableArea->id,
                'bookable_area_id' => $bookableArea->id,
            ])
            ->create();

        $result = BookableHasValidPlannings::run(
            dates: app('bookings')->periodsToDates(periods: new PeriodCollection(Period::make(
                now()->subWeek()->startOf('week')->format('Y-m-d'),
                now()->subWeek()->endOf('week')->format('Y-m-d'),
            ))),
            bookable: $mainBookableArea->bookableResources()->first(),
            relations: collect([ $bookableArea ])
        );

        $this->assertNull($result);
    }
}
