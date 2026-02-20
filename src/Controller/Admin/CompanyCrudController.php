<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Mailer\AppMailer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use App\Controller\Admin\CategoryCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CompanyCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AppMailer $mailer,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Company::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Professionnel')
            ->setEntityLabelInPlural('Professionnels')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield TextField::new('siret', 'SIRET');
        yield AssociationField::new('owner', 'Email')->onlyOnIndex();
        yield AssociationField::new('category', 'Catégorie')->autocomplete()->setCrudController(CategoryCrudController::class);
        yield AssociationField::new('address', 'Adresse')->onlyOnIndex();
        yield TextField::new('phone', 'Téléphone')->hideOnIndex();
        yield TextField::new('website', 'Site web')->hideOnIndex();
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield BooleanField::new('approved', 'Approuvé');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Company) {
            $originalData = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
            $wasApproved = $originalData['approved'] ?? null;

            parent::updateEntity($entityManager, $entityInstance);

            if (!$wasApproved && $entityInstance->isApproved() === true) {
                $this->mailer->sendCompanyApproved($entityInstance);
            }

            return;
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
