<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Mailer\AppMailer;
use App\Service\AddressResolverService;
use App\Service\ImageOptimizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class CompanyCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AppMailer $mailer,
        private readonly AddressResolverService $addressResolver,
        private readonly ImageOptimizer $imageOptimizer,
        private readonly Security $security,
        private ParameterBagInterface $params,
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
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->overrideTemplate('crud/new', 'admin/company/new.html.twig')
            ->overrideTemplate('crud/edit', 'admin/company/edit.html.twig');
    }

    public function configureAssets(Assets $assets): Assets
    {
        // Include the app entrypoint so Stimulus (places_controller.js) is loaded in EasyAdmin.
        return $assets->addAssetMapperEntry('app');
    }

    public function configureFields(string $pageName): iterable
    {
        // ── Always visible ────────────────────────────────────────────────────
        yield TextField::new('name', 'Nom');

        // ── Image — affichage index/détail ────────────────────────────────────
        yield ImageField::new('img', 'Logo')
            ->hideOnForm();

        // ── Image — formulaire (upload + suppression) ─────────────────────────
        yield Field::new('imageFile', 'Nouveau logo (jpg, png, avif, webp — max 100×100)')
            ->setFormType(FileType::class)
            ->onlyOnForms()
            ->setFormTypeOptions([
                'required' => false,
                'mapped'   => true,
                'attr'     => ['accept' => 'image/jpeg,image/png,image/avif,image/webp'],
            ]);

        yield Field::new('deleteImage', 'Supprimer le logo actuel')
            ->onlyOnForms()
            ->setFormType(CheckboxType::class)
            ->setFormTypeOptions(['required' => false, 'mapped' => true])
            ->hideWhenCreating();

        yield TextField::new('siret', 'SIRET');
        yield AssociationField::new('owner', 'Email')->onlyOnIndex();
        yield AssociationField::new('categories', 'Catégories')
            ->autocomplete()
            ->setCrudController(CategoryCrudController::class);
        yield AssociationField::new('interventionDepartments', 'Zones d\'intervention')
            ->autocomplete()
            ->setCrudController(DepartmentCrudController::class);

        // ── Address — index / detail only (read-only display) ─────────────────
        yield AssociationField::new('address', 'Adresse')->onlyOnIndex();
        yield TextField::new('postalCode', 'Code postal')->hideOnForm();

        // ── Address — form only (Google Places inputs) ────────────────────────
        yield TextField::new('formattedAddress', 'Adresse')
            ->onlyOnForms()
            ->setFormTypeOptions(['attr' => ['data-places-target' => 'address', 'autocomplete' => 'off']]);

        yield TextField::new('inputCityName', 'Ville')
            ->onlyOnForms()
            ->setFormTypeOptions(['attr' => ['data-places-target' => 'cityName', 'readonly' => true]]);

        yield TextField::new('inputPostalCode', 'Code postal')
            ->onlyOnForms()
            ->setFormTypeOptions(['attr' => ['data-places-target' => 'postalCode', 'readonly' => true]]);

        // Hidden fields filled by the Stimulus controller
        yield Field::new('placeId')
            ->onlyOnForms()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions(['attr' => ['data-places-target' => 'placeId']]);

        yield Field::new('inputLat')
            ->onlyOnForms()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions(['attr' => ['data-places-target' => 'lat']]);

        yield Field::new('inputLng')
            ->onlyOnForms()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions(['attr' => ['data-places-target' => 'lng']]);

        yield Field::new('inputDepartmentName')
            ->onlyOnForms()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions(['attr' => ['data-places-target' => 'departmentName']]);

        yield Field::new('inputDepartmentCode')
            ->onlyOnForms()
            ->setFormType(HiddenType::class)
            ->setFormTypeOptions(['attr' => ['data-places-target' => 'departmentCode']]);



        // ── Other fields ──────────────────────────────────────────────────────
        yield TextField::new('phone', 'Téléphone')->hideOnIndex();
        yield TextField::new('website', 'Site web')->hideOnIndex();
        yield TextareaField::new('description', 'Description')->hideOnIndex();

        if ($this->isGranted('ROLE_ADMIN')) {
            yield BooleanField::new('approved', 'Approuvé');
        }
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if (!$this->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('entity.owner = :currentUser')
                ->setParameter('currentUser', $this->security->getUser());
        }

        return $qb;
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $company = $context->getEntity()->getInstance();
        if ($company instanceof Company) {
            $company->populateAddressInputs();
            $company->initInterventionDepartmentFromCity();
        }

        return parent::edit($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Company) {
            $this->resolveAndSetAddress($entityInstance);
            $entityInstance->initInterventionDepartmentFromCity();
            $this->handleImageUpload($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Company) {
            $this->resolveAndSetAddress($entityInstance);
            $entityInstance->initInterventionDepartmentFromCity();
            $this->handleImageDeletion($entityInstance);
            $this->handleImageUpload($entityInstance);

            $originalData = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
            $wasApproved  = $originalData['approved'] ?? null;

            parent::updateEntity($entityManager, $entityInstance);

            if (!$wasApproved && $entityInstance->isApproved() === true) {
                $this->mailer->sendCompanyApproved($entityInstance);
            }

            return;
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Company && $entityInstance->getImg() !== null) {
            $this->imageOptimizer->clearImage($entityInstance->getImg());
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    private function handleImageDeletion(Company $company): void
    {
        if (!$company->isDeleteImage() || $company->getImg() === null) {
            return;
        }

        $this->imageOptimizer->clearImage($company->getImg());
        $company->setImg(null)->setSrcset(null)->setImgWidth(null)->setImgHeight(null);
    }

    private function handleImageUpload(Company $company): void
    {
        $file = $company->getImageFile();
        if (!$file instanceof UploadedFile) {
            return;
        }

        try {
            $slug = $company->getSlug() ?? uniqid('company_');
            $this->imageOptimizer->setPicture($file, $company, $slug);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
    }

    private function resolveAndSetAddress(Company $company): void
    {
        $placeId = $company->getPlaceId();
        $city    = $company->getInputCityName();

        if (!$placeId || !$city) {
            return;
        }

        $address = $this->addressResolver->resolve(
            placeId:          $placeId,
            formattedAddress: $company->getFormattedAddress() ?? '',
            cityName:         $city,
            departmentName:   $company->getInputDepartmentName() ?? '',
            departmentCode:   $company->getInputDepartmentCode() ?? '',
            postalCode:       $company->getInputPostalCode(),
            lat:              $company->getInputLat(),
            lng:              $company->getInputLng(),
        );

        if ($address !== null) {
            $company->setAddress($address);
        }
    }
}
