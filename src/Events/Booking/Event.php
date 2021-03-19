<?php

namespace Masterix21\Bookings\Events\Booking;

use Illuminate\Foundation\Auth\User;
use Masterix21\Bookings\Models\Booking;

abstract class Event
{
    public function __construct(protected Booking $booking) { }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->booking->user;
    }
}
