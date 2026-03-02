<?php

namespace App\Controller;

use App\Entity\City;
use App\Entity\Category;
use App\Entity\Department;
use App\Repository\CategoryRepository;
use App\Repository\CityRepository;
use App\Repository\CompanyRepository;
use App\Repository\DepartmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AnnuaireController extends AbstractController
{
    #[Route('/annuaire-pro', name: 'app_annuaire_index')]
    public function index(
        Request $request,
        DepartmentRepository $departmentRepository,
        CategoryRepository $categoryRepository,
        CompanyRepository $companyRepository,
        CityRepository $cityRepository
    ): Response {
        $query = trim((string) $request->query->get('q', ''));
        $companies = [];
        $cities = [];
        if ($query !== '') {
            $companies = $companyRepository->searchByQuery($query);
            $cities = $cityRepository->searchByName($query);
        }

        return $this->render('annuaire/index.html.twig', [
            'departments' => $departmentRepository->findBy([], ['code' => 'ASC']),
            'categories' => $categoryRepository->findBy([], ['name' => 'ASC']),
            'query' => $query,
            'companies' => $companies,
            'cities' => $cities,
        ]);
    }

    #[Route('/annuaire-pro/categorie/{categorySlug}', name: 'app_annuaire_category')]
    public function category(
        string $categorySlug,
        CategoryRepository $categoryRepository,
        CompanyRepository $companyRepository,
        DepartmentRepository $departmentRepository
    ): Response {
        $category = $categoryRepository->findOneBy(['slug' => $categorySlug]);
        if (!$category instanceof Category) {
            throw $this->createNotFoundException();
        }

        $companies = $companyRepository->findByCategory($category);

        return $this->render('annuaire/category.html.twig', [
            'category' => $category,
            'companies' => $companies,
            'departments' => $departmentRepository->findBy([], ['code' => 'ASC']),
            'department' => null,
            'cities' => [],
            'city' => null,
        ]);
    }

    #[Route('/annuaire-pro/categorie/{categorySlug}/{departmentSlug}', name: 'app_annuaire_category_department')]
    public function categoryDepartment(
        string $categorySlug,
        string $departmentSlug,
        CategoryRepository $categoryRepository,
        DepartmentRepository $departmentRepository,
        CityRepository $cityRepository,
        CompanyRepository $companyRepository
    ): Response {
        $category = $categoryRepository->findOneBy(['slug' => $categorySlug]);
        if (!$category instanceof Category) {
            throw $this->createNotFoundException();
        }

        $department = $departmentRepository->findOneBy(['slug' => $departmentSlug]);
        if (!$department instanceof Department) {
            throw $this->createNotFoundException();
        }

        $companies = $companyRepository->findByCategoryAndDepartment($category, $department);
        $cities = $cityRepository->findBy(['department' => $department], ['name' => 'ASC']);

        return $this->render('annuaire/category.html.twig', [
            'category' => $category,
            'companies' => $companies,
            'departments' => $departmentRepository->findBy([], ['code' => 'ASC']),
            'department' => $department,
            'cities' => $cities,
            'city' => null,
        ]);
    }

    #[Route('/annuaire-pro/categorie/{categorySlug}/{departmentSlug}/{citySlug}', name: 'app_annuaire_category_city')]
    public function categoryCity(
        string $categorySlug,
        string $departmentSlug,
        string $citySlug,
        CategoryRepository $categoryRepository,
        DepartmentRepository $departmentRepository,
        CityRepository $cityRepository,
        CompanyRepository $companyRepository
    ): Response {
        $category = $categoryRepository->findOneBy(['slug' => $categorySlug]);
        if (!$category instanceof Category) {
            throw $this->createNotFoundException();
        }

        $department = $departmentRepository->findOneBy(['slug' => $departmentSlug]);
        if (!$department instanceof Department) {
            throw $this->createNotFoundException();
        }

        $city = $cityRepository->findOneBy(['slug' => $citySlug, 'department' => $department]);
        if (!$city instanceof City) {
            throw $this->createNotFoundException();
        }

        $companies = $companyRepository->findByCategoryAndCity($category, $city);
        $cities = $cityRepository->findBy(['department' => $department], ['name' => 'ASC']);

        return $this->render('annuaire/category.html.twig', [
            'category' => $category,
            'companies' => $companies,
            'departments' => $departmentRepository->findBy([], ['code' => 'ASC']),
            'department' => $department,
            'cities' => $cities,
            'city' => $city,
        ]);
    }

    #[Route('/annuaire-pro/{departmentSlug}', name: 'app_annuaire_department')]
    public function department(
        string $departmentSlug,
        DepartmentRepository $departmentRepository,
        CityRepository $cityRepository,
        CompanyRepository $companyRepository
    ): Response {
        $department = $departmentRepository->findOneBy(['slug' => $departmentSlug]);
        if (!$department instanceof Department) {
            throw $this->createNotFoundException();
        }

        $cities = $cityRepository->findBy(['department' => $department], ['name' => 'ASC']);
        $counts = $companyRepository->countByCity($department);

        return $this->render('annuaire/department.html.twig', [
            'department' => $department,
            'cities' => $cities,
            'counts' => $counts,
        ]);
    }

    #[Route('/annuaire-pro/{departmentSlug}/{citySlug}', name: 'app_annuaire_city')]
    public function city(
        string $departmentSlug,
        string $citySlug,
        DepartmentRepository $departmentRepository,
        CityRepository $cityRepository,
        CompanyRepository $companyRepository
    ): Response {
        $department = $departmentRepository->findOneBy(['slug' => $departmentSlug]);
        if (!$department instanceof Department) {
            throw $this->createNotFoundException();
        }

        $city = $cityRepository->findOneBy(['slug' => $citySlug, 'department' => $department]);
        if (!$city instanceof City) {
            throw $this->createNotFoundException();
        }

        $companies = $companyRepository->findByCity($city);
        if (count($companies) === 0) {
            return $this->render('annuaire/empty.html.twig', [
                'department' => $department,
                'city' => $city,
            ]);
        }

        return $this->render('annuaire/city.html.twig', [
            'department' => $department,
            'city' => $city,
            'companies' => $companies,
        ]);
    }

    #[Route('/annuaire-pro/{departmentSlug}/{citySlug}/{companySlug}', name: 'app_annuaire_company')]
    public function company(
        string $departmentSlug,
        string $citySlug,
        string $companySlug,
        DepartmentRepository $departmentRepository,
        CityRepository $cityRepository,
        CompanyRepository $companyRepository
    ): Response {
        $department = $departmentRepository->findOneBy(['slug' => $departmentSlug]);
        if (!$department instanceof Department) {
            throw $this->createNotFoundException();
        }

        $city = $cityRepository->findOneBy(['slug' => $citySlug, 'department' => $department]);
        if (!$city instanceof City) {
            throw $this->createNotFoundException();
        }

        $company = $companyRepository->findOneBySlugCityDepartment($companySlug, $city);
        if (!$company) {
            throw $this->createNotFoundException();
        }

        return $this->render('annuaire/company.html.twig', [
            'department' => $department,
            'city' => $city,
            'company' => $company,
        ]);
    }
}
