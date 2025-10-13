<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
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
        ?Model $relatable = null,
        ?string $label = null,
        ?string $note = null
    ): static {
        return tap($this, function () use ($relatable, $periods, $bookableResource, $isExcluded, $label, $note) {
            if ($periods->isEmpty()) {
                return;
            }

            $now = now();
            $baseAttributes = [
                'booking_id' => $this->id,
                'bookable_resource_id' => $bookableResource?->getKey(),
                'is_excluded' => $isExcluded,
                'label' => $label,
                'note' => $note,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($relatable) {
                $baseAttributes['relatable_type'] = $relatable::class;
                $baseAttributes['relatable_id'] = $relatable->getKey();
            }

            $model = resolve(config('bookings.models.booked_period'));
            $chunkSize = 500;
            $records = [];

            foreach ($periods as $period) {
                $records[] = array_merge($baseAttributes, [
                    'starts_at' => $period->start(),
                    'ends_at' => $period->end(),
                ]);

                if (count($records) >= $chunkSize) {
                    $model::insert($records);
                    $records = [];
                }
            }

            if (! empty($records)) {
                $model::insert($records);
            }
        });
    }
}
