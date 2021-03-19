<?php

namespace Masterix21\Bookings\Actions;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Events\Booking\RefreshedDates;
use Masterix21\Bookings\Events\Booking\RefreshingDates;
use Masterix21\Bookings\Models\BookedDates;
use Masterix21\Bookings\Models\BookedResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\Period;

class GenerateBookingDates
{
    use AsAction;

    public function handle(Booking $booking)
    {
        event(new RefreshingDates($booking));

        $booking->bookedDates()->delete();

        $booking->load([
            'bookedPeriods',
            'unbookedPeriods',
            'bookedResource',
            'bookedResources.bookedPeriods',
            'bookedResources.unbookedPeriods',
        ]);

        $booking->bookedResources->each(function (BookedResource $bookedResource) use ($booking) {
            $periods = app('bookings')->periodsSubtractToDates(
                main: $bookedResource->bookedPeriods ?? $booking->bookedPeriods,
                others: $bookedResource->unbookedPeriods ?? $booking->unbookedPeriods
            );

            $booking->bookedDates()->saveMany($periods->map(fn (Period $period) => new BookedDates([
                'booking_id' => $booking->id,
                'booked_resource_id' => $bookedResource->id,
                'bookable_area_id' => $bookedResource->bookable_area_id,
                'bookable_resource_id' => $bookedResource->bookable_resource_id,
                'from_date' => Carbon::parse($period->start())->format('Y-m-d'),
                'to_date' => Carbon::parse($period->end())->format('Y-m-d'),
                'from_time' => Carbon::parse($period->start())->format('H:i:s'),
                'to_time' => Carbon::parse($period->end())->format('H:i:s'),
                'timezone' => Carbon::parse($period->start())->tzName,
            ])));
        });

        event(new RefreshedDates($booking));
    }
}
