<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait HasBookings
{
    public function bookings(): MorphMany
    {
        return $this->morphMany(config('bookings.models.booking'), 'booker');
    }
}
