<?php

namespace Masterix21\Bookings\Enums;

use Carbon\Carbon;

enum Weekday: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    public static function fromCarbonDay(int $carbonDay): self
    {
        return match ($carbonDay) {
            Carbon::MONDAY => self::Monday,
            Carbon::TUESDAY => self::Tuesday,
            Carbon::WEDNESDAY => self::Wednesday,
            Carbon::THURSDAY => self::Thursday,
            Carbon::FRIDAY => self::Friday,
            Carbon::SATURDAY => self::Saturday,
            Carbon::SUNDAY => self::Sunday,
        };
    }

    public function isWeekend(): bool
    {
        return $this === self::Saturday || $this === self::Sunday;
    }

    public function toCarbonDay(): int
    {
        return match ($this) {
            self::Monday => Carbon::MONDAY,
            self::Tuesday => Carbon::TUESDAY,
            self::Wednesday => Carbon::WEDNESDAY,
            self::Thursday => Carbon::THURSDAY,
            self::Friday => Carbon::FRIDAY,
            self::Saturday => Carbon::SATURDAY,
            self::Sunday => Carbon::SUNDAY,
        };
    }
}
