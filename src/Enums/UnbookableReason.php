<?php

namespace Masterix21\Bookings\Enums;

enum UnbookableReason: string
{
    case PERIOD_OVERLAP = 'period_overlap';
    case EXCEPTION = 'exception';
}
