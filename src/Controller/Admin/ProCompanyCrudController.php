<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

class ProCompanyCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly Security $security,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Company::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ma fiche')
            ->setEntityLabelInPlural('Ma fiche')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        // Statut d'approbation (toujours visible, jamais modifiable)
        $approvedField = BooleanField::new('approved', 'Statut d\'approbation')
            ->renderAsSwitch(false)
            ->setFormTypeOption('disabled', true);

        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('name', 'Nom');
            yield TextField::new('phone', 'Téléphone');
            yield AssociationField::new('category', 'Catégorie');
            yield $approvedField;
            return;
        }

        // Formulaire d'édition
        yield FormField::addPanel('Informations de l\'entreprise');
        yield TextField::new('name', 'Nom de l\'entreprise');
        yield TextField::new('siret', 'SIRET')
            ->setFormTypeOption('disabled', true)
            ->setHelp('Le numéro SIRET ne peut pas être modifié.');
        yield TextField::new('phone', 'Téléphone');
        yield TextField::new('website', 'Site web');
        yield TextareaField::new('description', 'Description');
        yield AssociationField::new('category', 'Catégorie');

        yield FormField::addPanel('Statut');
        yield $approvedField
            ->setHelp('Ce statut est géré par l\'administrateur. Votre fiche sera visible dans l\'annuaire une fois approuvée.');
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $user = $this->security->getUser();
        $qb->andWhere('entity.owner = :user')
            ->setParameter('user', $user);

        return $qb;
    }

    public function edit(AdminContext $context)
    {
        $company = $context->getEntity()->getInstance();
        if ($company instanceof Company) {
            $user = $this->security->getUser();
            if (!$user instanceof User || $company->getOwner() !== $user) {
                throw $this->createAccessDeniedException('Accès refusé.');
            }
        }

        return parent::edit($context);
    }
}
