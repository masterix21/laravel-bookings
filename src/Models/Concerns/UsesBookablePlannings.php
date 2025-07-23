<?php

namespace Masterix21\Bookings\Models\Concerns;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Masterix21\Bookings\Events\PlanningValidationFailed;
use Masterix21\Bookings\Events\PlanningValidationPassed;
use Masterix21\Bookings\Events\PlanningValidationStarted;
use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
use Masterix21\Bookings\Exceptions\RelationsOutOfPlanningsException;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableResource;

/** @mixin Model */
trait UsesBookablePlannings
{
    public function bookablePlannings(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_planning'));
    }

    /**
     * Validates that this resource and its relations have valid plannings for the given dates.
     *
     * @param Collection<\Carbon\Carbon> $dates
     * @param Collection<BookableResource>|EloquentCollection<int, BookableResource>|null $relations
     *
     * @throws OutOfPlanningsException
     * @throws RelationsOutOfPlanningsException
     * @throws InvalidArgumentException
     */
    public function validatePlanningAvailability(Collection $dates, Collection|EloquentCollection|null $relations = null): void
    {
        $this->validateInputParameters($dates, $relations);

        event(new PlanningValidationStarted($this, $dates, $relations));

        try {
            if (! $this->hasValidPlanningsForDates($dates)) {
                throw new OutOfPlanningsException(
                    "Resource does not have valid plannings for dates: " . 
                    $dates->map->format('Y-m-d')->implode(', ')
                );
            }

            $this->validateRelationsPlanningAvailability($dates, $relations);

            event(new PlanningValidationPassed($this, $dates, $relations));
        } catch (Exception $e) {
            event(new PlanningValidationFailed($this, $dates, $relations, $e));
            throw $e;
        }
    }

    /**
     * Validates that the given relations have valid plannings for the specified dates.
     *
     * @param Collection<\Carbon\Carbon> $dates
     * @param Collection<BookableResource>|EloquentCollection<int, BookableResource>|null $relations
     *
     * @throws RelationsOutOfPlanningsException
     */
    public function validateRelationsPlanningAvailability(Collection $dates, Collection|EloquentCollection|null $relations = null): void
    {
        $relations = $relations ?? collect();
        
        if ($relations->isEmpty()) {
            return;
        }

        $bookableResources = $this->getBookableResourcesFromRelations($relations);
        
        if ($bookableResources->isEmpty()) {
            return;
        }

        $this->validateRelationsWithBatching($bookableResources, $dates);
    }

    /**
     * Validates input parameters for planning validation methods.
     *
     * @param Collection<\Carbon\Carbon> $dates
     * @param Collection<BookableResource>|EloquentCollection<int, BookableResource>|null $relations
     *
     * @throws InvalidArgumentException
     */
    protected function validateInputParameters(Collection $dates, Collection|EloquentCollection|null $relations = null): void
    {
        if ($dates->isEmpty()) {
            throw new InvalidArgumentException('Dates collection cannot be empty');
        }

        // Convert all dates to Carbon instances if they aren't already
        $dates->transform(function ($date) {
            if (!$date instanceof Carbon) {
                return Carbon::parse($date);
            }
            return $date;
        });

        if ($relations !== null && $relations->isNotEmpty()) {
            $nonModelItems = $relations->filter(fn($item) => !$item instanceof Model);
            if ($nonModelItems->isNotEmpty()) {
                throw new InvalidArgumentException('All relation items must be Model instances');
            }
        }
    }

    /**
     * Checks if this resource has valid plannings for the given dates.
     *
     * @param Collection<\Carbon\Carbon> $dates
     */
    protected function hasValidPlanningsForDates(Collection $dates): bool
    {
        return BookablePlanning::query()
            ->when($this instanceof BookableResource, fn ($query) => $query->where('bookable_resource_id', $this->id))
            ->whereDatesAreValids($dates)
            ->exists();
    }

    /**
     * Filters relations to get only BookableResource instances.
     *
     * @param Collection<mixed>|EloquentCollection<int, mixed> $relations
     * @return Collection<BookableResource>
     */
    protected function getBookableResourcesFromRelations(Collection|EloquentCollection $relations): Collection
    {
        return $relations->filter(fn ($bookable) => $bookable instanceof BookableResource);
    }

