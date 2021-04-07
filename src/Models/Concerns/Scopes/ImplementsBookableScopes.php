<?php

namespace Masterix21\Bookings\Models\Concerns\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @mixin Model */
trait ImplementsBookableScopes
{
    public function scopeBookable(Builder $builder): Builder
    {
        return $builder->where('is_bookable', true);
    }

    public function scopeUnbookable(Builder $builder): Builder
    {
        return $builder->where('is_bookable', false);
    }
}
