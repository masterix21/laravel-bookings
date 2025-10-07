<?php

namespace Masterix21\Bookings\Tests\TestClasses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Concerns\Bookable;
use Masterix21\Bookings\Models\Concerns\IsBookable;

class Product extends Model implements Bookable
{
    use HasFactory;
    use IsBookable;

    protected $guarded = [];

    public int $syncCallCount = 0;

    public function syncBookableResource(BookableResource $resource): void
    {
        $this->syncCallCount++;
    }
}
