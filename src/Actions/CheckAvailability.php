<?php

namespace Masterix21\Bookings\Actions;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Actions\Checks\BookableHasAvailableSeats;
use Masterix21\Bookings\Actions\Checks\BookableHasValidPlannings;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\PeriodCollection;

class CheckAvailability
{
    use AsAction;

    public function handle(
        PeriodCollection $periods,
        BookableArea | BookableResource $bookable,
        Collection | EloquentCollection | null $relations = null,
        bool $ignoresUnbookableResources = false
    ) {
        $dates = app('bookings')->periodsToDates($periods);

        BookableHasValidPlannings::run(dates: $dates, bookable: $bookable, relations: $relations);

        BookableHasAvailableSeats::run(
            dates: $dates,
            bookable: $bookable,
            relations: $relations,
            ignoresUnbookableResources: $ignoresUnbookableResources
        );
    }
}
