<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Entity\City;
use App\Entity\Company;
use App\Entity\Department;
use App\Repository\CategoryRepository;
use App\Repository\CityRepository;
use App\Repository\CompanyRepository;
use App\Repository\DepartmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/annuaire')]
class AnnuaireApiController extends AbstractController
{
    #[Route('', name: 'api_annuaire_index', methods: ['GET'])]
    public function index(
        DepartmentRepository $departmentRepository,
        CategoryRepository $categoryRepository,
    ): JsonResponse {
        $departments = $departmentRepository->findBy([], ['code' => 'ASC']);
        $categories = $categoryRepository->findBy([], ['name' => 'ASC']);

        return $this->json([
            'departments' => array_map(fn (Department $d) => $this->serializeDepartment($d), $departments),
            'categories' => array_map(fn (Category $c) => $this->serializeCategory($c), $categories),
        ]);
    }

    #[Route('/search', name: 'api_annuaire_search', methods: ['GET'])]
    public function search(
        Request $request,
        CompanyRepository $companyRepository,
        CityRepository $cityRepository,
    ): JsonResponse {
        $query = trim((string) $request->query->get('q', ''));
        $companies = [];
        $cities = [];

        if ($query !== '') {
            $companies = $companyRepository->searchByQuery($query);
            $cities = $cityRepository->searchByName($query);
        }

        $response = $this->json([
            'companies' => array_map(fn (Company $c) => $this->serializeCompany($c), $companies),
            'cities' => array_map(fn (City $c) => $this->serializeCity($c), $cities),
        ]);

        return $response;
    }

    #[Route('/categorie/{categorySlug}', name: 'api_annuaire_category', methods: ['GET'])]
    public function category(
        string $categorySlug,
        CategoryRepository $categoryRepository,
        CompanyRepository $companyRepository,
    ): JsonResponse {
        $category = $categoryRepository->findOneBy(['slug' => $categorySlug]);
        if (!$category instanceof Category) {
            throw $this->createNotFoundException();
        }

        $companies = $companyRepository->findByCategory($category);

        return $this->json([
            'category' => $this->serializeCategory($category),
            'companies' => array_map(fn (Company $c) => $this->serializeCompany($c), $companies),
        ]);
    }

    #[Route('/categorie/{categorySlug}/{departmentSlug}', name: 'api_annuaire_category_department', methods: ['GET'])]
    public function categoryDepartment(
        string $categorySlug,
        string $departmentSlug,
        CategoryRepository $categoryRepository,
        DepartmentRepository $departmentRepository,
        CityRepository $cityRepository,
        CompanyRepository $companyRepository,
    ): JsonResponse {
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

        return $this->json([
            'category' => $this->serializeCategory($category),
            'department' => $this->serializeDepartment($department),
            'companies' => array_map(fn (Company $c) => $this->serializeCompany($c), $companies),
            'cities' => array_map(fn (City $c) => $this->serializeCity($c), $cities),
        ]);
    }

    #[Route('/categorie/{categorySlug}/{departmentSlug}/{citySlug}', name: 'api_annuaire_category_city', methods: ['GET'])]
    public function categoryCity(
        string $categorySlug,
        string $departmentSlug,
        string $citySlug,
        CategoryRepository $categoryRepository,
        DepartmentRepository $departmentRepository,
        CityRepository $cityRepository,
        CompanyRepository $companyRepository,
    ): JsonResponse {
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

        return $this->json([
            'category' => $this->serializeCategory($category),
            'department' => $this->serializeDepartment($department),
            'city' => $this->serializeCity($city),
            'companies' => array_map(fn (Company $c) => $this->serializeCompany($c), $companies),
        ]);
    }

