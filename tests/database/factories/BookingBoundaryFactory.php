<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Masterix21\Bookings\Models\Area;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Models\BookingChild;
use Masterix21\Bookings\Models\Resource;

class BookingBoundaryFactory extends Factory
{
    public function modelName()
    {
        return config('bookings.models.booking_children');
    }

    public function definition()
    {

        $fromDay = $this->faker->boolean(25) ? $this->faker->dateTimeThisYear : null;
        $fromTime = $this->faker->dateTime;

        /** @var Booking $bookingClass */
        $bookingClass = config('bookings.models.booking');

        /** @var BookingChild $bookingChildClass */
        $bookingChildClass = config('bookings.models.children');

        return [
            resolve($bookingClass)->getForeignKey() => $bookingClass::query()->select('id')->inRandomOrder()->first()->id,
            resolve($bookingChildClass)->getForeignKey() => $this->faker->boolean(10) ? $bookingChildClass::query()->select('id')->inRandomOrder()->first()->id : null,
                'weekdays' =>
                $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1'])
                . $this->faker->randomElement(['0', '1']),
            'day_from' => optional($fromDay)->format('Y-m-d'),
            'day_to' => ! blank($fromDay) ? Carbon::parse($fromDay)->addDays(7) : null,
            'time_from' => Carbon::parse($fromTime),
            'time_to' => Carbon::parse($fromTime)->addHour(),
        ];
    }
}
