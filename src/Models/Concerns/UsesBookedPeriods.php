<?php

namespace Masterix21\Bookings\Models\Concerns;

use Masterix21\Bookings\Models\BookedResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

/** @mixin Booking | BookedResource */
trait UsesBookedPeriods
{
    public function getBookedPeriods(
        $isExcluded = false,
        ?PeriodCollection $mergePeriods = null,
        ?PeriodCollection $fallbackPeriods = null
    ): PeriodCollection {
        $periods = $this->bookedPeriods
            ->where('is_excluded', $isExcluded)
            ->transform(fn ($planning) => Period::make(
                $planning->from_date,
                $planning->to_date,
                Precision::DAY()
            ));

        if ($mergePeriods && ! $mergePeriods->isEmpty()) {
            $periods = $periods->merge(...$mergePeriods);
        }

        if ($periods->isEmpty() && $fallbackPeriods && ! $fallbackPeriods->isEmpty()) {
            return $fallbackPeriods;
        }

        return PeriodCollection::make(...$periods->toArray());
    }
}
