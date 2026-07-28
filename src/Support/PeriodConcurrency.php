<?php

declare(strict_types=1);

namespace Masterix21\Bookings\Support;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Masterix21\Bookings\Models\BookedPeriod;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class PeriodConcurrency
{
    /**
     * Highest number of distinct bookings active at any single instant within
     * any of the given periods.
     *
     * @param EloquentCollection<int, BookedPeriod> $bookedPeriods
     */
    public static function maxConcurrentBookings(PeriodCollection $periods, EloquentCollection $bookedPeriods): int
    {
        if ($bookedPeriods->isEmpty()) {
            return 0;
        }

        return (int) collect($periods)
            ->map(fn (Period $period) => static::maxConcurrencyWithin($period, $bookedPeriods))
            ->max();
    }

    /**
     * The maximum of the concurrency step function always falls on the
     * (clamped) start of one of the candidate periods.
     *
     * @param EloquentCollection<int, BookedPeriod> $bookedPeriods
     */
    protected static function maxConcurrencyWithin(Period $period, EloquentCollection $bookedPeriods): int
    {
        $candidates = $bookedPeriods->filter(
            fn (BookedPeriod $bookedPeriod) => $bookedPeriod->starts_at <= $period->end()
                && $bookedPeriod->ends_at >= $period->start()
        );

        return (int) $candidates
            ->map(function (BookedPeriod $bookedPeriod) use ($period, $candidates) {
                $instant = $bookedPeriod->starts_at->max(Carbon::instance($period->start()));

                return $candidates
                    ->filter(
                        fn (BookedPeriod $candidate) => $candidate->starts_at <= $instant
                            && $candidate->ends_at >= $instant
                    )
                    ->unique('booking_id')
                    ->count();
            })
            ->max();
    }
}
