<?php

namespace Masterix21\Bookings\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Models\BookedPeriod;
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

    public function isBookedAt(Carbon $date): bool
    {
        if (! $this->relationLoaded('bookedPeriods')) {
            throw new \Exception('Relation "bookedPeriods" not loaded.');
        }

        return $this->bookedPeriods
            ->contains(fn (BookedPeriod $bookedPeriod) => $bookedPeriod->period->contains($date));
    }

    public function bookedPeriodsOfDate(Carbon $date): Collection
    {
        if (! $this->relationLoaded('bookedPeriods')) {
            throw new \Exception('Relation "bookedPeriods" not loaded.');
        }

        return $this->bookedPeriods
            ->where(fn (BookedPeriod $bookedPeriod) => $bookedPeriod->period->contains($date));
    }
}
