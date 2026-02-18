<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class CompanyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Company::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('idHex', 'ID')->onlyOnIndex();
        yield TextField::new('name', 'Nom');
        yield TextField::new('siret', 'SIRET');
        yield AssociationField::new('address', 'Adresse')->onlyOnIndex();
        yield TextField::new('phone', 'Téléphone');
        yield TextField::new('website', 'Site')->hideOnIndex();
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield AssociationField::new('owner', 'Compte');
        yield AssociationField::new('category', 'Catégorie')->onlyOnIndex();
    }
}
