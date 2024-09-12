<?php

namespace Masterix21\Bookings\Models\Concerns;

use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\PeriodCollection;

/** @mixin Booking */
trait UsesGenerateBookedPeriods
{
    public function addBookedPeriods(
        PeriodCollection $periods,
        ?BookableResource $bookableResource = null,
        bool $isExcluded = false,
        ?string $label = null,
        ?string $note = null
    ): static {
        return tap($this, function () use ($periods, $bookableResource, $isExcluded, $label, $note) {
            foreach ($periods as $period) {
                $bookedPeriod = resolve(config('bookings.models.booked_period'))
                    ->fill([
                        'booking_id' => $this->id,
                        'bookable_area_id' => $bookableResource?->bookable_area_id,
                        'bookable_resource_id' => $bookableResource?->getKey(),
                        'is_excluded' => $isExcluded,
                        'label' => $label,
                        'starts_at' => $period->start(),
                        'ends_at' => $period->end(),
                        'note' => $note,
                    ]);

                $bookedPeriod->save();
            }
        });
    }
}
