<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Masterix21\Bookings\Exceptions\NoFreeSizeException;
use Masterix21\Bookings\Exceptions\RelationsHaveNoFreeSizeException;
use Masterix21\Bookings\Exceptions\UnbookableException;
use Masterix21\Bookings\Models\BookableResource;
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

        $bookingsCount = $this->bookedPeriods()
            ->whereDatesAreWithinPeriods($periods) // @phpstan-ignore method.notFound
            ->count(DB::raw('DISTINCT booking_id'));

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

        if ($bookableResources->isNotEmpty()) {
            $resourceIds = $bookableResources->pluck('id');

            $hasAvailableResources = BookableResource::query()
                ->select(['id', 'size'])
                ->withCount([
                    'bookedPeriods' => fn (Builder $query) => $query
                        ->whereDatesAreWithinPeriods($periods) // @phpstan-ignore method.notFound
                        ->distinct('booking_id'),
                ])
                ->whereIn('id', $resourceIds)
                ->when(! $ignoresUnbookable, fn ($query) => $query->where('is_bookable', true))
                ->havingRaw('size > booked_periods_count')
                ->exists();

            if (! $hasAvailableResources) {
                throw new RelationsHaveNoFreeSizeException;
            }
        }
    }
}
