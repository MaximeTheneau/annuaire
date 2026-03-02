<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create-super-admin',
    description: 'Crée un super admin avec email + mot de passe.'
)]
class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email du super admin')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe du super admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = mb_strtolower((string) $input->getArgument('email'));
        $password = (string) $input->getArgument('password');

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing instanceof User) {
            $existing->setRoles(['ROLE_SUPER_ADMIN']);
            $output->writeln('Utilisateur existant promu en super admin.');
            $this->entityManager->flush();
            return Command::SUCCESS;
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setIsVerified(true)
            ->setTwoFactorEnabled(true);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln('Super admin créé.');

        return Command::SUCCESS;
    }
}
