<?php

namespace Masterix21\Bookings\Tests\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableResource;

trait CreatesResources
{
    protected function createsResources(
        ?Carbon $startsAt = null,
        ?Carbon $endsAt = null,
        int $resourcesCount = 3,
        array $resourcesStates = [],
        array $planningsStates = [],
    ): Collection|Model {
        if (! $startsAt) {
            $startsAt = now()->subWeek()->startOf('week');
        }

        if (! $endsAt) {
            $endsAt = now()->subWeek()->endOf('week');
        }

        return BookableResource::factory()
            ->count($resourcesCount)
            ->state([
                ...$resourcesStates,
                'size' => 1,
                'is_bookable' => true,
            ])
            ->has(
                BookablePlanning::factory()->count(1)->state([
                    ...$planningsStates,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ])
            )
            ->create();
    }
}
