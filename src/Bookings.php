<?php

namespace Masterix21\Bookings;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Masterix21\Bookings\Events\Booking\CreatedBooking;
use Masterix21\Bookings\Events\Booking\CreatingBooking;
use Masterix21\Bookings\Events\Booking\GeneratedBookedPeriods;
use Masterix21\Bookings\Events\Booking\GeneratingBookedPeriods;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\BookedResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\Period;
use Spatie\Period\Period as SpatiePeriod;
use Spatie\Period\PeriodCollection;

class Bookings
{
    public function checksum()
    {
        throw new \Exception('Not implemented');
    }

    public function freeze()
    {
        throw new \Exception('Not implemented');
    }

    public function unfreeze()
    {
        throw new \Exception('Not implemented');
    }

    public function isFreezed()
    {
        throw new \Exception('Not implemented');
    }

    public function create(
        User $user,
        PeriodCollection $periods,
        BookableResource $bookableResource,
        Collection | EloquentCollection | null $relations = null,
        ?string $code = null,
        ?string $label = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $note = null,
    ): void {
        event(new CreatingBooking($bookableResource, $periods));

        /** @var Booking $booking */
        $booking = resolve(config('bookings.models.booking'));
        $booking->fill([
            'code' => $code ?? Str::uuid(),
            'user_id' => $user->id,
            'label' => $label,
            'email' => $email ?? $user->email,
            'phone' => $phone,
            'note' => $note,
        ]);
        $booking->save();

        $bookedResource = $this->createResource(booking: $booking, bookable: $bookableResource);

        if ($relations?->isNotEmpty()) {
            $relations->each(fn (BookableRelation $bookableRelation) => $this->createResource(
                booking: $booking,
                bookable: $bookableRelation,
                parent: $bookedResource,
            ));
        }

        $this->createPlannings($booking, $periods);

        $this->generateBookedPeriods($booking);

        event(new CreatedBooking($booking));
    }

    protected function createResource(Booking $booking, BookableResource | BookableRelation $bookable, ?BookedResource $parent = null): BookedResource
    {
        /** @var BookedResource $mainResource */
        $bookedResource = resolve(config('bookings.models.booked_resource'));

        $bookedResource->fill([
            'booking_id' => $booking->id,
            'parent_id' => $parent?->id,
            'bookable_area_id' => $bookable->bookable_area_id,
            'bookable_resource_id' => $bookable instanceof BookableResource
                ? $bookable->id
                : $bookable->bookable_resource_id,
            'is_required' => $bookable?->is_required ?? false,
            'min' => $bookable->min,
            'max' => $bookable->max,
            'max_nested' => $bookable?->max_nested,
        ]);

        $bookedResource->save();

        return $bookedResource;
    }

    protected function createPlannings(Booking $booking, PeriodCollection $periods, ?BookedResource $bookedResource = null, bool $isExcluded = false, ?string $label = null, ?string $note = null): void
    {
        collect($periods)->each(function (Period $period) use ($booking, $bookedResource, $isExcluded, $label, $note) {
            $bookingPlanning = resolve(config('bookings.models.booking_planning'));

            $from = Carbon::parse($period->start());
            $to = Carbon::parse($period->end());

            $bookingPlanning->fill([
                'booking_id' => $booking->id,
                'booked_resource_id' => $bookedResource?->id,
                'is_excluded' => $isExcluded,
                'label' => $label,
                'from_date' => $from->format('Y-m-d'),
                'to_date' => $to->format('Y-m-d'),
                'from_time' => $from->format('H:i:s'),
                'to_time' => $to->format('H:i:s'),
                'timezone' => $from->tzName,
                'note' => $note,
            ]);

            $bookingPlanning->save();
        });
    }

    public function generateBookedPeriods(Booking $booking): void
    {
        event(new GeneratingBookedPeriods($booking));

        $booking->bookedPeriods()->delete();

        $booking->load([
            'bookingPlannings',
            'bookedResources',
            'bookedResources.bookingPlannings',
        ]);

        $booking->bookedResources->each(function (BookedResource $bookedResource) use ($booking) {
            $plannings = $bookedResource->bookingPlannings;

            if ($plannings->isEmpty()) {
                $plannings = $booking->bookingPlannings;
            }

            $periods = \Masterix21\Bookings\Period::periodsSubtractToDates(
                main: $plannings->where('is_excluded', false),
                others: $plannings->where('is_excluded', true)
            );

            $booking->bookedPeriods()->saveMany(
                collect($periods)->map(fn (SpatiePeriod $period) => new BookedPeriod([
                    'booking_id' => $booking->id,
                    'booked_resource_id' => $bookedResource->id,
                    'bookable_area_id' => $bookedResource->bookable_area_id,
                    'bookable_resource_id' => $bookedResource->bookable_resource_id,
                    'from_date' => Carbon::parse($period->start())->format('Y-m-d'),
                    'to_date' => Carbon::parse($period->end())->format('Y-m-d'),
                    'from_time' => Carbon::parse($period->start())->format('H:i:s'),
                    'to_time' => Carbon::parse($period->end())->format('H:i:s'),
                    'timezone' => Carbon::parse($period->start())->tzName,
                ]))
            );
        });

        event(new GeneratedBookedPeriods($booking));
    }
}
