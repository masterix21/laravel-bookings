<?php

namespace Masterix21\Bookings\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masterix21\Bookings\Tests\TestClasses\Rate;

class RateFactory extends Factory
{
    protected $model = Rate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'price' => $this->faker->randomFloat(2, 50, 500),
            'valid_from' => now(),
            'valid_to' => now()->addMonths(3),
        ];
    }
}
