<?php

declare(strict_types=1);

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

/**
 * @property int $id
 * @property int $booking_id
 * @property int|null $bookable_resource_id
 * @property string|null $relatable_type
 * @property int|null $relatable_id
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property bool $is_excluded
 * @property string|null $label
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Period $period
 */
class BookedPeriod extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'booking_id',
        'bookable_resource_id',
        'relatable_type',
        'relatable_id',
        'parent_id',
        'starts_at',
        'ends_at',
        'is_excluded',
        'label',
        'note',
    ];

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
