<?php

namespace Masterix21\Bookings\Events\Booking;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\PeriodCollection;

class CreatingBooking implements ShouldQueue
{
    use InteractsWithQueue;

    public $afterCommit = true;

    public function __construct(
        protected BookableResource $bookableResource,
        protected PeriodCollection $periods
    ) {
    }

    public function getBookableResource(): BookableResource
    {
        return $this->bookableResource;
    }

    public function getPeriods(): PeriodCollection
    {
        return $this->periods;
    }
}
