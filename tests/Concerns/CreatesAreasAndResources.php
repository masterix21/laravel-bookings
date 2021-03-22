<?php

namespace Masterix21\Bookings\Tests\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Masterix21\Bookings\Models\BookableArea;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableResource;

trait CreatesAreasAndResources
{
    protected function createsAreasAndResources(
        ?Carbon $fromDate,
        ?Carbon $toDate,
        int $areasCount = 1,
        int $resourcesCount = 3,
        int $planningsCount = 1,
        string $fromTime = '00:00:00',
        string $toTime = '23:59:59'
    ): Collection|Model
    {
        if (! $fromDate) {
            $fromDate = now()->subWeek()->startOf('week');
        }

        if (! $toDate) {
            $toDate = now()->subWeek()->endOf('week');
        }

        return BookableArea::factory()
            ->count($areasCount)
            ->has(BookableResource::factory()->count($resourcesCount))
            ->has(BookablePlanning::factory()->count($planningsCount)->state([
                'from_date' => $fromDate->format('Y-m-d'),
                'to_date' => $toDate->format('Y-m-d'),
                'from_time' => '00:00:00',
                'to_time' => '23:59:59',
            ]))
            ->create();
    }
}
