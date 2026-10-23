<?php

namespace App\Service;

use App\Entity\Sector;
use Doctrine\ORM\EntityManagerInterface;

class SectorManager
{
    public function __construct(private EntityManagerInterface $manager) {}

    public function save(Sector $sector): void
    {
        $this->manager->persist($sector);
        $this->manager->flush();
    }

    public function delete(Sector $sector): bool
    {
        if (!$sector->getBoulders()->isEmpty()) {
            return false;
        }
        $this->manager->remove($sector);
        $this->manager->flush();

        return true;
    }
}
