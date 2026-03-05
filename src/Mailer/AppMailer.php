<?php

namespace App\Mailer;

use App\Entity\Company;
use App\Entity\SecurityToken;
use App\Entity\User;
use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class AppMailer implements AuthCodeMailerInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RouterInterface $router,
        private readonly string $adminEmail
    ) {
    }

    public function sendRegistrationConfirmation(User $user, SecurityToken $token): void
    {
        $url = $this->router->generate('app_register_confirm', ['token' => $token->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Confirmez votre compte professionnel')
            ->htmlTemplate('emails/confirm_account.html.twig')
            ->context([
                'user' => $user,
                'url' => $url,
                'expiresAt' => $token->getExpiresAt(),
            ]);

        $this->mailer->send($email);
    }

    public function sendTwoFactorCode(User $user, SecurityToken $token): void
    {
        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Votre code de connexion')
            ->htmlTemplate('emails/two_factor.html.twig')
            ->context([
                'user' => $user,
                'code' => $token->getToken(),
                'expiresAt' => $token->getExpiresAt(),
            ]);

        $this->mailer->send($email);
    }

    public function sendLoginConfirmation(User $user, SecurityToken $token): void
    {
        $url = $this->router->generate('app_confirm_login_verify', ['token' => $token->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Confirmez votre connexion')
            ->htmlTemplate('emails/confirm_login.html.twig')
            ->context([
                'user' => $user,
                'url' => $url,
                'expiresAt' => $token->getExpiresAt(),
            ]);

        $this->mailer->send($email);
    }

    public function sendPasswordReset(User $user, SecurityToken $token): void
    {
        $url = $this->router->generate('app_reset_password', ['token' => $token->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Réinitialisation de mot de passe')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'user' => $user,
                'url' => $url,
                'expiresAt' => $token->getExpiresAt(),
            ]);

        $this->mailer->send($email);
    }

    public function sendNewCompanyNotification(Company $company): void
    {
        $adminUrl = $this->router->generate('admin', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->to($this->adminEmail)
            ->subject('Nouvelle entreprise à approuver : ' . $company->getName())
            ->htmlTemplate('emails/new_company_notification.html.twig')
            ->context([
                'company' => $company,
                'adminUrl' => $adminUrl,
            ]);

        $this->mailer->send($email);
    }

    public function sendCompanyApproved(Company $company): void
    {
        $email = (new TemplatedEmail())
            ->to($company->getOwner()->getEmail())
            ->subject('Votre fiche a été approuvée !')
            ->htmlTemplate('emails/company_approved.html.twig')
            ->context([
                'company' => $company,
            ]);

        $this->mailer->send($email);
    }

    public function sendPasswordChangeConfirmation(User $user, SecurityToken $token): void
    {
        $url = $this->router->generate('app_change_password_confirm', ['token' => $token->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Confirmez le changement de mot de passe')
            ->htmlTemplate('emails/confirm_password_change.html.twig')
            ->context([
                'user' => $user,
                'url' => $url,
                'expiresAt' => $token->getExpiresAt(),
            ]);

        $this->mailer->send($email);
    }

    public function sendEmailChangeConfirmation(User $user, string $newEmail, SecurityToken $token): void
    {
        $url = $this->router->generate('app_confirm_email_change', ['token' => $token->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->to($newEmail)
            ->subject('Confirmez votre nouvelle adresse e-mail')
            ->htmlTemplate('emails/confirm_email_change.html.twig')
            ->context([
                'user' => $user,
                'newEmail' => $newEmail,
                'url' => $url,
                'expiresAt' => $token->getExpiresAt(),
            ]);

        $this->mailer->send($email);
    }

    public function sendAuthCode(TwoFactorInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        try {

            $authCode = $user->getEmailAuthCode();

            if (!$authCode) {
                return;
            }

            $email = (new TemplatedEmail())
                ->to($user->getEmail())
                ->subject($authCode . ' : Votre code d\'authentification')
                ->htmlTemplate('emails/two_factor.html.twig')
                ->context([
                    'user' => $user,
                    'code' => $authCode,
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log the error or handle it as needed
            echo $e->getMessage();
         }
    }
}
