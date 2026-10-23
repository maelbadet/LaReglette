<?php

namespace App\Entity;

use App\Repository\SectorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SectorRepository::class)]
class Sector
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, Boulder>
     */
    #[ORM\OneToMany(targetEntity: Boulder::class, mappedBy: 'sector')]
    private Collection $boulders;

    public function __construct()
    {
        $this->boulders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * @return Collection<int, Boulder>
     */
    public function getBoulders(): Collection
    {
        return $this->boulders;
    }

    public function addBoulder(Boulder $boulder): static
    {
        if (!$this->boulders->contains($boulder)) {
            $this->boulders->add($boulder);
            $boulder->setSector($this);
        }

        return $this;
    }

    public function removeBoulder(Boulder $boulder): static
    {
        if ($this->boulders->removeElement($boulder)) {
            // set the owning side to null (unless already changed)
            if ($boulder->getSector() === $this) {
                $boulder->setSector(null);
            }
        }

        return $this;
    }
}
