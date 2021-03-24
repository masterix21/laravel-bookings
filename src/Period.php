<?php

namespace Masterix21\Bookings;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Spatie\Period\Period as SpatiePeriod;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class Period
{
    /**
     * Transform periods to dates collection
     *
     * @param PeriodCollection|SpatiePeriod $periods
     * @param bool $removeDuplicates
     * @return Collection
     */
    public static function toDates(PeriodCollection | SpatiePeriod $periods, bool $removeDuplicates = true): Collection
    {
        $dates = collect();

        if ($periods instanceof SpatiePeriod) {
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
     * @param Arrayable $main
     * @param Arrayable|null $others
     * @param Precision|null $precision
     * @return PeriodCollection
     */
    public static function periodsSubtractToDates(Arrayable $main, Arrayable | null $others, ?Precision $precision = null): PeriodCollection
    {
        $main = Collection::wrap($main);

        if ($main->isEmpty()) {
            return new PeriodCollection();
        }

        if (! $precision instanceof Precision) {
            $precision = Precision::SECOND();
        }

        $main = PeriodCollection::make(
            ...$main
            ->transform(fn ($period) => SpatiePeriod::make(
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
            ...$others
            ->transform(fn (UnbookedPeriod $period) => SpatiePeriod::make(
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
