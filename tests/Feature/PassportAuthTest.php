<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PassportAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $client = app(ClientRepository::class)->createPasswordGrantClient('Innora Test', 'users', true);
        putenv('PASSPORT_CLIENT_ID='.$client->getKey());
        putenv('PASSPORT_CLIENT_SECRET='.$client->plainSecret);
        $_ENV['PASSPORT_CLIENT_ID'] = (string) $client->getKey();
        $_ENV['PASSPORT_CLIENT_SECRET'] = (string) $client->plainSecret;
        $_SERVER['PASSPORT_CLIENT_ID'] = (string) $client->getKey();
        $_SERVER['PASSPORT_CLIENT_SECRET'] = (string) $client->plainSecret;
    }

    public function test_login_returns_passport_tokens(): void
    {
        $this->seedRolesAndUser();

        $response = $this->postJson('/api/login', [
            'email' => 'recepsion@viladea.al',
            'password' => UserSeeder::DEFAULT_PASSWORD,
        ])->assertOk();

        $response->assertJsonPath('data.user.email', 'recepsion@viladea.al');
        $response->assertJsonStructure([
            'data' => [
                'user' => ['name', 'email', 'role', 'permissions'],
                'authorization' => ['token_type', 'expires_in', 'access_token', 'refresh_token'],
            ],
        ]);

        $token = $response->json('data.authorization.access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'recepsion@viladea.al');
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = $this->seedRolesAndUser();
        Passport::actingAs($user);

        $this->postJson('/api/auth/password', [
            'current_password' => 'wrong',
            'new_password' => 'Admin1234.2x',
            'new_password_confirmation' => 'Admin1234.2x',
        ])->assertStatus(422);

        $this->postJson('/api/auth/password', [
            'current_password' => UserSeeder::DEFAULT_PASSWORD,
            'new_password' => 'Admin1234.2x',
            'new_password_confirmation' => 'Admin1234.2x',
        ])->assertOk();
    }

    private function seedRolesAndUser(): User
    {
        foreach (UserRole::cases() as $role) {
            Role::query()->updateOrCreate(['code' => $role->value], ['name' => $role->label()]);
        }

        $property = Property::query()->create([
            'name' => 'Vila Dea',
            'city' => 'Sarandë',
            'currency' => 'EUR',
        ]);

        return User::query()->create([
            'name' => 'Recepsioni',
            'email' => 'recepsion@viladea.al',
            'password' => UserSeeder::DEFAULT_PASSWORD,
            'property_id' => $property->id,
            'role_id' => Role::query()->where('code', UserRole::Reception->value)->value('id'),
        ]);
    }
}
