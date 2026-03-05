<?php

namespace App\Controller\Admin;

use App\Entity\SecurityToken;
use App\Entity\User;
use App\Mailer\AppMailer;
use App\Service\SecurityTokenManager;
use Doctrine\ORM\EntityManagerInterface;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ProAccountCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly Security $security,
        private readonly SecurityTokenManager $tokenManager,
        private readonly AppMailer $mailer,
        private readonly ParameterBagInterface $params,
    ) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Mon compte')
            ->setEntityLabelInPlural('Mon compte');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('firstName', 'Prénom');
        yield TextField::new('lastName', 'Nom');
        yield EmailField::new('email', 'Adresse e-mail');

    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $user = $this->security->getUser();
        $qb->andWhere('entity = :user')
            ->setParameter('user', $user);

        return $qb;
    }

    private function assertIsCurrentUser(mixed $entity): void
    {
        $currentUser = $this->security->getUser();

        if (!$entity instanceof User || !$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$entity->getId() || $entity->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que votre propre compte.');
        }
    }

    public function edit(AdminContext $context)
    {
        $this->assertIsCurrentUser($context->getEntity()->getInstance());

        return parent::edit($context);
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->assertIsCurrentUser($entityInstance);

        if (!$entityInstance instanceof User) {
            parent::updateEntity($entityManager, $entityInstance);
            return;
        }

        $uow = $entityManager->getUnitOfWork();
        $uow->computeChangeSets();
        $changeSet = $uow->getEntityChangeSet($entityInstance);

        if (isset($changeSet['email'])) {
            $originalEmail = $changeSet['email'][0];
            $newEmail = $changeSet['email'][1];

            // Revert email — le changement sera appliqué après confirmation
            $entityInstance->setEmail($originalEmail);

            $ttl = (int) $this->params->get('app.confirm_email_change_ttl_minutes');
            $token = $this->tokenManager->createToken(
                $entityInstance,
                SecurityToken::TYPE_CONFIRM_EMAIL_CHANGE,
                new \DateTimeImmutable(sprintf('+%d minutes', $ttl)),
                ['new_email' => $newEmail]
            );

            $this->mailer->sendEmailChangeConfirmation($entityInstance, $newEmail, $token);
            $this->addFlash('warning', sprintf(
                'Un email de confirmation a été envoyé à %s. Cliquez sur le lien pour valider le changement.',
                $newEmail
            ));
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
