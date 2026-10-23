<?php

namespace App\Enum;

use App\Entity\User;

enum UserRole: string
{
    case Climber = 'ROLE_GRIMPEUR';
    case Opener = 'ROLE_OUVREUR';
    case Admin = 'ROLE_ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::Climber => 'Grimpeur',
            self::Opener => 'Ouvreur',
            self::Admin => 'Administrateur',
        };
    }

    public static function forUser(User $user): ?self
    {
        foreach ([self::Admin, self::Opener, self::Climber] as $role) {
            if (in_array($role->value, $user->getRoles(), true)) {
                return $role;
            }
        }

        return null;
    }
}
