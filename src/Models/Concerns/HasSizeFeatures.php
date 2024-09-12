<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Masterix21\Bookings\Exceptions\NoFreeSizeException;
use Masterix21\Bookings\Exceptions\RelationsHaveNoFreeSizeException;
use Masterix21\Bookings\Exceptions\UnbookableException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;

/** @mixin IsBookable */
trait HasSizeFeatures
{
    abstract public function size(bool $ignoresUnbookable = false): int;

    /**
     * @throws NoFreeSizeException
     * @throws RelationsHaveNoFreeSizeException
     * @throws UnbookableException
     */
    public function ensureHasFreeSize(
        Collection $dates,
        Collection|EloquentCollection|null $relations = null,
        bool $ignoresUnbookable = false
    ): void {
        $size = $this->size($ignoresUnbookable);

        if ($size === 0) {
            throw new UnbookableException;
        }

        $bookingsCount = $this->bookedPeriods()
            ->whereDatesAreWithinPeriods($dates)
            ->when($this instanceof BookableResource, fn ($q) => $q->where('bookable_resource_id', $this->id))
            ->when($this instanceof BookableArea, fn ($q) => $q->where('bookable_area_id', $this->id))
            ->count(DB::raw('DISTINCT booking_id'));

        if ($size <= $bookingsCount) {
            throw new NoFreeSizeException;
        }

        $this->ensureRelationsHaveFreeSize(
            dates: $dates,
            relations: $relations,
            ignoresUnbookable: $ignoresUnbookable
        );
    }

    /**
     * @throws RelationsHaveNoFreeSizeException
     */
    public function ensureRelationsHaveFreeSize(
        Collection $dates,
        Collection|EloquentCollection|null $relations = null,
        bool $ignoresUnbookable = false
    ): void {
        if (($relations ?? collect())->isEmpty()) {
            return;
        }

        $bookableAreas = $relations->filter(fn ($bookable) => $bookable instanceof BookableArea);

        if ($bookableAreas->isNotEmpty()) {
            $bookableAreasCheck = BookableArea::query()
                ->select('id')
                ->withSum(['bookableResources' => fn ($query) => $query
                    ->when(! $ignoresUnbookable, fn ($query) => $query->where('is_bookable', true)),
                ], 'size')
                ->withCount([
                    'bookedPeriods' => fn (Builder $query) => $query
                        ->whereDatesAreWithinPeriods($dates)
                        ->distinct('booking_id'),
                ])
                ->whereIn('id', $bookableAreas->pluck('id'))
                ->when(! $ignoresUnbookable, fn ($query) => $query->where('is_bookable', true))
                ->get();

            if (
                $bookableAreasCheck->filter(fn ($bookable) => (int) $bookable->bookable_resources_sum_size > (int) $bookable->booked_period_changes_count)->isEmpty()
            ) {
                throw new RelationsHaveNoFreeSizeException;
            }
        }

        $bookableResources = $relations->filter(fn ($bookable) => $bookable instanceof BookableResource);

        if ($bookableResources->isNotEmpty()) {
            $bookableResourcesCheck = BookableResource::query()
                ->select(['id', 'size'])
                ->withCount([
                    'bookedPeriods' => fn (Builder $query) => $query
                        ->whereDatesAreWithinPeriods($dates)
                        ->distinct('booking_id'),
                ])
                ->whereIn('id', $bookableResources->pluck('id'))
                ->when(! $ignoresUnbookable, fn ($query) => $query->where('is_bookable', true))
                ->get();

            if (
                $bookableResourcesCheck->filter(fn ($bookable) => (int) $bookable->size > (int) $bookable->booked_period_changes_count)->isEmpty()
            ) {
                throw new RelationsHaveNoFreeSizeException;
            }
        }
    }
}
