<?php

namespace Masterix21\Bookings\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Exceptions\NoFreeSizeException;
use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
use Masterix21\Bookings\Exceptions\RelationsHaveNoFreeSizeException;
use Masterix21\Bookings\Exceptions\RelationsOutOfPlanningsException;
use Masterix21\Bookings\Exceptions\UnbookableException;
use Masterix21\Bookings\Models\BookedPeriod;
use Spatie\Period\PeriodCollection;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/** @mixin Model */
// @phpstan-ignore trait.unused
trait IsBookable
{
    use HasRelationships;

    public static function bootIsBookable(): void
    {
        static::deleting(static function (Bookable $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->bookableResource()->delete();
        });
    }

    /**
     * Ensure at least one of the model's bookable resources can host the
     * given periods. Rethrows the last failure when none is available.
     *
     * @throws NoFreeSizeException
     * @throws OutOfPlanningsException
     * @throws RelationsHaveNoFreeSizeException
     * @throws RelationsOutOfPlanningsException
     * @throws UnbookableException
     */
    public function ensureIsAvailable(
        PeriodCollection $periods,
        Collection|EloquentCollection|null $relations = null,
        bool $ignoresUnbookable = false
    ): void {
        if ($this->bookableResources->isEmpty()) {
            throw new UnbookableException;
        }

        $lastException = null;

        foreach ($this->bookableResources as $bookableResource) {
            try {
                $bookableResource->ensureIsAvailable($periods, $relations, $ignoresUnbookable);

                return;
            } catch (
                NoFreeSizeException|
                OutOfPlanningsException|
                RelationsHaveNoFreeSizeException|
                RelationsOutOfPlanningsException|
                UnbookableException $exception
            ) {
                $lastException = $exception;
            }
        }

        throw $lastException;
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
        return $this->hasManyDeepFromRelations($this->bookedPeriods(), (new BookedPeriod)->booking());
    }
}
