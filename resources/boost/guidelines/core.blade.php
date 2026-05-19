## Laravel Bookings

`masterix21/laravel-bookings` adds time-based booking to any Eloquent model:
make a model bookable, reserve it for one or more periods, and detect overlaps
against availability planning.

### Core models

- `BookableResource` — the bookable entity; polymorphic to any model. Holds
  `max` (concurrent capacity), `is_visible`, `is_bookable`, `size`.
- `Booking` — a reservation with a unique `code`, a polymorphic `booker`, and
  metadata. Bookings can be nested via a `parent` booking.
- `BookedPeriod` — an individual time slot belonging to a booking.
- `BookablePlanning` — availability rules (date range + weekday flags) for a
  resource; optionally linked to a polymorphic `source`.
- `BookableRelation` — relationships between bookable resources.

Always resolve models through `config('bookings.models.*')` — every model is
swappable by the host application. Never reference the concrete classes
directly when extensibility matters.

### Traits and contracts

- `Bookable` interface + `IsBookable` trait — make a model bookable. Adds
  `bookableResource()`/`bookableResources()`, `bookings()`, `bookedPeriods()`,
  `isBookedAt(Carbon $date)`. Calls `syncBookableResource()` on save and
  deletes resources on model deletion.
- `HasBookings` trait — for models that make bookings (e.g. `User`); adds
  `bookings()`.
- `BookablePlanningSource` interface + `IsBookablePlanningSource` trait — let a
  business model (rate, season, maintenance window) own a `BookablePlanning`;
  calls `syncBookablePlanning()` on save.
- `SyncBookableResource` / `SyncBookablePlanning` traits — implement the
  `syncBookableResource(BookableResource $resource)` /
  `syncBookablePlanning()` hooks to keep planning in sync with your model.

### Booking a resource

Use the `BookResource` action — it wraps everything in a DB transaction and
fires the lifecycle events. Periods are always `Spatie\Period\PeriodCollection`.

@verbatim
<code-snippet name="Create a booking" lang="php">
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
);
</code-snippet>
@endverbatim

Pass an existing `Booking` as `booking:` to update it instead of creating one.
Use `parent:` to nest a booking under another. `onBookingSaving()` /
`onBookingSaved()` register callbacks around persistence.

### Checking availability

@verbatim
<code-snippet name="Check overlaps before booking" lang="php">
use Masterix21\Bookings\Actions\CheckBookingOverlaps;

$isFree = app(CheckBookingOverlaps::class)->run(
    periods: $periods,
    bookableResource: $room->bookableResource,
    throw: true, // throws BookingResourceOverlappingException when full
);
</code-snippet>
@endverbatim

Availability is bounded by `BookableResource::$max`. A null `max` means
unlimited capacity and `CheckBookingOverlaps` short-circuits to available.

### Events and exceptions

Lifecycle events: `BookingInProgress`, `BookingCompleted`, `BookingFailed`,
`BookingChanging`, `BookingChanged`, `BookingChangeFailed`,
`PlanningValidationStarted`, `PlanningValidationPassed`,
`PlanningValidationFailed`. Extend behaviour via listeners — do not edit the
action classes.

Exceptions: `BookingResourceOverlappingException`, `NoFreeSizeException`,
`OutOfPlanningsException`, `RelationsHaveNoFreeSizeException`,
`RelationsOutOfPlanningsException`, `UnbookableException`.

### Conventions

- Use Carbon for all dates and datetimes.
- Use `spatie/period` (`Period`, `PeriodCollection`, `Precision`) for time
  ranges — never raw date strings in booking logic.
- Customize booking codes by binding a `BookingCodeGenerator` implementation in
  `config('bookings.generators.booking_code')`.
