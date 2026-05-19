<?php

namespace Masterix21\Bookings;

use Illuminate\Support\Facades\Facade;

/**
 * @see Bookings
 */
class BookingsFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'bookings';
    }
}
