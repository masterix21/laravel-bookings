<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnbookedPeriodFactory extends Factory
{
    public function modelName()
    {
        return config('bookings.models.unbooked_period');
    }

    public function definition()
    {
        $fromDay = $this->faker->boolean(25) ? $this->faker->dateTimeThisYear : null;
        $fromTime = $this->faker->dateTime;

        return [
            'from_date' => optional($fromDay)->format('Y-m-d'),
            'to_date' => ! blank($fromDay) ? Carbon::parse($fromDay)->addDays(7) : null,
            'from_time' => Carbon::parse($fromTime),
            'to_time' => Carbon::parse($fromTime)->addHour(),
            'note' => $this->faker->randomElement(null, $this->faker->text),
        ];
    }
}
