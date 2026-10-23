<?php

namespace App\Service;

use App\Entity\Boulder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BoulderManager
{
    public function __construct(private EntityManagerInterface $manager) {}

    public function save(Boulder $boulder, User $user): void
    {
        if (null === $boulder->getId()) {
            $boulder->setOpenedBy($user);
        }
        $this->manager->persist($boulder);
        $this->manager->flush();
    }

    public function dismantle(Boulder $boulder): void
    {
        $this->dismantleMany([$boulder], new \DateTimeImmutable());
    }

    /** @param iterable<Boulder> $boulders */
    public function dismantleMany(iterable $boulders, \DateTimeImmutable $at): int
    {
        $at = $at->setTimezone(new \DateTimeZone('UTC'));
        return $this->manager->wrapInTransaction(function () use ($boulders, $at): int {
            $count = 0;
            foreach ($boulders as $boulder) {
                if (null === $boulder->getDismantledAt() || $boulder->getDismantledAt() > $at) {
                    $boulder->setDismantledAt($at);
                    ++$count;
                }
            }

            return $count;
        });
    }
}
