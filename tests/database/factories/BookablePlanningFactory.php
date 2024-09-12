<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookablePlanningFactory extends Factory
{
    public function modelName(): string
    {
        return config('bookings.models.bookable_planning');
    }

    public function definition(): array
    {
        $fromDay = $this->faker->boolean(25) ? $this->faker->dateTimeThisYear : null;
        $fromTime = $this->faker->dateTime;

        return [
            'label' => $this->faker->randomElement([null, $this->faker->text(80)]),
            'from_date' => optional($fromDay)->format('Y-m-d'),
            'to_date' => ! blank($fromDay) ? Carbon::parse($fromDay)->addDays(7) : null,
            'from_time' => Carbon::parse($fromTime),
            'to_time' => Carbon::parse($fromTime)->addHour(),
        ];
    }
}
