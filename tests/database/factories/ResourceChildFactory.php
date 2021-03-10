<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masterix21\Bookings\Models\Area;
use Masterix21\Bookings\Models\Resource;

class ResourceChildFactory extends Factory
{
    public function modelName()
    {
        return config('bookings.models.resource_child');
    }

    public function definition()
    {
        /** @var Area $areaClass */
        $areaClass = config('bookings.models.area');

        /** @var Resource $resourceClass */
        $resourceClass = config('bookings.models.resource');

        return [
            'parent_id' => $this->faker->boolean(30) ? $resourceClass::query()->select('id')->inRandomOrder()->first()->id : null,
            resolve($areaClass)->getForeignKey() => $areaClass::query()->select('id')->inRandomOrder()->first()->id,
            resolve($resourceClass)->getForeignKey() => $resourceClass::query()->select('id')->inRandomOrder()->first()->id,
            'required' => $this->faker->boolean(),
            'min' => 1,
            'max' => $this->faker->numberBetween(1, 50),
        ];
    }
}
