<?php

namespace Masterix21\Bookings\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookableResource;
use Masterix21\Bookings\Models\Concerns\Scopes\HasWherePeriodFromDatesScope;

class BookablePlanning extends Model
{
    use HasFactory;
    use BelongsToBookableArea;
    use BelongsToBookableResource;
    use HasWherePeriodFromDatesScope;

    protected $guarded = [];

    protected $casts = [
        'monday' => 'bool',
        'tuesday' => 'bool',
        'wednesday' => 'bool',
        'thursday' => 'bool',
        'friday' => 'bool',
        'saturday' => 'bool',
        'sunday' => 'bool',
        'from_date' => 'datetime:Y-m-d',
        'to_date' => 'datetime:Y-m-d',
    ];

    public function scopeWhereWeekdaysDates(Builder $builder, Collection | array | string $dates): Builder
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

    public function scopeWhereDatesAreValids(Builder $builder, Collection | array | string $dates): Builder
    {
        return $builder
            ->whereWeekdaysDates($dates)
            ->whereAllDatesAreWithinPeriods($dates);
    }
}
