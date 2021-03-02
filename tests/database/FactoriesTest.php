<?php

namespace Masterix21\Bookings\Tests\database;

use Masterix21\Bookings\Models\Area;
use Masterix21\Bookings\Models\Resource;
use Masterix21\Bookings\Models\Timetable;
use Masterix21\Bookings\Tests\TestCase;

class FactoriesTest extends TestCase
{
    /** @test */
    public function assert_areas_factory_works()
    {
        /** @var Area $area */
        $areaClass = config('bookings.models.area');
        /** @var Resource $resourceClass */
        $resourceClass = config('bookings.models.resource');
        /** @var Timetable $timetableClass */
        $timetableClass = config('bookings.models.timetable');

        $areaClass::factory()->count(21)->create();
        $this->assertEquals(21, $areaClass::query()->count());

        $areaClass::factory()->count(1)
            ->has($resourceClass::factory()->count(2))
            ->has(
                $timetableClass::factory()
                    ->state(fn (array $attributes, Area $area) => ['area_id' => $area->id])
                    ->count(10),
            )
            ->create();
    }
}
