<?php

namespace Masterix21\Bookings\Actions\Timetables;

use Carbon\CarbonPeriod;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Models\Area;
use Masterix21\Bookings\Models\Resource;

class BuildTimetable
{
    use AsAction;

    public function handle(CarbonPeriod $period, Area $area, Resource $resource = null)
    {
        $area->loadMissing('time');
    }
}
