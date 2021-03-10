<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Tests\TestClasses\User;

class BookingFactory extends Factory
{
    public function modelName()
    {
        return config('bookings.models.booking');
    }

    public function definition()
    {
        /** @var Model $userClass */
        $userClass = $this->faker->randomElement([User::class]);
        $userClass::factory()->count(1)->create();
        $user = $userClass::query()->select('id', 'first_name', 'last_name', 'email', 'phone')->inRandomOrder()->first();

        return [
            'user_id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'note' => null,
        ];
    }
}
