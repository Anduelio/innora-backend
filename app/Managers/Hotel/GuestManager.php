<?php

namespace App\Managers\Hotel;

use App\Access\Visibility\PropertyVisibility;
use App\Models\Guest;
use App\Models\Property;
use App\Models\User;
use App\Pipelines\Filters\Guest\HasPhoneFilter;
use App\Pipelines\Filters\Guest\QueryFilter;
use App\Traits\HasMessages;
use App\Traits\PipelineHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

class GuestManager
{
    use HasMessages, PipelineHandler;

    public function __construct(private readonly PropertyVisibility $visibility) {}

    public function list(User $actor, array $filters): Builder
    {
        $query = $this->visibility->list(
            Guest::query()
                ->withCount('reservations as stays_count')
                ->withMax('reservations as last_stay', 'check_in')
                ->orderByDesc('last_stay')
                ->orderBy('name'),
            $actor,
        );

        return $this->applyPipeline($query, $filters, [
            QueryFilter::class,
            HasPhoneFilter::class,
        ]);
    }

    public function find(User $actor, Guest $guest): Guest
    {
        $this->assertOwned($actor, $guest);

        return Guest::query()
            ->whereKey($guest->id)
            ->withCount('reservations as stays_count')
            ->withMax('reservations as last_stay', 'check_in')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Guest
    {
        $property = $this->property($actor);
        [$prefix, $phone] = $this->phonePair($data);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $this->throwValidationError('name', 'messages.guests.name_required');
        }
        $this->assertPhoneFree($property, $prefix, $phone);

        try {
            $guest = Guest::query()->create([
                'property_id' => $property->id,
                'name' => $name,
                'phone_prefix' => $prefix,
                'phone' => $phone,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->throwValidationError('phone', 'messages.guests.phone_taken');
        }

        return $guest->loadCount('reservations as stays_count');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Guest $guest, array $data): Guest
    {
        $this->assertOwned($actor, $guest);
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                $this->throwValidationError('name', 'messages.guests.name_required');
            }
            $guest->name = $name;
        }

        $phoneTouched = array_key_exists('phone', $data) || array_key_exists('phonePrefix', $data);
        if ($phoneTouched) {
            [$prefix, $phone] = $this->phonePair([
                'phonePrefix' => array_key_exists('phonePrefix', $data) ? $data['phonePrefix'] : $guest->phone_prefix,
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $guest->phone,
            ]);
            $this->assertPhoneFree($this->property($actor), $prefix, $phone, $guest->id);
            $guest->phone_prefix = $prefix;
            $guest->phone = $phone;
        }

        try {
            $guest->save();
        } catch (UniqueConstraintViolationException) {
            $this->throwValidationError('phone', 'messages.guests.phone_taken');
        }

        return Guest::query()
            ->whereKey($guest->id)
            ->withCount('reservations as stays_count')
            ->withMax('reservations as last_stay', 'check_in')
            ->firstOrFail();
    }

    public function delete(User $actor, Guest $guest): void
    {
        $this->assertOwned($actor, $guest);
        if ($guest->reservations()->exists()) {
            $this->throwValidationError('guest', 'messages.guests.cannot_delete_with_stays');
        }
        $guest->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: ?string}
     */
    private function phonePair(array $data): array
    {
        $prefix = $this->blank(isset($data['phonePrefix']) ? (string) $data['phonePrefix'] : null);
        $raw = isset($data['phone']) ? (string) $data['phone'] : null;
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        $phone = $digits === '' ? null : $digits;

        if ($prefix !== null && ! preg_match('/^\+\d{1,4}$/', $prefix)) {
            $this->throwValidationError('phonePrefix', 'messages.guests.phone_prefix_invalid');
        }

        if ($phone !== null && $prefix === null) {
            $this->throwValidationError('phonePrefix', 'messages.guests.phone_prefix_invalid');
        }

        if ($prefix !== null && $phone === null) {
            $prefix = null;
        }

        return [$prefix, $phone];
    }

    private function assertPhoneFree(Property $property, ?string $prefix, ?string $phone, ?int $ignoreId = null): void
    {
        if ($phone === null) {
            return;
        }

        $taken = Guest::query()
            ->where('property_id', $property->id)
            ->where('phone_prefix', $prefix)
            ->where('phone', $phone)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            $this->throwValidationError('phone', 'messages.guests.phone_taken');
        }
    }

    private function property(User $actor): Property
    {
        if ($actor->property === null) {
            $this->throwAuthorizationError();
        }

        return $actor->property;
    }

    private function assertOwned(User $actor, Guest $guest): void
    {
        if ((int) $guest->property_id !== (int) $actor->property_id) {
            $this->throwValidationError('guest', 'messages.guests.not_found');
        }
    }

    private function blank(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
