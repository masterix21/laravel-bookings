<?php
namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableResource;
use Masterix21\Bookings\Models\Concerns\BelongsToBooking;
use Masterix21\Bookings\Models\Concerns\Scopes\HasWherePeriodFromDatesScope;

class BookedDates extends Model
{
    use HasFactory;
    use BelongsToBooking;
    use BelongsToBookableArea;
    use BelongsToBookableResource;
    use HasWherePeriodFromDatesScope;

    protected $guarded = [];

    protected $casts = [
        'from' => 'datetime',
        'to' => 'datetime',
    ];

    public static function bootHasUuid()
    {
        static::creating(fn (self $model) => $model->{$model->getKeyName()} = Str::uuid());
    }

    public function getKeyName()
    {
        return 'uuid';
    }

    public function getKeyType()
    {
        return 'string';
    }

    public function getIncrementing()
    {
        return false;
    }
}
