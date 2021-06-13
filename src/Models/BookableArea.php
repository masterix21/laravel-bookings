<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kirschbaum\PowerJoins\PowerJoins;
use Masterix21\Bookings\Models\Concerns\HasSizeFeatures;
use Masterix21\Bookings\Models\Concerns\Relationships\HasBookedPeriods;
use Masterix21\Bookings\Models\Concerns\Scopes\ImplementsBookableScopes;
use Masterix21\Bookings\Models\Concerns\Scopes\ImplementsVisibleScopes;
use Masterix21\Bookings\Models\Concerns\UsesBookablePlannings;

class BookableArea extends Model
{
    use HasFactory;
    use PowerJoins;
    use HasBookedPeriods;
    use UsesBookablePlannings;
    use HasSizeFeatures;
    use ImplementsVisibleScopes;
    use ImplementsBookableScopes;

    protected $guarded = [];

    public function bookableResources(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_resource'));
    }

    public function bookableRelations(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_relation'), 'parent_bookable_area_id');
    }

    public function size(bool $ignoresUnbookable = false): int
    {
        return $this
            ->bookableResources()
            ->when(! $ignoresUnbookable, fn ($query) => $query->where('is_bookable', true))
            ->sum('size');
    }

    public function firstAvailableResource(int $quantity, Model $model): Collection
    {
        return $this->bookableResources()
            ->select(['bookable_resources.*'])
            ->leftJoin(
                'booked_resources',
                fn (JoinClause $join) => $join
                    ->whereColumn('bookable_resources.id', 'booked_resources.bookable_resource_id')
            )
            ->whereNull('booked_resources.bookable_resource_id')
            ->where('model_id', $model->getKey())
            ->where('model_type', get_class($model))
            ->limit($quantity)
            ->get();
    }

}
