<?php

namespace Masterix21\Bookings\Events\Booking;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Auth\User;
use Illuminate\Queue\InteractsWithQueue;
use Masterix21\Bookings\Models\Booking;

abstract class Event implements ShouldQueue
{
    use InteractsWithQueue;

    public $afterCommit = true;

    public function __construct(protected Booking $booking)
    {
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->booking->user;
    }
}
