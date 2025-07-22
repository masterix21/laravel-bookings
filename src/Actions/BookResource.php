<?php

namespace Masterix21\Bookings\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Masterix21\Bookings\Enums\UnbookableReason;
use Masterix21\Bookings\Events\BookingChanged;
use Masterix21\Bookings\Events\BookingChangeFailed;
use Masterix21\Bookings\Events\BookingChanging;
use Masterix21\Bookings\Events\BookingCompleted;
use Masterix21\Bookings\Events\BookingFailed;
use Masterix21\Bookings\Events\BookingInProgress;
use Masterix21\Bookings\Generators\Contracts\BookingCodeGenerator;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\PeriodCollection;

class BookResource
{
    public function run(
        PeriodCollection $periods,
        BookableResource $bookableResource,
        ?Model $booker,
        ?Booking $booking = null,
        ?User $creator = null,
        ?Model $relatable = null,
        ?string $code = null,
        ?string $codePrefix = null,
        ?string $codeSuffix = null,
        ?string $label = null,
        ?string $note = null,
        ?array $meta = null,
    ): ?Booking {
        /** @var Booking $booking */
        $booking ??= resolve(config('bookings.models.booking'));

        if ($booking->exists) {
            return $this->update(
                booking: $booking,
                booker: $booker,
                relatable: $relatable,
                periods: $periods,
                bookableResource: $bookableResource,
                code: $code,
                codePrefix: $codePrefix,
                codeSuffix: $codeSuffix,
                label: $label,
                note: $note,
                meta: $meta,
            );
        }

        return $this->create(
            booking: $booking,
            booker: $booker,
            periods: $periods,
            bookableResource: $bookableResource,
            creator: $creator,
            relatable: $relatable,
            code: $code,
            codePrefix: $codePrefix,
            codeSuffix: $codeSuffix,
            label: $label,
            note: $note,
            meta: $meta,
        );
    }

    protected function create(
        Booking $booking,
        PeriodCollection $periods,
        BookableResource $bookableResource,
        ?Model $relatable,
        ?Model $booker,
        ?User $creator,
        ?string $code,
        ?string $codePrefix,
        ?string $codeSuffix,
        ?string $label,
        ?string $note,
        ?array $meta,
    ): ?Booking {
        try {
            $booking->getConnection()->beginTransaction();

            event(new BookingInProgress($bookableResource, $periods));

            (new CheckBookingOverlaps())->run($periods, $bookableResource, emitEvent: true, throw: true);

            $booking
                ->fill([
                    'code' => $code ?: app(BookingCodeGenerator::class)->run(prefix: $codePrefix, suffix: $codeSuffix),
                    'booker_type' => $booker ? $booker::class : null,
                    'booker_id' => $booker?->getKey(),
                    'label' => $label,
                    'note' => $note,
                    'meta' => $meta,
                ])
                ->save();

            $booking
                ->addBookedPeriods(
                    periods: $periods,
                    bookableResource: $bookableResource,
                    relatable: $relatable,
                );

            event(new BookingCompleted($booking, $periods));

            $booking->getConnection()->commit();

            return $booking;
        } catch (\Exception $e) {
            event(new BookingFailed(
                UnbookableReason::EXCEPTION,
                $bookableResource,
                $periods,
                $e->getMessage(),
                $e->getTraceAsString()
            ));

            $booking->getConnection()->rollBack();

            throw $e;
        }

        return null;
    }

    protected function update(
        Booking $booking,
        PeriodCollection $periods,
        BookableResource $bookableResource,
        ?Model $relatable,
        ?Model $booker,
        ?string $code,
        ?string $codePrefix,
        ?string $codeSuffix,
        ?string $label,
        ?string $note,
        ?array $meta,
    ): ?Booking {
        try {
            $booking->getConnection()->beginTransaction();

            event(new BookingChanging($booking, $bookableResource, $periods));

            (new CheckBookingOverlaps())->run(
                periods: $periods,
                bookableResource: $bookableResource,
                emitEvent: true,
                throw: true,
                ignoreBooking: $booking
            );

            $booking
                ->fill([
                    'code' => $code
                        ?: $booking->code
                            ?: app(BookingCodeGenerator::class)->run(prefix: $codePrefix, suffix: $codeSuffix),
                    'booker_type' => $booker
                        ? $booker::class
                        : $booking->booker_type,
                    'booker_id' => $booker?->getKey() ?: $booking->booker_id,
                    'label' => $label,
                    'note' => $note,
                    'meta' => $meta,
                ])
                ->save();

            $booking->bookedPeriods()->forceDelete();

            $booking
                ->addBookedPeriods(
                    periods: $periods,
                    bookableResource: $bookableResource,
                    relatable: $relatable,
                );

            event(new BookingChanged($booking, $periods));

            $booking->getConnection()->commit();

            return $booking;
        } catch (\Exception $e) {
            event(new BookingChangeFailed(
                $booking,
                UnbookableReason::EXCEPTION,
                $bookableResource,
                $periods,
                $e->getMessage(),
                $e->getTraceAsString()
            ));

            $booking->getConnection()->rollBack();

            throw $e;
        }
    }
}
