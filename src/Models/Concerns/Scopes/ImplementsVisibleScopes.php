<?php

namespace Masterix21\Bookings\Models\Concerns\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Kirschbaum\PowerJoins\PowerJoinClause;

/** @mixin Model */
trait ImplementsVisibleScopes
{
    public function scopeVisible(Builder|PowerJoinClause $builder): Builder|PowerJoinClause
    {
        return $builder->where($this->getTable() .'.is_visible', true);
    }

    public function scopeHidden(Builder|PowerJoinClause $builder): Builder|PowerJoinClause
    {
        return $builder->where($this->getTable() .'.is_visible', false);
    }
}
