<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProController extends AbstractController
{
    #[Route('/pro/entreprise', name: 'app_pro_company')]
    public function company(CompanyRepository $companyRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('pro/company.html.twig', [
            'companies' => $companyRepository->findBy(['owner' => $user]),
        ]);
    }
}
