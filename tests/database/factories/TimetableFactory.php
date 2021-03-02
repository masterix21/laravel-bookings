<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimetableFactory extends Factory
{
    public function modelName()
    {
        return config('bookings.models.timetable');
    }

    public function definition()
    {
        $fromDay = $this->faker->boolean(25) ? $this->faker->dateTimeThisYear : null;
        $fromTime = $this->faker->dateTime;

        return [
            'weekdays' =>
                $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1']),
            'day_from' => optional($fromDay)->format('Y-m-d'),
            'day_to' => ! blank($fromDay) ? Carbon::parse($fromDay)->addDays(7) : null,
            'time_from' => Carbon::parse($fromTime),
            'time_to' => Carbon::parse($fromTime)->addHour(),
        ];
    }
}
