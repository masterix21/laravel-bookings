<?php

namespace Masterix21\Bookings\Tests\TestClasses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\Concerns\BookablePlanningSource;
use Masterix21\Bookings\Models\Concerns\IsBookablePlanningSource;
use Masterix21\Bookings\Tests\Database\Factories\RateFactory;

class Rate extends Model implements BookablePlanningSource
{
    use HasFactory;
    use IsBookablePlanningSource;

    protected $guarded = [];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
    ];

    public int $syncCallCount = 0;

    public function syncBookablePlanning(): void
    {
        $this->syncCallCount++;
    }

    protected static function newFactory(): RateFactory
    {
        return RateFactory::new();
    }
}

