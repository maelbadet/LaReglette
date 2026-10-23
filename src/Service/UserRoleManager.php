<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;

class UserRoleManager
{
    public function __construct(private EntityManagerInterface $manager)
    {
    }

    public function changeRole(User $user, UserRole $role): void
    {
        $user->setRoles([$role->value]);
        $this->manager->flush();
    }
}
