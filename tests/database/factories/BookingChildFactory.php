<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masterix21\Bookings\Models\Area;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Models\BookingChild;
use Masterix21\Bookings\Models\Resource;

class BookingChildFactory extends Factory
{
    public function modelName()
    {
        return config('bookings.models.booking_children');
    }

    public function definition()
    {
        /** @var Area $areaClass */
        $areaClass = config('bookings.models.area');

        /** @var Booking $bookingClass */
        $bookingClass = config('bookings.models.booking');

        /** @var Resource $resourceClass */
        $resourceClass = config('bookings.models.resources');

        /** @var BookingChild $bookingChildClass */
        $bookingChildClass = config('bookings.models.booking_children');

        return [
            resolve($bookingClass)->getForeignKey() => $bookingClass::query()->select('id')->inRandomOrder()->first()->id,
            'parent_child_id' => $this->faker->boolean(30) ? $bookingChildClass::query()->select('id')->inRandomOrder()->first()->id : null,
            resolve($areaClass)->getForeignKey() => $areaClass::query()->select('id')->inRandomOrder()->first()->id,
            resolve($resourceClass)->getForeignKey() => $resourceClass::query()->select('id')->inRandomOrder()->first()->id,
            'required' => $this->faker->boolean(50),
        ];
    }
}
