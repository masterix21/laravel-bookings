<?php

namespace Masterix21\Bookings\Tests\Feature\Models\BookableResource;

use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Period;
use Masterix21\Bookings\Tests\Concerns\CreatesAreasAndResources;
use Masterix21\Bookings\Tests\TestCase;
use Spatie\Period\Period as SpatiePeriod;
use Spatie\Period\PeriodCollection;

class BookablePlanningsTest extends TestCase
{
    use CreatesAreasAndResources;

    /** @test */
    public function it_throws_an_exception_because_has_no_plannings()
    {
        BookableArea::factory()->count(1)
            ->has(BookableResource::factory()->count(1))
            ->create();

        $this->expectException(OutOfPlanningsException::class);

        /** @var BookableResource $bookableResource */
        $bookableResource = BookableResource::first();

        $bookableResource->ensureHasValidPlannings(dates: collect([now()]));
    }

    /** @test */
    public function it_works_because_has_a_valid_planning()
    {
        $this->createsAreasAndResources();

        /** @var BookableResource $bookableResource */
        $bookableResource = BookableResource::first();

        $dates = Period::toDates(periods: SpatiePeriod::make(
            now()->subWeek()->startOf('week')->format('Y-m-d'),
            now()->subWeek()->endOf('week')->format('Y-m-d'),
        ));

        $bookableResource->ensureHasValidPlannings(dates: $dates);

        $this->assertTrue(true);
    }
}
