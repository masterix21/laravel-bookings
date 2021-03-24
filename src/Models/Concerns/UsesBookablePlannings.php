<?php


namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
use Masterix21\Bookings\Exceptions\RelationsOutOfPlanningsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableResource;

/** @mixin Model */
trait UsesBookablePlannings
{
    public function bookablePlannings(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_planning'));
    }

    /**
     * @param Collection $dates
     * @param Collection|EloquentCollection $relations
     * @throws OutOfPlanningsException
     * @throws RelationsOutOfPlanningsException
     */
    public function ensureHasValidPlannings(Collection $dates, Collection | EloquentCollection | null $relations = null): void
    {
        $result = BookablePlanning::query()
                ->when($this instanceof BookableArea, fn ($query) => $query->where('bookable_area_id', $this->id))
                ->when($this instanceof BookableResource, fn ($query) => $query->where(function ($query) {
                    $query->where('bookable_resource_id', $this->id)
                        ->orWhere('bookable_area_id', $this->bookable_area_id);
                }))
                ->whereDatesAreValids($dates)
                ->count() > 0;

        if (! $result) {
            throw new OutOfPlanningsException();
        }

        $this->ensureRelationsHaveValidPlannings(dates: $dates, relations: $relations);
    }

    /**
     * @param Collection $dates
     * @param Collection|EloquentCollection $relations
     * @throws RelationsOutOfPlanningsException
     */
    public function ensureRelationsHaveValidPlannings(
        Collection $dates,
        Collection | EloquentCollection | null $relations = null,
    ): void {
        if (($relations ?? collect())->isEmpty()) {
            return;
        }

        $result = BookableResource::query()
                ->where(function (Builder $query) use ($relations) {
                    $bookableAreas = $relations->filter(fn ($bookable) => $bookable instanceof BookableArea);
                    $bookableResources = $relations->filter(fn ($bookable) => $bookable instanceof BookableResource);

                    $query->when($bookableAreas->isNotEmpty(), fn ($q) => $q->orWhereIn('bookable_area_id', $bookableAreas->pluck('id')));
                    $query->when($bookableResources->isNotEmpty(), fn ($q) => $q->orWhereIn('id', $bookableResources->pluck('id')));
                })
                ->where(
                    fn (Builder $query) => $query
                        ->where(
                            fn (Builder $query) => $query
                                ->whereDoesntHave('bookablePlannings')
                                ->whereDoesntHave('bookableArea.bookablePlannings')
                        )
                        ->orWhere(
                            fn (Builder $query) => $query
                                ->whereHas('bookablePlannings', fn (Builder $query) => $query->whereDatesAreValids($dates))
                                ->orWhereHas('bookableArea.bookablePlannings', fn (Builder $query) => $query->whereDatesAreValids($dates))
                        )
                )
                ->count() > 0;

        if (! $result) {
            throw new RelationsOutOfPlanningsException();
        }
    }
}
