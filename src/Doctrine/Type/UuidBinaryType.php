<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Stores UUIDs as binary(16) in MySQL, but exposes them as 32-char hex strings in PHP.
 * This keeps URLs and EasyAdmin entity IDs URL-safe without any schema change.
 */
class UuidBinaryType extends Type
{
    public const NAME = 'uuid_binary';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'BINARY(16)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        // Already a hex string (32 chars) — already converted upstream
        if (is_string($value) && strlen($value) === 32 && ctype_xdigit($value)) {
            return $value;
        }

        return bin2hex((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        // Hex string → binary bytes for storage
        if (is_string($value) && strlen($value) === 32 && ctype_xdigit($value)) {
            return hex2bin($value);
        }

        // Already binary (16 bytes) — pass through
        return (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
