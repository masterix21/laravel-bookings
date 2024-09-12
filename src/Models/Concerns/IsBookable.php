<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/** @mixin Model */
trait IsBookable
{
    use HasRelationships;
    use ImplementsEnsureIsAvailable;

    public static function bootIsBookable(): void
    {
        static::deleting(static function (Bookable $model) {
            $model->bookableResource()->delete();
        });
    }

    public function bookableResources(): MorphMany
    {
        return $this->morphMany(config('bookings.models.bookable_resource'), 'resource');
    }

    public function bookableResource(): MorphOne
    {
        return $this->morphOne(config('bookings.models.bookable_resource'), 'resource');
    }

    public function bookedPeriods(): HasManyDeep
    {
        return $this->hasManyDeep(
            config('bookings.models.booked_period'),
            [config('bookings.models.bookable_resource')],
            [['resource_type', 'resource_id'], null]
        );
    }
}
