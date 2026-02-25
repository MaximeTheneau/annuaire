<?php

namespace App\Security;

use App\Entity\User as AppUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('email_not_verified', ['email' => $user->getEmail()]);
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof AppUser) {
            return;
        }
    }
}
