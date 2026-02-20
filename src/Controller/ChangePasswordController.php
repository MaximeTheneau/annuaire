<?php

namespace App\Controller;

use App\Entity\SecurityToken;
use App\Entity\User;
use App\Mailer\AppMailer;
use App\Service\SecurityTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordController extends AbstractController
{
    #[Route('/mot-de-passe/changer', name: 'app_change_password')]
    public function requestChange(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SecurityTokenManager $tokenManager,
        AppMailer $mailer,
        ParameterBagInterface $params
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createFormBuilder()
            ->add('current_password', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'constraints' => [new NotBlank()],
            ])
            ->add('new_password', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'constraints' => [new NotBlank(), new Length(min: 8)],
            ])
            ->add('confirm_password', PasswordType::class, [
                'label' => 'Confirmer le mot de passe',
                'constraints' => [new NotBlank(), new Length(min: 8)],
            ])
            ->getForm();

        $status = Response::HTTP_OK;
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            if (!$passwordHasher->isPasswordValid($user, $data['current_password'])) {
                $this->addFlash('error', 'Mot de passe actuel invalide.');
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            } elseif ($data['new_password'] !== $data['confirm_password']) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            } else {
                $newHash = $passwordHasher->hashPassword($user, $data['new_password']);

                $token = $tokenManager->createToken(
                    $user,
                    SecurityToken::TYPE_CONFIRM_PASSWORD_CHANGE,
                    new \DateTimeImmutable(sprintf('+%d minutes', (int) $params->get('app.confirm_password_change_ttl_minutes'))),
                    ['password_hash' => $newHash]
                );

                $mailer->sendPasswordChangeConfirmation($user, $token);
                $this->addFlash('success', 'Confirmez le changement via le lien envoyé par email.');

                return $this->redirectToRoute('pro_admin');
            }
        } elseif ($form->isSubmitted()) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return $this->render('security/change_password.html.twig', [
            'form' => $form->createView(),
        ], new Response(null, $status));
    }

    #[Route('/mot-de-passe/changer/confirm/{token}', name: 'app_change_password_confirm')]
    public function confirmChange(
        string $token,
        EntityManagerInterface $entityManager
    ): Response {
        $securityToken = $entityManager->getRepository(SecurityToken::class)
            ->findOneBy(['token' => $token, 'type' => SecurityToken::TYPE_CONFIRM_PASSWORD_CHANGE]);

        if (!$securityToken || $securityToken->isExpired() || $securityToken->isUsed()) {
            $this->addFlash('error', 'Le lien de confirmation est invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        $payload = $securityToken->getPayload();
        if (!isset($payload['password_hash'])) {
            $this->addFlash('error', 'Le lien de confirmation est invalide.');
            return $this->redirectToRoute('app_login');
        }

        $user = $securityToken->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Compte introuvable.');
            return $this->redirectToRoute('app_login');
        }

        $user->setPassword($payload['password_hash']);
        $securityToken->setUsedAt(new \DateTimeImmutable());

        $entityManager->persist($user);
        $entityManager->persist($securityToken);
        $entityManager->flush();

        $this->addFlash('success', 'Mot de passe mis à jour.');

        return $this->redirectToRoute('app_login');
    }
}
