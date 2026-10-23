<?php

namespace App\Service;

use App\Dto\BoulderFilters;
use App\Entity\Ascent;
use App\Entity\Boulder;
use App\Repository\BoulderRepository;

class BoulderDisplay
{
    public function __construct(private BoulderRepository $boulders)
    {
    }

    public function list(BoulderFilters $filters): array
    {
        return array_map(static function (Boulder $boulder): array {
            $ascents = $boulder->getAscents()->toArray();

            return [
                'id' => $boulder->getId(),
                'name' => $boulder->getName(),
                'color' => $boulder->getColor(),
                'grade' => $boulder->getGrade(),
                'gradeLabel' => $boulder->getGradeLabel(),
                'openedAt' => $boulder->getOpenedAt()?->format(\DateTimeInterface::ATOM),
                'sector' => ['id' => $boulder->getSector()->getId(), 'name' => $boulder->getSector()->getName()],
                'successCount' => $boulder->getSuccessCount(),
                'averageRating' => $boulder->getAverageRating(),
                'ascents' => array_values(array_map(static fn (Ascent $ascent): array => [
                    'id' => $ascent->getId(),
                    'completedAt' => $ascent->getCompletedAt()?->format(\DateTimeInterface::ATOM),
                    'rating' => $ascent->getRating(),
                ], $ascents)),
            ];
        }, $this->boulders->findAvailable($filters->toArray(), withAscents: true));
    }
}
