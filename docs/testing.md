# Testing Guide

This guide covers how to effectively test applications built with Laravel Bookings, including unit tests, feature tests, and integration tests.

## Test Setup

### Test Environment Configuration

```php
<?php
// phpunit.xml or phpunit.xml.dist

<phpunit>
    <testsuites>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
    </testsuites>
    
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
    </php>
</phpunit>
```

### Test Database Setup

```php
<?php
// tests/TestCase.php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Load package migrations
        $this->loadLaravelMigrations();
        $this->artisan('migrate', ['--database' => 'testing']);
        
        // Seed test data if needed
        $this->seedTestData();
    }

    protected function seedTestData(): void
    {
        // Create basic test data that most tests need
    }
}
```

## Testing Models

### BookableResource Tests

```php
<?php
// tests/Unit/Models/BookableResourceTest.php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Room;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Models\BookedPeriod;

class BookableResourceTest extends TestCase
{
    public function test_can_create_bookable_resource(): void
    {
        $room = Room::factory()->create();
        
        $resource = BookableResource::create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
            'max' => 1,
            'size' => 4,
            'is_bookable' => true,
            'is_visible' => true,
        ]);

        $this->assertDatabaseHas('bookable_resources', [
            'resource_type' => Room::class,
            'resource_id' => $room->id,
            'is_bookable' => true,
        ]);
    }

    public function test_resource_relationship_works(): void
    {
        $room = Room::factory()->create(['name' => 'Test Room']);
        $resource = BookableResource::factory()->create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
        ]);

        $this->assertEquals('Test Room', $resource->resource->name);
        $this->assertInstanceOf(Room::class, $resource->resource);
    }

    public function test_bookings_relationship(): void
    {
        $resource = BookableResource::factory()->create();
        $booking = Booking::factory()->create();
        
        BookedPeriod::factory()->create([
            'booking_id' => $booking->id,
            'bookable_resource_id' => $resource->id,
        ]);

        $this->assertTrue($resource->bookings->contains($booking));
    }

    public function test_scope_bookable(): void
    {
        BookableResource::factory()->create(['is_bookable' => true]);
        BookableResource::factory()->create(['is_bookable' => false]);

        $bookableResources = BookableResource::bookable()->get();

        $this->assertCount(1, $bookableResources);
        $this->assertTrue($bookableResources->first()->is_bookable);
    }

    public function test_scope_visible(): void
    {
        BookableResource::factory()->create(['is_visible' => true]);
        BookableResource::factory()->create(['is_visible' => false]);

        $visibleResources = BookableResource::visible()->get();

        $this->assertCount(1, $visibleResources);
        $this->assertTrue($visibleResources->first()->is_visible);
    }

    public function test_scope_of_type(): void
    {
        BookableResource::factory()->create(['resource_type' => Room::class]);
        BookableResource::factory()->create(['resource_type' => 'App\\Models\\Vehicle']);

        $roomResources = BookableResource::ofType(Room::class)->get();

        $this->assertCount(1, $roomResources);
        $this->assertEquals(Room::class, $roomResources->first()->resource_type);
    }
}
```

### IsBookable Trait Tests

```php
<?php
// tests/Unit/Models/Concerns/IsBookableTest.php

namespace Tests\Unit\Models\Concerns;

use Tests\TestCase;
use App\Models\Room;
use App\Models\User;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Models\BookedPeriod;
use Carbon\Carbon;

class IsBookableTest extends TestCase
{
    public function test_bookable_resource_relationship(): void
    {
        $room = Room::factory()->create();
        $resource = BookableResource::factory()->create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
        ]);

        $this->assertEquals($resource->id, $room->bookableResource->id);
    }

    public function test_bookable_resources_relationship(): void
    {
        $room = Room::factory()->create();
        BookableResource::factory()->count(3)->create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
        ]);

        $this->assertCount(3, $room->bookableResources);
    }

    public function test_is_booked_at(): void
    {
        $room = Room::factory()->create();
        $resource = BookableResource::factory()->create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
        ]);
        
        $booking = Booking::factory()->create();
        BookedPeriod::factory()->create([
            'booking_id' => $booking->id,
            'bookable_resource_id' => $resource->id,
            'starts_at' => Carbon::now()->subHour(),
            'ends_at' => Carbon::now()->addHour(),
        ]);

        $this->assertTrue($room->isBookedAt(Carbon::now()));
        $this->assertFalse($room->isBookedAt(Carbon::now()->addHours(2)));
    }

    public function test_booked_periods_of_date(): void
    {
        $room = Room::factory()->create();
        $resource = BookableResource::factory()->create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
        ]);
        
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        
        // Create periods for today and tomorrow
        $booking1 = Booking::factory()->create();
        $booking2 = Booking::factory()->create();
        
        BookedPeriod::factory()->create([
            'booking_id' => $booking1->id,
            'bookable_resource_id' => $resource->id,
            'starts_at' => $today->copy()->setTime(9, 0),
            'ends_at' => $today->copy()->setTime(11, 0),
        ]);
        
        BookedPeriod::factory()->create([
            'booking_id' => $booking2->id,
            'bookable_resource_id' => $resource->id,
            'starts_at' => $tomorrow->copy()->setTime(9, 0),
            'ends_at' => $tomorrow->copy()->setTime(11, 0),
        ]);

        $todayPeriods = $room->bookedPeriodsOfDate($today);
        $tomorrowPeriods = $room->bookedPeriodsOfDate($tomorrow);

        $this->assertCount(1, $todayPeriods);
        $this->assertCount(1, $tomorrowPeriods);
    }
}
```

