<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @mixin Model */
trait HasBookings
{
    public function bookings(): HasMany
    {
        return $this->morphMany(config('bookings.models.booking'), 'booker');
    }
}
