<?php

namespace Masterix21\Bookings\Models\Concerns\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @mixin Model */
trait ImplementsVisibleScopes
{
    public function scopeVisible(Builder $builder): Builder
    {
        return $builder->where('is_visible', true);
    }

    public function scopeHidden(Builder $builder): Builder
    {
        return $builder->where('is_visible', false);
    }
}
