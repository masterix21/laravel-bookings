<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin Model */
trait BelongsToBookableArea
{
    public function bookableArea(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_area'), 'bookable_area_id');
    }
}
