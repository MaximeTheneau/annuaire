<?php

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\Department;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class DepartmentCityFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $fixturesDir = $_ENV['FIXTURES_DIR'] ?? 'fixtures';
        $path = dirname(__DIR__, 2) . '/' . $fixturesDir . '/departments_prefectures.csv';
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return;
        }

        $isHeader = true;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if ($isHeader) {
                $isHeader = false;
                continue;
            }

            [$code, $departmentName, $cityName, $insee] = array_map('trim', $row + ['', '', '', '']);
            if ($code === '' || $departmentName === '' || $cityName === '') {
                continue;
            }

            $department = $manager->getRepository(Department::class)->findOneBy(['code' => $code]);
            if (!$department instanceof Department) {
                $department = (new Department())
                    ->setCode($code)
                    ->setName($departmentName);
                $manager->persist($department);
            }

            $city = $manager->getRepository(City::class)->findOneBy([
                'name' => $cityName,
                'department' => $department,
            ]);
            if (!$city instanceof City) {
                $city = (new City())
                    ->setName($cityName)
                    ->setDepartment($department)
                    ->setInseeCode($insee ?: null);
                $manager->persist($city);
            }
        }

        fclose($handle);
        $manager->flush();
    }
}
