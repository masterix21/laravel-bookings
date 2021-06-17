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
use Kirschbaum\PowerJoins\PowerJoins;
use Masterix21\Bookings\Events\Booking\CreatingBooking;
use Masterix21\Bookings\Models\Concerns\HasSizeFeatures;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\Relationships\HasBookedPeriods;
use Masterix21\Bookings\Models\Concerns\Scopes\ImplementsBookableScopes;
use Masterix21\Bookings\Models\Concerns\Scopes\ImplementsVisibleScopes;
use Masterix21\Bookings\Models\Concerns\UsesBookablePlannings;
use Spatie\Period\PeriodCollection;

class BookableResource extends Model
{
    use HasFactory;
    use PowerJoins;
    use BelongsToBookableArea;
    use HasBookedPeriods;
    use UsesBookablePlannings;
    use HasSizeFeatures;
    use ImplementsVisibleScopes;
    use ImplementsBookableScopes;

    protected $guarded = [];

    protected $casts = [
        'is_visible' => 'bool',
        'is_bookable' => 'bool',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookableRelations(): HasMany
    {
        return $this->hasMany(
            config('bookings.models.bookable_relation'),
            'parent_bookable_area_id',
            'bookable_area_id'
        )->where(function (Builder $query) {
            $query->whereNull('parent_bookable_resource_id')
                ->orWhere('parent_bookable_resource_id', 'bookable_resources.id');
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
        ?User $user = null,
        PeriodCollection $periods,
        Collection | EloquentCollection | null $relations = null,
        ?string $code = null,
        ?string $label = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $tax_code = null,
        ?string $address = null,
        ?string $note = null,
    ): Booking {
        return DB::transaction(function () use ($user, $periods, $relations, $code, $label, $email, $phone, $tax_code, $address, $note) {
            if (is_null($relations)) {
                $relations = collect();
            }

            event(new CreatingBooking($this, $periods));

            /** @var Booking $booking */
            $booking = resolve(config('bookings.models.booking'));

            $booking->fill([
                'code' => $code ?? (string) Str::uuid(),
                'user_id' => $user->id ?? null,
                'label' => $label,
                'email' => $email ?? $user->email,
                'phone' => $phone,
                'tax_code' => Str::upper($tax_code),
                'address' => $address,
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
