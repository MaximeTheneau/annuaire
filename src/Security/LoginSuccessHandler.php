<?php

namespace App\Security;

use App\Entity\SecurityToken;
use App\Entity\User;
use App\Mailer\AppMailer;
use App\Service\SecurityTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly SecurityTokenManager $tokenManager,
        private readonly AppMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly ParameterBagInterface $params
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return new RedirectResponse($this->router->generate('app_login'));
        }

        $session = $this->requestStack->getSession();
        $ip = (string) $request->getClientIp();

        if ($this->requiresLoginConfirmation($user, $ip)) {
            $confirmToken = $this->tokenManager->createToken(
                $user,
                SecurityToken::TYPE_CONFIRM_LOGIN,
                new \DateTimeImmutable(sprintf('+%d minutes', (int) $this->params->get('app.confirm_login_ttl_minutes')))
            );

            $this->mailer->sendLoginConfirmation($user, $confirmToken);
            $session->set('login_confirm_pending', true);
            $session->set('login_confirm_user_email', $user->getEmail());

            return new RedirectResponse($this->router->generate('app_confirm_login'));
        }

        if ($user->isTwoFactorEnabled()) {
            $code = $this->tokenManager->createNumericCode();
            $twoFactorToken = $this->tokenManager->createToken(
                $user,
                SecurityToken::TYPE_TWO_FACTOR,
                new \DateTimeImmutable(sprintf('+%d minutes', (int) $this->params->get('app.two_factor_ttl_minutes'))),
                ['code' => $code],
                $code
            );

            $this->mailer->sendTwoFactorCode($user, $twoFactorToken);
            $session->set('two_factor_pending', true);
            $session->set('two_factor_user_email', $user->getEmail());

            return new RedirectResponse($this->router->generate('app_two_factor'));
        }

        $this->completeLogin($user, $ip);

        return new RedirectResponse($this->router->generate('pro_admin'));
    }

    private function requiresLoginConfirmation(User $user, string $ip): bool
    {
        $lastIp = $user->getLastLoginIp();
        if ($lastIp === null) {
            return false;
        }

        return $lastIp !== $ip;
    }

    private function completeLogin(User $user, string $ip): void
    {
        if (!$user->isTwoFactorEnabled()) {
            $user->setTwoFactorEnabled(true);
        }

        $user->setLastLoginAt(new \DateTimeImmutable());
        $user->setLastLoginIp($ip);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
