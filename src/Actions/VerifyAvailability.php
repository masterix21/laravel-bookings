<?php

namespace Masterix21\Bookings\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookableTimetable;
use Masterix21\Bookings\Models\BookedResource;
use Spatie\Period\PeriodCollection;

class VerifyAvailability
{
    use AsAction;

    /**
     * @param PeriodCollection $periods
     * @param BookableArea|BookableResource|BookableResource[] $resources
     * @param null|BookableRelation[] $children
     * @return bool
     */
    public function handle(
        PeriodCollection $periods,
        BookableArea | BookableResource | array $resources,
        ?array $children = null
    ): bool {
        if (! $resources instanceof BookableArea) {
            $resources = Collection::wrap($resources);
        }

        $dates = collect();

        foreach ($periods as $period) {
            foreach ($period as $date) {
                $dates->push($date);
            }
        }

        $dates = $dates->unique();

        ///
        /// Verify if the resource (or area) has a timetable compatible with the supplied period.
        // $hasValidTimetable = BookableTimetable::query()


        //
        // Verify if already exists a booking in the same period(s).
        $hasBookings = BookedResource::query()
            ->when($resources instanceof BookableArea, fn ($query) => $query->where('bookable_area_id', $resources->id))
            ->when(! $resources instanceof BookableArea, fn ($query) => $query->whereIn('bookable_resource_id', $resources->pluck('id')))
            ->whereHas('bookedPeriods', function (Builder $query) use ($dates) {
                $dates->each(fn ($date) => $query->whereBetweenColumns($date->format('Y-m-d'), ['from_date', 'to_date']));
            })
            ->count() > 0;

        return ! $hasBookings;
    }
}
