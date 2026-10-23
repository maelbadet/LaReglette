<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserRolesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private User $admin;
    private User $member;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires pdo_sqlite.');
        }
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->manager = static::getContainer()->get(EntityManagerInterface::class);
        $params = $this->manager->getConnection()->getParams();
        if ('pdo_sqlite' !== $params['driver'] || !($params['memory'] ?? false)) {
            self::markTestSkipped('Requires isolated SQLite memory database.');
        }
        (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());
        $this->admin = $this->user('admin', ['ROLE_ADMIN']);
        $this->member = $this->user('member', ['ROLE_GRIMPEUR']);
        $this->manager->flush();
    }

    public function testOnlyAdminsCanReadOrSubmitTheForm(): void
    {
        $this->client->request('GET', '/login/role');
        self::assertResponseRedirects('/login');
        $this->client->request('POST', '/login/role');
        self::assertResponseRedirects('/login');
        foreach (['ROLE_USER', 'ROLE_GRIMPEUR', 'ROLE_OUVREUR'] as $role) {
            $this->member = $this->manager->find(User::class, $this->member->getId());
            $this->member->setRoles([$role]);
            $this->manager->flush();
            $this->client->loginUser($this->member);
            foreach (['/login/role', '/login/role/'.$this->admin->getId()] as $path) {
                foreach (['GET', 'POST'] as $method) {
                    $this->client->request($method, $path);
                    self::assertResponseStatusCodeSame(403);
                }
            }
            $this->member = $this->manager->find(User::class, $this->member->getId());
        }
    }

    public function testAdminSeesMembersAndAnExistingRoleIsPreselected(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/login/role/'.$this->member->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('nav a[href="/login/role"]');
        self::assertSelectorTextContains('.members-table', 'member@example.com');
        self::assertSelectorCount(2, '.members-table tbody tr');
        self::assertSelectorExists('option[value="ROLE_GRIMPEUR"][selected]');
        self::assertSelectorNotExists('option[value="ROLE_SUPER_ADMIN"]');
        self::assertSelectorExists('select[name="change_role[user]"] option[value="'.$this->member->getId().'"][selected]');
        self::assertSelectorNotExists('input[name="change_role[password]"]');
    }

    public function testAdminCanPromoteAndDemoteAndPreviousRolesAreReplaced(): void
    {
        $this->client->loginUser($this->admin);
        foreach (['ROLE_OUVREUR', 'ROLE_ADMIN', 'ROLE_GRIMPEUR'] as $role) {
            $this->client->request('GET', '/login/role');
            $this->client->submitForm('Enregistrer le rôle', [
                'change_role[user]' => $this->member->getId(),
                'change_role[role]' => $role,
            ]);
            self::assertResponseRedirects('/login/role');
            $this->manager->clear();
            $saved = $this->manager->find(User::class, $this->member->getId());
            self::assertSame([$role, 'ROLE_USER'], $saved->getRoles());
            self::assertSame('member@example.com', $saved->getEmail());
            self::assertSame('unchanged-password', $saved->getPassword());
            self::assertSame('Member', $saved->getFirstName());
            $this->client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('[role="status"]', 'Le rôle de Member Test est maintenant');
        }
    }

    public function testCsrfInvalidRolesAndExtraFieldsAreRejected(): void
    {
        $this->client->loginUser($this->admin);
        foreach (['csrf', 'role', 'extra', 'user'] as $invalid) {
            $crawler = $this->client->request('GET', '/login/role');
            $values = $crawler->selectButton('Enregistrer le rôle')->form([
                'change_role[user]' => $this->member->getId(),
                'change_role[role]' => 'ROLE_OUVREUR',
            ])->getPhpValues();
            match ($invalid) {
                'csrf' => $values['change_role']['_token'] = 'invalid',
                'role' => $values['change_role']['role'] = 'ROLE_SUPER_ADMIN',
                'extra' => $values['change_role']['password'] = 'injected-password',
                'user' => $values['change_role']['user'] = 999999,
            };
            $this->client->request('POST', '/login/role', $values);
            self::assertResponseStatusCodeSame(422);
            $this->manager->clear();
            self::assertSame(['ROLE_GRIMPEUR', 'ROLE_USER'], $this->manager->find(User::class, $this->member->getId())->getRoles());
        }
    }

    public function testUnknownUserReturns404AndGetDoesNotChangeRoles(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/login/role/'.$this->member->getId(), ['change_role' => ['role' => 'ROLE_ADMIN']]);
        self::assertResponseIsSuccessful();
        self::assertSame(['ROLE_GRIMPEUR', 'ROLE_USER'], $this->manager->find(User::class, $this->member->getId())->getRoles());
        $this->client->request('GET', '/login/role/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRemovedAdminRightsDoNotRemainInAnExistingSession(): void
    {
        $this->member->setRoles(['ROLE_ADMIN']);
        $this->manager->flush();
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/login/role');
        self::assertResponseIsSuccessful();
        $member = $this->manager->find(User::class, $this->member->getId());
        $member->setRoles(['ROLE_GRIMPEUR']);
        $this->manager->flush();
        $this->client->request('GET', '/login/role');
        self::assertResponseRedirects('/login');
    }

    private function user(string $name, array $roles): User
    {
        $user = (new User())->setEmail($name.'@example.com')->setFirstName(ucfirst($name))->setLastName('Test')->setPassword('unchanged-password')->setRoles($roles);
        $this->manager->persist($user);

        return $user;
    }
}
