<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\MappedSuperclass]
abstract class BaseEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid_binary', length: 16, unique: true)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    protected ?string $id = null;

    #[ORM\Column(length: 180, nullable: true)]
    protected ?string $name = null;

    #[ORM\Column(length: 200, nullable: true)]
    protected ?string $slug = null;

    public function __construct()
    {
        // Store as hex — UuidBinaryType converts to binary(16) on DB write
        $this->id = bin2hex(\App\Util\Uuid::v4Bytes());
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Returns the UUID as a 32-char hex string.
     * With uuid_binary type, $this->id is already hex — no double-encoding needed.
     */
    public function getIdHex(): string
    {
        return $this->id ?? '';
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        $this->slug = $name ? $this->slugify($name) : null;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    protected function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $slugger = new AsciiSlugger('en_FR', ['en' => ['%' => 'percent', '€' => 'euro']]);

        $normalized = $slugger->slug($value)->lower()->toString();

        return $normalized;
    }
}
