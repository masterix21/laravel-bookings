<?php

namespace Masterix21\Bookings\Actions;

use Masterix21\Bookings\Enums\UnbookableReason;
use Masterix21\Bookings\Events\BookingChangeFailed;
use Masterix21\Bookings\Events\BookingFailed;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\PeriodCollection;

class CheckBookingOverlaps
{
    public function run(
        PeriodCollection $periods,
        BookableResource $bookableResource,
        bool $emitEvent = false,
        bool $throw = false,
        ?Booking $ignoreBooking = null,
    ): bool {
        $overlaps = collect();

        // Costruisco le condizioni SUM
        $sumConditions = [];
        $bindings = [];

        foreach ($periods as $index => $period) {
            $sumConditions[] = "SUM(CASE WHEN starts_at < ? AND ends_at > ? THEN 1 ELSE 0 END) as period_{$index}_count";
            $bindings[] = $period->end();
            $bindings[] = $period->start();
        }

        // Query unica sui BookedPeriod
        $periodCounts = resolve(config('bookings.models.booked_period'))
            ->selectRaw(collect($sumConditions)->join(', '), $bindings)
            ->where('bookable_resource_id', $bookableResource->id)
            ->when($ignoreBooking, fn ($q) => $q->whereNot('booking_id', $ignoreBooking->getKey()))
            ->lockForUpdate()
            ->first();

        // Verifico ogni periodo
        foreach ($periods as $index => $period) {
            $countKey = "period_{$index}_count";
            if ($periodCounts->{$countKey} >= $bookableResource->max) {
                $overlaps->add([
                    'starts_at' => $period->start(),
                    'ends_at' => $period->end(),
                    'overlaps_count' => $periodCounts->{$countKey},
                ]);
            }
        }

        if ($overlaps->isEmpty()) {
            return true;
        }

        if ($emitEvent) {
            if ($ignoreBooking) {
                event(new BookingChangeFailed($ignoreBooking, UnbookableReason::PERIOD_OVERLAP, $bookableResource, $periods));
            } else {
                event(new BookingFailed(UnbookableReason::PERIOD_OVERLAP, $bookableResource, $periods));
            }
        }

        if ($throw) {
            throw new BookingResourceOverlappingException;
        }

        return false;
    }
}