## Testing Actions

### BookResource Action Tests

```php
<?php
// tests/Unit/Actions/BookResourceTest.php

namespace Tests\Unit\Actions;

use Tests\TestCase;
use App\Models\Room;
use App\Models\User;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Carbon\Carbon;

class BookResourceTest extends TestCase
{
    private BookResource $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new BookResource();
    }

    public function test_creates_booking_successfully(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $resource = BookableResource::factory()->create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
        ]);

        $periods = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(9, 0),
                Carbon::tomorrow()->setTime(11, 0)
            )
        ]);

        $booking = $this->action->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user,
            label: 'Test Booking'
        );

        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals($user->id, $booking->booker_id);
        $this->assertEquals(User::class, $booking->booker_type);
        $this->assertEquals('Test Booking', $booking->label);
        $this->assertCount(1, $booking->bookedPeriods);
    }

    public function test_throws_exception_on_overlap(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $resource = BookableResource::factory()->create();

        $periods = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(9, 0),
                Carbon::tomorrow()->setTime(11, 0)
            )
        ]);

        // Create first booking
        $this->action->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user1
        );

        // Try to create overlapping booking
        $this->expectException(BookingResourceOverlappingException::class);
        
        $this->action->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user2
        );
    }

    public function test_updates_existing_booking(): void
    {
        $user = User::factory()->create();
        $resource = BookableResource::factory()->create();

        $originalPeriods = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(9, 0),
                Carbon::tomorrow()->setTime(11, 0)
            )
        ]);

        // Create original booking
        $originalBooking = $this->action->run(
            periods: $originalPeriods,
            bookableResource: $resource,
            booker: $user,
            label: 'Original Booking'
        );

        // Update booking with new periods
        $newPeriods = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(14, 0),
                Carbon::tomorrow()->setTime(16, 0)
            )
        ]);

        $updatedBooking = $this->action->run(
            periods: $newPeriods,
            bookableResource: $resource,
            booker: $user,
            booking: $originalBooking,
            label: 'Updated Booking'
        );

        $this->assertEquals($originalBooking->id, $updatedBooking->id);
        $this->assertEquals('Updated Booking', $updatedBooking->label);
        
        // Check that periods were updated
        $updatedBooking->refresh();
        $period = $updatedBooking->bookedPeriods->first();
        $this->assertEquals('14:00:00', $period->starts_at->format('H:i:s'));
        $this->assertEquals('16:00:00', $period->ends_at->format('H:i:s'));
    }

    public function test_handles_metadata(): void
    {
        $user = User::factory()->create();
        $resource = BookableResource::factory()->create();
        $periods = PeriodCollection::make([
            Period::make(Carbon::tomorrow()->setTime(9, 0), Carbon::tomorrow()->setTime(11, 0))
        ]);

        $metadata = [
            'attendees' => 5,
            'equipment' => ['projector', 'whiteboard'],
            'catering' => true,
        ];

        $booking = $this->action->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user,
            meta: $metadata
        );

        $this->assertEquals(5, $booking->meta['attendees']);
        $this->assertEquals(['projector', 'whiteboard'], $booking->meta['equipment']);
        $this->assertTrue($booking->meta['catering']);
    }
}
```

### CheckBookingOverlaps Action Tests

