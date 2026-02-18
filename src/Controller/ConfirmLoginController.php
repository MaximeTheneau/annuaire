<?php

namespace App\Controller;

use App\Entity\SecurityToken;
use App\Entity\User;
use App\Mailer\AppMailer;
use App\Service\SecurityTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConfirmLoginController extends AbstractController
{
    #[Route('/confirmation-connexion', name: 'app_confirm_login')]
    public function info(): Response
    {
        return $this->render('security/confirm_login.html.twig');
    }

    #[Route('/confirmation-connexion/{token}', name: 'app_confirm_login_verify')]
    public function verify(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        SecurityTokenManager $tokenManager,
        AppMailer $mailer,
        ParameterBagInterface $params
    ): Response {
        $securityToken = $entityManager->getRepository(SecurityToken::class)
            ->findOneBy(['token' => $token, 'type' => SecurityToken::TYPE_CONFIRM_LOGIN]);

        if (!$securityToken || $securityToken->isExpired() || $securityToken->isUsed()) {
            $this->addFlash('error', 'Le lien de confirmation est invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        $securityToken->setUsedAt(new \DateTimeImmutable());
        $user = $securityToken->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Compte introuvable.');
            return $this->redirectToRoute('app_login');
        }

        $session = $request->getSession();
        $session->remove('login_confirm_pending');
        $session->remove('login_confirm_user_email');

        if ($user->isTwoFactorEnabled()) {
            $code = $tokenManager->createNumericCode();
            $twoFactorToken = $tokenManager->createToken(
                $user,
                SecurityToken::TYPE_TWO_FACTOR,
                new \DateTimeImmutable(sprintf('+%d minutes', (int) $params->get('app.two_factor_ttl_minutes'))),
                ['code' => $code],
                $code
            );
            $mailer->sendTwoFactorCode($user, $twoFactorToken);

            $session->set('two_factor_pending', true);
            $session->set('two_factor_user_id', $user->getId());

            $entityManager->persist($securityToken);
            $entityManager->flush();

            return $this->redirectToRoute('app_two_factor');
        }

        if (!$user->isTwoFactorEnabled()) {
            $user->setTwoFactorEnabled(true);
        }

        $user->setLastLoginAt(new \DateTimeImmutable());
        $user->setLastLoginIp((string) $request->getClientIp());

        $entityManager->persist($user);
        $entityManager->persist($securityToken);
        $entityManager->flush();

        return $this->redirectToRoute('app_pro_company');
    }
}
