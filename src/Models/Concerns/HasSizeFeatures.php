<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Exceptions\NoFreeSizeException;
use Masterix21\Bookings\Exceptions\RelationsHaveNoFreeSizeException;
use Masterix21\Bookings\Exceptions\UnbookableException;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Support\PeriodConcurrency;
use Spatie\Period\PeriodCollection;

/** @mixin BookableResource */
trait HasSizeFeatures
{
    abstract public function size(bool $ignoresUnbookable = false): int;

    /**
     * @throws NoFreeSizeException
     * @throws RelationsHaveNoFreeSizeException
     * @throws UnbookableException
     */
    public function ensureHasFreeSize(
        PeriodCollection $periods,
        Collection|EloquentCollection|null $relations = null,
        bool $ignoresUnbookable = false
    ): void {
        $size = $this->size($ignoresUnbookable);

        if ($size === 0) {
            throw new UnbookableException;
        }

        $bookedPeriods = $this->bookedPeriods()
            ->whereDatesAreWithinPeriods($periods) // @phpstan-ignore method.notFound
            ->get();

        $bookingsCount = PeriodConcurrency::maxConcurrentBookings($periods, $bookedPeriods);

        if ($size <= $bookingsCount) {
            throw new NoFreeSizeException;
        }

        $this->ensureRelationsHaveFreeSize(
            periods: $periods,
            relations: $relations,
            ignoresUnbookable: $ignoresUnbookable
        );
    }

    /**
     * @throws RelationsHaveNoFreeSizeException
     */
    public function ensureRelationsHaveFreeSize(
        PeriodCollection $periods,
        Collection|EloquentCollection|null $relations = null,
        bool $ignoresUnbookable = false
    ): void {
        if (($relations ?? collect())->isEmpty()) {
            return;
        }

        $bookableResources = $relations->filter(fn ($bookable) => $bookable instanceof BookableResource);

        if ($bookableResources->isEmpty()) {
            return;
        }

        $resourceIds = $bookableResources->pluck('id');

        $resources = resolve(config('bookings.models.bookable_resource'))::query()
            ->whereIn('id', $resourceIds)
            ->when(! $ignoresUnbookable, fn ($query) => $query->where('is_bookable', true))
            ->get(['id', 'size']);

        $bookedPeriodsByResource = resolve(config('bookings.models.booked_period'))::query()
            ->whereIn($this->getForeignKey(), $resourceIds)
            ->whereDatesAreWithinPeriods($periods) // @phpstan-ignore method.notFound
            ->get()
            ->groupBy($this->getForeignKey());

        $hasAvailableResources = $resources->contains(function (BookableResource $resource) use ($periods, $bookedPeriodsByResource) {
            $occupancy = PeriodConcurrency::maxConcurrentBookings(
                $periods,
                $bookedPeriodsByResource->get($resource->id) ?? new EloquentCollection
            );

            return $resource->size > $occupancy;
        });

        if (! $hasAvailableResources) {
            throw new RelationsHaveNoFreeSizeException;
        }
    }
}
