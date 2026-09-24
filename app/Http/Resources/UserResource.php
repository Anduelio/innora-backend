<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->code,
            'permissions' => array_map(
                fn ($permission) => $permission->value,
                \App\Enums\UserRole::tryFrom((string) $this->role?->code)?->permissions() ?? [],
            ),
        ];
    }
}
