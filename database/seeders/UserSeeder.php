<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'Admin1234.2';

    public function run(): void
    {
        $property = Property::query()->first();
        if ($property === null) {
            return;
        }

        $users = [
            [
                'email' => 'owner@viladea.al',
                'name' => 'Pronari',
                'role' => UserRole::Owner,
            ],
            [
                'email' => 'pronar@viladea.al',
                'name' => 'Pronari',
                'role' => UserRole::Owner,
            ],
            [
                'email' => 'reception@viladea.al',
                'name' => 'Recepsioni',
                'role' => UserRole::Reception,
            ],
            [
                'email' => 'recepsion@viladea.al',
                'name' => 'Recepsioni',
                'role' => UserRole::Reception,
            ],
        ];

        foreach ($users as $row) {
            $roleId = Role::query()->where('code', $row['role']->value)->value('id');

            User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => self::DEFAULT_PASSWORD,
                    'property_id' => $property->id,
                    'role_id' => $roleId,
                ],
            );
        }
    }
}
