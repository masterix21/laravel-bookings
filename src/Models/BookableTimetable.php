<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableResource;

class BookableTimetable extends Model
{
    use HasFactory;
    use BelongsToBookableArea;
    use BelongsToBookableResource;

    protected $guarded = [];
}
