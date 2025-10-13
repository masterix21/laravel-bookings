<?php

declare(strict_types=1);

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Masterix21\Bookings\Models\Concerns\UsesBookedPeriods;
use Masterix21\Bookings\Models\Concerns\UsesGenerateBookedPeriods;

/**
 * @property int $id
 * @property string $code
 * @property string|null $booker_type
 * @property int|null $booker_id
 * @property string|null $label
 * @property string|null $note
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Booking extends Model
{
    use HasFactory;
    use UsesBookedPeriods;
    use UsesGenerateBookedPeriods;

    protected $fillable = [
        'code',
        'booker_type',
        'booker_id',
        'label',
        'note',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => AsArrayObject::class,
        ];
    }

    public function booker(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookedPeriod(): HasOne
    {
        return $this->hasOne(config('bookings.models.booked_period'));
    }

    public function bookedPeriods(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_period'));
    }
}
