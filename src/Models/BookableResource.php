<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Masterix21\Bookings\Events\Booking\CreatingBooking;
use Masterix21\Bookings\Models\Concerns\HasSizeFeatures;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\Relationships\HasBookedPeriods;
use Masterix21\Bookings\Models\Concerns\UsesBookablePlannings;
use Spatie\Period\PeriodCollection;

class BookableResource extends Model
{
    use HasFactory;
    use BelongsToBookableArea;
    use HasBookedPeriods;
    use UsesBookablePlannings;
    use HasSizeFeatures;

    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookableRelations(): HasMany
    {
        return $this->hasMany(
            config('bookings.models.bookable_relation'),
            'bookable_area_id',
            'bookable_area_id'
        )->where(function (Builder $query) {
            $query->whereNull('bookable_resource_id')
                ->orWhereColumn('bookable_resource_id', 'bookable_resources.id');
        });
    }

    public function size(bool $ignoresUnbookable = false): int
    {
        if ($ignoresUnbookable || $this->is_bookable) {
            return $this->size;
        }

        return 0;
    }

    public function reserve(
        User $user,
        PeriodCollection $periods,
        Collection | EloquentCollection | null $relations = null,
        ?string $code = null,
        ?string $label = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $note = null,
    ): Booking {
        return DB::transaction(function () use ($user, $periods, $relations, $code, $label, $email, $phone, $note) {
            if (is_null($relations)) {
                $relations = collect();
            }

            event(new CreatingBooking($this, $periods));

            /** @var Booking $booking */
            $booking = resolve(config('bookings.models.booking'));

            $booking->fill([
                'code' => $code ?? (string) Str::uuid(),
                'user_id' => $user->id,
                'label' => $label,
                'email' => $email ?? $user->email,
                'phone' => $phone,
                'note' => $note,
            ]);

            $booking->save();

            $booking
                ->addBookingPlannings(periods: $periods)
                ->addBookedResource(bookable: $this)
                ->addBookedResources(relations: $relations);

            $booking->generateBookedPeriods();

            event(new CreatingBooking($this, $periods));

            return $booking;
        });
    }
}
