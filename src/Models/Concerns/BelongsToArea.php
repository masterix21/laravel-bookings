<?php
namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin Model */
trait BelongsToArea
{
    public function area(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.area'));
    }
}
