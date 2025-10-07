<?php

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

class BookableResource extends Model
{
    use HasFactory;
    use HasSizeFeatures;
    use ImplementsBook;
    use ImplementsBookableScopes;
    use ImplementsVisibleScopes;
    use UsesBookablePlannings;

    protected $guarded = [];

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
                ->orWhere('parent_bookable_resource_id', 'bookable_resources.id');
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

    public function scopeAvailableForPeriod(Builder $query, Period $period): Builder
    {
        return $query->where('is_bookable', true)
            ->withCount(['bookedPeriods' => function (Builder $query) use ($period) {
                $query->where('starts_at', '<', $period->end())
                    ->where('ends_at', '>', $period->start());
            }])
            ->havingRaw('booked_periods_count < max');
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
