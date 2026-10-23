<?php

namespace App\Service;

use App\Entity\User;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AccountManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerifier $emailVerifier,
    ) {}

    /** @param array{firstName: string, lastName: string, email: string} $edited */
    public function updateProfile(User $user, array $edited): bool
    {
        $emailChanged = $user->getEmail() !== $edited['email'];
        $user->setFirstName($edited['firstName']);
        $user->setLastName($edited['lastName']);
        $user->setEmail($edited['email']);
        if ($emailChanged) {
            $user->setIsVerified(false);
        }
        $this->entityManager->flush();
        if ($emailChanged) {
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address('contact@lareglette.fr', 'La Réglette'))
                    ->to($user->getEmail())
                    ->subject('Confirmez votre adresse e-mail · La Réglette')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );
        }

        return $emailChanged;
    }

    public function changePassword(User $user, string $newPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();
    }

    public function delete(User $user): void
    {
        // Foreign keys remove ascents and preserve boulders without their author.
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