```php
<?php
// tests/Unit/Actions/CheckBookingOverlapsTest.php

namespace Tests\Unit\Actions;

use Tests\TestCase;
use Masterix21\Bookings\Actions\CheckBookingOverlaps;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use App\Models\User;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Carbon\Carbon;

class CheckBookingOverlapsTest extends TestCase
{
    private CheckBookingOverlaps $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new CheckBookingOverlaps();
    }

    public function test_returns_true_when_no_overlap(): void
    {
        $resource = BookableResource::factory()->create();
        $periods = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(9, 0),
                Carbon::tomorrow()->setTime(11, 0)
            )
        ]);

        $result = $this->checker->run(
            periods: $periods,
            bookableResource: $resource,
            throw: false
        );

        $this->assertTrue($result);
    }

    public function test_returns_false_when_overlap_exists(): void
    {
        $user = User::factory()->create();
        $resource = BookableResource::factory()->create();
        
        $periods = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(9, 0),
                Carbon::tomorrow()->setTime(11, 0)
            )
        ]);

        // Create existing booking
        (new BookResource())->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user
        );

        // Check for overlap
        $result = $this->checker->run(
            periods: $periods,
            bookableResource: $resource,
            throw: false
        );

        $this->assertFalse($result);
    }

    public function test_throws_exception_when_overlap_and_throw_enabled(): void
    {
        $user = User::factory()->create();
        $resource = BookableResource::factory()->create();
        
        $periods = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(9, 0),
                Carbon::tomorrow()->setTime(11, 0)
            )
        ]);

        // Create existing booking
        (new BookResource())->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user
        );

        // Check for overlap with throw enabled
        $this->expectException(BookingResourceOverlappingException::class);
        
        $this->checker->run(
            periods: $periods,
            bookableResource: $resource,
            throw: true
        );
    }

    public function test_ignores_specified_booking(): void
    {
        $user = User::factory()->create();
        $resource = BookableResource::factory()->create();
        
        $periods = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(9, 0),
                Carbon::tomorrow()->setTime(11, 0)
            )
        ]);

        // Create existing booking
        $existingBooking = (new BookResource())->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user
        );

        // Check for overlap, ignoring the existing booking
        $result = $this->checker->run(
            periods: $periods,
            bookableResource: $resource,
            ignoreBooking: $existingBooking,
            throw: false
        );

        $this->assertTrue($result);
    }
}
```

## Testing Events

### Event Firing Tests

```php
<?php
// tests/Unit/Events/BookingEventsTest.php

namespace Tests\Unit\Events;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Masterix21\Bookings\Events\BookingCompleted;
use Masterix21\Bookings\Events\BookingInProgress;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\BookableResource;
use App\Models\User;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Carbon\Carbon;

class BookingEventsTest extends TestCase
{
    public function test_booking_in_progress_event_fired(): void
    {
        Event::fake([BookingInProgress::class]);

        $user = User::factory()->create();
        $resource = BookableResource::factory()->create();
        $periods = PeriodCollection::make([
            Period::make(Carbon::tomorrow()->setTime(9, 0), Carbon::tomorrow()->setTime(11, 0))
        ]);

        (new BookResource())->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user
        );

        Event::assertDispatched(BookingInProgress::class, function ($event) use ($periods, $resource, $user) {
            return $event->periods->equals($periods) &&
                   $event->bookableResource->is($resource) &&
                   $event->booker->is($user);
        });
    }

    public function test_booking_completed_event_fired(): void
    {
        Event::fake([BookingCompleted::class]);

        $user = User::factory()->create();
        $resource = BookableResource::factory()->create();
        $periods = PeriodCollection::make([
            Period::make(Carbon::tomorrow()->setTime(9, 0), Carbon::tomorrow()->setTime(11, 0))
        ]);

        $booking = (new BookResource())->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user
        );

        Event::assertDispatched(BookingCompleted::class, function ($event) use ($booking, $periods, $resource) {
            return $event->booking->is($booking) &&
                   $event->periods->equals($periods) &&
                   $event->bookableResource->is($resource);
        });
    }
}
```

### Event Listener Tests

