<?php

namespace Masterix21\Bookings\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Masterix21\Bookings\Enums\UnbookableReason;
use Masterix21\Bookings\Events\BookingCompleted;
use Masterix21\Bookings\Events\BookingFailed;
use Masterix21\Bookings\Events\BookingInProgress;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\PeriodCollection;

class BookResource
{
    public function run(
        Model $booker,
        PeriodCollection $periods,
        ?BookableResource $bookableResource = null,
        ?User $creator = null,
        ?string $code = null,
        ?string $label = null,
        ?string $note = null,
    ): ?Booking {
        /** @var Booking $booking */
        $booking = resolve(config('bookings.models.booking'));

        try {
            $booking->getConnection()->beginTransaction();

            event(new BookingInProgress($bookableResource, $periods));

            $this->preventsOverlapping($periods, $bookableResource);

            $booking
                ->fill([
                    'code' => $code ?: $this->generateRandomUniqueBookingCode(),
                    'booker_type' => $booker ? $booker::class : null,
                    'booker_id' => $booker?->getKey(),
                    'label' => $label,
                    'note' => $note,
                ])
                ->save();

            $booking
                ->addBookedPeriods(
                    periods: $periods,
                    bookableResource: $bookableResource
                );

            event(new BookingCompleted($booking, $periods));

            $booking->getConnection()->commit();

            return $booking;
        } catch (\Exception $e) {
            Log::error($e);

            event(new BookingFailed(
                UnbookableReason::EXCEPTION,
                $bookableResource,
                $periods,
                $e->getMessage(),
                $e->getTraceAsString()
            ));

            $booking->getConnection()->rollBack();
        }

        return null;
    }

    protected function preventsOverlapping(
        PeriodCollection $periods,
        ?BookableResource $bookableResource = null,
    ): void {
        if (blank($bookableResource)) {
            return;
        }

        $overlaps = collect();

        foreach ($periods as $period) {
            $count = $bookableResource
                ->bookedPeriods()
                ->where('starts_at', '<', $period->end())
                ->where('ends_at', '>', $period->start())
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
            return;
        }

        event(new BookingFailed(UnbookableReason::PERIOD_OVERLAP, $bookableResource, $periods));

        throw new BookingResourceOverlappingException;
    }

    protected function generateRandomUniqueBookingCode(): string
    {
        $ulid = Str::ulid();

        return $ulid.str(Str::random(64 - strlen($ulid)))->upper();
    }
}
