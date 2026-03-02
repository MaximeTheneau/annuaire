<?php

namespace App\Entity;

use App\Repository\CityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CityRepository::class)]
#[ORM\Table(name: 'city')]
#[ORM\UniqueConstraint(name: 'uniq_city_slug_department', columns: ['slug', 'department_id'])]
class City extends BaseEntity
{
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $inseeCode = null;

    #[ORM\ManyToOne(targetEntity: Department::class, inversedBy: 'city')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Department $department = null;

    public function getInseeCode(): ?string
    {
        return $this->inseeCode;
    }

    public function setInseeCode(?string $inseeCode): self
    {
        $this->inseeCode = $inseeCode;

        return $this;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
