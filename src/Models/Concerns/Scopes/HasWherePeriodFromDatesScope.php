<?php


namespace Masterix21\Bookings\Models\Concerns\Scopes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** @mixin Model */
trait HasWherePeriodFromDatesScope
{
    public function scopeWhereAllDatesAreWithinPeriods(Builder $builder, Collection | array | string $dates): Builder
    {
        return $builder->where(function ($query) use ($dates) {
            Collection::wrap($dates)
                ->unique()
                ->each(function ($date) use ($query) {
                    $date = Carbon::parse($date);

                    return $query
                        ->where(function ($query) use ($date) {
                            $query
                                ->where(fn ($q) => $q->whereNull('from_date')->orWhereDate('from_date', '<=', $date))
                                ->where(fn ($q) => $q->whereNull('to_date')->orWhereDate('to_date', '>=', $date));
                        })
                        ->where(function ($query) use ($date) {
                            $query
                                ->where(fn ($q) => $q->whereNull('from_time')->orWhereTime('from_time', '<=', $date))
                                ->where(fn ($q) => $q->whereNull('to_time')->orWhereTime('to_time', '>=', $date));
                        });
                });
        });
    }

    public function scopeWhereDatesAreWithinPeriods(Builder $builder, Collection | array | string $dates): Builder
    {
        return $builder->where(function (Builder $builder) use ($dates) {
            Collection::wrap($dates)
                ->unique()
                ->each(function ($date) use ($builder) {
                    $builder->orWhere(function (Builder $query) use ($date) {
                        $date = Carbon::parse($date);

                        $query
                            ->where(function ($query) use ($date) {
                                $query
                                    ->where(fn ($q) => $q->whereNull('from_date')->orWhereDate('from_date', '<=', $date))
                                    ->where(fn ($q) => $q->whereNull('to_date')->orWhereDate('to_date', '>=', $date));
                            })
                            ->where(function ($query) use ($date) {
                                $query
                                    ->where(fn ($q) => $q->whereNull('from_time')->orWhereTime('from_time', '<=', $date))
                                    ->where(fn ($q) => $q->whereNull('to_time')->orWhereTime('to_time', '>=', $date));
                            });
                    });
                });
        });
    }
}
