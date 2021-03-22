<?php

namespace Masterix21\Bookings\Actions\Checks;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Exceptions\CheckAvailability\NoSeatsException;
use Masterix21\Bookings\Exceptions\CheckAvailability\UnbookableException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;

class BookableHasAvailableSeats
{
    use AsAction;

    public function handle(
        Collection $dates,
        BookableArea | BookableResource $bookable,
        ?array $relations = null,
        bool $ignoresUnbookableResources = false
    ) {
        $size = $bookable instanceof BookableResource
            ? (int) $bookable->size
            : (int) BookableResource::query()
                ->when(! $ignoresUnbookableResources, fn ($query) => $query->where('is_bookable', true))
                ->where('bookable_area_id', $bookable->id)
                ->sum('size');

        if ($bookable instanceof BookableResource && ! $bookable->is_bookable && ! $ignoresUnbookableResources) {
            throw new UnbookableException();
        }

        $bookingsCount = BookedPeriod::query()
            ->whereDatesAreWithinPeriods($dates)
            ->when($bookable instanceof BookableResource, fn ($q) => $q->where('bookable_resource_id', $bookable->id))
            ->when($bookable instanceof BookableArea, fn ($q) => $q->where('bookable_area_id', $bookable->id))
            ->count(DB::raw('DISTINCT booking_id'));

        if ($size <= $bookingsCount) {
            throw new NoSeatsException();
        }
    }
}
