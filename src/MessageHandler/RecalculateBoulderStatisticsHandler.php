<?php

namespace App\MessageHandler;

use App\Entity\Boulder;
use App\Message\RecalculateBoulderStatistics;
use App\Repository\BoulderRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RecalculateBoulderStatisticsHandler
{
    public function __construct(
        private EntityManagerInterface $manager,
        private BoulderRepository $boulders,
    ) {
    }

    public function __invoke(RecalculateBoulderStatistics $message): void
    {
        $this->manager->wrapInTransaction(function () use ($message): void {
            $boulder = $this->manager->find(Boulder::class, $message->boulderId, LockMode::PESSIMISTIC_WRITE);
            if (null === $boulder) {
                return;
            }
            $this->manager->refresh($boulder);
            $statistics = $this->boulders->calculateStatistics($boulder);
            $boulder->setSuccessCount((int) $statistics['ascents']);
            $boulder->setClimberCount((int) $statistics['climbers']);
            $boulder->setAverageRating(null === $statistics['rating'] ? null : (float) $statistics['rating']);
        });
    }
}
