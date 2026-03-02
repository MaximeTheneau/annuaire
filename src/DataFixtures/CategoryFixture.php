<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $fixturesDir = $_ENV['FIXTURES_DIR'] ?? 'fixtures';
        $csv = new \SplFileObject(dirname(__DIR__, 2) . '/' . $fixturesDir . '/categories.csv');
        $csv->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_AHEAD);

        $header = null;
        foreach ($csv as $row) {
            if ($header === null) {
                $header = $row; // skip header line
                continue;
            }

            $name = trim($row[0] ?? '');
            if ($name === '') {
                continue;
            }

            $existing = $manager->getRepository(Category::class)->findOneBy(['name' => $name]);
            if ($existing instanceof Category) {
                continue;
            }

            $manager->persist((new Category())->setName($name));
        }

        $manager->flush();
    }
}
