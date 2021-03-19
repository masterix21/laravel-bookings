<?php

namespace Masterix21\Bookings;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Models\BookingPlanning;
use Masterix21\Bookings\Models\UnbookedPeriod;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class Bookings
{
    /**
     * Transform periods to dates collection
     *
     * @param PeriodCollection|Period $periods
     * @param bool $removeDuplicates
     * @return Collection
     */
    public function periodsToDates(PeriodCollection | Period $periods, bool $removeDuplicates = true): Collection
    {
        $dates = collect();

        if ($periods instanceof Period) {
            $periods = PeriodCollection::make($periods);
        }

        foreach ($periods as $period) {
            foreach ($period as $date) {
                $dates->push($date);
            }
        }

        if ($removeDuplicates) {
            return $dates->unique();
        }

        return $dates;
    }

    /**
     * @param BookingPlanning[] $main
     * @param UnbookedPeriod[]|null $others
     * @param Precision|null $precision
     * @return PeriodCollection
     */
    public function periodsSubtractToDates(Arrayable $main, Arrayable | null $others, ?Precision $precision = null): PeriodCollection
    {
        $main = Collection::wrap($main);

        if ($main->isEmpty()) {
            return new PeriodCollection();
        }

        if (! $precision instanceof Precision) {
            $precision = Precision::SECOND();
        }

        $main = PeriodCollection::make(
            ...$main->transform(fn ($period) => Period::make(
                start: $period->from_date . ' ' . $period->from_time,
                end: $period->to_date . ' ' . $period->to_time,
                precision: $precision,
            ))->toArray()
        );

        $others = Collection::wrap($others);

        if ($others->isEmpty()) {
            return $main;
        }

        $others = PeriodCollection::make(
            ...$others->transform(fn (UnbookedPeriod $period) => Period::make(
                start: $period->from_date .' '. $period->from_time,
                end: $period->to_date .' '. $period->to_time,
                precision: $precision,
            ))->toArray()
        );

        /** @TODO: Cambiare con $main->subtract($others) appena Spatie aggiorna il pacchetto (se) */
        return new PeriodCollection(
            ...collect($main)
                ->map(fn ($period) => collect($period->subtract(...$others)))
                ->flatten()
                ->toArray()
        );
    }
}
