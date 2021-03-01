<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin Model */
trait BelongsToResource
{
    public function resource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.resource'));
    }
}
