<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedResource;
use Spatie\Period\PeriodCollection;

trait UsesAddBookedResources
{
    public function addBookedResources(Collection | EloquentCollection | null $relations = null): Collection | EloquentCollection
    {
        if (is_null($relations)) {
            return collect();
        }

        return $relations->transform(fn ($bookable) => $this->addBookedResource($bookable));
    }

    public function addBookedResource(BookableResource | BookableRelation $bookable, PeriodCollection $periods = null): BookedResource
    {
        /** @var BookedResource $bookedResource */
        $bookedResource = resolve(config('bookings.models.booked_resource'));

        $bookedResource->fill([
            'booking_id' => get_class($this) === config('bookings.models.booking')
                ? $this->id
                : $this->booking_id,
            'parent_id' => get_class($this) === config('bookings.models.booked_resource')
                ? $this->id
                : null,
            'bookable_area_id' => $bookable->bookable_area_id,
            'bookable_resource_id' => get_class($bookable) !== config('bookings.models.booked_resource')
                ? $bookable->id
                : $bookable->bookable_resource_id,
            'is_required' => $bookable?->is_required ?? false,
            'min' => $bookable->min,
            'max' => $bookable->max,
            'max_nested' => $bookable?->max_nested
        ]);

        $bookedResource->save();

        if (! blank($periods)) {
            $this->addBookingPlannings($periods);
        }

        return $bookedResource;
    }
}