```php
<?php
// tests/Unit/Listeners/SendBookingConfirmationTest.php

namespace Tests\Unit\Listeners;

use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Masterix21\Bookings\Events\BookingCompleted;
use App\Listeners\SendBookingConfirmation;
use App\Mail\BookingConfirmationMail;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Models\BookableResource;
use App\Models\User;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class SendBookingConfirmationTest extends TestCase
{
    public function test_sends_confirmation_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'test@example.com']);
        $booking = Booking::factory()->create([
            'booker_type' => User::class,
            'booker_id' => $user->id,
        ]);
        $resource = BookableResource::factory()->create();
        $periods = PeriodCollection::make([
            Period::make('2024-01-01 09:00', '2024-01-01 11:00')
        ]);

        $event = new BookingCompleted($booking, $periods, $resource);
        $listener = new SendBookingConfirmation();
        
        $listener->handle($event);

        Mail::assertSent(BookingConfirmationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_does_not_send_email_when_no_booker_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => null]);
        $booking = Booking::factory()->create([
            'booker_type' => User::class,
            'booker_id' => $user->id,
        ]);
        $resource = BookableResource::factory()->create();
        $periods = PeriodCollection::make([
            Period::make('2024-01-01 09:00', '2024-01-01 11:00')
        ]);

        $event = new BookingCompleted($booking, $periods, $resource);
        $listener = new SendBookingConfirmation();
        
        $listener->handle($event);

        Mail::assertNothingSent();
    }
}
```

## Feature Tests

### Booking Workflow Tests

```php
<?php
// tests/Feature/BookingWorkflowTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Room;
use App\Models\User;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Carbon\Carbon;

class BookingWorkflowTest extends TestCase
{
    public function test_complete_booking_workflow(): void
    {
        // Setup
        $user = User::factory()->create();
        $room = Room::factory()->create(['name' => 'Conference Room A']);
        $resource = BookableResource::factory()->create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
            'max' => 1,
            'size' => 10,
        ]);

        $periods = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(9, 0),
                Carbon::tomorrow()->setTime(11, 0)
            )
        ]);

        // Test booking creation
        $booking = (new BookResource())->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user,
            label: 'Team Meeting',
            note: 'Weekly standup',
            meta: [
                'attendees' => 8,
                'equipment' => ['projector'],
            ]
        );

        // Assertions
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'booker_type' => User::class,
            'booker_id' => $user->id,
            'label' => 'Team Meeting',
            'note' => 'Weekly standup',
        ]);

        $this->assertDatabaseHas('booked_periods', [
            'booking_id' => $booking->id,
            'bookable_resource_id' => $resource->id,
        ]);

        // Test relationships work
        $this->assertEquals($user->id, $booking->booker->id);
        $this->assertEquals($room->name, $booking->bookedPeriods->first()->bookableResource->resource->name);
        $this->assertEquals(8, $booking->meta['attendees']);
        $this->assertEquals(['projector'], $booking->meta['equipment']);
    }

    public function test_prevents_double_booking(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $resource = BookableResource::factory()->create();

        $periods = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(9, 0),
                Carbon::tomorrow()->setTime(11, 0)
            )
        ]);

        // First booking succeeds
        $booking1 = (new BookResource())->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user1
        );

        $this->assertNotNull($booking1);

        // Second booking fails
        $this->expectException(\Exception::class);
        
        (new BookResource())->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user2
        );
    }

    public function test_can_book_adjacent_periods(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $resource = BookableResource::factory()->create();

        $periods1 = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(9, 0),
                Carbon::tomorrow()->setTime(11, 0)
            )
        ]);

        $periods2 = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(11, 0),
                Carbon::tomorrow()->setTime(13, 0)
            )
        ]);

        // Both bookings should succeed
        $booking1 = (new BookResource())->run(
            periods: $periods1,
            bookableResource: $resource,
            booker: $user1
        );

        $booking2 = (new BookResource())->run(
            periods: $periods2,
            bookableResource: $resource,
            booker: $user2
        );

        $this->assertNotNull($booking1);
        $this->assertNotNull($booking2);
        $this->assertNotEquals($booking1->id, $booking2->id);
    }
}
```

### API Tests

```php
<?php
// tests/Feature/Api/BookingApiTest.php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use App\Models\Room;
use App\Models\User;
use Masterix21\Bookings\Models\BookableResource;

class BookingApiTest extends TestCase
{
    public function test_can_create_booking_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $room = Room::factory()->create();
        $resource = BookableResource::factory()->create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
        ]);

        $response = $this->postJson('/api/bookings', [
            'resource_id' => $resource->id,
            'start_time' => now()->addDay()->setTime(9, 0)->toISOString(),
            'end_time' => now()->addDay()->setTime(11, 0)->toISOString(),
            'label' => 'API Test Booking',
            'meta' => [
                'attendees' => 5,
            ],
        ]);

        $response->assertCreated()
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'code',
                        'label',
                        'booker',
                        'periods',
                        'meta',
                    ]
                ]);

        $this->assertDatabaseHas('bookings', [
            'label' => 'API Test Booking',
            'booker_id' => $user->id,
        ]);
    }

    public function test_api_validation_works(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bookings', [
            'resource_id' => 999, // Non-existent resource
            'start_time' => 'invalid-date',
            'end_time' => now()->subDay()->toISOString(), // Past date
        ]);

        $response->assertUnprocessable()
                ->assertJsonValidationErrors(['resource_id', 'start_time', 'end_time']);
    }
}
```

