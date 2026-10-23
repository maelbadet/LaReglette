<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    /**
     * @var Collection<int, Boulder>
     */
    #[ORM\OneToMany(targetEntity: Boulder::class, mappedBy: 'openedBy')]
    private Collection $boulders;

    /**
     * @var Collection<int, Ascent>
     */
    #[ORM\OneToMany(targetEntity: Ascent::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $ascents;

    public function __construct()
    {
        $this->boulders = new ArrayCollection();
        $this->ascents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

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
            $boulder->setOpenedBy($this);
        }

        return $this;
    }

    public function removeBoulder(Boulder $boulder): static
    {
        if ($this->boulders->removeElement($boulder)) {
            // set the owning side to null (unless already changed)
            if ($boulder->getOpenedBy() === $this) {
                $boulder->setOpenedBy(null);
            }
        }

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
            $ascent->setUser($this);
        }

        return $this;
    }

    public function removeAscent(Ascent $ascent): static
    {
        if ($this->ascents->removeElement($ascent)) {
            // set the owning side to null (unless already changed)
            if ($ascent->getUser() === $this) {
                $ascent->setUser(null);
            }
        }

        return $this;
    }
}
