<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BookedResourceFactory extends Factory
{
    public function modelName(): string
    {
        return config('bookings.models.booked_resource');
    }

    public function definition(): array
    {
        return [
            'is_required' => $this->faker->boolean(50),
            'min' => 1,
            'max' => $this->faker->numberBetween(1, 10),
            'max_nested' => $this->faker->numberBetween(1, 10),
        ];
    }
}
