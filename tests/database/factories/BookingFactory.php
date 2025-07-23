<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masterix21\Bookings\Tests\TestClasses\User;

class BookingFactory extends Factory
{
    public function modelName(): string
    {
        return config('bookings.models.booking');
    }

    public function definition(): array
    {
        return [
            'code' => $this->faker->firstName,
            'booker_type' => User::class,
            'booker_id' => UserFactory::new(),
            'label' => $this->faker->lastName,
            'note' => $this->faker->boolean(60) ? null : $this->faker->realText(180),
        ];
    }
}
