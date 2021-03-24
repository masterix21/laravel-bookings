<?php

namespace Masterix21\Bookings;

use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\PeriodCollection;

class Bookings
{
    public function checksum(
        BookableResource $bookableResource,
        PeriodCollection $periods,
    ): string {
        $checksumData = [
            'bookable_resource_id' => $bookableResource->id,
            'periods' => $periods,
        ];

        return md5(json_encode($checksumData));
    }
}
