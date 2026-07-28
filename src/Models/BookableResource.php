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

    /**
     * A resource is available when its max is unlimited or when no instant of
     * the requested period reaches max concurrent distinct bookings. The peak
     * of the concurrency step function always falls on the (clamped) start of
     * one of the booked periods, so it is enough to test those instants.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAvailableSlotForPeriod(Builder $query, Period $period): Builder
    {
        return $query
            ->where('is_bookable', true)
            ->where(function (Builder $query) use ($period) {
                $query
                    ->whereNull($this->qualifyColumn('max'))
                    ->orWhereNotExists(function (QueryBuilder $exists) use ($period) {
                        $grammar = $exists->getGrammar();
                        $bookedPeriodsTable = app(config('bookings.models.booked_period'))->getTable();
                        $bookingForeignKey = app(config('bookings.models.booking'))->getForeignKey();
                        $resourceForeignKey = $this->getForeignKey();
                        $requestStart = $period->start()->format('Y-m-d H:i:s');

                        $clampedInstant = 'CASE WHEN '.$grammar->wrap('p1.starts_at').' > ? THEN '.$grammar->wrap('p1.starts_at').' ELSE ? END';

                        $concurrencySql = 'SELECT COUNT(DISTINCT '.$grammar->wrap("p2.{$bookingForeignKey}").')'
                            .' FROM '.$grammar->wrapTable("{$bookedPeriodsTable} as p2")
                            .' WHERE '.$grammar->wrap("p2.{$resourceForeignKey}").' = '.$grammar->wrap($this->qualifyColumn('id'))
                            .' AND '.$grammar->wrap('p2.is_excluded').' = false'
                            .' AND '.$grammar->wrap('p2.deleted_at').' IS NULL'
                            .' AND '.$grammar->wrap('p2.starts_at').' <= ('.$clampedInstant.')'
                            .' AND '.$grammar->wrap('p2.ends_at').' >= ('.$clampedInstant.')';

                        $exists
                            ->from("{$bookedPeriodsTable} as p1")
                            ->whereColumn("p1.{$resourceForeignKey}", $this->qualifyColumn('id'))
                            ->where('p1.is_excluded', false)
                            ->whereNull('p1.deleted_at')
                            ->where('p1.starts_at', '<=', $period->end())
                            ->where('p1.ends_at', '>=', $period->start())
                            ->whereRaw(
                                "({$concurrencySql}) >= ".$grammar->wrap($this->qualifyColumn('max')),
                                [$requestStart, $requestStart, $requestStart, $requestStart]
                            );
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
