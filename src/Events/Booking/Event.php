<?php

namespace Masterix21\Bookings\Events\Booking;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Masterix21\Bookings\Models\Booking;

abstract class Event implements ShouldQueue
{
    use InteractsWithQueue;

    public $afterCommit = true;

    public function __construct(public Booking $booking)
    {
    }
}
