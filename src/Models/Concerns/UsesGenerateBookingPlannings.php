<?php

namespace Masterix21\Bookings\Models\Concerns;

use Carbon\Carbon;
use Masterix21\Bookings\Models\BookedResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\PeriodCollection;

/** @mixin Booking | BookedResource */
trait UsesGenerateBookingPlannings
{
    public function addBookingPlannings(PeriodCollection $periods, bool $isExcluded = false, ?string $label = null, ?string $note = null): self
    {
        return tap($this, function () use ($periods, $isExcluded, $label, $note) {
            foreach ($periods as $period) {
                $bookingPlanning = resolve(config('bookings.models.booking_planning'));

                $from = Carbon::parse($period->start());
                $to = Carbon::parse($period->end());

                $bookingPlanning->fill([
                    'booking_id' => $this instanceof BookedResource
                        ? $this->booking_id
                        : $this->id,
                    'booked_resource_id' => $this instanceof BookedResource
                        ? $this->id
                        : null,
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

                unset($bookingPlanning);
            }
        });
    }
}
