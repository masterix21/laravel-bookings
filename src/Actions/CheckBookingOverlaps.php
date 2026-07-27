<?php

declare(strict_types=1);

namespace Masterix21\Bookings\Actions;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Masterix21\Bookings\Enums\UnbookableReason;
use Masterix21\Bookings\Events\BookingChangeFailed;
use Masterix21\Bookings\Events\BookingFailed;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\Period;
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

        $foundOverlaps = $this->maxConcurrentBookings($periods, $bookableResource, $ignoreBooking);

        if ($bookableResource->max > $foundOverlaps) {
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
        $this->lockBookableResource($bookableResource);

        $bookedPeriods = $this->lockOverlappingPeriods($periods, $bookableResource, $ignoreBooking);

        if ($bookedPeriods->isEmpty()) {
            return 0;
        }

        return collect($periods)
            ->map(fn (Period $period) => $this->maxConcurrencyWithin($period, $bookedPeriods))
            ->max();
    }

    protected function lockBookableResource(BookableResource $bookableResource): void
    {
        $lockedResource = BookableResource::query()
            ->whereKey($bookableResource->getKey())
            ->lockForUpdate()
            ->first();

        if (! $lockedResource) {
            throw new \RuntimeException('BookableResource not found or locked');
        }
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

    /**
     * Compute the highest number of distinct bookings active at any single
     * instant within the given period. The maximum of the concurrency step
     * function always falls on the (clamped) start of one of the candidates.
     *
     * @param EloquentCollection<int, BookedPeriod> $bookedPeriods
     */
    protected function maxConcurrencyWithin(Period $period, EloquentCollection $bookedPeriods): int
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
