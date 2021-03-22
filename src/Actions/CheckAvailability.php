<?php

namespace Masterix21\Bookings\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Actions\Checks\BookableHasAvailableSeats;
use Masterix21\Bookings\Actions\Checks\BookableHasValidPlannings;
use Masterix21\Bookings\Exceptions\CheckAvailability\UnbookableException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\PeriodCollection;

class CheckAvailability
{
    use AsAction;

    /**
     * @param PeriodCollection $periods
     * @param BookableArea|BookableResource $bookable
     * @param null|BookableRelation[] $relations
     * @throws OutOfPlanningsException
     * @throws UnbookableException
     * @throws NoSeatsException
     */
    public function handle(
        PeriodCollection $periods,
        BookableArea | BookableResource $bookable,
        ?array $relations = null,
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
