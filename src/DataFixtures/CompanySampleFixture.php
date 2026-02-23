<?php

namespace App\DataFixtures;

use App\Entity\Address;
use App\Entity\Category;
use App\Entity\City;
use App\Entity\Company;
use App\Entity\Department;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CompanySampleFixture extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $path = __DIR__ . '/data/companies_sample.csv';
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return;
        }

        $isHeader = true;
        $index = 1;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if ($isHeader) {
                $isHeader = false;
                continue;
            }

            [
                $name,
                $categoryName,
                $addressLabel,
                $placeId,
                $lat,
                $lng,
                $postalCode,
                $cityName,
                $departmentCode,
                $departmentName,
                $siret,
                $phone,
                $website,
                $description,
            ] = array_map('trim', $row + array_fill(0, 14, ''));

            if ($name === '' || $addressLabel === '' || $cityName === '' || $departmentCode === '') {
                continue;
            }

            $department = $manager->getRepository(Department::class)->findOneBy(['code' => $departmentCode]);
            if (!$department instanceof Department) {
                $department = (new Department())
                    ->setCode($departmentCode)
                    ->setName($departmentName !== '' ? $departmentName : $departmentCode);
                $manager->persist($department);
            }

            $city = $manager->getRepository(City::class)->findOneBy([
                'name' => $cityName,
                'department' => $department,
            ]);
            if (!$city instanceof City) {
                $city = (new City())
                    ->setName($cityName)
                    ->setDepartment($department);
                $manager->persist($city);
            }

            $address = null;
            if ($placeId !== '') {
                $address = $manager->getRepository(Address::class)->findOneBy(['googlePlaceId' => $placeId]);
            }

            if (!$address instanceof Address) {
                $address = (new Address())
                    ->setFormatted($addressLabel)
                    ->setGooglePlaceId($placeId !== '' ? $placeId : 'PLACE_' . $index)
                    ->setLat($lat !== '' ? $lat : null)
                    ->setLng($lng !== '' ? $lng : null)
                    ->setPostalCode($postalCode !== '' ? $postalCode : '')
                    ->setCity($city);
                $manager->persist($address);
            }

            $category = null;
            if ($categoryName !== '') {
                $category = $manager->getRepository(Category::class)->findOneBy(['name' => $categoryName]);
                if (!$category instanceof Category) {
                    $category = (new Category())->setName($categoryName);
                    $manager->persist($category);
                }
            }

            $email = sprintf('pro%d@example.com', $index);
            $user = $manager->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user instanceof User) {
                $user = (new User())
                    ->setEmail($email)
                    ->setRoles(['ROLE_PRO'])
                    ->setIsVerified(true)
                    ->setTwoFactorEnabled(false);
                $user->setPassword($this->passwordHasher->hashPassword($user, 'ChangeMe123!'));
                $manager->persist($user);
            }

            $company = (new Company())
                ->setName($name)
                ->setSiret($siret !== '' ? $siret : '00000000000000')
                ->setPhone($phone !== '' ? $phone : '0000000000')
                ->setWebsite($website !== '' ? $website : '')
                ->setDescription($description !== '' ? $description : null)
                ->setOwner($user)
                ->setAddress($address);

            if ($category !== null) {
                $company->addCategory($category);
            }

            $user->setCompany($company);

            $manager->persist($company);
            $index++;
        }

        fclose($handle);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DepartmentCityFixture::class,
            CategoryFixture::class,
        ];
    }
}
