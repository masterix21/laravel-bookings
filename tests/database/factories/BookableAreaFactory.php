<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BookableAreaFactory extends Factory
{
    public function modelName(): string
    {
        return config('bookings.models.bookable_area');
    }

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'prefix' => $this->faker->randomElement([null, $this->faker->randomLetter, $this->faker->randomDigit]),
            'suffix' => $this->faker->randomElement([null, $this->faker->randomLetter, $this->faker->randomDigit]),
            'is_visible' => $this->faker->boolean(70),
            'is_bookable' => $this->faker->boolean(60),
        ];
    }
}
