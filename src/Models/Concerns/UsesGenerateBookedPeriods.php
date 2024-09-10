<?php

namespace Masterix21\Bookings\Models\Concerns;

use Carbon\Carbon;
use Masterix21\Bookings\Events\Booking\GeneratedBookedPeriods;
use Masterix21\Bookings\Events\Booking\GeneratingBookedPeriods;
use Masterix21\Bookings\Models\BookedPeriodChange;
use Masterix21\Bookings\Models\BookedResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\Period as SpatiePeriod;
use Spatie\Period\PeriodCollection;

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

        $bookingPeriods = $this->getBookingPlanningPeriods();
        $bookingPeriodsExcluded = $this->getBookingPlanningPeriods(isExcluded: true);

        $this->bookedResources->each(function (BookedResource $bookedResource) use ($bookingPeriods, $bookingPeriodsExcluded) {
            $includedPeriods = $bookedResource->getBookingPlanningPeriods(fallbackPeriods: $bookingPeriods);

            $excludedPeriods = $bookedResource->getBookingPlanningPeriods(isExcluded: true, mergePeriods: $bookingPeriodsExcluded);

            $periods = PeriodCollection::make(...$includedPeriods);

            if (! blank($excludedPeriods)) {
                $periods = $periods->subtract(...$excludedPeriods);
            }

            $this->bookedPeriods()->saveMany(
                collect($periods)->map(fn (SpatiePeriod $period) => new BookedPeriodChange([
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
