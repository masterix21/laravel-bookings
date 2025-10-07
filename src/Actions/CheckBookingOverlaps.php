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
        if (blank($bookableResource->max) || $periods->isEmpty()) {
            return true;
        }

        $builder = resolve(config('bookings.models.booked_period'))::query()
            ->where('bookable_resource_id', $bookableResource->getKey());

        $builder->where(function ($query) use ($periods) {
            foreach ($periods as $period) {
                $query->orWhere(
                    fn ($q) => $q
                        ->where('starts_at', '<=', $period->end())
                        ->where('ends_at', '>=', $period->start())
                );
            }
        });

        if ($ignoreBooking) {
            $builder->where('booking_id', '!=', $ignoreBooking->getKey());
        }

        $foundOverlaps = $builder->count();

        if ($bookableResource->max > $foundOverlaps) {
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
