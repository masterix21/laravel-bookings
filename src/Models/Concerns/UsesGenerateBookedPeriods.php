<?php

namespace Masterix21\Bookings\Models\Concerns;

use Carbon\Carbon;
use Masterix21\Bookings\Events\Booking\GeneratedBookedPeriods;
use Masterix21\Bookings\Events\Booking\GeneratingBookedPeriods;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\BookedResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\Period as SpatiePeriod;

/** @mixin Booking */
trait UsesGenerateBookedPeriods
{
    public function generateBookedPeriods(): self
    {
        event(new GeneratingBookedPeriods($this));

        $this->bookedPeriods()->delete();

        $this->load([
            'bookingPlannings',
            'bookedResources',
            'bookedResources.bookingPlannings',
        ]);

        $this->bookedResources->each(function (BookedResource $bookedResource) {
            $plannings = $bookedResource->bookingPlannings;

            if ($plannings->isEmpty()) {
                $plannings = $this->bookingPlannings;
            }

            $periods = \Masterix21\Bookings\Period::periodsSubtractToDates(
                main: $plannings->where('is_excluded', false),
                others: $plannings->where('is_excluded', true)
            );

            $this->bookedPeriods()->saveMany(
                collect($periods)->map(fn (SpatiePeriod $period) => new BookedPeriod([
                    'booking_id' => $this->id,
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

        event(new GeneratedBookedPeriods($this));

        return $this;
    }
}