    #[Route('/{departmentSlug}', name: 'api_annuaire_department', methods: ['GET'])]
    public function department(
        string $departmentSlug,
        DepartmentRepository $departmentRepository,
        CityRepository $cityRepository,
        CompanyRepository $companyRepository,
    ): JsonResponse {
        $department = $departmentRepository->findOneBy(['slug' => $departmentSlug]);
        if (!$department instanceof Department) {
            throw $this->createNotFoundException();
        }

        $cities = $cityRepository->findBy(['department' => $department], ['name' => 'ASC']);
        $rawCounts = $companyRepository->countByCity($department);

        $counts = [];
        foreach ($cities as $city) {
            $counts[$city->getSlug()] = $rawCounts[$city->getIdHex()] ?? 0;
        }

        return $this->json([
            'department' => $this->serializeDepartment($department),
            'cities' => array_map(fn (City $c) => $this->serializeCity($c), $cities),
            'counts' => $counts,
        ]);
    }

    #[Route('/{departmentSlug}/{citySlug}', name: 'api_annuaire_city', methods: ['GET'])]
    public function city(
        string $departmentSlug,
        string $citySlug,
        DepartmentRepository $departmentRepository,
        CityRepository $cityRepository,
        CompanyRepository $companyRepository,
    ): JsonResponse {
        $department = $departmentRepository->findOneBy(['slug' => $departmentSlug]);
        if (!$department instanceof Department) {
            throw $this->createNotFoundException();
        }

        $city = $cityRepository->findOneBy(['slug' => $citySlug, 'department' => $department]);
        if (!$city instanceof City) {
            throw $this->createNotFoundException();
        }

        $companies = $companyRepository->findByCity($city);

        return $this->json([
            'department' => $this->serializeDepartment($department),
            'city' => $this->serializeCity($city),
            'companies' => array_map(fn (Company $c) => $this->serializeCompany($c), $companies),
        ]);
    }

    #[Route('/{departmentSlug}/{citySlug}/{companySlug}', name: 'api_annuaire_company', methods: ['GET'])]
    public function company(
        string $departmentSlug,
        string $citySlug,
        string $companySlug,
        DepartmentRepository $departmentRepository,
        CityRepository $cityRepository,
        CompanyRepository $companyRepository,
    ): JsonResponse {
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

        return $this->json($this->serializeCompany($company));
    }

    private function serializeDepartment(Department $d): array
    {
        return [
            'slug' => $d->getSlug(),
            'name' => $d->getName(),
            'code' => $d->getCode(),
        ];
    }

    private function serializeCategory(Category $c): array
    {
        return [
            'slug' => $c->getSlug(),
            'name' => $c->getName(),
        ];
    }

    private function serializeCity(City $city): array
    {
        return [
            'slug' => $city->getSlug(),
            'name' => $city->getName(),
            'inseeCode' => $city->getInseeCode(),
            'department' => $this->serializeDepartment($city->getDepartment()),
        ];
    }

    private function serializeCompany(Company $company): array
    {
        $address = $company->getAddress();
        $city = $address?->getCity();
        $department = $city?->getDepartment();
        return [
            'slug' => $company->getSlug(),
            'name' => $company->getName(),
            'shortDescription' => $company->getShortDescription(),
            'phone' => $company->getPhone(),
            'img' => $company->getImg(),
            'srcset' => $company->getSrcset(),
            'altImg' => 'Logo de ' . $company->getName(),
            'imgWidth' => $company->getImgWidth(),
            'imgHeight' => $company->getImgHeight(),
            'website' => $company->getWebsite(),
            'description' => $company->getDescription(),
            'siret' => $company->getSiret(),
            'categories' => array_values($company->getCategories()->map(fn (Category $c) => $this->serializeCategory($c))->toArray()),
            'address' => $address ? [
                'formatted' => $address->getFormatted(),
                'lat' => $address->getLat(),
                'lng' => $address->getLng(),
                'postalCode' => $address->getPostalCode(),
                'city' => $city ? [
                    'slug' => $city->getSlug(),
                    'name' => $city->getName(),
                    'inseeCode' => $city->getInseeCode(),
                    'department' => $department ? $this->serializeDepartment($department) : null,
                ] : null,
            ] : null,
            'interventionDept' => $company->getinterventionDepartments()->map(fn (Department $d) => $this->serializeDepartment($d))->toArray(),
        ];
    }
}
