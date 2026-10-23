<?php

namespace App\Service;

use App\Entity\Ascent;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AscentManager
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function create(Ascent $ascent, User $user): void
    {
        $ascent->setUser($user);
        $this->entityManager->persist($ascent);
        $this->entityManager->flush();
    }

    public function delete(Ascent $ascent): void
    {
        $this->entityManager->remove($ascent);
        $this->entityManager->flush();
    }
}
