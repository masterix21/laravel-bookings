<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\BelongsToArea;
use Masterix21\Bookings\Models\Concerns\BelongsToResource;

class Booking extends Model
{
    use HasFactory;
    use BelongsToArea;
    use BelongsToResource;
}
