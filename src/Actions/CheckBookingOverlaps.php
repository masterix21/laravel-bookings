<?php

declare(strict_types=1);

namespace Masterix21\Bookings\Actions;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Masterix21\Bookings\Enums\UnbookableReason;
use Masterix21\Bookings\Events\BookingChangeFailed;
use Masterix21\Bookings\Events\BookingFailed;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Support\PeriodConcurrency;
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

        $lockedResource = $this->lockBookableResource($bookableResource);

        $foundOverlaps = $this->maxConcurrentBookings($periods, $bookableResource, $ignoreBooking);

        if (blank($lockedResource->max) || $lockedResource->max > $foundOverlaps) {
            return true;
        }

        $this->handleOverlapFailure($emitEvent, $throw, $ignoreBooking, $bookableResource, $periods);

        return false;
    }

    protected function maxConcurrentBookings(
        PeriodCollection $periods,
        BookableResource $bookableResource,
        ?Booking $ignoreBooking
    ): int {
        $bookedPeriods = $this->lockOverlappingPeriods($periods, $bookableResource, $ignoreBooking);

        return PeriodConcurrency::maxConcurrentBookings($periods, $bookedPeriods);
    }

    protected function lockBookableResource(BookableResource $bookableResource): BookableResource
    {
        $lockedResource = $bookableResource->newQuery()
            ->whereKey($bookableResource->getKey())
            ->lockForUpdate()
            ->first();

        if (! $lockedResource) {
            throw new \RuntimeException('BookableResource not found or locked');
        }

        return $lockedResource;
    }

    /** @return EloquentCollection<int, BookedPeriod> */
    protected function lockOverlappingPeriods(
        PeriodCollection $periods,
        BookableResource $bookableResource,
        ?Booking $ignoreBooking
    ): EloquentCollection {
        $builder = resolve(config('bookings.models.booked_period'))::query()
            ->where('bookable_resource_id', $bookableResource->getKey())
            ->where('is_excluded', false)
            ->lockForUpdate();

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

        return $builder->get();
    }

    protected function handleOverlapFailure(
        bool $emitEvent,
        bool $throw,
        ?Booking $ignoreBooking,
        BookableResource $bookableResource,
        PeriodCollection $periods
    ): void {
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
    }
}
