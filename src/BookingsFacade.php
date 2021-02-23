<?php

namespace Masterix21\Bookings;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Masterix21\Bookings\Bookings
 */
class BookingsFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-bookings';
    }
}
