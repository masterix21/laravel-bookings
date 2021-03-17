<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\BelongsToBookedResource;
use Masterix21\Bookings\Models\Concerns\BelongsToBooking;

class UnbookedPeriod extends Model
{
    use HasFactory;
    use BelongsToBooking;
    use BelongsToBookedResource;

    protected $guarded = [];
}
