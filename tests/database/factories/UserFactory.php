<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masterix21\Bookings\Tests\TestClasses\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'name' => $this->faker->firstName .' '. $this->faker->lastName,
            'email' => $this->faker->email,
            'password' => bcrypt('password'),
        ];
    }
}
