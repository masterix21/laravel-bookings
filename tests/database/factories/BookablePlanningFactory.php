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
        $startsAt = $this->faker->boolean(25) ? $this->faker->dateTimeThisYear : null;

        return [
            'label' => $this->faker->randomElement([null, $this->faker->text(80)]),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt ? Carbon::parse($startsAt)->addHour() : null,
        ];
    }
}
