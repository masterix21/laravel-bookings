<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BookableRelationFactory extends Factory
{
    public function modelName()
    {
        return config('bookings.models.bookable_relation');
    }

    public function definition()
    {
        return [
            'is_required' => $this->faker->boolean(),
            'min' => 1,
            'max' => $this->faker->numberBetween(1, 50),
        ];
    }
}
