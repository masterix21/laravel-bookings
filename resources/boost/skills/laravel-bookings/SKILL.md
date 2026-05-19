---
name: laravel-bookings
description: Build booking and reservation features with masterix21/laravel-bookings — make Eloquent models bookable, reserve them across time periods, manage availability planning, and detect overlaps.
---

# Laravel Bookings Development

## When to use this skill

Use this skill when working in a Laravel application that depends on
`masterix21/laravel-bookings` and the task involves: making a model bookable,
creating or updating reservations, defining availability rules, checking
overlaps, or extending the booking lifecycle.

## Concepts

- **`BookableResource`** — wraps any model and makes it reservable. Key
  columns: `max` (concurrent capacity, `null` = unlimited), `size`,
  `is_visible`, `is_bookable`. Polymorphic `resource` relation to the host
  model.
- **`Booking`** — a reservation: unique `code`, polymorphic `booker`, optional
  `parent` booking, `label`, `note`, `meta`. Owns many `BookedPeriod`.
- **`BookedPeriod`** — one time slot inside a booking.
- **`BookablePlanning`** — availability window: `starts_at`/`ends_at` plus
  weekday boolean flags. Optionally linked to a polymorphic `source`.
- **`BookableRelation`** — links bookable resources to each other.

Resolve every model via `config('bookings.models.*')` — they are all swappable.

## Make a model bookable

```php
use Masterix21\Bookings\Models\Concerns\Bookable;
use Masterix21\Bookings\Models\Concerns\IsBookable;

class Room extends Model implements Bookable
{
    use IsBookable;
}
```

`IsBookable` syncs a `BookableResource` on every save and deletes it when the
model is deleted. To control the synced attributes, add the
`SyncBookableResource` trait and implement the hook:

```php
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Concerns\SyncBookableResource;

public function syncBookableResource(BookableResource $resource): void
{
    $resource->update([
        'is_visible' => $this->is_published,
        'is_bookable' => $this->is_available,
        'max' => $this->capacity,
    ]);
}
```

For models that make bookings (e.g. `User`), add the `HasBookings` trait.

## Create a booking

`BookResource::run()` is transaction-safe and fires lifecycle events. Periods
are always a `Spatie\Period\PeriodCollection`.

```php
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

$periods = new PeriodCollection(
    Period::make('2026-06-01', '2026-06-05', Precision::DAY()),
);

$booking = app(BookResource::class)->run(
    periods: $periods,
    bookableResource: $room->bookableResource,
    booker: $user,
    label: 'Summer stay',
    note: 'Late check-in',
    meta: ['source' => 'web'],
);
```

Optional arguments: `code`, `codePrefix`, `codeSuffix`, `codeGenerator`,
`parent` (nest under another booking), `relatable`.

## Update or cancel a booking

Pass an existing `Booking` to update it in place:

```php
$booking = app(BookResource::class)->run(
    periods: $newPeriods,
    bookableResource: $room->bookableResource,
    booker: $user,
    booking: $existingBooking,
);
```

Use `onBookingSaving()` / `onBookingSaved()` to hook persistence:

```php
app(BookResource::class)
    ->onBookingSaved(fn ($booking) => Notification::send(...))
    ->run(/* ... */);
```

Cancel by deleting the `Booking`; its `BookedPeriod` rows cascade.

## Check availability

```php
use Masterix21\Bookings\Actions\CheckBookingOverlaps;

$isFree = app(CheckBookingOverlaps::class)->run(
    periods: $periods,
    bookableResource: $room->bookableResource,
    emitEvent: true,
    throw: true,
    ignoreBooking: $currentBooking, // exclude when re-checking an edit
);
```

Returns `true` when capacity is available. With `throw: true` it raises
`BookingResourceOverlappingException` when the resource is full. A `null`
`max` means unlimited and always returns `true`.

## Availability planning

Define availability with `BookablePlanning`, or let a business model own it via
`BookablePlanningSource`:

```php
use Masterix21\Bookings\Models\Concerns\BookablePlanningSource;
use Masterix21\Bookings\Models\Concerns\IsBookablePlanningSource;
use Masterix21\Bookings\Models\Concerns\SyncBookablePlanning;

class Rate extends Model implements BookablePlanningSource
{
    use IsBookablePlanningSource;
    use SyncBookablePlanning;

    public function syncBookablePlanning(): void
    {
        $this->planning()->updateOrCreate(
            ['bookable_resource_id' => $this->room->bookableResource->id],
            [
                'starts_at' => $this->valid_from,
                'ends_at' => $this->valid_to,
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => true,
                'saturday' => $this->includes_weekend,
                'sunday' => $this->includes_weekend,
            ],
        );
    }
}
```

Planning is synced on save and deleted with the source. `PlanningMatchingStrategy`
(`All`/`Any`) controls how multiple plannings combine.

## Lifecycle events

Listen instead of editing the action classes: `BookingInProgress`,
`BookingCompleted`, `BookingFailed`, `BookingChanging`, `BookingChanged`,
`BookingChangeFailed`, `PlanningValidationStarted`, `PlanningValidationPassed`,
`PlanningValidationFailed`.

## Custom booking codes

Implement `Masterix21\Bookings\Generators\Contracts\BookingCodeGenerator` and
register it in `config('bookings.generators.booking_code')`, or pass it
per-call via the `codeGenerator` argument of `BookResource::run()`.

## Conventions

- Use Carbon for all dates and datetimes.
- Use `spatie/period` for time ranges; never raw date strings in booking logic.
- Resolve models through `config('bookings.models.*')`.
- Extend through events and config, not by editing package classes.

## Reference

Full guides live in the package `docs/` directory: `getting-started.md`,
`actions.md`, `events.md`, `models.md`, `configuration.md`,
`related-bookings.md`, `synchronization.md`, `api-reference.md`.
