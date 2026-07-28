<?php

namespace Masterix21\Bookings;

use Illuminate\Support\Collection;
use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\Period as SpatiePeriod;
use Spatie\Period\PeriodCollection;

class Bookings
{
    public function checksum(
        BookableResource $bookableResource,
        PeriodCollection $periods,
    ): string {
        $checksumData = [
            'bookable_resource_id' => $bookableResource->getKey(),
            'periods' => collect($periods)
                ->map(fn (SpatiePeriod $period) => $period->start()->format('Y-m-d H:i:s').'|'.$period->end()->format('Y-m-d H:i:s'))
                ->sort()
                ->values()
                ->all(),
        ];

        return md5(json_encode($checksumData, JSON_THROW_ON_ERROR));
    }

    public function periodsToDates(PeriodCollection $periods, bool $removeDuplicates = true): Collection
    {
        return Period::toDates($periods, $removeDuplicates);
    }
}
