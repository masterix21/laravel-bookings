<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingPlanningFactory extends Factory
{
    public function modelName(): string
    {
        return config('bookings.models.booking_planning');
    }

    public function definition(): array
    {
        $startsAt = $this->faker->boolean(25) ? $this->faker->dateTimeThisYear : null;

        return [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt ? Carbon::parse($startsAt)->addHour() : null,
        ];
    }
}
