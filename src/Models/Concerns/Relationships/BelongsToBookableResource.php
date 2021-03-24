<?php

namespace Masterix21\Bookings\Models\Concerns\Relationships;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin Model */
trait BelongsToBookableResource
{
    public function bookableResource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_resource'), 'bookable_resource_id');
    }
}
