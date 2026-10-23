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

class BoulderRequirementsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private User $opener;
    private User $other;
    private User $admin;
    private User $climber;
    private Sector $sector;
    private Boulder $own;
    private Boulder $foreign;
    private Boulder $retired;
    private Boulder $orphan;

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
        $this->opener = $this->user('opener', 'ROLE_OUVREUR');
        $this->other = $this->user('other', 'ROLE_OUVREUR');
        $this->admin = $this->user('admin', 'ROLE_ADMIN');
        $this->climber = $this->user('climber', 'ROLE_GRIMPEUR');
        $this->sector = (new Sector())->setName('La Dalle');
        $this->manager->persist($this->sector);
        $this->own = $this->block('Ma ligne 5+', 4, $this->opener)->setSuccessCount(2)->setAverageRating(4.5);
        $this->foreign = $this->block('Autre ligne 6A', 5, $this->other);
        $this->retired = $this->block('Ancienne ligne 6A+', 6, $this->opener)->setDismantledAt(new \DateTimeImmutable('-1 day'));
        $this->block('Ma ligne 6B', 7, $this->opener);
        $this->block('Ma ligne 6B+', 8, $this->opener);
        $this->orphan = $this->block('Sans ouvreur 6C', 9, null);
        $this->manager->flush();
    }

    public function testOpenerCannotEditOrDismantleAnotherOpenersBlock(): void
    {
        $this->client->loginUser($this->opener);
        $this->client->request('GET', '/boulder/'.$this->own->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('select[name="boulder[grade]"]', '6A+');
        $this->client->request('GET', '/boulder/'.$this->foreign->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/boulder/'.$this->foreign->getId().'/edit', ['boulder' => ['name' => 'Modification interdite']]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/boulder/'.$this->foreign->getId().'/dismantle');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/boulder/'.$this->foreign->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/boulder/'.$this->foreign->getId().'/edit"]');
        self::assertSelectorNotExists('form[action="/boulder/'.$this->foreign->getId().'/dismantle"]');
        $this->manager->clear();
        $saved = $this->manager->find(Boulder::class, $this->foreign->getId());
        self::assertSame('Autre ligne 6A', $saved->getName());
        self::assertNull($saved->getDismantledAt());
        $this->client->request('GET', '/boulder/'.$this->orphan->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanEditAndDismantleAnyBlock(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/boulder/'.$this->foreign->getId().'/edit');
        $this->client->submitForm('Enregistrer le bloc', ['boulder[name]' => 'Modifié par admin']);
        self::assertResponseRedirects();
        $this->client->request('GET', '/boulder/'.$this->foreign->getId());
        $this->client->submitForm('Démonter le bloc');
        self::assertResponseRedirects();
        $this->manager->clear();
        $saved = $this->manager->find(Boulder::class, $this->foreign->getId());
        self::assertSame('Modifié par admin', $saved->getName());
        self::assertNotNull($saved->getDismantledAt());
        self::assertSame($this->other->getId(), $saved->getOpenedBy()->getId());
        $this->client->request('GET', '/boulder/'.$this->orphan->getId().'/edit');
        self::assertResponseIsSuccessful();
    }

    public function testOpenerInheritsClimberActionsAndCanCreateAnAscent(): void
    {
        $this->client->loginUser($this->opener);
        $this->client->request('GET', '/ascent/personnal');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/boulder/'.$this->foreign->getId().'/ascent/create');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Enregistrer mon ascension', [
            'ascent[completedAt]' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d\TH:i'),
            'ascent[rating]' => 4,
        ]);
        self::assertResponseRedirects();
        $ascent = $this->manager->getRepository(Ascent::class)->findOneBy([]);
        self::assertSame($this->opener->getId(), $ascent->getUser()->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Cotation 6A');
        $this->client->submitForm('Supprimer cette ascension');
        self::assertResponseRedirects('/ascent/personnal');
        self::assertSame(0, $this->manager->getRepository(Ascent::class)->count([]));
    }

    public function testFrenchGradesRemainNumericInStorage(): void
    {
        $this->client->loginUser($this->opener);
        $this->client->request('GET', '/sector/'.$this->sector->getId().'/boulder/create');
        self::assertSelectorTextContains('select[name="boulder[grade]"]', '8A+');
        $this->client->submitForm('Enregistrer le bloc', [
            'boulder[name]' => 'Ultime ligne', 'boulder[color]' => 'noir', 'boulder[grade]' => 18,
            'boulder[openedAt]' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d\TH:i'),
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.detail-grade', '8A+');
        $this->manager->clear();
        $saved = $this->manager->getRepository(Boulder::class)->findOneBy(['name' => 'Ultime ligne']);
        self::assertSame(18, $saved->getGrade());
        self::assertSame('8A+', $saved->getGradeLabel());
        $this->client->request('GET', '/api/blocs', ['grade' => 18]);
        self::assertResponseIsSuccessful();
        $block = $this->payload()['blocs'][0];
        self::assertSame(18, $block['grade']);
        self::assertSame('8A+', $block['gradeLabel']);
    }

    public function testHtmlAndApiCombineSectorAndInclusiveGradeRange(): void
    {
        $filters = ['sector' => $this->sector->getId(), 'gradeMin' => 4, 'gradeMax' => 7];
        $this->client->loginUser($this->climber);
        $this->client->request('GET', '/boulder', ['boulder_filter' => $filters]);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '.boulder-item');
        self::assertSelectorTextContains('select[name="boulder_filter[gradeMin]"]', '5+');
        self::assertSelectorTextContains('select[name="boulder_filter[gradeMax]"]', '6B');
        self::assertSelectorTextContains('.climbing-grid', '5+');
        $this->client->request('GET', '/api/blocs', $filters);
        self::assertResponseIsSuccessful();
        $blocks = $this->payload()['blocs'];
        self::assertCount(3, $blocks);
        self::assertEqualsCanonicalizing(['5+', '6A', '6B'], array_column($blocks, 'gradeLabel'));
        $this->client->request('GET', '/api/blocs', $filters + ['color' => 'bleu']);
        self::assertSame([], $this->payload()['blocs']);
        $this->client->request('GET', '/api/blocs', ['gradeMin' => 7]);
        self::assertCount(3, $this->payload()['blocs']);
        $this->client->request('GET', '/api/blocs', ['gradeMax' => 4]);
        self::assertCount(1, $this->payload()['blocs']);
    }

    public function testInvalidGradeRangesAreRejectedInHtmlAndApi(): void
    {
        foreach ([['gradeMin' => 8, 'gradeMax' => 4], ['gradeMin' => 0], ['gradeMax' => 19]] as $filters) {
            $this->client->request('GET', '/api/blocs', $filters);
            self::assertResponseStatusCodeSame(400);
        }
        $this->client->loginUser($this->climber);
        $this->client->request('GET', '/boulder', ['boulder_filter' => ['gradeMin' => 8, 'gradeMax' => 4]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'La cotation maximale doit être supérieure ou égale à la cotation minimale.');
    }

    public function testOpenerProfileShowsOnlyOwnBlocksWithStoredStatisticsAndHistory(): void
    {
        $this->client->loginUser($this->opener);
        $this->client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(4, '.opener-block');
        self::assertSelectorTextContains('.opened-boulders', 'Ma ligne 5+');
        self::assertSelectorTextContains('.opened-boulders', 'Ancienne ligne 6A+');
        self::assertSelectorTextContains('.opened-boulders', '4,5/5');
        self::assertSelectorTextContains('.opened-boulders', 'Aucune note');
        self::assertSelectorNotExists('.opened-boulders a[href="/boulder/'.$this->foreign->getId().'"]');
        self::assertSelectorNotExists('.opened-boulders a[href="/boulder/'.$this->orphan->getId().'"]');
        self::assertSame(0, $this->manager->getRepository(Ascent::class)->count([]));
        self::assertSame(2, $this->manager->find(Boulder::class, $this->own->getId())->getSuccessCount());
        $this->client->loginUser($this->climber);
        $this->client->request('GET', '/profil');
        self::assertSelectorNotExists('.opened-boulders');
    }

    public function testVisitorsCanBrowseSectorsAndBlocksWithFilters(): void
    {
        foreach (['/', '/sector', '/sector/'.$this->sector->getId(), '/boulder', '/boulder/'.$this->own->getId()] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('nav a[href="/sector"]');
            self::assertSelectorExists('nav a[href="/boulder"]');
        }
        self::assertSelectorExists('.success-panel a[href="/login"]');
        self::assertSelectorExists('.success-panel a[href="/register"]');
        self::assertSelectorNotExists('a[href="/boulder/'.$this->own->getId().'/ascent/create"]');
        self::assertSelectorNotExists('a[href="/boulder/'.$this->own->getId().'/edit"]');
        $this->client->request('GET', '/boulder', ['boulder_filter' => ['sector' => $this->sector->getId(), 'gradeMin' => 4, 'gradeMax' => 7]]);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '.boulder-item');
        self::assertSelectorNotExists('[name="boulder_filter[unfinished]"]');
    }

    public function testVisitorsCannotRecordAscentsOrManageBlocksAndSectors(): void
    {
        foreach ([
            ['GET', '/ascent/create'], ['POST', '/ascent/create'],
            ['GET', '/boulder/'.$this->own->getId().'/ascent/create'],
            ['POST', '/boulder/'.$this->own->getId().'/ascent/create'],
            ['GET', '/ascent/personnal'], ['POST', '/ascent/1/delete'],
            ['GET', '/sector/create'], ['POST', '/sector/create'],
            ['GET', '/sector/'.$this->sector->getId().'/edit'],
            ['POST', '/sector/'.$this->sector->getId().'/delete'],
            ['GET', '/sector/'.$this->sector->getId().'/boulder/create'],
            ['GET', '/boulder/'.$this->own->getId().'/edit'],
            ['POST', '/boulder/'.$this->own->getId().'/edit'],
            ['POST', '/boulder/'.$this->own->getId().'/dismantle'],
        ] as [$method, $path]) {
            $this->client->request($method, $path);
            self::assertResponseRedirects('/login');
        }
        $this->client->request('GET', '/boulder', ['boulder_filter' => ['unfinished' => 1]]);
        self::assertResponseRedirects('/login');
        self::assertSame(0, $this->manager->getRepository(Ascent::class)->count([]));
        $this->manager->clear();
        self::assertNull($this->manager->find(Boulder::class, $this->own->getId())->getDismantledAt());
    }

    private function user(string $name, string $role): User
    {
        $user = (new User())->setEmail($name.'@example.com')->setFirstName(ucfirst($name))->setLastName('Test')->setPassword('unused')->setRoles([$role]);
        $this->manager->persist($user);

        return $user;
    }

    private function block(string $name, int $grade, ?User $opener): Boulder
    {
        $block = (new Boulder())->setName($name)->setGrade($grade)->setColor('rouge')->setSector($this->sector)->setOpenedBy($opener)->setOpenedAt(new \DateTimeImmutable('-14 days'));
        $this->manager->persist($block);

        return $block;
    }

    private function payload(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
