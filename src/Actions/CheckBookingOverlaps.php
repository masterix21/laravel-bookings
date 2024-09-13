<?php

namespace Masterix21\Bookings\Actions;

use Masterix21\Bookings\Enums\UnbookableReason;
use Masterix21\Bookings\Events\BookingFailed;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\PeriodCollection;

class CheckBookingOverlaps
{
    public function run(
        PeriodCollection $periods,
        BookableResource $bookableResource,
        bool $emitEvent = false,
        bool $throw = false,
    ): bool {
        $overlaps = collect();

        foreach ($periods as $period) {
            $count = $bookableResource
                ->bookedPeriods()
                ->where('starts_at', '<=', $period->end())
                ->where('ends_at', '>=', $period->start())
                ->lockForUpdate()
                ->count();

            if ($count >= $bookableResource->max) {
                $overlaps->add([
                    'starts_at' => $period->start(),
                    'ends_at' => $period->end(),
                    'overlaps_count' => $count,
                ]);
            }
        }

        if ($overlaps->isEmpty()) {
            return true;
        }

        if ($emitEvent) {
            event(new BookingFailed(UnbookableReason::PERIOD_OVERLAP, $bookableResource, $periods));
        }

        if ($throw) {
            throw new BookingResourceOverlappingException;
        }

        return false;
    }
}
