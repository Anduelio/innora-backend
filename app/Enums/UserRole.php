<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Reception = 'reception';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pronar',
            self::Reception => 'Recepsion',
        };
    }

    /**
     * @return list<PermissionEnum>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => PermissionEnum::cases(),
            self::Reception => [
                PermissionEnum::FoliosView,
                PermissionEnum::FoliosAddCharge,
                PermissionEnum::FoliosRecordPayment,
                PermissionEnum::FoliosClose,
            ],
        };
    }

    public function allows(PermissionEnum $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
