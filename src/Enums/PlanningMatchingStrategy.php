<?php

declare(strict_types=1);

namespace Masterix21\Bookings\Enums;

enum PlanningMatchingStrategy: string
{
    case All = 'all';
    case Any = 'any';
}
