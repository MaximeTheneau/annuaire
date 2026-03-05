<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'company')]
#[ORM\UniqueConstraint(name: 'uniq_company_siret', columns: ['siret'])]
#[ORM\UniqueConstraint(name: 'uniq_company_name', columns: ['name'])]
#[ORM\UniqueConstraint(name: 'uniq_company_slug', columns: ['slug'])]
class Company extends BaseEntity
{
    #[ORM\Column(type: 'bigint')]
    private string $siret = '';

    #[ORM\Column(length: 30)]
    private string $phone = '';

    #[Assert\NotBlank(message: 'Le site web est obligatoire.')]
    #[ORM\Column(length: 255)]
    private string $website = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[Assert\Count(min: 1, minMessage: 'Sélectionnez au moins une catégorie.')]
    #[ORM\ManyToMany(targetEntity: Category::class)]
    #[ORM\JoinTable(name: 'company_category')]
    private Collection $categories;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Address $address = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?bool $approved = null;

    #[Assert\Count(min: 1, minMessage: "Sélectionnez au moins une zone d'intervention.")]
    #[ORM\ManyToMany(targetEntity: Department::class)]
    #[ORM\JoinTable(name: 'company_intervention_department')]
    private Collection $interventionDepartments;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $img = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $srcset = null;

    #[ORM\Column(nullable: true)]
    private ?int $imgWidth = null;

    #[ORM\Column(nullable: true)]
    private ?int $imgHeight = null;

    // ── Transient properties (not persisted by Doctrine) ──────────────────────
    // Used only by the admin CRUD form to collect Google Places data before
    // resolving to Address / City / Department entities in the controller.

    private ?File $imageFile = null;
    private bool $deleteImage = false;

    #[Assert\NotBlank(message: "Sélectionnez une adresse dans la liste de suggestions.")]
    private ?string $placeId = null;
    private ?string $formattedAddress = null;
    #[Assert\NotBlank(message: "La ville est obligatoire.")]
    private ?string $inputCityName = null;
    #[Assert\NotBlank(message: 'Le code postal est obligatoire.')]
    private ?string $inputPostalCode = null;
    private ?string $inputDepartmentName = null;
    private ?string $inputDepartmentCode = null;
    private ?string $inputLat = null;
    private ?string $inputLng = null;

    public function __construct()
    {
        parent::__construct();
        $this->createdAt = new \DateTimeImmutable();
        $this->categories = new ArrayCollection();
        $this->interventionDepartments = new ArrayCollection();
    }

