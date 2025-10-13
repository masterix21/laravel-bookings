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
        if (! $this->relationLoaded('bookedPeriods')) {
            throw new \Exception('Relation "bookedPeriods" not loaded.');
        }

        $filteredPeriods = $this->bookedPeriods->where('is_excluded', $isExcluded);

        if ($filteredPeriods->isEmpty() && $fallbackPeriods && ! $fallbackPeriods->isEmpty()) {
            return $fallbackPeriods;
        }

        $periods = $filteredPeriods
            ->map(fn ($bookedPeriod) => Period::make(
                $bookedPeriod->starts_at,
                $bookedPeriod->ends_at,
                Precision::DAY()
            ))
            ->all();

        $periodCollection = new PeriodCollection(...$periods);

        if ($mergePeriods && ! $mergePeriods->isEmpty()) {
            return $periodCollection->add(...$mergePeriods);
        }

        return $periodCollection;
    }
}
