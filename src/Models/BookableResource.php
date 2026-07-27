<?php

declare(strict_types=1);

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Masterix21\Bookings\Models\Concerns\HasSizeFeatures;
use Masterix21\Bookings\Models\Concerns\ImplementsBook;
use Masterix21\Bookings\Models\Concerns\ImplementsEnsureIsAvailable;
use Masterix21\Bookings\Models\Concerns\Scopes\ImplementsBookableScopes;
use Masterix21\Bookings\Models\Concerns\Scopes\ImplementsVisibleScopes;
use Masterix21\Bookings\Models\Concerns\UsesBookablePlannings;
use Spatie\Period\Period;

/**
 * @property int $id
 * @property string|null $code
 * @property string $resource_type
 * @property int $resource_id
 * @property int|null $min
 * @property int|null $max
 * @property int|null $max_nested
 * @property int $size
 * @property bool $is_visible
 * @property bool $is_bookable
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder availableSlotForPeriod(Period $period)
 * @method static Builder availableForPeriod(Period $period)
 * @method static Builder withBookingsInPeriod(Period $period)
 */
class BookableResource extends Model
{
    use HasFactory;
    use HasSizeFeatures;
    use ImplementsBook;
    use ImplementsBookableScopes;
    use ImplementsEnsureIsAvailable;
    use ImplementsVisibleScopes;
    use UsesBookablePlannings;

    protected $fillable = [
        'code',
        'resource_type',
        'resource_id',
        'min',
        'max',
        'max_nested',
        'size',
        'is_visible',
        'is_bookable',
    ];

    protected $casts = [
        'is_visible' => 'bool',
        'is_bookable' => 'bool',
    ];

    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookableRelations(): HasMany
    {
        return $this->hasMany(
            config('bookings.models.bookable_relation'),
            'parent_bookable_resource_id'
        );
    }

    public function bookedPeriods(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_period'))->chaperone();
    }

    public function size(bool $ignoresUnbookable = false): int
    {
        if ($ignoresUnbookable || $this->is_bookable) {
            return $this->size;
        }

        return 0;
    }

    /** @param Builder<self> $query */
    public function scopeAvailableSlotForPeriod(Builder $query, Period $period): Builder
    {
        return $query
            ->where('is_bookable', true)
            ->where(function (Builder $query) use ($period) {
                $query
                    ->whereNull($this->qualifyColumn('max'))
                    ->orWhere($this->qualifyColumn('max'), '>', function (QueryBuilder $subQuery) use ($period) {
                        $bookedPeriodModel = app(config('bookings.models.booked_period'));
                        $bookingForeignKey = app(config('bookings.models.booking'))->getForeignKey();

                        $subQuery
                            ->from($bookedPeriodModel->getTable())
                            ->selectRaw("count(distinct {$bookingForeignKey})")
                            ->whereColumn($bookedPeriodModel->qualifyColumn($this->getForeignKey()), $this->qualifyColumn('id'))
                            ->where('is_excluded', false)
                            ->whereNull('deleted_at')
                            ->where('starts_at', '<=', $period->end())
                            ->where('ends_at', '>=', $period->start());
                    });
            });
    }

    /** @param Builder<self> $query */
    public function scopeAvailableForPeriod(Builder $query, Period $period): Builder
    {
        return $query
            ->availableSlotForPeriod($period)
            ->whereHas('bookablePlannings', fn (Builder $q) => $q->wherePeriodIsValid($period)); // @phpstan-ignore method.notFound
    }

    /** @param Builder<self> $query */
    public function scopeWithBookingsInPeriod(Builder $query, Period $period): Builder
    {
        return $query->with(['bookedPeriods' => function (Relation $query) use ($period) {
            $query->where('starts_at', '<=', $period->end())
                ->where('ends_at', '>=', $period->start())
                ->with('booking');
        }]);
    }
}