## Test Factories

### BookableResource Factory

```php
<?php
// database/factories/BookableResourceFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masterix21\Bookings\Models\BookableResource;
use App\Models\Room;

class BookableResourceFactory extends Factory
{
    protected $model = BookableResource::class;

    public function definition(): array
    {
        return [
            'resource_type' => Room::class,
            'resource_id' => Room::factory(),
            'max' => $this->faker->numberBetween(1, 5),
            'size' => $this->faker->numberBetween(1, 20),
            'is_bookable' => true,
            'is_visible' => true,
        ];
    }

    public function notBookable(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_bookable' => false,
            ];
        });
    }

    public function hidden(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_visible' => false,
            ];
        });
    }

    public function forRoom(Room $room): Factory
    {
        return $this->state(function (array $attributes) use ($room) {
            return [
                'resource_type' => Room::class,
                'resource_id' => $room->id,
            ];
        });
    }
}
```

### Booking Factory

```php
<?php
// database/factories/BookingFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Generators\RandomBookingCode;
use App\Models\User;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'code' => (new RandomBookingCode())->generate(),
            'booker_type' => User::class,
            'booker_id' => User::factory(),
            'label' => $this->faker->sentence(3),
            'note' => $this->faker->optional()->paragraph(),
            'meta' => collect([
                'created_via' => 'test',
                'attendees' => $this->faker->numberBetween(1, 10),
            ]),
        ];
    }

    public function forUser(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'booker_type' => User::class,
                'booker_id' => $user->id,
            ];
        });
    }

    public function withLabel(string $label): Factory
    {
        return $this->state(function (array $attributes) use ($label) {
            return [
                'label' => $label,
            ];
        });
    }
}
```

## Mock Testing

### Mocking External Services

```php
<?php
// tests/Unit/Services/EmailServiceTest.php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use App\Services\EmailService;
use App\Services\BookingNotificationService;
use Masterix21\Bookings\Models\Booking;

class BookingNotificationServiceTest extends TestCase
{
    public function test_sends_booking_confirmation(): void
    {
        // Mock the email service
        $emailService = Mockery::mock(EmailService::class);
        $emailService->shouldReceive('send')
                    ->once()
                    ->with(Mockery::type('string'), Mockery::type('array'))
                    ->andReturn(true);

        // Inject mock into service
        $notificationService = new BookingNotificationService($emailService);
        $booking = Booking::factory()->create();

        $result = $notificationService->sendConfirmation($booking);

        $this->assertTrue($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

## Performance Testing

### Load Testing

```php
<?php
// tests/Performance/BookingLoadTest.php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Carbon\Carbon;

class BookingLoadTest extends TestCase
{
    public function test_can_handle_concurrent_bookings(): void
    {
        $users = User::factory()->count(10)->create();
        $resources = BookableResource::factory()->count(5)->create();

        $startTime = microtime(true);
        $bookings = collect();

        // Simulate concurrent booking attempts
        foreach ($users as $index => $user) {
            $resource = $resources[$index % $resources->count()];
            
            try {
                $booking = (new BookResource())->run(
                    periods: PeriodCollection::make([
                        Period::make(
                            Carbon::tomorrow()->addHours($index),
                            Carbon::tomorrow()->addHours($index + 1)
                        )
                    ]),
                    bookableResource: $resource,
                    booker: $user
                );
                
                $bookings->push($booking);
            } catch (\Exception $e) {
                // Expected for overlapping bookings
            }
        }

        $duration = microtime(true) - $startTime;

        // Assert performance is acceptable (less than 2 seconds for 10 bookings)
        $this->assertLessThan(2.0, $duration);
        $this->assertGreaterThan(0, $bookings->count());
    }
}
```

This comprehensive testing guide provides patterns and examples for thoroughly testing Laravel Bookings implementations, ensuring reliability and performance.