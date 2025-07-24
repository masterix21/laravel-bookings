<?php

namespace Masterix21\Bookings\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Models\BookedPeriod;
use Spatie\Period\Period;
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
        return $this->morphMany(config('bookings.models.bookable_resource'), 'resource')->chaperone();
    }

    public function bookableResource(): MorphOne
    {
        return $this->morphOne(config('bookings.models.bookable_resource'), 'resource')->chaperone();
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
        if ($this->relationLoaded('bookedPeriods')) {
            return $this->bookedPeriods
                ->contains(fn (BookedPeriod $bookedPeriod) => $bookedPeriod->period->contains($date));
        }

        return $this->bookedPeriods()
            ->where('starts_at', '<=', $date)
            ->where('ends_at', '>=', $date)
            ->exists();
    }

    public function bookedPeriodsOfDate(Carbon $date): Collection
    {
        if ($this->relationLoaded('bookedPeriods')) {
            return $this->bookedPeriods
                ->filter(fn (BookedPeriod $bookedPeriod) => $bookedPeriod->period->contains($date));
        }

        return $this->bookedPeriods()
            ->where('starts_at', '<=', $date)
            ->where('ends_at', '>=', $date)
            ->get();
    }

    public function bookings(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations($this->bookedPeriods(), (new BookedPeriod())->booking());
    }
}
