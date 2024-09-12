<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kirschbaum\PowerJoins\PowerJoins;
use Masterix21\Bookings\Models\Concerns\Scopes\HasWherePeriodFromDatesScope;

class BookedPeriod extends Model
{
    use HasFactory;
    use HasWherePeriodFromDatesScope;
    use PowerJoins;

    protected $guarded = [];

    protected $casts = [
        'is_excluded' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.booking'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }
}
