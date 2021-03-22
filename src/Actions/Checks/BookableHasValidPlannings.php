<?php

namespace Masterix21\Bookings\Actions\Checks;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Exceptions\CheckAvailability\OutOfPlanningsException;
use Masterix21\Bookings\Exceptions\CheckAvailability\RelationsHaveNoSeatsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableResource;

class BookableHasValidPlannings
{
    use AsAction;

    public function handle(
        Collection $dates,
        BookableArea | BookableResource $bookable,
        Collection | EloquentCollection | null $relations = null,
    ) {
        $result = BookablePlanning::query()
            ->when($bookable instanceof BookableArea, fn ($query) => $query->where('bookable_area_id', $bookable->id))
            ->when($bookable instanceof BookableResource, fn ($query) => $query->where(function ($query) use ($bookable) {
                $query->where('bookable_resource_id', $bookable->id)
                    ->orWhere('bookable_area_id', $bookable->bookable_area_id);
            }))
            ->whereDatesAreValids($dates)
            ->count() > 0;

        if (! $result) {
            throw new OutOfPlanningsException();
        }

        if (blank($relations)) {
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
            throw new RelationsHaveNoSeatsException();
        }
    }
}
