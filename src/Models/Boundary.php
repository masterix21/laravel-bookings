<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Boundary extends Model
{
    use HasFactory;

    public function booking(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.booking'));
    }

    public function exclusions(): HasMany
    {
        return $this->hasMany(config('bookings.models.exclusion'));
    }
}
