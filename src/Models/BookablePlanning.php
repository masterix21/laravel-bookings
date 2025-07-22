<?php

namespace Masterix21\Bookings\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class BookablePlanning extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'monday' => 'bool',
        'tuesday' => 'bool',
        'wednesday' => 'bool',
        'thursday' => 'bool',
        'friday' => 'bool',
        'saturday' => 'bool',
        'sunday' => 'bool',
        'from_date' => 'date:Y-m-d',
        'to_date' => 'date:Y-m-d',
        'from_time' => 'datetime:H:i',
        'to_time' => 'datetime:H:i',
    ];

    public function bookableResource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_resource'));
    }

    public function scopeWhereWeekdaysDates(Builder $builder, Collection|array|string $dates): Builder
    {
        return $builder->where(function (Builder $query) use ($dates) {
            Collection::wrap($dates)
                ->unique()
                ->each(fn ($date) => tap(Carbon::parse($date), function ($date) use ($query) {
                    $query
                        ->when($date->isMonday(), fn ($q) => $q->where('monday', 1))
                        ->when($date->isTuesday(), fn ($q) => $q->where('tuesday', 1))
                        ->when($date->isWednesday(), fn ($q) => $q->where('wednesday', 1))
                        ->when($date->isThursday(), fn ($q) => $q->where('thursday', 1))
                        ->when($date->isFriday(), fn ($q) => $q->where('friday', 1))
                        ->when($date->isSaturday(), fn ($q) => $q->where('saturday', 1))
                        ->when($date->isSunday(), fn ($q) => $q->where('sunday', 1));
                }));
        });
    }

    public function scopeWhereAllDatesAreWithinPeriods(Builder $builder, Collection|array|string $dates): Builder
    {
        return $builder->where(function ($query) use ($dates) {
            Collection::wrap($dates)
                ->unique()
                ->each(function ($date) use ($query) {
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
    }

    public function scopeWhereDatesAreWithinPeriods(Builder $builder, Collection|array|string $dates): Builder
    {
        return $builder->where(function (Builder $builder) use ($dates) {
            Collection::wrap($dates)
                ->unique()
                ->each(function ($date) use ($builder) {
                    $builder
                        ->orWhere(function ($query) use ($date) {
                            $query
                                ->where(fn ($q) => $q->whereNull('from_date')->orWhereDate('from_date', '<=', $date))
                                ->where(fn ($q) => $q->whereNull('to_date')->orWhereDate('to_date', '>=', $date));
                        })
                        ->orWhere(function ($query) use ($date) {
                            $query
                                ->where(fn ($q) => $q->whereNull('from_time')->orWhereTime('from_time', '<=', $date))
                                ->where(fn ($q) => $q->whereNull('to_time')->orWhereTime('to_time', '>=', $date));
                        });
                });
        });
    }

    public function scopeWhereDatesAreValids(Builder $builder, Collection|array|string $dates): Builder
    {
        return $builder
            ->whereWeekdaysDates($dates)
            ->whereAllDatesAreWithinPeriods($dates);
    }
}
