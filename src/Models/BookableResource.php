<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Masterix21\Bookings\Models\Concerns\HasSizeFeatures;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\Relationships\HasBookedPeriods;
use Masterix21\Bookings\Models\Concerns\UsesBookablePlannings;

class BookableResource extends Model
{
    use HasFactory;
    use BelongsToBookableArea;
    use HasBookedPeriods;
    use UsesBookablePlannings;
    use HasSizeFeatures;

    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookableRelations(): HasMany
    {
        return $this->hasMany(
            config('bookings.models.bookable_relation'),
            'bookable_area_id',
            'bookable_area_id'
        )->where(function (Builder $query) {
            $query->whereNull('bookable_resource_id')
                ->orWhereColumn('bookable_resource_id', 'bookable_resources.id');
        });
    }

    public function size(bool $ignoresUnbookable = false): int
    {
        if ($ignoresUnbookable || $this->is_bookable) {
            return $this->size;
        }

        return 0;
    }
}
