<?php

namespace Masterix21\Bookings\Generators\Contracts;

interface BookingCodeGenerator
{
    public function run(?string $prefix = null, ?string $suffix = null): string;
}
