<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\PeriodCollection;

/** @mixin BookableArea | BookableResource */
trait ImplementsEnsureIsAvailable
{
    /**
     * @param PeriodCollection $periods
     * @param Collection|EloquentCollection|null $relations
     * @param bool $ignoresUnbookable
     * @throws \Masterix21\Bookings\Exceptions\NoSeatsException
     * @throws \Masterix21\Bookings\Exceptions\OutOfPlanningsException
     * @throws \Masterix21\Bookings\Exceptions\RelationsHaveNoSeatsException
     * @throws \Masterix21\Bookings\Exceptions\RelationsOutOfPlanningsException
     * @throws \Masterix21\Bookings\Exceptions\UnbookableException
     */
    public function ensureIsAvailable(
        PeriodCollection $periods,
        Collection | EloquentCollection | null $relations = null,
        bool $ignoresUnbookable = false
    ): void {
        $dates = app('bookings')->periodsToDates($periods);

        $this->ensureHasValidPlannings(dates: $dates, relations: $relations);

        $this->ensureHasFreeSize(dates: $dates, relations: $relations, ignoresUnbookable: $ignoresUnbookable);
    }
}
