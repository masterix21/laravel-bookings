<?php

declare(strict_types=1);

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Masterix21\Bookings\Models\Concerns\HasSizeFeatures;
use Masterix21\Bookings\Models\Concerns\ImplementsBook;
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
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class BookableResource extends Model
{
    use HasFactory;
    use HasSizeFeatures;
    use ImplementsBook;
    use ImplementsBookableScopes;
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
            'parent_bookable_area_id',
            'bookable_area_id'
        )->where(function (Builder $query) {
            $query->whereNull('parent_bookable_resource_id')
                ->orWhereColumn('parent_bookable_resource_id', 'bookable_resources.id');
        });
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

    public function scopeAvailableSlotForPeriod(Builder $query, Period $period): Builder
    {
        $bookedPeriodModel = app(config('bookings.models.booked_period'));
        $bookedPeriodTable = $bookedPeriodModel->getTable();

        return $query->where('is_bookable', true)
            ->whereRaw('bookable_resources.max > (SELECT COUNT(*) FROM '.$bookedPeriodTable.' WHERE bookable_resource_id = bookable_resources.id AND starts_at < ? AND ends_at > ? AND deleted_at IS NULL)', [
                $period->end()->format('Y-m-d H:i:s'),
                $period->start()->format('Y-m-d H:i:s'),
            ]);
    }

    public function scopeAvailableForPeriod(Builder $query, Period $period): Builder
    {
        return $query
            ->availableSlotForPeriod($period)
            ->whereHas('bookablePlannings', fn (Builder $q) => $q->wherePeriodIsValid($period));
    }

    public function scopeWithBookingsInPeriod(Builder $query, Period $period): Builder
    {
        return $query->with(['bookedPeriods' => function (Builder $query) use ($period) {
            $query->where('starts_at', '<', $period->end())
                ->where('ends_at', '>', $period->start())
                ->with('booking');
        }]);
    }
}
