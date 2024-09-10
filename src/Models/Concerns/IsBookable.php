<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\BookedResource;
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
            related: BookedPeriod::class,
            through: [
                BookableResource::class,
                BookedResource::class,
            ],
            foreignKeys: [['resource_type', 'resource_id']]
        );
    }
}
