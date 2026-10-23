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

class AscentPagesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private User $user;
    private Boulder $boulder;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires pdo_sqlite and DATABASE_URL=sqlite:///:memory:.');
        }
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->manager = static::getContainer()->get(EntityManagerInterface::class);
        if ('pdo_sqlite' !== $this->manager->getConnection()->getParams()['driver']) {
            self::markTestSkipped('Requires an isolated SQLite in-memory database.');
        }
        (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());
        $this->user = (new User())->setEmail('climber@example.com')->setFirstName('Camille')->setLastName('Martin')->setPassword('unused')->setRoles(['ROLE_GRIMPEUR']);
        $sector = (new Sector())->setName('Zone test');
        $this->boulder = (new Boulder())->setName('Bloc test')->setGrade(3)->setColor('rouge')->setSector($sector)->setOpenedBy($this->user)->setOpenedAt(new \DateTimeImmutable('-10 days'));
        foreach ([$this->user, $sector, $this->boulder] as $entity) {
            $this->manager->persist($entity);
        }
        $this->manager->flush();
        $this->client->loginUser($this->user);
    }

    public function testCreationUsesCurrentUserAndDetailShowsSavedData(): void
    {
        $this->submitDate(new \DateTimeImmutable('-1 day'));
        $ascent = $this->manager->getRepository(Ascent::class)->findOneBy([]);
        self::assertNotNull($ascent);
        self::assertSame($this->user->getId(), $ascent->getUser()->getId());
        self::assertResponseRedirects('/ascent/personnal/'.$ascent->getId());
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bloc test');
        self::assertSelectorTextContains('.ascent-comment', 'Belle ascension');
    }

    public function testFutureDateIsRejected(): void
    {
        $this->submitDate(new \DateTimeImmutable('+1 day'));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'La date ne peut pas être dans le futur.');
        self::assertSame(0, $this->manager->getRepository(Ascent::class)->count([]));
    }

    public function testDateBeforeOpeningIsRejected(): void
    {
        $this->submitDate(new \DateTimeImmutable('-11 days'));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'La date ne peut pas précéder l’ouverture du bloc.');
        self::assertSame(0, $this->manager->getRepository(Ascent::class)->count([]));
    }

    public function testPersonalListAndDetailExcludeOtherUsers(): void
    {
        $other = (new User())->setEmail('other@example.com')->setFirstName('Autre')->setLastName('Grimpeur')->setPassword('unused');
        $this->manager->persist($other);
        $own = (new Ascent())->setUser($this->user)->setBoulder($this->boulder)->setCompletedAt(new \DateTimeImmutable('-1 day'))->setRating(4);
        $foreign = (new Ascent())->setUser($other)->setBoulder($this->boulder)->setCompletedAt(new \DateTimeImmutable('-2 days'))->setRating(3);
        $this->manager->persist($own);
        $this->manager->persist($foreign);
        $this->manager->flush();
        $this->client->request('GET', '/ascent/personnal');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.ascent-item');
        self::assertSelectorExists('a[href="/ascent/personnal/'.$own->getId().'"]');
        self::assertSelectorNotExists('a[href="/ascent/personnal/'.$foreign->getId().'"]');
        $this->client->request('GET', '/ascent/personnal/'.$foreign->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/ascent/personnal/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnonymousUsersMustLogIn(): void
    {
        $this->client->getCookieJar()->clear();
        foreach (['/create', '/ascent/personnal', '/ascent/personnal/1'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseRedirects('/login');
        }
    }

    public function testCsrfAndUserInjectionAreRejected(): void
    {
        $crawler = $this->client->request('GET', '/create');
        $form = $crawler->selectButton('Enregistrer mon ascension')->form($this->values(new \DateTimeImmutable('-1 day')));
        $values = $form->getPhpValues();
        $values['ascent']['_token'] = 'invalid';
        $values['ascent']['user'] = 123;
        $this->client->request('POST', '/create', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->manager->getRepository(Ascent::class)->count([]));
    }

    private function submitDate(\DateTimeImmutable $date): void
    {
        $this->client->request('GET', '/create');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[name="ascent[user]"]');
        $this->client->submitForm('Enregistrer mon ascension', $this->values($date));
    }

    private function values(\DateTimeImmutable $date): array
    {
        return [
            'ascent[boulder]' => $this->boulder->getId(),
            'ascent[completedAt]' => $date->format('Y-m-d\TH:i'),
            'ascent[rating]' => 4,
            'ascent[comment]' => 'Belle ascension',
        ];
    }
}
