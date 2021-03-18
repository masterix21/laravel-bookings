<?php


namespace Masterix21\Bookings\Models\Concerns\Scopes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** @mixin Model */
trait HasWherePeriodFromDatesScope
{
    public function scopeWhereAllDatesAreWithinPeriods(Builder $builder, Collection|array|string $dates): Builder
    {
        return $builder->where(function ($query) use ($dates) {
            Collection::wrap($dates)
                ->unique()
                ->each(fn ($date) => $query->whereBetweenColumns(Carbon::parse($date)->format('Y-m-d'), ['from_date', 'to_date']));
        });
    }

    public function scopeWhereDatesAreWithinPeriods(Builder $builder, Collection|array|string $dates): Builder
    {
        return $builder->where(function ($query) use ($dates) {
            Collection::wrap($dates)
                ->unique()
                ->each(fn ($date) => $query->orWhereBetweenColumns(Carbon::parse($date)->format('Y-m-d'), ['from_date', 'to_date']));
        });
    }
}
