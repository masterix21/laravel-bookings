<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class BookedPeriod extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_excluded' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function period(): Attribute
    {
        return Attribute::get(fn () => Period::make($this->starts_at, $this->ends_at));
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.booking'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function bookableResource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_resource'));
    }

    public function relatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeWhereAllDatesAreWithinPeriods(Builder $builder, PeriodCollection $periods, bool $excluded = false): Builder
    {
        return $builder->where(function (Builder $builder) use ($periods, $excluded) {
            $periods = $periods->unique()->sort();

            $builder->where('is_excluded', $excluded);

            foreach ($periods as $period) {
                $builder->where(
                    fn ($query) => $query
                    ->where('starts_at', '<=', $period->end())
                    ->where('ends_at', '>=', $period->start())
                );
            }
        });
    }

    public function scopeWhereDatesAreWithinPeriods(Builder $builder, PeriodCollection $periods, bool $excluded = false): Builder
    {
        return $builder->where(function (Builder $builder) use ($periods, $excluded) {
            $periods = $periods->unique()->sort();

            $builder
                ->where('is_excluded', $excluded)
                ->where(function (Builder $builder) use ($periods) {
                    foreach ($periods as $period) {
                        $builder->orWhere(
                            fn ($query) => $query
                            ->where('starts_at', '<=', $period->end())
                            ->where('ends_at', '>=', $period->start())
                        );
                    }
                });
        });
    }
}
