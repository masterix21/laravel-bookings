<?php

namespace Masterix21\Bookings\Models\Concerns\Relationships;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin Model */
trait BelongsToBooking
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.booking'), 'booking_id');
    }
}
