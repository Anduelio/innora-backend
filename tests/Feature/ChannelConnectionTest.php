<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ChannelConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_stored_encrypted_and_masked_in_the_api(): void
    {
        $user = $this->reception();
        Passport::actingAs($user);

        $secret = 'secret-token-value';

        $this->postJson('/api/channels', [
            'providerCode' => 'hotelrunner',
            'isActive' => true,
            'credentials' => [
                'api_key' => $secret,
                'hotel_id' => 'HR-44',
            ],
        ])->assertOk()
            ->assertJsonPath('data.providerCode', 'hotelrunner')
            ->assertJsonPath('data.credentials.hotel_id', 'HR-44')
            ->assertJsonPath('data.credentials.api_key', '••••••••alue')
            ->assertJsonMissing(['api_key' => $secret]);

        $stored = ChannelConnection::query()->firstOrFail();
        $this->assertSame($secret, $stored->config_encrypted['api_key']);
        $this->assertSame('https://app.hotelrunner.com', $stored->config_encrypted['base_url']);
        $this->assertStringNotContainsString($secret, (string) $stored->getRawOriginal('config_encrypted'));

        $this->getJson('/api/channels')
            ->assertOk()
            ->assertJsonPath('data.0.credentials.api_key', '••••••••alue')
            ->assertJsonMissing(['api_key' => $secret]);
    }

    public function test_blank_secret_keeps_the_stored_value_and_only_one_provider_stays_active(): void
    {
        $user = $this->reception();
        Passport::actingAs($user);

        $this->postJson('/api/channels', [
            'providerCode' => 'hotelrunner',
            'credentials' => ['api_key' => 'secret-token-value', 'hotel_id' => 'HR-44'],
        ])->assertOk();

        $this->postJson('/api/channels', [
            'providerCode' => 'hotelrunner',
            'credentials' => ['hotel_id' => 'HR-99', 'api_key' => '••••••••alue'],
        ])->assertOk()
            ->assertJsonPath('data.credentials.hotel_id', 'HR-99');

        $this->assertSame('secret-token-value', ChannelConnection::query()->first()->config_encrypted['api_key']);

        $this->postJson('/api/channels', [
            'providerCode' => 'beds24',
            'isActive' => true,
            'credentials' => ['api_key' => 'beds-secret-key', 'prop_id' => '900'],
        ])->assertOk();

        $this->assertFalse((bool) ChannelConnection::query()->where('provider_code', 'hotelrunner')->value('is_active'));
        $this->assertTrue((bool) ChannelConnection::query()->where('provider_code', 'beds24')->value('is_active'));
    }

    public function test_a_connection_from_another_property_cannot_be_removed(): void
    {
        $owner = $this->reception();
        $other = $this->reception();
        Passport::actingAs($owner);

        $created = $this->postJson('/api/channels', [
            'providerCode' => 'siteminder',
            'credentials' => [
                'username' => 'desk',
                'password' => 'siteminder-pass',
                'hotel_code' => 'SM1',
            ],
        ])->assertOk()->json('data.id');

        Passport::actingAs($other);

        $this->deleteJson('/api/channels/'.$created)->assertStatus(422);
        $this->assertNotNull(ChannelConnection::query()->find($created));
    }

    private function reception(): User
    {
        $property = Property::query()->create([
            'name' => 'Hotel Innora',
            'city' => 'Sarandë',
            'currency' => 'EUR',
        ]);

        return User::factory()->create([
            'property_id' => $property->id,
            'role_id' => Role::query()->firstOrCreate(
                ['code' => UserRole::Reception->value],
                ['name' => UserRole::Reception->label()],
            )->id,
            'email' => 'recepsion-'.uniqid().'@innora.test',
        ]);
    }
}
