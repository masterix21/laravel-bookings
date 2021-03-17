<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin Model */
trait BelongsToBookedResource
{
    public function bookedResource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.booked_resource'), 'booked_resource_id');
    }
}
