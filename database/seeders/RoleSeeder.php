<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::query()->updateOrCreate(
                ['code' => $role->value],
                ['name' => $role->label()],
            );
        }

        $reception = Role::query()->where('code', UserRole::Reception->value)->first();

        User::query()
            ->where('email', 'recepsion@viladea.al')
            ->whereNull('role_id')
            ->update(['role_id' => $reception->id]);
    }
}
