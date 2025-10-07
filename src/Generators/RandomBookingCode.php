<?php

namespace Masterix21\Bookings\Generators;

use Illuminate\Support\Str;
use Masterix21\Bookings\Generators\Contracts\BookingCodeGenerator;

class RandomBookingCode implements BookingCodeGenerator
{
    public function run(
        ?string $prefix = null,
        ?string $suffix = null,
    ): string {
        $prefix ??= '';
        $suffix ??= '';

        $missingCharsLen = 64 - str($prefix)->append($suffix)->length();

        if ($missingCharsLen <= 26) {
            throw new \Exception('Please keep your prefix and suffix below 26 characters.');
        }

        $str = str($prefix)->append(Str::ulid());

        $missingCharsLen -= 26;

        if ($missingCharsLen > 0) {
            $str = $str->append(Str::random($missingCharsLen));
        }

        return $str
            ->append($suffix)
            ->upper();
    }
}
