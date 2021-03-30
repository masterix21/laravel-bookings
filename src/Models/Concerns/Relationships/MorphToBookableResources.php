<?php

namespace Masterix21\Bookings\Models\Concerns\Relationships;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait MorphToBookableResources
{
    public function bookableResources(): MorphMany
    {
        return $this->morphMany(config('bookings.models.bookable_resource'), 'model');
    }
}
