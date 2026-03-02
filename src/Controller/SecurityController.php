<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        Request $request,
        AuthorizationCheckerInterface $authChecker,
        TokenStorageInterface $tokenStorage
    ): Response
    {

        // Si l'utilisateur est en cours de 2FA et retourne sur /login, on le déconnecte
        if (null !== $this->getUser() && $authChecker->isGranted('IS_AUTHENTICATED_2FA_IN_PROGRESS')) {
            $tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            $this->addFlash('info', 'Votre session de connexion a été réinitialisée. Veuillez vous reconnecter.');
        }
        // Si l'utilisateur est déjà connecté et n'est pas en cours de 2FA, on le redirige vers le bon back-office
        if (null !== $this->getUser() && !$authChecker->isGranted('IS_AUTHENTICATED_2FA_IN_PROGRESS')) {
            if ($authChecker->isGranted('ROLE_SUPER_ADMIN')) {
                return $this->redirectToRoute('admin');
            }

            return $this->redirectToRoute('pro_admin');
        }


        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Logout is handled by Symfony.');
    }
}
