<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class ProDashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {}

    #[Route('/pro/admin', name: 'pro_admin')]
    public function index(): Response
    {
        // Le super admin n'a rien à faire ici
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->redirectToRoute('admin');
        }

        $user = $this->getUser();

        if ($user instanceof User && $user->getCompany() instanceof Company) {
            $url = $this->adminUrlGenerator
                ->setController(CompanyCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($user->getCompany()->getId())
                ->generateUrl();

            return $this->redirect($url);
        }

        // PRO sans fiche → formulaire de création
        $url = $this->adminUrlGenerator
            ->setController(CompanyCrudController::class)
            ->setAction(Action::NEW)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()

            ->setTitle('Mon espace pro');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $userMenu = parent::configureUserMenu($user);

        if ($user instanceof User) {
            $editAccountUrl = $this->adminUrlGenerator
                ->setController(CompanyCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($user->getId())
                ->generateUrl();

            $userMenu->addMenuItems([
                MenuItem::linkToRoute('Changer le mot de passe', 'fa fa-key', 'app_change_password'),
            ]);
        }

        return $userMenu;
    }

    public function configureMenuItems(): iterable
    {
        $user = $this->getUser();

        if ($user instanceof User && $user->getCompany() instanceof Company) {
            $url = $this->adminUrlGenerator
                ->setController(CompanyCrudController::class)
                ->setAction(Action::INDEX)
                // ->setEntityId($user->getCompany()->getId())
                ->generateUrl();

            yield MenuItem::linkToUrl('Ma fiche', 'fa fa-building', $url);
            $urlnew = $this->adminUrlGenerator
                ->setController(CompanyCrudController::class)
                ->setAction(Action::NEW)
                ->generateUrl();

            yield MenuItem::linkToUrl('Ajouter une entreprise', 'fa fa-plus', $urlnew);
        } else {
        }
    }
}
