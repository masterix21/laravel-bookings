<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** @mixin Model */
trait HasBookedPeriodRelated
{
    public function bookedPeriods(): MorphMany
    {
        return $this->morphMany(config('bookings.models.booked_period'), 'relatable')->chaperone();
    }

    public function bookedPeriod(): MorphOne
    {
        return $this->morphOne(config('bookings.models.booked_period'), 'relatable')->chaperone();
    }
}
