<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AreaFactory extends Factory
{
    public function modelName()
    {
        return config('bookings.models.area');
    }

    public function definition()
    {
        return [
            'name' => $this->faker->text(50),
            'prefix' => $this->faker->randomElement([null, $this->faker->randomLetter, $this->faker->randomDigit]),
            'suffix' => $this->faker->randomElement([null, $this->faker->randomLetter, $this->faker->randomDigit]),
            'visible' => $this->faker->boolean(70),
            'bookable' => $this->faker->boolean(60),
        ];
    }
}
