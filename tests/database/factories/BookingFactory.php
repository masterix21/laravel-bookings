<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    public function modelName()
    {
        return config('bookings.models.booking');
    }

    public function definition()
    {
        return [
            'code' => $this->faker->firstName,
            'label' => $this->faker->lastName,
            'email' => $this->faker->email,
            'phone' => $this->faker->phoneNumber,
            'note' => $this->faker->boolean(60) ? null : $this->faker->realText(180),
        ];
    }
}
