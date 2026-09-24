<?php

namespace App\Managers\Hotel;

use App\Channels\ChannelCatalog;
use App\Models\ChannelConnection;
use App\Models\User;
use App\Traits\HasMessages;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ChannelConnectionManager
{
    use HasMessages;

    /**
     * @return Collection<int, ChannelConnection>
     */
    public function list(User $actor): Collection
    {
        return ChannelConnection::query()
            ->where('property_id', $actor->property_id)
            ->orderBy('provider_code')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(User $actor, array $data): ChannelConnection
    {
        $code = (string) $data['providerCode'];
        $this->assertKnown($code);

        $existing = ChannelConnection::query()
            ->where('property_id', $actor->property_id)
            ->where('provider_code', $code)
            ->first();

        $credentials = $this->mergeCredentials($code, (array) ($data['credentials'] ?? []), $existing);
        $active = array_key_exists('isActive', $data) ? (bool) $data['isActive'] : true;

        return DB::transaction(function () use ($actor, $code, $credentials, $active, $existing) {
            if ($active) {
                ChannelConnection::query()
                    ->where('property_id', $actor->property_id)
                    ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
                    ->update(['is_active' => false, 'status' => 'inactive']);
            }

            $connection = $existing ?? new ChannelConnection([
                'property_id' => $actor->property_id,
                'provider' => $code,
                'provider_code' => $code,
            ]);

            $connection->fill([
                'provider' => $code,
                'provider_code' => $code,
                'is_active' => $active,
                'status' => $active ? 'connected' : 'inactive',
                'config_encrypted' => $credentials,
            ])->save();

            return $connection->refresh();
        });
    }

    public function delete(User $actor, ChannelConnection $connection): void
    {
        $this->assertOwned($actor, $connection);
        $connection->delete();
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function present(ChannelConnection $connection): array
    {
        $stored = (array) ($connection->config_encrypted ?? []);

        return [
            'id' => $connection->id,
            'providerCode' => $connection->provider_code,
            'label' => ChannelCatalog::has((string) $connection->provider_code)
                ? ChannelCatalog::get((string) $connection->provider_code)['label']
                : $connection->provider_code,
            'isActive' => (bool) $connection->is_active,
            'credentials' => $this->mask((string) $connection->provider_code, $stored),
        ];
    }

    private function assertKnown(string $code): void
    {
        if (! ChannelCatalog::has($code)) {
            $this->throwValidationError('providerCode', 'messages.channels.unknown_provider');
        }
    }

    private function assertOwned(User $actor, ChannelConnection $connection): void
    {
        if ((int) $connection->property_id !== (int) $actor->property_id) {
            $this->throwValidationError('channel', 'messages.channels.not_found');
        }
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, string>
     */
    private function mergeCredentials(string $code, array $incoming, ?ChannelConnection $existing): array
    {
        $provider = ChannelCatalog::get($code);
        $stored = (array) ($existing?->config_encrypted ?? []);
        $merged = $stored;

        foreach ($provider['fields'] as $field) {
            $key = $field['key'];
            $value = isset($incoming[$key]) ? trim((string) $incoming[$key]) : '';

            if ($value === '' || $this->isMasked($value)) {
                continue;
            }

            $merged[$key] = $value;
        }

        if (blank($merged['base_url'] ?? null)) {
            $merged['base_url'] = $provider['base_url'];
        }

        foreach ($provider['fields'] as $field) {
            if (! $field['required']) {
                continue;
            }

            if (blank($merged[$field['key']] ?? null)) {
                $this->throwValidationError(
                    'credentials.'.$field['key'],
                    'messages.channels.credential_required',
                    ['field' => $field['key']],
                );
            }
        }

        return array_map(fn ($value) => (string) $value, $merged);
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, string>
     */
    private function mask(string $code, array $stored): array
    {
        if (! ChannelCatalog::has($code)) {
            return [];
        }

        $secrets = ChannelCatalog::secretKeys($code);
        $visible = [];

        foreach (ChannelCatalog::get($code)['fields'] as $field) {
            $value = (string) ($stored[$field['key']] ?? '');
            if ($value === '') {
                continue;
            }

            $visible[$field['key']] = in_array($field['key'], $secrets, true)
                ? str_repeat('•', 8).substr($value, -4)
                : $value;
        }

        return $visible;
    }

    private function isMasked(string $value): bool
    {
        return str_contains($value, '•');
    }
}
