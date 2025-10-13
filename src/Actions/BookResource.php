<?php

declare(strict_types=1);

namespace Masterix21\Bookings\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Masterix21\Bookings\Enums\UnbookableReason;
use Masterix21\Bookings\Events\BookingChanged;
use Masterix21\Bookings\Events\BookingChangeFailed;
use Masterix21\Bookings\Events\BookingChanging;
use Masterix21\Bookings\Events\BookingCompleted;
use Masterix21\Bookings\Events\BookingFailed;
use Masterix21\Bookings\Events\BookingInProgress;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
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
        ?Model $relatable = null,
        ?string $code = null,
        ?string $codePrefix = null,
        ?string $codeSuffix = null,
        ?string $label = null,
        ?string $note = null,
        ?array $meta = null,
    ): ?Booking {
        if ($booking?->exists) {
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

        /** @var Booking $booking */
        $booking ??= resolve(config('bookings.models.booking'));

        return $this->create(
            booking: $booking,
            booker: $booker,
            periods: $periods,
            bookableResource: $bookableResource,
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
        ?string $code,
        ?string $codePrefix,
        ?string $codeSuffix,
        ?string $label,
        ?string $note,
        ?array $meta,
    ): ?Booking {
        return $this->executeInTransaction($booking, $bookableResource, $periods, function () use (
            $booking,
            $periods,
            $bookableResource,
            $relatable,
            $booker,
            $code,
            $codePrefix,
            $codeSuffix,
            $label,
            $note,
            $meta
        ) {
            event(new BookingInProgress($bookableResource, $periods));

            (new CheckBookingOverlaps)->run($periods, $bookableResource, emitEvent: true, throw: true);

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

            DB::afterCommit(function () use ($booking, $periods) {
                event(new BookingCompleted($booking, $periods));
            });

            return $booking;
        });
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
        return $this->executeInTransaction($booking, $bookableResource, $periods, function () use (
            $booking,
            $periods,
            $bookableResource,
            $relatable,
            $booker,
            $code,
            $codePrefix,
            $codeSuffix,
            $label,
            $note,
            $meta
        ) {
            event(new BookingChanging($booking, $bookableResource, $periods));

            (new CheckBookingOverlaps)->run(
                periods: $periods,
                bookableResource: $bookableResource,
                emitEvent: false,
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

            if (config('bookings.booking_update.preserve_deleted_periods', false)) {
                $booking->bookedPeriods()->delete();
            } else {
                $booking->bookedPeriods()->forceDelete();
            }

            $booking
                ->addBookedPeriods(
                    periods: $periods,
                    bookableResource: $bookableResource,
                    relatable: $relatable,
                );

            DB::afterCommit(function () use ($booking, $periods) {
                event(new BookingChanged($booking, $periods));
            });

            return $booking;
        });
    }

    protected function executeInTransaction(
        Booking $booking,
        BookableResource $bookableResource,
        PeriodCollection $periods,
        callable $callback
    ): ?Booking {
        try {
            $booking->getConnection()->beginTransaction();

            $result = $callback();

            $booking->getConnection()->commit();

            return $result;
        } catch (\Exception $e) {
            $this->emitFailureEvent($e, $bookableResource, $periods, $booking->exists ? $booking : null);

            $booking->getConnection()->rollBack();

            throw $e;
        }
    }

    protected function emitFailureEvent(
        \Exception $e,
        BookableResource $bookableResource,
        PeriodCollection $periods,
        ?Booking $booking = null
    ): void {
        if ($booking?->exists) {
            event(new BookingChangeFailed(
                $booking,
                UnbookableReason::EXCEPTION,
                $bookableResource,
                $periods,
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        } else {
            event(new BookingFailed(
                UnbookableReason::EXCEPTION,
                $bookableResource,
                $periods,
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }
    }
}
