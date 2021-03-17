<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableArea;

class BookableResource extends Model
{
    use HasFactory;
    use BelongsToBookableArea;

    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookableTimetables(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_timetable'), 'bookable_resource_id');
    }
}
