<?php

namespace App\Entity;

use App\Enum\Grade;
use App\Repository\BoulderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BoulderRepository::class)]
class Boulder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private ?string $color = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull]
    #[Assert\Range(min: Grade::MIN, max: Grade::MAX)]
    private ?int $grade = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $successCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $climberCount = 0;

    #[ORM\Column(nullable: true)]
    private ?float $averageRating = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $openedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dismantledAt = null;

    #[ORM\ManyToOne(inversedBy: 'boulders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Sector $sector = null;

    #[ORM\ManyToOne(inversedBy: 'boulders')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $openedBy = null;

    /**
     * @var Collection<int, Ascent>
     */
    #[ORM\OneToMany(targetEntity: Ascent::class, mappedBy: 'boulder')]
    private Collection $ascents;

    public function __construct()
    {
        $this->ascents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function setSuccessCount(int $successCount): static
    {
        $this->successCount = $successCount;

        return $this;
    }

    public function getClimberCount(): int
    {
        return $this->climberCount;
    }

    public function setClimberCount(int $climberCount): static
    {
        $this->climberCount = $climberCount;

        return $this;
    }

    public function getAverageRating(): ?float
    {
        return $this->averageRating;
    }

    public function setAverageRating(?float $averageRating): static
    {
        $this->averageRating = $averageRating;

        return $this;
    }

    public function isAvailable(): bool
    {
        $now = new \DateTimeImmutable();

        return null !== $this->openedAt && $this->openedAt <= $now && (null === $this->dismantledAt || $this->dismantledAt > $now);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getGrade(): ?int
    {
        return $this->grade;
    }

    public function getGradeLabel(): string
    {
        return Grade::tryFrom($this->grade ?? 0)?->label() ?? 'Non renseignée';
    }

    public function setGrade(int $grade): static
    {
        $this->grade = $grade;

        return $this;
    }

    public function getOpenedAt(): ?\DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(?\DateTimeImmutable $openedAt): static
    {
        $this->openedAt = $openedAt;

        return $this;
    }

    public function getDismantledAt(): ?\DateTimeImmutable
    {
        return $this->dismantledAt;
    }

    public function setDismantledAt(?\DateTimeImmutable $dismantledAt): static
    {
        $this->dismantledAt = $dismantledAt;

        return $this;
    }

    public function getSector(): ?Sector
    {
        return $this->sector;
    }

    public function setSector(?Sector $sector): static
    {
        $this->sector = $sector;

        return $this;
    }

    public function getOpenedBy(): ?User
    {
        return $this->openedBy;
    }

    public function setOpenedBy(?User $openedBy): static
    {
        $this->openedBy = $openedBy;

        return $this;
    }

    /**
     * @return Collection<int, Ascent>
     */
    public function getAscents(): Collection
    {
        return $this->ascents;
    }

    public function addAscent(Ascent $ascent): static
    {
        if (!$this->ascents->contains($ascent)) {
            $this->ascents->add($ascent);
            $ascent->setBoulder($this);
        }

        return $this;
    }

    public function removeAscent(Ascent $ascent): static
    {
        if ($this->ascents->removeElement($ascent)) {
            // set the owning side to null (unless already changed)
            if ($ascent->getBoulder() === $this) {
                $ascent->setBoulder(null);
            }
        }

        return $this;
    }
}
