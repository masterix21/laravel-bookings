<?php
namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @mixin Model */
trait HasResources
{
    public function resources(): HasMany
    {
        return $this->hasMany(config('bookings.models.resource'));
    }
}
