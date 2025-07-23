<?php

namespace Masterix21\Bookings\Tests\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masterix21\Bookings\Tests\database\factories\BookingFactory;

class BookedPeriodFactory extends Factory
{
    public function modelName(): string
    {
        return config('bookings.models.booked_period');
    }

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('-1 week', '+1 week');
        $endsAt = $this->faker->dateTimeBetween($startsAt, '+2 weeks');

        return [
            'booking_id' => BookingFactory::new(),
            'is_excluded' => $this->faker->boolean(20),
            'label' => $this->faker->words(3, true),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => $this->faker->timezone,
            'note' => $this->faker->boolean(60) ? null : $this->faker->realText(180),
        ];
    }

    public function excluded(): static
    {
        return $this->state(['is_excluded' => true]);
    }

    public function included(): static
    {
        return $this->state(['is_excluded' => false]);
    }

    public function withDates(string $startsAt, string $endsAt): static
    {
        return $this->state([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}