<?php

namespace Masterix21\Bookings;

use Illuminate\Support\Collection;
use Spatie\Period\Period as SpatiePeriod;
use Spatie\Period\PeriodCollection;

class Period
{
    /**
     * Transform periods to dates collection
     */
    public static function toDates(PeriodCollection|SpatiePeriod $periods, bool $removeDuplicates = true): Collection
    {
        $dates = collect();

        if ($periods instanceof SpatiePeriod) {
            $periods = PeriodCollection::make($periods);
        }

        foreach ($periods as $period) {
            foreach ($period as $date) {
                $dates->push($date);
            }
        }

        if ($removeDuplicates) {
            return $dates->unique();
        }

        return $dates;
    }
}