    public function __serialize(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'siret'       => $this->siret,
            'phone'       => $this->phone,
            'website'     => $this->website,
            'description' => $this->description,
            'img'         => $this->img,
            'srcset'      => $this->srcset,
            'imgWidth'    => $this->imgWidth,
            'imgHeight'   => $this->imgHeight,
            'approved'    => $this->approved,
            'createdAt'   => $this->createdAt,
        ];
        // imageFile (UploadedFile) et les champs transients exclus intentionnellement
    }

    public function __unserialize(array $data): void
    {
        $this->id          = $data['id'];
        $this->name        = $data['name'];
        $this->slug        = $data['slug'];
        $this->siret       = $data['siret'];
        $this->phone       = $data['phone'];
        $this->website     = $data['website'];
        $this->description = $data['description'];
        $this->img         = $data['img'];
        $this->srcset      = $data['srcset'];
        $this->imgWidth    = $data['imgWidth'];
        $this->imgHeight   = $data['imgHeight'];
        $this->approved    = $data['approved'];
        $this->createdAt   = $data['createdAt'];
        $this->imageFile               = null;
        $this->deleteImage             = false;
        $this->categories              = new \Doctrine\Common\Collections\ArrayCollection();
        $this->interventionDepartments = new \Doctrine\Common\Collections\ArrayCollection();
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

    public function getWebsite(): string
    {
        return $this->website;
    }

    public function setWebsite(string $website): self
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

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function getPostalCode(): ?string
    {
        return $this->address?->getPostalCode();
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

    public function isApproved(): ?bool
    {
        return $this->approved;
    }

    public function setApproved(?bool $approved): static
    {
        $this->approved = $approved;

        return $this;
    }

    public function getInterventionArea(): ?InterventionArea
    {
        return $this->address?->getCity()?->getDepartment()?->getInterventionArea();
    }

    /**
     * @return Collection<int, Department>
     */
    public function getInterventionDepartments(): Collection
    {
        return $this->interventionDepartments;
    }

    public function addInterventionDepartment(Department $department): static
    {
        if (!$this->interventionDepartments->contains($department)) {
            $this->interventionDepartments->add($department);
        }

        return $this;
    }

    public function removeInterventionDepartment(Department $department): static
    {
        $this->interventionDepartments->removeElement($department);

        return $this;
    }

    public function initInterventionDepartmentFromCity(): void
    {
        $department = $this->address?->getCity()?->getDepartment();
        if ($department !== null && $this->interventionDepartments->isEmpty()) {
            $this->addInterventionDepartment($department);
        }
    }

    public function getImg(): ?string
    {
        return $this->img;
    }

    public function setImg(?string $img): self
    {
        $this->img = $img;

        return $this;
    }

    public function getSrcset(): ?string
    {
        return $this->srcset;
    }

    public function setSrcset(?string $srcset): static
    {
        $this->srcset = $srcset;

        return $this;
    }

      public function getImgWidth(): ?int
    {
        return $this->imgWidth;
    }

    public function setImgWidth(?int $imgWidth): static
    {
        $this->imgWidth = $imgWidth;

        return $this;
    }

    public function getImgHeight(): ?int
    {
        return $this->imgHeight;
    }

    public function setImgHeight(?int $imgHeight): static
    {
        $this->imgHeight = $imgHeight;

        return $this;
    }

    // ── Transient getters / setters ───────────────────────────────────────────

    public function getImageFile(): ?File { return $this->imageFile; }
    public function setImageFile(?File $file): static { $this->imageFile = $file; return $this; }

    public function isDeleteImage(): bool { return $this->deleteImage; }
    public function setDeleteImage(bool $v): static { $this->deleteImage = $v; return $this; }

    public function getPlaceId(): ?string { return $this->placeId; }
    public function setPlaceId(?string $v): static { $this->placeId = $v; return $this; }

    public function getFormattedAddress(): ?string { return $this->formattedAddress; }
    public function setFormattedAddress(?string $v): static { $this->formattedAddress = $v; return $this; }

    public function getInputCityName(): ?string { return $this->inputCityName; }
    public function setInputCityName(?string $v): static { $this->inputCityName = $v; return $this; }

    public function getInputPostalCode(): ?string { return $this->inputPostalCode; }
    public function setInputPostalCode(?string $v): static { $this->inputPostalCode = $v; return $this; }

    public function getInputDepartmentName(): ?string { return $this->inputDepartmentName; }
    public function setInputDepartmentName(?string $v): static { $this->inputDepartmentName = $v; return $this; }

    public function getInputDepartmentCode(): ?string { return $this->inputDepartmentCode; }
    public function setInputDepartmentCode(?string $v): static { $this->inputDepartmentCode = $v; return $this; }

    public function getInputLat(): ?string { return $this->inputLat; }
    public function setInputLat(?string $v): static { $this->inputLat = $v; return $this; }

    public function getInputLng(): ?string { return $this->inputLng; }
    public function setInputLng(?string $v): static { $this->inputLng = $v; return $this; }

    /**
     * Pre-fills transient fields from the current Address entity.
     * Called in CompanyCrudController::edit() so the form shows existing data.
     */
    public function populateAddressInputs(): void
    {
        if ($this->address === null) {
            return;
        }

        $this->formattedAddress     = $this->address->getFormatted();
        $this->placeId              = $this->address->getGooglePlaceId();
        $this->inputLat             = $this->address->getLat();
        $this->inputLng             = $this->address->getLng();
        $this->inputPostalCode      = $this->address->getPostalCode();
        $this->inputCityName        = $this->address->getCity()?->getName();
        $this->inputDepartmentName  = $this->address->getCity()?->getDepartment()?->getName();
        $this->inputDepartmentCode  = $this->address->getCity()?->getDepartment()?->getCode();
    }
}
