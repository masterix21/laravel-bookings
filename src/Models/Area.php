<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Masterix21\Bookings\Models\Concerns\HasResources;
use Masterix21\Bookings\Models\Concerns\HasTimetables;

class Area extends Model
{
    use HasFactory;
    use HasResources;
    use HasTimetables;

    public function bookings(): HasMany
    {
        return $this->hasMany(config('bookings.models.booking'));
    }
}
