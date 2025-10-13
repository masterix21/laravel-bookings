<?php

declare(strict_types=1);

namespace Masterix21\Bookings\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Enums\PlanningMatchingStrategy;
use Masterix21\Bookings\Enums\Weekday;

/**
 * @property int $id
 * @property int $bookable_resource_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property bool $monday
 * @property bool $tuesday
 * @property bool $wednesday
 * @property bool $thursday
 * @property bool $friday
 * @property bool $saturday
 * @property bool $sunday
 * @property PlanningMatchingStrategy $matching_strategy
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class BookablePlanning extends Model
{
    use HasFactory;

    protected $fillable = [
        'bookable_resource_id',
        'source_type',
        'source_id',
        'starts_at',
        'ends_at',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
        'matching_strategy',
    ];

    protected $casts = [
        'monday' => 'bool',
        'tuesday' => 'bool',
        'wednesday' => 'bool',
        'thursday' => 'bool',
        'friday' => 'bool',
        'saturday' => 'bool',
        'sunday' => 'bool',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'matching_strategy' => PlanningMatchingStrategy::class,
    ];

    public function bookableResource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_resource'));
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
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
                                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $date))
                                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $date));
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
                                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $date))
                                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $date));
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

    public function scopeWherePeriodIsValid(Builder $builder, \Spatie\Period\Period $period): Builder
    {
        $start = Carbon::parse($period->start());
        $end = Carbon::parse($period->end());

        $weekdaysInPeriod = collect();
        $current = $start->copy();

        while ($current->lte($end)) {
            $weekdaysInPeriod->push($current->dayOfWeek);
            $current->addDay();

            if ($weekdaysInPeriod->count() >= 7) {
                break;
            }
        }

        $weekdaysInPeriod = $weekdaysInPeriod->unique();

        return $builder->where(function (Builder $query) use ($weekdaysInPeriod, $start, $end) {
            $query->where(function (Builder $subQuery) use ($weekdaysInPeriod) {
                $subQuery->where(function (Builder $allStrategy) use ($weekdaysInPeriod) {
                    $allStrategy->where('matching_strategy', PlanningMatchingStrategy::All->value);

                    $weekdaysInPeriod->each(function ($dayOfWeek) use ($allStrategy) {
                        $weekdayColumn = Weekday::fromCarbonDay($dayOfWeek)->value;

                        $allStrategy->where($weekdayColumn, true);
                    });
                })->orWhere(function (Builder $anyStrategy) use ($weekdaysInPeriod) {
                    $anyStrategy->where('matching_strategy', PlanningMatchingStrategy::Any->value);

                    $anyStrategy->where(function (Builder $weekdayOr) use ($weekdaysInPeriod) {
                        $weekdaysInPeriod->each(function ($dayOfWeek) use ($weekdayOr) {
                            $weekdayColumn = Weekday::fromCarbonDay($dayOfWeek)->value;

                            $weekdayOr->orWhere($weekdayColumn, true);
                        });
                    });
                });
            });

            $query
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $end))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $start));
        });
    }
}
