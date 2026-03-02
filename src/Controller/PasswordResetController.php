<?php

namespace App\Controller;

use App\Entity\SecurityToken;
use App\Entity\User;
use App\Mailer\AppMailer;
use App\Service\SecurityTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class PasswordResetController extends AbstractController
{
    #[Route('/mot-de-passe/oublie', name: 'app_reset_password_request')]
    public function request(
        Request $request,
        EntityManagerInterface $entityManager,
        SecurityTokenManager $tokenManager,
        AppMailer $mailer,
        ParameterBagInterface $params
    ): Response {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => mb_strtolower($email)]);

            if ($user instanceof User) {
                $token = $tokenManager->createToken(
                    $user,
                    SecurityToken::TYPE_RESET_PASSWORD,
                    new \DateTimeImmutable(sprintf('+%d minutes', (int) $params->get('app.reset_password_ttl_minutes')))
                );
                $mailer->sendPasswordReset($user, $token);
            }

            $this->addFlash('success', 'Si un compte existe, un email vous a été envoyé.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_request.html.twig', [
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/mot-de-passe/reinitialiser/{token}', name: 'app_reset_password')]
    public function reset(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $securityToken = $entityManager->getRepository(SecurityToken::class)
            ->findOneBy(['token' => $token, 'type' => SecurityToken::TYPE_RESET_PASSWORD]);

        if (!$securityToken || $securityToken->isExpired() || $securityToken->isUsed()) {
            $this->addFlash('error', 'Le lien de réinitialisation est invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createFormBuilder()
            ->add('password', PasswordType::class, [
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
            if ($data['password'] !== $data['confirm_password']) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            } else {
                $user = $securityToken->getUser();
                $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
                $securityToken->setUsedAt(new \DateTimeImmutable());

                $entityManager->persist($user);
                $entityManager->persist($securityToken);
                $entityManager->flush();

                $this->addFlash('success', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        } elseif ($form->isSubmitted()) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form->createView(),
        ], new Response(null, $status));
    }
}
