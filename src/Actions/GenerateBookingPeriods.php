<?php

namespace Masterix21\Bookings\Actions;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Events\Booking\GeneratedBookedPeriods;
use Masterix21\Bookings\Events\Booking\GeneratingBookedPeriods;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\BookedResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\Period;

class GenerateBookingPeriods
{
    use AsAction;

    public function handle(Booking $booking)
    {
        event(new GeneratingBookedPeriods($booking));

        $booking->bookedPeriods()->delete();

        $booking->load([
            'bookingPlannings',
            'bookedResources',
            'bookedResources.bookingPlannings',
        ]);

        $booking->bookedResources->each(function (BookedResource $bookedResource) use ($booking) {
            $plannings = $bookedResource->bookingPlannings->isNotEmpty()
                ? $bookedResource->bookingPlannings
                : $booking->bookingPlannings;

            $periods = app('bookings')->periodsSubtractToDates(
                main: $plannings->where('is_excluded', false),
                others: $plannings->where('is_excluded', true)
            );

            $booking->bookedPeriods()->saveMany(collect($periods)->map(fn (Period $period) => new BookedPeriod([
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

        event(new GeneratedBookedPeriods($booking));
    }
}
