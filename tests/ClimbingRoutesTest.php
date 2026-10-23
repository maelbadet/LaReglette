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

class ClimbingRoutesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private User $climber;
    private User $opener;
    private User $admin;
    private Sector $sector;
    private Boulder $boulder;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires pdo_sqlite.');
        }
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->manager = static::getContainer()->get(EntityManagerInterface::class);
        if ('sqlite:///:memory:' !== ($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL'))) {
            self::markTestSkipped('Requires isolated SQLite memory database.');
        }
        (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());
        $this->climber = $this->user('climber', 'ROLE_GRIMPEUR');
        $this->opener = $this->user('opener', 'ROLE_OUVREUR');
        $this->admin = $this->user('admin', 'ROLE_ADMIN');
        $this->sector = (new Sector())->setName('Zone test');
        $this->boulder = (new Boulder())->setName('Bloc rouge')->setColor('rouge')->setGrade(3)
            ->setSector($this->sector)->setOpenedBy($this->opener)->setOpenedAt(new \DateTimeImmutable('-10 days'));
        $this->manager->persist($this->sector);
        $this->manager->persist($this->boulder);
        $this->manager->flush();
    }

    public function testNavigationFiltersAndStatistics(): void
    {
        $ascent = $this->ascent($this->climber);
        $this->client->loginUser($this->climber);
        $this->client->request('GET', '/');
        self::assertSelectorExists('a[href="/sector"]');
        $this->client->request('GET', '/sector');
        self::assertSelectorExists('a[href="/sector/'.$this->sector->getId().'"]');
        $this->client->request('GET', '/sector/'.$this->sector->getId());
        self::assertSelectorCount(1, '.boulder-item');
        $this->client->request('GET', '/boulder', ['boulder_filter' => ['color' => 'bleu', 'gradeMin' => 3, 'gradeMax' => 3]]);
        self::assertSelectorCount(0, '.boulder-item');
        $this->client->request('GET', '/boulder', ['boulder_filter' => ['sector' => $this->sector->getId(), 'color' => 'rouge', 'gradeMin' => 3, 'gradeMax' => 3, 'unfinished' => 1]]);
        self::assertSelectorCount(0, '.boulder-item');
        $this->client->request('GET', '/boulder/'.$this->boulder->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.boulder-statistics', 'Aucune note');
        self::assertSelectorExists('a[href="/boulder/'.$this->boulder->getId().'/ascent/create"]');
        self::assertSame($this->climber->getId(), $ascent->getUser()->getId());
    }

    public function testRoleBoundaries(): void
    {
        $this->client->request('GET', '/sector');
        self::assertResponseIsSuccessful();
        $this->client->loginUser($this->climber);
        foreach (['/sector/create', '/sector/'.$this->sector->getId().'/edit', '/boulder/'.$this->boulder->getId().'/edit', '/sector/'.$this->sector->getId().'/boulder/create'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/boulder/'.$this->boulder->getId().'/dismantle');
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->opener);
        $this->client->request('GET', '/sector/create');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/ascent/create');
        self::assertResponseIsSuccessful();
    }

    public function testOpenerCreatesEditsAndDismantlesWithoutLosingAscents(): void
    {
        $ascent = $this->ascent($this->climber);
        $this->client->loginUser($this->opener);
        $this->client->request('GET', '/sector/'.$this->sector->getId().'/boulder/create');
        $this->client->submitForm('Enregistrer le bloc', [
            'boulder[name]' => 'Nouveau bloc', 'boulder[color]' => 'bleu', 'boulder[grade]' => 4,
            'boulder[openedAt]' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d\TH:i'),
        ]);
        self::assertResponseRedirects();
        $new = $this->manager->getRepository(Boulder::class)->findOneBy(['name' => 'Nouveau bloc']);
        self::assertSame($this->opener->getId(), $new->getOpenedBy()->getId());
        $this->client->request('GET', '/boulder/'.$new->getId().'/edit');
        $this->client->submitForm('Enregistrer le bloc', ['boulder[name]' => 'Bloc modifié']);
        self::assertResponseRedirects('/boulder/'.$new->getId());
        $this->client->request('POST', '/boulder/'.$this->boulder->getId().'/dismantle', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/boulder/'.$this->boulder->getId());
        $this->client->submit($crawler->selectButton('Démonter le bloc')->form());
        self::assertResponseRedirects();
        self::assertNotNull($this->manager->find(Ascent::class, $ascent->getId()));
        $this->client->loginUser($this->climber);
        $this->client->request('GET', '/boulder/'.$this->boulder->getId().'/ascent/create');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminManagesSectorsAndInheritsBoulderAndAscentRights(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/sector/create');
        $this->client->submitForm('Enregistrer', ['sector[name]' => 'Nouvelle zone']);
        self::assertResponseRedirects();
        $sector = $this->manager->getRepository(Sector::class)->findOneBy(['name' => 'Nouvelle zone']);
        $this->client->request('GET', '/sector/'.$sector->getId().'/edit');
        $this->client->submitForm('Enregistrer', ['sector[name]' => 'Zone modifiée']);
        self::assertResponseRedirects();
        $this->client->request('GET', '/boulder/'.$this->boulder->getId().'/edit');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/ascent/create');
        self::assertResponseIsSuccessful();
        $crawler = $this->client->request('GET', '/sector');
        $form = $crawler->filter('form[action="/sector/'.$sector->getId().'/delete"]')->form();
        $id = $sector->getId();
        $this->client->submit($form);
        self::assertResponseRedirects('/sector');
        self::assertNull($this->manager->find(Sector::class, $id));
        $crawler = $this->client->request('GET', '/sector');
        $this->client->submit($crawler->filter('form[action="/sector/'.$this->sector->getId().'/delete"]')->form());
        self::assertResponseRedirects('/sector');
        self::assertNotNull($this->manager->find(Sector::class, $this->sector->getId()));
    }

    public function testAscentFromBoulderIsBoundToCurrentUserAndCanBeDeleted(): void
    {
        $this->client->loginUser($this->climber);
        $this->client->request('GET', '/boulder/'.$this->boulder->getId().'/ascent/create');
        $this->client->submitForm('Enregistrer mon ascension', [
            'ascent[completedAt]' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d\TH:i'),
            'ascent[rating]' => 5,
        ]);
        self::assertResponseRedirects();
        $ascent = $this->manager->getRepository(Ascent::class)->findOneBy([]);
        self::assertSame($this->climber->getId(), $ascent->getUser()->getId());
        self::assertSame($this->boulder->getId(), $ascent->getBoulder()->getId());
        $id = $ascent->getId();
        $this->client->request('POST', '/ascent/'.$id.'/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/ascent/personnal/'.$id);
        $this->client->submit($crawler->selectButton('Supprimer cette ascension')->form());
        self::assertResponseRedirects('/ascent/personnal');
        self::assertNull($this->manager->find(Ascent::class, $id));
    }

    public function testCannotDeleteAnotherClimbersAscent(): void
    {
        $other = $this->user('other', 'ROLE_GRIMPEUR');
        $ascent = $this->ascent($other);
        $this->client->loginUser($this->climber);
        $this->client->request('POST', '/ascent/'.$ascent->getId().'/delete');
        self::assertResponseStatusCodeSame(404);
        self::assertNotNull($this->manager->find(Ascent::class, $ascent->getId()));
        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/ascent/personnal/'.$ascent->getId());
        $this->client->submit($crawler->selectButton('Supprimer cette ascension')->form());
        self::assertResponseRedirects('/ascent/personnal');
    }

    public function testInvalidBoulderAndSectorCsrfAreRejected(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/sector/'.$this->sector->getId().'/boulder/create');
        $this->client->submitForm('Enregistrer le bloc', [
            'boulder[name]' => '', 'boulder[color]' => 'rouge', 'boulder[grade]' => '',
            'boulder[openedAt]' => (new \DateTimeImmutable())->format('Y-m-d\TH:i'),
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->manager->getRepository(Boulder::class)->count([]));
        $this->client->request('POST', '/sector/'.$this->sector->getId().'/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/sector/'.$this->sector->getId().'/delete');
        self::assertResponseStatusCodeSame(405);
        $this->client->request('GET', '/boulder/'.$this->boulder->getId().'/dismantle');
        self::assertResponseStatusCodeSame(405);
    }

    private function user(string $name, string $role): User
    {
        $user = (new User())->setEmail($name.'@example.com')->setFirstName($name)->setLastName('Test')->setPassword('unused')->setRoles([$role]);
        $this->manager->persist($user);

        return $user;
    }

    private function ascent(User $user): Ascent
    {
        $ascent = (new Ascent())->setUser($user)->setBoulder($this->boulder)->setCompletedAt(new \DateTimeImmutable('-1 day'))->setRating(4);
        $this->manager->persist($ascent);
        $this->manager->flush();

        return $ascent;
    }
}
