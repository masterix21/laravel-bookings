<?php

namespace LucaLongo\Bookings;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LucaLongo\Bookings\Bookings
 */
class BookingsFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-bookings';
    }
}
