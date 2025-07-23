<?php

namespace Masterix21\Bookings\Events;

use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PlanningValidationFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $resource,
        public readonly Collection $dates,
        public readonly Collection|EloquentCollection|null $relations,
        public readonly Exception $exception,
    ) {
    }
}
