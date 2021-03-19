<?php

namespace Masterix21\Bookings\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Masterix21\Bookings\Exceptions\VerifyAvailability\NoSeatsException;
use Masterix21\Bookings\Exceptions\VerifyAvailability\OutOfPlanningsException;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\PeriodCollection;

class VerifyAvailability
{
    use AsAction;

    /**
     * @param PeriodCollection $periods
     * @param BookableArea|BookableResource $bookable
     * @param null|BookableRelation[] $relations
     * @throws OutOfPlanningsException
     * @throws NoSeatsException
     */
    public function handle(
        PeriodCollection $periods,
        BookableArea | BookableResource $bookable,
        ?array $relations = null,
    ) {
        $dates = app('bookings')->periodsToDates($periods);

        if (! BookableHasValidPlannings::run(dates: $dates, bookable: $bookable, relations: $relations)) {
            throw new OutOfPlanningsException();
        }

        if (! BookableHasAvailableSeats::run(dates: $dates, bookable: $bookable, relations: $relations)) {
            throw new NoSeatsException();
        }
    }
}
