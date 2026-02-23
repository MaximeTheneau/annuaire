<?php

namespace App\Entity;

use App\Repository\InterventionAreaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InterventionAreaRepository::class)]
class InterventionArea
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid_binary', length: 16, unique: true)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    protected ?string $id = null;

    /**
     * @var Collection<int, Department>
     */
    #[ORM\OneToMany(targetEntity: Department::class, mappedBy: 'interventionArea')]
    private Collection $department;

    public function __construct()
    {
        $this->department = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Department>
     */
    public function getDepartment(): Collection
    {
        return $this->department;
    }

    public function addDepartment(Department $department): static
    {
        if (!$this->department->contains($department)) {
            $this->department->add($department);
            $department->setInterventionArea($this);
        }

        return $this;
    }

    public function removeDepartment(Department $department): static
    {
        if ($this->department->removeElement($department)) {
            // set the owning side to null (unless already changed)
            if ($department->getInterventionArea() === $this) {
                $department->setInterventionArea(null);
            }
        }

        return $this;
    }
public function __toString(): string
{
    // Si vous n'avez pas de champ 'name', utilisez l'ID par défaut
    return $this->name ?? (string) $this->id;
}
}
