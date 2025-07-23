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
            ->map(fn ($bookedPeriod) => Period::make(
                $bookedPeriod->starts_at,
                $bookedPeriod->ends_at,
                Precision::DAY()
            ))
            ->values()
            ->toArray();

        $periodCollection = new PeriodCollection(...$periods);

        if ($mergePeriods && ! $mergePeriods->isEmpty()) {
            $periodCollection = $periodCollection->add(...$mergePeriods);
        }

        if ($periodCollection->isEmpty() && $fallbackPeriods && ! $fallbackPeriods->isEmpty()) {
            return $fallbackPeriods;
        }

        return $periodCollection;
    }
}
