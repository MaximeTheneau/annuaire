<?php

namespace App\Controller\Api;

use App\Entity\Company;
use App\Entity\SecurityToken;
use App\Entity\User;
use App\Mailer\AppMailer;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use App\Repository\UserRepository;
use App\Service\AddressResolverService;
use App\Service\ImageOptimizer;
use App\Service\SecurityTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/company')]
class CompanyRegistrationApiController extends AbstractController
{
    private const ALLOWED_MIME_TYPES  = ['image/jpeg', 'image/png', 'image/avif', 'image/webp'];
    private const MAX_IMAGE_SIZE      = 2 * 1024 * 1024; // 2 Mo
    private const PHONE_PATTERN       = '/^[0-9+\s\-().]{7,30}$/';

    /**
     * POST /api/company/register
     *
     * Champs attendus (multipart/form-data) :
     *   email, first_name, last_name, company_name, siret, phone
     *   website (optionnel), description (optionnel)
     *   category_id (UUID de catégorie)
     *   place_id, formatted_address, city_name
     *   postal_code, department_name, department_code, lat, lng (optionnels)
     *   logo (fichier image optionnel — jpg, png, avif, webp, max 2 Mo)
     */
    #[Route('/register', name: 'api_company_register', methods: ['POST'])]
    public function register(
        Request                $request,
        EntityManagerInterface $em,
        ValidatorInterface     $validator,
        CategoryRepository     $categoryRepository,
        CompanyRepository      $companyRepository,
        UserRepository         $userRepository,
        AddressResolverService $addressResolver,
        ImageOptimizer         $imageOptimizer,
        SecurityTokenManager   $tokenManager,
        AppMailer              $mailer,
        ParameterBagInterface  $params,
    ): JsonResponse {

        // ── 1. Extraction & nettoyage des champs texte ────────────────────────
        $email       = mb_strtolower(trim((string) $request->request->get('email', '')));
        $firstName   = strip_tags(trim((string) $request->request->get('first_name', '')));
        $lastName    = strip_tags(trim((string) $request->request->get('last_name', '')));
        $companyName = strip_tags(trim((string) $request->request->get('company_name', '')));
        $siret       = preg_replace('/\s+/', '', trim((string) $request->request->get('siret', '')));
        $phone       = trim((string) $request->request->get('phone', ''));
        $website     = trim((string) $request->request->get('website', ''));
        $description = strip_tags(trim((string) $request->request->get('description', '')));
        $categoryId  = trim((string) $request->request->get('category_id', ''));
        $placeId     = trim((string) $request->request->get('place_id', ''));
        $fmtAddr     = strip_tags(trim((string) $request->request->get('formatted_address', '')));
        $cityName    = strip_tags(trim((string) $request->request->get('city_name', '')));
        $postalCode  = trim((string) $request->request->get('postal_code', '')) ?: null;
        $deptName    = strip_tags(trim((string) $request->request->get('department_name', '')));
        $deptCode    = trim((string) $request->request->get('department_code', ''));
        $lat         = trim((string) $request->request->get('lat', '')) ?: null;
        $lng         = trim((string) $request->request->get('lng', '')) ?: null;

        // ── 2. Validation des champs ──────────────────────────────────────────
        $errors = [];

        // Email
        foreach ($validator->validate($email, [
            new Assert\NotBlank(message: 'L\'email est obligatoire.'),
            new Assert\Email(message: 'L\'adresse e-mail est invalide.'),
            new Assert\Length(max: 180, maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.'),
        ]) as $v) {
            $errors['email'][] = $v->getMessage();
        }

        // Prénom
        if ($firstName === '') {
            $errors['first_name'][] = 'Le prénom est obligatoire.';
        } elseif (mb_strlen($firstName) > 255) {
            $errors['first_name'][] = 'Le prénom ne peut pas dépasser 255 caractères.';
        }

        // Nom
        if ($lastName === '') {
            $errors['last_name'][] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($lastName) > 255) {
            $errors['last_name'][] = 'Le nom ne peut pas dépasser 255 caractères.';
        }

        // Nom de l'entreprise
        if ($companyName === '') {
            $errors['company_name'][] = 'Le nom de l\'entreprise est obligatoire.';
        } elseif (mb_strlen($companyName) > 180) {
            $errors['company_name'][] = 'Le nom de l\'entreprise ne peut pas dépasser 180 caractères.';
        }

        // SIRET
        if ($siret === '') {
            $errors['siret'][] = 'Le SIRET est obligatoire.';
        } elseif (!ctype_digit($siret) || strlen($siret) !== 14) {
            $errors['siret'][] = 'Le SIRET doit contenir exactement 14 chiffres.';
        }

        // Téléphone
        if ($phone === '') {
            $errors['phone'][] = 'Le téléphone est obligatoire.';
        } elseif (!preg_match(self::PHONE_PATTERN, $phone)) {
            $errors['phone'][] = 'Le numéro de téléphone est invalide.';
        }

        // Site web (optionnel, mais doit être une URL valide si fourni)
        if ($website !== '') {
            foreach ($validator->validate($website, [
                new Assert\Url(message: 'Le site web doit être une URL valide (https://...).'),
                new Assert\Length(max: 255, maxMessage: 'Le site web ne peut pas dépasser {{ limit }} caractères.'),
            ]) as $v) {
                $errors['website'][] = $v->getMessage();
            }
        }

        // Description
        if ($description !== '' && mb_strlen($description) > 5000) {
            $errors['description'][] = 'La description ne peut pas dépasser 5 000 caractères.';
        }

        // Catégorie
        if ($categoryId === '') {
            $errors['category_id'][] = 'La catégorie est obligatoire.';
        }

        // Adresse
        if ($placeId === '') {
            $errors['place_id'][] = 'L\'identifiant Google Place est obligatoire.';
        }
        if ($fmtAddr === '') {
            $errors['formatted_address'][] = 'L\'adresse est obligatoire.';
        }
        if ($cityName === '') {
            $errors['city_name'][] = 'La ville est obligatoire.';
        }

        // Coordonnées GPS (si fournies, doivent être numériques)
        if ($lat !== null && !is_numeric($lat)) {
            $errors['lat'][] = 'La latitude doit être un nombre.';
        }
        if ($lng !== null && !is_numeric($lng)) {
            $errors['lng'][] = 'La longitude doit être un nombre.';
        }

        // Logo (optionnel)
        $logoFile = $request->files->get('logo');
        if ($logoFile instanceof UploadedFile) {
            if (!$logoFile->isValid()) {
                $errors['logo'][] = sprintf(
                    'Le fichier image est invalide ou corrompu (erreur PHP %d).',
                    $logoFile->getError()
                );
            } elseif ($logoFile->getSize() > self::MAX_IMAGE_SIZE) {
                $errors['logo'][] = 'L\'image ne doit pas dépasser 2 Mo.';
            } else {
                // Vérification du MIME réel (contenu fichier, pas l'extension)
                $finfo    = new \finfo(\FILEINFO_MIME_TYPE);
                $realMime = $finfo->file($logoFile->getRealPath());
                if (!in_array($realMime, self::ALLOWED_MIME_TYPES, true)) {
                    $errors['logo'][] = sprintf(
                        'Type de fichier non autorisé (%s). Seuls jpg, png, avif et webp sont acceptés.',
                        $realMime
                    );
                }
            }
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // ── 3. Unicité ────────────────────────────────────────────────────────
        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->json(
                ['errors' => ['email' => ['Cette adresse e-mail est déjà utilisée.']]],
                Response::HTTP_CONFLICT
            );
        }

        if ($companyRepository->findOneBy(['siret' => $siret])) {
            return $this->json(
                ['errors' => ['siret' => ['Ce numéro SIRET est déjà enregistré.']]],
                Response::HTTP_CONFLICT
            );
        }

        if ($companyRepository->findOneBy(['name' => $companyName])) {
            return $this->json(
                ['errors' => ['company_name' => ['Ce nom d\'entreprise est déjà utilisé.']]],
                Response::HTTP_CONFLICT
            );
        }

        // ── 4. Résolution de la catégorie ─────────────────────────────────────
        $category = $categoryRepository->find($categoryId);
        if ($category === null) {
            return $this->json(
                ['errors' => ['category_id' => ['Catégorie introuvable.']]],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // ── 5. Résolution de l'adresse ────────────────────────────────────────
        $address = $addressResolver->resolve(
            placeId:          $placeId,
            formattedAddress: $fmtAddr,
            cityName:         $cityName,
            departmentName:   $deptName,
            departmentCode:   $deptCode,
            postalCode:       $postalCode,
            lat:              $lat,
            lng:              $lng,
        );

        if ($address === null) {
            return $this->json(
                ['errors' => ['address' => ['Adresse invalide. Veuillez fournir un identifiant de lieu Google valide.']]],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // ── 6. Création du User ───────────────────────────────────────────────
        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)        // met aussi à jour name + slug via BaseEntity
            ->setRoles(['ROLE_PRO'])
            ->setIsVerified(false)
            ->setTwoFactorEnabled(false)
            ->setPassword('!disabled_' . bin2hex(random_bytes(16))); // inconnectable avant confirmation

        // ── 7. Création de la Company ─────────────────────────────────────────
        $company = (new Company())
            ->setName($companyName)
            ->setSiret($siret)
            ->setPhone($phone)
            ->setWebsite($website ?: '')
            ->setDescription($description ?: null)
            ->setOwner($user)
            ->setAddress($address)
            ->setApproved(null); // En attente de validation admin

        $company->addCategory($category);
        $company->initInterventionDepartmentFromCity();

        $em->persist($user);
        $em->persist($company);
        $em->flush();

        // ── 8. Upload du logo (non bloquant) ──────────────────────────────────
        if ($logoFile instanceof UploadedFile && $logoFile->isValid()) {
            try {
                $slug = $company->getSlug() ?? uniqid('company_');
                $imageOptimizer->setPicture($logoFile, $company, $slug);
                $em->flush();
            } catch (\InvalidArgumentException $e) {
                // Image refusée par l'optimizer — la company est créée sans logo
            }
        }

        // ── 9. Token de confirmation + emails ─────────────────────────────────
        $ttl   = (int) $params->get('app.confirm_email_ttl_minutes');
        $token = $tokenManager->createToken(
            $user,
            SecurityToken::TYPE_CONFIRM_EMAIL,
            new \DateTimeImmutable(sprintf('+%d minutes', $ttl))
        );

        // Email au nouvel utilisateur : confirme son compte et définit son mot de passe
        $mailer->sendRegistrationConfirmation($user, $token);

        // Email à l'admin : nouvelle entreprise en attente d'approbation
        $mailer->sendNewCompanyNotification($company);

        return $this->json(
            ['message' => 'Votre demande a bien été enregistrée. Un email de confirmation vous a été envoyé.'],
            Response::HTTP_CREATED
        );
    }
}
