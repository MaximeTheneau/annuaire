<?php

namespace App\Service;

use App\Entity\SecurityToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class SecurityTokenManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function createToken(
        User $user,
        string $type,
        \DateTimeImmutable $expiresAt,
        ?array $payload = null,
        ?string $token = null
    ): SecurityToken {
        $securityToken = new SecurityToken();
        $securityToken->setUser($user);
        $securityToken->setType($type);
        $securityToken->setExpiresAt($expiresAt);
        $securityToken->setPayload($payload);
        $securityToken->setToken($token ?? $this->createRandomToken());

        $this->entityManager->persist($securityToken);
        $this->entityManager->flush();

        return $securityToken;
    }

    public function createRandomToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function createNumericCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
