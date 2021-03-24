<?php

namespace Masterix21\Bookings\Tests\Feature\Models\BookableArea;

use Masterix21\Bookings\Exceptions\RelationsHaveNoFreeSizeException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Models\BookingPlanning;
use Masterix21\Bookings\Period;
use Masterix21\Bookings\Tests\Concerns\CreatesAreasAndResources;
use Masterix21\Bookings\Tests\TestCase;
use Masterix21\Bookings\Tests\TestClasses\User;
use Spatie\Period\Period as SpatiePeriod;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class SizesTest extends TestCase
{
    use CreatesAreasAndResources;

    /** @test */
    public function it_works_because_has_bookable_area_relations_with_free_size()
    {
        $this->createsAreasAndResources();

        /** @var BookableArea $mainBookableArea */
        $mainBookableArea = BookableArea::first();

        /** @var BookableArea $bookableArea */
        $bookableArea = BookableArea::factory()
            ->state(['is_bookable' => true])
            ->has(BookableResource::factory()->count(1)->state(['is_bookable' => true]))
            ->count(1)
            ->create()
            ->first();

        $dates = Period::toDates(periods: SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d'),
        ));

        try {
            $mainBookableArea->ensureHasFreeSize(
                dates: $dates,
                relations: collect([$bookableArea])
            );

            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    /** @test */
    public function it_throw_exception_because_has_bookable_area_without_free_size()
    {
        $this->createsAreasAndResources();

        /** @var BookableArea $mainBookableArea */
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

        $bookingPeriods = new PeriodCollection(
            SpatiePeriod::make(
                now()->subWeek()->startOf('week'),
                now()->subWeek()->endOf('week'),
                Precision::SECOND()
            )
        );

        $booking = $mainBookableArea->bookableResources()->first()->reserve(
            user: $user,
            periods: $bookingPeriods,
            relations: BookableRelation::get()
        );

        $this->assertEquals(Booking::class, $booking::class);

        $dates = Period::toDates(periods: SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d'),
        ));

        $this->expectException(RelationsHaveNoFreeSizeException::class);

        $mainBookableArea->ensureHasFreeSize(
            dates: $dates,
            relations: collect([ $bookableArea ])
        );
    }

    /** @test */
    public function it_works_because_has_bookable_resources_relations_with_free_size()
    {
        $this->createsAreasAndResources();

        /** @var BookableArea $mainBookableArea */
        $mainBookableArea = BookableArea::first();

        /** @var BookableArea $bookableArea */
        $bookableArea = BookableArea::factory()
            ->state(['is_bookable' => true])
            ->has(BookableResource::factory()->count(1)->state(['is_bookable' => true]))
            ->count(1)
            ->create()
            ->first();

        $dates = Period::toDates(periods: SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d'),
        ));

        $bookableResource = $bookableArea->bookableResources()->first();

        try {
            $mainBookableArea->ensureHasFreeSize(
                dates: $dates,
                relations: collect([$bookableResource])
            );

            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }
}
