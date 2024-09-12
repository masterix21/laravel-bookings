<?php

namespace Masterix21\Bookings\Tests\TestClasses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Masterix21\Bookings\Models\Concerns\HasBookings;

class User extends \Illuminate\Foundation\Auth\User
{
    use HasBookings;
    use HasFactory;
}
