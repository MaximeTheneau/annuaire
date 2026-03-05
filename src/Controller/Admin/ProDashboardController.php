<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\CompanyRepository;
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
        private readonly CompanyRepository $companyRepository,
    ) {}

    #[Route('/pro/admin', name: 'pro_admin')]
    public function index(): Response
    {
        // Le super admin n'a rien à faire ici
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->redirectToRoute('admin');
        }

        $user = $this->getUser();

        $hasCompanies = $user instanceof User
            && count($this->companyRepository->findBy(['owner' => $user])) > 0;

        $url = $this->adminUrlGenerator
            ->setController(CompanyCrudController::class)
            ->setAction($hasCompanies ? Action::INDEX : Action::NEW)
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
                ->setController(ProAccountCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($user->getId())
                ->generateUrl();

            $userMenu->addMenuItems([
                MenuItem::linkToUrl('Mon compte', 'fa fa-user', $editAccountUrl),
                MenuItem::linkToRoute('Changer le mot de passe', 'fa fa-key', 'app_change_password'),
            ]);
        }

        return $userMenu;
    }

    public function configureMenuItems(): iterable
    {
        $indexUrl = $this->adminUrlGenerator
            ->setController(CompanyCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        $newUrl = $this->adminUrlGenerator
            ->setController(CompanyCrudController::class)
            ->setAction(Action::NEW)
            ->generateUrl();

        yield MenuItem::linkToUrl('Mes entreprises', 'fa fa-building', $indexUrl);
        yield MenuItem::linkToUrl('Ajouter une entreprise', 'fa fa-plus', $newUrl);
    }
}
