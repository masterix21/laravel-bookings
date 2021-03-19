<?php

namespace Masterix21\Bookings\Actions;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Events\Booking\RefreshedBooking;
use Masterix21\Bookings\Events\Booking\RefreshingBooking;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class CreateBooking
{
    use AsAction;

    public function handle(
        User $user,
        PeriodCollection $periods,
        BookableResource $bookableResource,
        ?array $bookableRelations = null,
        ?string $code = null,
        ?string $label = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $note = null,
    ) {
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

        event(new RefreshingBooking($booking));

        $bookedResource = $this->createResource($booking, $bookableResource); // @TODO: quando gestiamo le relations, ci servirà...

        $this->createPlannings($booking, $periods);

        GenerateBookingPeriods::run($booking);

        event(new RefreshedBooking($booking));
    }

    private function createResource(Booking $booking, BookableResource $bookableResource, ?BookedResource $parent = null, ?BookableRelation $bookableRelation = null): BookedResource
    {
        /** @var BookedResource $mainResource */
        $mainResource = resolve(config('bookings.models.booked_resource'));

        $mainResource->fill([
            'booking_id' => $booking->id,
            'parent_id' => $parent?->id,
            'bookable_area_id' => $bookableResource->bookable_area_id,
            'bookable_resource_id' => $bookableResource->id,
            'is_required' => $bookableRelation?->is_required ?? true,
            'min' => $bookableRelation ? $bookableRelation->min : $bookableResource->min,
            'max' => $bookableRelation ? $bookableRelation->max : $bookableResource->max,
            'max_nested' => $bookableRelation ? null : $bookableResource->max_nested,
        ]);

        $mainResource->save();

        return $mainResource;
    }

    private function createPlannings(Booking $booking, PeriodCollection $periods, ?BookedResource $bookedResource = null, bool $isExcluded = false, ?string $label = null, ?string $note = null)
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
}
