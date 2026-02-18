<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'company')]
#[ORM\UniqueConstraint(name: 'uniq_company_siret', columns: ['siret'])]
#[ORM\UniqueConstraint(name: 'uniq_company_name', columns: ['name'])]
#[ORM\UniqueConstraint(name: 'uniq_company_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_company_owner', columns: ['owner_id'])]
class Company extends BaseEntity
{
    #[ORM\Column(type: 'bigint')]
    private string $siret = '';

    #[ORM\Column(length: 30)]
    private string $phone = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\OneToOne(inversedBy: 'company', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Address $address = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        parent::__construct();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getSiret(): string
    {
        return $this->siret;
    }

    public function setSiret(string $siret): self
    {
        $this->siret = $this->normalizeSiret($siret);

        return $this;
    }

    public function getSiretFormatted(): string
    {
        $digits = $this->siret;
        if (strlen($digits) !== 14) {
            return $digits;
        }

        return sprintf(
            '%s %s %s %s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 5)
        );
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    private function normalizeSiret(string $siret): string
    {
        return preg_replace('/\s+/', '', $siret) ?? $siret;
    }
}
