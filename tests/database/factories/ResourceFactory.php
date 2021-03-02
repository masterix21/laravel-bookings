<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Area;
use Masterix21\Bookings\Tests\TestClasses\Product;
use Masterix21\Bookings\Tests\TestClasses\User;

class ResourceFactory extends Factory
{
    public function modelName()
    {
        return config('bookings.models.resource');
    }

    public function definition()
    {
        /** @var Area $areaClass */
        $areaClass = config('bookings.models.area');

        /** @var Model $modelClass */
        $modelClass = $this->faker->randomElement([User::class, Product::class]);

        $modelClass::factory()->count(1)->create();

        return [
            resolve($areaClass)->getForeignKey() => $areaClass::query()->select('id')->inRandomOrder()->first()->id,
            'code' => $this->faker->randomNumber(),
            'model_type' => $modelClass,
            'model_id' => $modelClass::query()->select('id')->inRandomOrder()->first()->id,
            'min' => 1,
            'max' => $this->faker->numberBetween(1, 10),
        ];
    }
}
