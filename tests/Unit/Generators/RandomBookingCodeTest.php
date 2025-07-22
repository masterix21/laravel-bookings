<?php

use Masterix21\Bookings\Generators\RandomBookingCode;

it('throws an exception when prefix and suffix are too long', function () {
    // Create an instance of RandomBookingCode
    $generator = new RandomBookingCode();

    // Create a prefix and suffix whose combined length would leave 26 or fewer characters
    // The condition is: 64 - (prefix length + suffix length) <= 26
    // So prefix length + suffix length >= 38
    $prefix = str_repeat('A', 20);
    $suffix = str_repeat('B', 18);

    // The combined length is 38, which should trigger the exception
    // Call the run method and expect an exception
    expect(fn () => $generator->run($prefix, $suffix))
        ->toThrow(\Exception::class, 'Please keep your prefix and suffix below 26 characters.');
});
