<?php

namespace Masterix21\Bookings\Actions;

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
     * @param BookableArea|BookableResource $bookable
     * @param null|BookableRelation[] $relations
     * @return bool
     */
    public function handle(
        PeriodCollection $periods,
        BookableArea|BookableResource $bookable,
        ?array $relations = null
    ): bool
    {
        $dates = collect();

        foreach ($periods as $period) {
            foreach ($period as $date) {
                $dates->push($date);
            }
        }

        $dates = $dates->unique();

        ///
        /// Verify if the resource (or area) has a timetable compatible with the supplied period.
        $hasValidTimetable = BookableTimetable::query()
            ->when($bookable instanceof BookableArea, fn ($query) => $query->where('bookable_area_id', $bookable->id))
            ->when($bookable instanceof BookableResource, fn ($query) => $query->where(function ($query) use ($bookable) {
                $query->where('bookable_resource_id', $bookable->id)
                    ->orWhere('bookable_area_id', $bookable->bookable_area_id);
            }))
            ->whereWeekdaysDates($dates)
            ->whereAllDatesAreWithinPeriods($dates)
            ->count() > 0;

        if (! $hasValidTimetable) {
            return false;
        }

        //
        // If $bookable is a BookableResource, check if is available because has no bookings.
        if ($bookable instanceof BookableResource) {
            return BookedResource::query()
                    ->where('bookable_resource_id', $bookable->id)
                    ->whereHas('bookedPeriods', fn ($query) => $query->whereDatesAreWithinPeriods($dates))
                    ->count() === 0;
        }

        //
        // Verify if the BookableArea has bookable resources within the periods.
        return BookableResource::query()
                ->where('bookable_area_id', $bookable->id)
                ->whereDoesntHave('bookedPeriods', fn ($query) => $query->whereDatesAreWithinPeriods($dates))
                ->count() > 0;
    }
}
