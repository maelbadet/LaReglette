<?php

namespace App\Tests;

use App\Entity\Ascent;
use App\Entity\Boulder;
use App\Entity\Sector;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileManagementTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $user;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Run with php -d extension=pdo_sqlite bin/phpunit and DATABASE_URL=sqlite:///:memory:.');
        }
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        if ('pdo_sqlite' !== $this->entityManager->getConnection()->getParams()['driver']) {
            self::markTestSkipped('These database tests require an isolated SQLite in-memory database.');
        }
        $this->entityManager->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->user = (new User())->setEmail('camille@example.com')->setFirstName('Camille')->setLastName('Martin')->setIsVerified(true);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->user->setPassword($hasher->hashPassword($this->user, 'old-password'));
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();
        $this->client->loginUser($this->user);
    }

    public function testPersonalInformationIsSaved(): void
    {
        $this->submit('Enregistrer mes informations', ['profile[firstName]' => 'Alex', 'profile[lastName]' => 'Durand']);
        self::assertResponseRedirects('/profil');
        $this->entityManager->clear();
        $saved = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'camille@example.com']);
        self::assertSame('Alex', $saved->getFirstName());
        self::assertSame('Durand', $saved->getLastName());
    }

    public function testProfileShowsPersonalTabAndKeepsDeletionInDialog(): void
    {
        $this->client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#personal-tab[aria-selected="true"]');
        self::assertSelectorExists('#personal-panel:not([hidden])');
        self::assertSelectorExists('#password-panel[hidden]');
        self::assertSelectorExists('dialog:not([open]) form[name="delete_account"] input[type="password"]');
        self::assertSelectorExists('button[data-action="click->profile#openDelete"]');
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $other = (new User())->setEmail('taken@example.com')->setFirstName('Autre')->setLastName('Personne')->setPassword('unused');
        $this->entityManager->persist($other);
        $this->entityManager->flush();
        $this->submit('Enregistrer mes informations', ['profile[email]' => 'taken@example.com']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form[name="profile"] ul');
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'camille@example.com']));
    }

    public function testEmailChangeRequiresConfirmationAndLogsOut(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(static fn (RawMessage $email): bool => 'new@example.com' === $email->getTo()[0]->getAddress()));
        static::getContainer()->set('mailer.mailer', $mailer);
        $this->submit('Enregistrer mes informations', ['profile[email]' => 'new@example.com']);
        self::assertResponseRedirects('/login');
        $this->entityManager->clear();
        $saved = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'new@example.com']);
        self::assertFalse($saved->isVerified());
        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/login');
    }

    public function testWrongOldPasswordIsRejected(): void
    {
        $hash = $this->user->getPassword();
        $this->submit('Modifier mon mot de passe', [
            'change_password[currentPassword]' => 'wrong-password',
            'change_password[newPassword][first]' => 'new-password',
            'change_password[newPassword][second]' => 'new-password',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="change_password"]', 'L’ancien mot de passe est incorrect.');
        self::assertSelectorExists('#password-tab[aria-selected="true"]');
        self::assertSelectorExists('#password-panel:not([hidden])');
        self::assertSelectorExists('#personal-panel[hidden]');
        self::assertSame($hash, $this->user->getPassword());
    }

    public function testPasswordChangeHashesPasswordAndLogsOut(): void
    {
        $id = $this->user->getId();
        $this->submit('Modifier mon mot de passe', [
            'change_password[currentPassword]' => 'old-password',
            'change_password[newPassword][first]' => 'new-password',
            'change_password[newPassword][second]' => 'new-password',
        ]);
        self::assertResponseRedirects('/login');
        $this->entityManager->clear();
        $saved = $this->entityManager->find(User::class, $id);
        self::assertTrue(password_verify('new-password', $saved->getPassword()));
        self::assertFalse(password_verify('old-password', $saved->getPassword()));
        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/login');
    }

    public function testDeletionRequiresConfirmation(): void
    {
        $this->submit('Supprimer définitivement mon compte', ['delete_account[currentPassword]' => 'old-password']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form[name="delete_account"] ul');
        self::assertNotNull($this->entityManager->find(User::class, $this->user->getId()));
    }

    public function testWrongPasswordCannotDeleteAccount(): void
    {
        $this->submit('Supprimer définitivement mon compte', ['delete_account[currentPassword]' => 'wrong-password', 'delete_account[confirm]' => '1']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="delete_account"]', 'Le mot de passe est incorrect.');
        self::assertSelectorExists('[data-profile-delete-invalid-value="true"]');
        self::assertNotNull($this->entityManager->find(User::class, $this->user->getId()));
    }

    public function testMismatchedNewPasswordsAreRejected(): void
    {
        $hash = $this->user->getPassword();
        $this->submit('Modifier mon mot de passe', [
            'change_password[currentPassword]' => 'old-password',
            'change_password[newPassword][first]' => 'new-password',
            'change_password[newPassword][second]' => 'different-password',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame($hash, $this->user->getPassword());
    }

    public function testDeletionPreservesBouldersAndRemovesAscents(): void
    {
        $sector = (new Sector())->setName('Test');
        $boulder = (new Boulder())->setName('Bloc')->setColor('rouge')->setGrade(3)->setSector($sector)->setOpenedBy($this->user);
        $ascent = (new Ascent())->setUser($this->user)->setBoulder($boulder)->setCompletedAt(new \DateTimeImmutable())->setRating(4);
        foreach ([$sector, $boulder, $ascent] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
        $userId = $this->user->getId();
        $boulderId = $boulder->getId();
        $ascentId = $ascent->getId();
        $this->submit('Supprimer définitivement mon compte', ['delete_account[currentPassword]' => 'old-password', 'delete_account[confirm]' => '1']);
        self::assertResponseRedirects('/');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(User::class, $userId));
        self::assertNull($this->entityManager->find(Ascent::class, $ascentId));
        self::assertNotNull($this->entityManager->find(Boulder::class, $boulderId));
        self::assertNull($this->entityManager->find(Boulder::class, $boulderId)->getOpenedBy());
        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/login');
    }

    public function testInvalidCsrfCannotDeleteAccount(): void
    {
        $crawler = $this->client->request('GET', '/profil');
        $form = $crawler->selectButton('Supprimer définitivement mon compte')->form([
            'delete_account[currentPassword]' => 'old-password', 'delete_account[confirm]' => '1',
        ]);
        $values = $form->getPhpValues();
        $values['delete_account']['_token'] = 'invalid';
        $this->client->request('POST', '/profil', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form[name="delete_account"] ul');
        self::assertNotNull($this->entityManager->find(User::class, $this->user->getId()));
    }

    private function submit(string $button, array $values): void
    {
        $this->client->request('GET', '/profil');
        $this->client->submitForm($button, $values);
    }
}
