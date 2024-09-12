<?php

namespace Masterix21\Bookings\Models\Concerns\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Kirschbaum\PowerJoins\PowerJoinClause;

/** @mixin Model */
trait ImplementsBookableScopes
{
    public function scopeBookable(Builder|PowerJoinClause $builder): Builder|PowerJoinClause
    {
        return $builder->where($this->getTable().'.is_bookable', true);
    }

    public function scopeUnbookable(Builder|PowerJoinClause $builder): Builder|PowerJoinClause
    {
        return $builder->where($this->getTable().'.is_bookable', false);
    }
}
