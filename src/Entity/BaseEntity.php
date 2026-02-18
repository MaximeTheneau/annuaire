<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class BaseEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'binary', length: 16, unique: true)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    protected ?string $id = null;

    #[ORM\Column(length: 180, nullable: true)]
    protected ?string $name = null;

    #[ORM\Column(length: 200, nullable: true)]
    protected ?string $slug = null;

    public function __construct()
    {
        $this->id = \App\Util\Uuid::v4Bytes();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getIdHex(): string
    {
        return $this->id ? \App\Util\Uuid::toHex($this->id) : '';
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

        $normalized = $value;
        if (function_exists('transliterator_transliterate')) {
            $normalized = transliterator_transliterate('Any-Latin; Latin-ASCII;', $value);
        }

        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-');

        return $normalized;
    }
}
