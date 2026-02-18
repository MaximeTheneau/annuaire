<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $categories = [
            'Boulangerie',
            'Plomberie',
            'Coiffure',
            'Avocat',
            'Informatique',
            'Restaurant',
            'Santé',
        ];

        foreach ($categories as $name) {
            $existing = $manager->getRepository(Category::class)->findOneBy(['name' => $name]);
            if ($existing instanceof Category) {
                continue;
            }

            $category = (new Category())->setName($name);
            $manager->persist($category);
        }

        $manager->flush();
    }
}
