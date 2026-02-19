<?php

namespace App\Entity;

use App\Repository\DepartmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DepartmentRepository::class)]
#[ORM\Table(name: 'department')]
#[ORM\UniqueConstraint(name: 'uniq_department_code', columns: ['code'])]
#[ORM\UniqueConstraint(name: 'uniq_department_slug', columns: ['slug'])]
class Department extends BaseEntity
{
    #[ORM\Column(length: 5)]
    private string $code = '';

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper($code);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? $this->code;
    }
}
