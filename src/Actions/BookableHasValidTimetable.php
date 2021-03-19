<?php

namespace Masterix21\Bookings\Actions;

use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookableTimetable;

class BookableHasValidTimetable
{
    use AsAction;

    public function handle(
        Collection $dates,
        BookableArea|BookableResource $bookable,
        ?array $relations = null,
    ): bool
    {
        return BookableTimetable::query()
            ->when($bookable instanceof BookableArea, fn ($query) => $query->where('bookable_area_id', $bookable->id))
            ->when($bookable instanceof BookableResource, fn ($query) => $query->where(function ($query) use ($bookable) {
                $query->where('bookable_resource_id', $bookable->id)
                    ->orWhere('bookable_area_id', $bookable->bookable_area_id);
            }))
            ->whereWeekdaysDates($dates)
            ->whereAllDatesAreWithinPeriods($dates)
            ->count() > 0;
    }
}
