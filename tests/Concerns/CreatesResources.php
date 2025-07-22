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
        ?Carbon $fromDate = null,
        ?Carbon $toDate = null,
        int $resourcesCount = 3,
        string $fromTime = '00:00:00',
        string $toTime = '23:59:59',
        array $resourcesStates = [],
        array $planningsStates = [],
    ): Collection|Model {
        if (! $fromDate) {
            $fromDate = now()->subWeek()->startOf('week');
        }

        if (! $toDate) {
            $toDate = now()->subWeek()->endOf('week');
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
                    'from_date' => $fromDate->format('Y-m-d'),
                    'to_date' => $toDate->format('Y-m-d'),
                    'from_time' => $fromTime,
                    'to_time' => $toTime,
                ])
            )
            ->create();
    }
}
