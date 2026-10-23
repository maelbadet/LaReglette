<?php

namespace App\Dto;

use App\Enum\Grade;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression('this.gradeMin == null or this.gradeMax == null or this.gradeMin <= this.gradeMax', message: 'La cotation maximale doit être supérieure ou égale à la cotation minimale.')]
final readonly class BoulderFilters
{
    public function __construct(
        #[Assert\Positive]
        public ?int $sector = null,
        #[Assert\Length(max: 50)]
        public ?string $color = null,
        #[Assert\Range(min: Grade::MIN, max: Grade::MAX)]
        public ?int $grade = null,
        #[Assert\Range(min: Grade::MIN, max: Grade::MAX)]
        public ?int $gradeMin = null,
        #[Assert\Range(min: Grade::MIN, max: Grade::MAX)]
        public ?int $gradeMax = null,
        #[Assert\IsFalse(message: 'Le filtre unfinished nécessite un grimpeur connecté et ne peut pas être utilisé sur cet écran public.')]
        public bool $unfinished = false,
    ) {
    }

    public function toArray(): array
    {
        return ['sector' => $this->sector, 'color' => $this->color, 'grade' => $this->grade, 'gradeMin' => $this->gradeMin, 'gradeMax' => $this->gradeMax];
    }
}
