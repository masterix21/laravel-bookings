<?php

namespace Masterix21\Bookings\Actions\Checks;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Exceptions\CheckAvailability\NoSeatsException;
use Masterix21\Bookings\Exceptions\CheckAvailability\RelationsHaveNoSeatsException;
use Masterix21\Bookings\Exceptions\CheckAvailability\UnbookableException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;

/**
 * Class BookableHasAvailableSeats
 * @package Masterix21\Bookings\Actions\Checks
 */
class BookableHasAvailableSeats
{
    use AsAction;

    public function handle(
        Collection $dates,
        BookableArea | BookableResource $bookable,
        Collection | EloquentCollection | null $relations = null,
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

        $bookableAreas = $relations?->filter(fn ($bookable) => $bookable instanceof BookableArea) ?? collect();

        if ($bookableAreas->isNotEmpty()) {
            $bookableAreasCheck = BookableArea::query()
                ->select('id')
                ->withSum(['bookableResources' => fn ($query) => $query
                    ->when(! $ignoresUnbookableResources, fn ($query) => $query->where('is_bookable', true)),
                ], 'size')
                ->withCount([
                    'bookedPeriods' => fn (Builder $query) => $query
                        ->whereDatesAreWithinPeriods($dates)
                        ->distinct('booking_id'),
                ])
                ->whereIn('id', $bookableAreas->pluck('id'))
                ->when(! $ignoresUnbookableResources, fn ($query) => $query->where('is_bookable', true))
                ->get();

            if (
                $bookableAreasCheck->filter(fn ($bookable) =>
                    (int) $bookable->bookable_resources_sum_size > (int) $bookable->booked_periods_count)->isEmpty()
            ) {
                throw new RelationsHaveNoSeatsException();
            }
        }

        $bookableResources = $relations?->filter(fn ($bookable) => $bookable instanceof BookableResource) ?? collect();

        if ($bookableResources->isNotEmpty()) {
            $bookableResourcesCheck = BookableResource::query()
                ->select('id', 'size')
                ->withCount([
                    'bookedPeriods' => fn (Builder $query) => $query
                        ->whereDatesAreWithinPeriods($dates)
                        ->distinct('booking_id'),
                ])
                ->whereIn('id', $bookableResources->pluck('id'))
                ->when(! $ignoresUnbookableResources, fn ($query) => $query->where('is_bookable', true))
                ->get();

            if (
                $bookableResourcesCheck->filter(fn ($bookable) =>
                    (int) $bookable->size > (int) $bookable->booked_periods_count)->isEmpty()
            ) {
                throw new RelationsHaveNoSeatsException();
            }
        }
    }
}
