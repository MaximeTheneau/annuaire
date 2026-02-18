<?php

namespace App\Controller;

use App\Entity\SecurityToken;
use App\Entity\User;
use App\Mailer\AppMailer;
use App\Service\SecurityTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TwoFactorController extends AbstractController
{
    #[Route('/2fa', name: 'app_two_factor')]
    public function verify(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response
    {
        $session = $request->getSession();
        $email = $session->get('two_factor_user_email');
        if (!$email) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createFormBuilder()
            ->add('code', TextType::class, [
                'label' => 'Code 2FA',
                'constraints' => [new NotBlank(), new Length(min: 6, max: 6)],
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $code = $form->get('code')->getData();
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user instanceof User) {
                $this->addFlash('error', 'Utilisateur introuvable.');
                return $this->redirectToRoute('app_login');
            }

            $token = $entityManager->getRepository(SecurityToken::class)->findOneBy([
                'token' => $code,
                'type' => SecurityToken::TYPE_TWO_FACTOR,
                'user' => $user,
            ]);

            if (!$token || $token->isExpired() || $token->isUsed()) {
                $this->addFlash('error', 'Code invalide ou expiré.');
            } else {
                $token->setUsedAt(new \DateTimeImmutable());
                $user = $token->getUser();
                if (!$user->isTwoFactorEnabled()) {
                    $user->setTwoFactorEnabled(true);
                }
                $user->setLastLoginAt(new \DateTimeImmutable());
                $user->setLastLoginIp((string) $request->getClientIp());
                $entityManager->persist($user);

                $entityManager->persist($token);
                $entityManager->flush();

                $session->remove('two_factor_pending');
                $session->remove('two_factor_user_email');

                return $this->redirectToRoute('app_pro_company');
            }
        }

        return $this->render('security/two_factor.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/2fa/renvoyer', name: 'app_two_factor_resend')]
    public function resend(
        Request $request,
        EntityManagerInterface $entityManager,
        SecurityTokenManager $tokenManager,
        AppMailer $mailer,
        ParameterBagInterface $params
    ): Response {
        $session = $request->getSession();
        $email = $session->get('two_factor_user_email');
        if (!$email) {
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_login');
        }

        $lastToken = $entityManager->getRepository(SecurityToken::class)->findOneBy(
            ['user' => $user, 'type' => SecurityToken::TYPE_TWO_FACTOR],
            ['createdAt' => 'DESC']
        );
        if ($lastToken instanceof SecurityToken) {
            $cooldown = (int) $params->get('app.two_factor_cooldown_seconds');
            $retryAt = $lastToken->getCreatedAt()->modify(sprintf('+%d seconds', $cooldown));
            if ($retryAt > new \DateTimeImmutable()) {
                $this->addFlash('error', 'Veuillez patienter avant de demander un nouveau code.');
                return $this->redirectToRoute('app_two_factor');
            }
        }

        $tokens = $entityManager->getRepository(SecurityToken::class)->findBy([
            'user' => $user,
            'type' => SecurityToken::TYPE_TWO_FACTOR,
            'usedAt' => null,
        ]);
        foreach ($tokens as $token) {
            $token->setUsedAt(new \DateTimeImmutable());
            $entityManager->persist($token);
        }

        $code = $tokenManager->createNumericCode();
        $newToken = $tokenManager->createToken(
            $user,
            SecurityToken::TYPE_TWO_FACTOR,
            new \DateTimeImmutable(sprintf('+%d minutes', (int) $params->get('app.two_factor_ttl_minutes'))),
            ['code' => $code],
            $code
        );
        $mailer->sendTwoFactorCode($user, $newToken);

        $entityManager->flush();

        $this->addFlash('success', 'Un nouveau code a été envoyé.');

        return $this->redirectToRoute('app_two_factor');
    }
}
