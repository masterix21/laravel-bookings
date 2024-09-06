<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Tests\TestClasses\Product;
use Masterix21\Bookings\Tests\TestClasses\User;

class BookableResourceFactory extends Factory
{
    public function modelName(): string
    {
        return config('bookings.models.bookable_resource');
    }

    public function definition()
    {
        /** @var Model $modelClass */
        $modelClass = $this->faker->randomElement([User::class, Product::class]);

        $modelClass::factory()->count(1)->create();

        return [
            'code' => $this->faker->randomNumber(),
            'model_id' => $modelClass::query()->select('id')->inRandomOrder()->first()->id,
            'model_type' => $modelClass,
            'min' => 1,
            'max' => $this->faker->numberBetween(1, 10),
            'max_nested' => $this->faker->numberBetween(1, 10),
            'is_visible' => $this->faker->boolean,
            'is_bookable' => $this->faker->boolean,
        ];
    }
}
