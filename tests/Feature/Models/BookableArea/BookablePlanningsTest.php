<?php

namespace Masterix21\Bookings\Tests\Feature\Models\BookableArea;

use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
use Masterix21\Bookings\Exceptions\RelationsOutOfPlanningsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Period;
use Masterix21\Bookings\Tests\Concerns\CreatesAreasAndResources;
use Masterix21\Bookings\Tests\TestCase;
use Spatie\Period\Period as SpatiePeriod;

class BookablePlanningsTest extends TestCase
{
    use CreatesAreasAndResources;

    /** @test */
    public function it_throws_an_exception_because_has_no_plannings(): void
    {
        /** @var BookableArea $bookableArea */
        $bookableArea = BookableArea::factory()->count(1)
            ->has(BookableResource::factory()->count(1))
            ->create()
            ->first();

        $this->expectException(OutOfPlanningsException::class);

        $bookableArea->ensureHasValidPlannings(dates: collect([ now() ]));
    }

    /** @test */
    public function it_works_because_has_a_valid_planning(): void
    {
        $this->createsAreasAndResources();

        /** @var BookableArea $bookableArea */
        $bookableArea = BookableArea::first();

        $dates = Period::toDates(periods: SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d'),
        ));

        try {
            $bookableArea->ensureHasValidPlannings(dates: $dates);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(true);
    }

    /** @test */
    public function it_throws_an_exception_because_has_relations_with_invalid_planning(): void
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

        $dates = Period::toDates(periods: SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d'),
        ));

        try {
            $mainBookableArea->ensureHasValidPlannings(dates: $dates, relations: collect([$bookableArea]));
            $this->fail('No RelationsOutOfPlanningsException exception');
        } catch (RelationsOutOfPlanningsException $e) {
            $this->assertTrue(true);
        }

        try {
            $mainBookableArea->ensureHasValidPlannings(dates: $dates, relations: $bookableArea->bookableResources);
            $this->fail('No RelationsOutOfPlanningsException exception');
        } catch (RelationsOutOfPlanningsException $e) {
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function it_works_because_has_relations_with_valid_planning(): void
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

        $dates = Period::toDates(periods: SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d'),
        ));

        try {
            $mainBookableArea->ensureHasValidPlannings(dates: $dates, relations: collect([ $bookableArea ]));
            $mainBookableArea->ensureHasValidPlannings(dates: $dates, relations: collect([ $bookableResource ]));
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(true);
    }
}
