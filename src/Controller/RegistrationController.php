<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Category;
use App\Entity\SecurityToken;
use App\Entity\User;
use App\Mailer\AppMailer;
use App\Service\AddressResolverService;
use App\Service\SecurityTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register_pro')]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        SecurityTokenManager $tokenManager,
        AppMailer $mailer,
        AddressResolverService $addressResolver,
        ParameterBagInterface $params
    ): Response {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
            ])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Choisir une catégorie',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('company_name', TextType::class, [
                'label' => 'Nom de l\'entreprise',
                'constraints' => [new NotBlank(), new Length(max: 180)],
            ])
            ->add('siret', TextType::class, [
                'label' => 'SIRET',
                'constraints' => [new NotBlank(), new Length(min: 14, max: 17)],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'constraints' => [new NotBlank(), new Length(max: 255)],
                'attr' => [
                    'data-places-input' => 'address',
                ],
            ])
            ->add('place_id', HiddenType::class)
            ->add('lat', HiddenType::class)
            ->add('lng', HiddenType::class)
            ->add('postal_code', HiddenType::class)
            ->add('city_name', HiddenType::class)
            ->add('department_name', HiddenType::class)
            ->add('department_code', HiddenType::class)
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'constraints' => [new NotBlank(), new Length(max: 30)],
            ])
            ->add('website', TextType::class, [
                'label' => 'Site web',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => mb_strtolower($data['email'])]);
            if ($existingUser) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');
                return $this->redirectToRoute('app_register_pro');
            }

            $normalizedSiret = preg_replace('/\s+/', '', $data['siret']) ?? $data['siret'];
            if (!ctype_digit($normalizedSiret) || strlen($normalizedSiret) !== 14) {
                $this->addFlash('error', 'Le SIRET doit contenir 14 chiffres.');
                return $this->redirectToRoute('app_register_pro');
            }

            $existingCompany = $entityManager->getRepository(Company::class)->findOneBy(['siret' => $normalizedSiret]);
            if ($existingCompany) {
                $this->addFlash('error', 'Ce SIRET est déjà utilisé.');
                return $this->redirectToRoute('app_register_pro');
            }

            $existingCompanyByName = $entityManager->getRepository(Company::class)->findOneBy(['name' => $data['company_name']]);
            if ($existingCompanyByName) {
                $this->addFlash('error', 'Une entreprise avec ce nom existe déjà.');
                return $this->redirectToRoute('app_register_pro');
            }
            $placeId = trim((string) $data['place_id']);
            $cityName = trim((string) $data['city_name']);
            if ($cityName === '' || $placeId === '') {
                $this->addFlash('error', 'Adresse invalide. Merci de sélectionner une adresse dans la liste.');
                return $this->redirectToRoute('app_register_pro');
            }

            $address = $addressResolver->resolve(
                placeId:          $placeId,
                formattedAddress: (string) $data['address'],
                cityName:         $cityName,
                departmentName:   trim((string) $data['department_name']),
                departmentCode:   trim((string) $data['department_code']),
                postalCode:       $data['postal_code'] ?: null,
                lat:              $data['lat'] ?: null,
                lng:              $data['lng'] ?: null,
            );

            if ($address === null) {
                $this->addFlash('error', 'Adresse invalide. Merci de sélectionner une adresse dans la liste.');
                return $this->redirectToRoute('app_register_pro');
            }

            $user = (new User())
                ->setEmail($data['email'])
                ->setRoles(['ROLE_PRO'])
                ->setIsVerified(false)
                ->setTwoFactorEnabled(false);

            $company = (new Company())
                ->setName($data['company_name'])
                ->setSiret($normalizedSiret)
                ->setPhone($data['phone'])
                ->setWebsite($data['website'] ?: null)
                ->setDescription($data['description'] ?: null)
                ->setOwner($user)
                ->setAddress($address);

            $category = $data['category'] ?? null;
            if ($category instanceof \App\Entity\Category) {
                $company->addCategory($category);
            }
            $user->setCompany($company);

            $entityManager->persist($user);
            $entityManager->persist($company);
            $entityManager->flush();

            $token = $tokenManager->createToken(
                $user,
                SecurityToken::TYPE_CONFIRM_EMAIL,
                new \DateTimeImmutable(sprintf('+%d minutes', (int) $params->get('app.confirm_email_ttl_minutes')))
            );

            $mailer->sendRegistrationConfirmation($user, $token);
            $mailer->sendNewCompanyNotification($company);

            $this->addFlash('success', 'Votre compte a été créé. Un email de confirmation vous a été envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/verification/compte/{token}', name: 'app_register_confirm')]
    public function confirm(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $securityToken = $entityManager->getRepository(SecurityToken::class)
            ->findOneBy(['token' => $token, 'type' => SecurityToken::TYPE_CONFIRM_EMAIL]);

        if (!$securityToken || $securityToken->isExpired() || $securityToken->isUsed()) {
            $this->addFlash('error', 'Le lien de confirmation est invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createFormBuilder()
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'constraints' => [new NotBlank(), new Length(min: 8)],
            ])
            ->add('confirm_password', PasswordType::class, [
                'label' => 'Confirmer le mot de passe',
                'constraints' => [new NotBlank(), new Length(min: 8)],
            ])
            ->getForm();

        $status = Response::HTTP_OK;
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ($data['password'] !== $data['confirm_password']) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            } else {
                $user = $securityToken->getUser();
                $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
                $user->setIsVerified(true);

                $securityToken->setUsedAt(new \DateTimeImmutable());

                $entityManager->persist($user);
                $entityManager->persist($securityToken);
                $entityManager->flush();

                $this->addFlash('success', 'Votre compte est confirmé. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        } elseif ($form->isSubmitted()) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return $this->render('registration/set_password.html.twig', [
            'form' => $form->createView(),
        ], new Response(null, $status));
    }
}
