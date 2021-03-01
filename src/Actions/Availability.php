<?php

namespace Masterix21\Bookings\Actions;

use Carbon\CarbonPeriod;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Models\Resource;

class Availability
{
    use AsAction;

    /**
     * Verify if a resource is bookable and return the number of available seats.
     *
     * @param Resource $booking
     * @param CarbonPeriod $period
     * @param Resource[] $children
     * @return int
     */
    public function handle(Resource $booking, CarbonPeriod $period, $children = null): int
    {
        return 0;
    }
}
