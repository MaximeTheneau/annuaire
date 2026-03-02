<?php

namespace App\Util;

final class Uuid
{
    public static function v4Bytes(): string
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return $data;
    }

    public static function toHex(string $bytes): string
    {
        return bin2hex($bytes);
    }
}
