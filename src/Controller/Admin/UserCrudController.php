<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('idHex', 'ID')->onlyOnIndex();
        yield EmailField::new('email');
        yield TextField::new('firstName', 'Prénom');
        yield TextField::new('lastName', 'Nom');
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            yield TextField::new('company.name', 'Entreprise')->onlyOnIndex();
            yield ArrayField::new('roles');
            yield BooleanField::new('isVerified', 'Vérifié');
            yield TextField::new('lastLoginIp')->onlyOnIndex();
        }
    }
}