    /**
     * Validates relations with batching support for large collections.
     *
     * @param Collection<BookableResource> $bookableResources
     * @param Collection<\Carbon\Carbon> $dates
     *
     * @throws RelationsOutOfPlanningsException
     */
    protected function validateRelationsWithBatching(Collection $bookableResources, Collection $dates): void
    {
        $batchSize = config('bookings.planning_validation.batch_size', 100);

        if ($bookableResources->count() > $batchSize) {
            // Process in batches
            $bookableResources->chunk($batchSize)->each(function ($chunk) use ($dates) {
                $this->validateRelationsChunk($chunk, $dates);
            });
        } else {
            // Process all at once
            $this->validateRelationsChunk($bookableResources, $dates);
        }
    }

    /**
     * Validates a chunk of bookable resources have valid plannings for the specified dates.
     *
     * @param Collection<BookableResource> $bookableResources
     * @param Collection<\Carbon\Carbon> $dates
     *
     * @throws RelationsOutOfPlanningsException
     */
    protected function validateRelationsChunk(Collection $bookableResources, Collection $dates): void
    {
        if ($bookableResources->isEmpty()) {
            return;
        }

        if (app()->hasDebugModeEnabled()) {
            Log::debug('Validating planning availability', [
                'resource_count' => $bookableResources->count(),
                'resource_ids' => $bookableResources->pluck('id')->toArray(),
                'dates' => $dates->map->format('Y-m-d')->toArray(),
            ]);
        }

        $validResources = BookableResource::query()
            ->whereIn('id', $bookableResources->pluck('id'))
            ->whereHas('bookablePlannings', fn ($query) => $query->whereDatesAreValids($dates))
            ->get();

        if ($validResources->count() !== $bookableResources->count()) {
            $invalidResourceIds = $bookableResources->pluck('id')->diff($validResources->pluck('id'));
            
            if (app()->hasDebugModeEnabled()) {
                Log::warning('Planning validation failed', [
                    'invalid_resource_ids' => $invalidResourceIds->toArray(),
                    'dates' => $dates->map->format('Y-m-d')->toArray(),
                ]);
            }
            
            throw new RelationsOutOfPlanningsException(
                "Resources with IDs [" . $invalidResourceIds->implode(', ') . "] " .
                "do not have valid plannings for the requested dates: " . 
                $dates->map->format('Y-m-d')->implode(', ')
            );
        }

        if (app()->hasDebugModeEnabled()) {
            Log::debug('Planning validation passed', [
                'resource_count' => $bookableResources->count(),
                'resource_ids' => $bookableResources->pluck('id')->toArray(),
            ]);
        }
    }

    /**
     * Checks if the given bookable resources have valid plannings for the specified dates.
     * A resource is considered valid if it has plannings that cover the requested dates.
     *
     * @param Collection<BookableResource> $bookableResources
     * @param Collection<\Carbon\Carbon> $dates
     *
     * @deprecated Use validateRelationsWithBatching() instead
     */
    protected function relationsHaveValidPlannings(Collection $bookableResources, Collection $dates): bool
    {
        if ($bookableResources->isEmpty()) {
            return true;
        }

        return BookableResource::query()
            ->whereIn('id', $bookableResources->pluck('id'))
            ->whereHas('bookablePlannings', fn ($query) => $query->whereDatesAreValids($dates))
            ->count() === $bookableResources->count();
    }

    // Legacy method names for backward compatibility
    /** @deprecated Use validatePlanningAvailability() instead */
    public function ensureHasValidPlannings(Collection $dates, Collection|EloquentCollection|null $relations = null): void
    {
        $this->validatePlanningAvailability($dates, $relations);
    }

    /** @deprecated Use validateRelationsPlanningAvailability() instead */
    public function ensureRelationsHaveValidPlannings(Collection $dates, Collection|EloquentCollection|null $relations = null): void
    {
        $this->validateRelationsPlanningAvailability($dates, $relations);
    }
}
