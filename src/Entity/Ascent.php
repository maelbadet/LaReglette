<?php

namespace App\Entity;

use App\Repository\AscentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: AscentRepository::class)]
class Ascent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Indiquez la date de votre ascension.')]
    #[Assert\LessThanOrEqual('now', message: 'La date ne peut pas être dans le futur.')]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $comment = null;

    #[ORM\ManyToOne(inversedBy: 'ascents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'ascents')]
    #[Assert\NotNull(message: 'Choisissez un bloc.')]
    private ?Boulder $boulder = null;

    #[Assert\Callback]
    public function validateOpeningDate(ExecutionContextInterface $context): void
    {
        if (null === $this->completedAt || null === $this->boulder) {
            return;
        }
        $openedAt = $this->boulder->getOpenedAt();
        if (null === $openedAt) {
            $context->buildViolation('Ce bloc ne possède pas de date d’ouverture.')->atPath('boulder')->addViolation();
        } elseif ($this->completedAt < $openedAt) {
            $context->buildViolation('La date ne peut pas précéder l’ouverture du bloc.')->atPath('completedAt')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getBoulder(): ?Boulder
    {
        return $this->boulder;
    }

    public function setBoulder(?Boulder $boulder): static
    {
        $this->boulder = $boulder;

        return $this;
    }
}
