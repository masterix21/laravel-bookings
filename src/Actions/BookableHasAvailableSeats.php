<?php

namespace Masterix21\Bookings\Actions;

use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedResource;

class BookableHasAvailableSeats
{
    use AsAction;

    public function handle(Collection $dates, BookableArea|BookableResource $bookable, ?array $relations = null): bool
    {
        //
        // If $bookable is a BookableResource, check if is available because has no bookings.
        if ($bookable instanceof BookableResource) {
            return BookedResource::query()
                    ->where('bookable_resource_id', $bookable->id)
                    ->whereHas('bookedPeriods', fn($query) => $query->whereDatesAreWithinPeriods($dates))
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
