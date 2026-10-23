<?php

namespace App\Tests;

use App\Entity\Ascent;
use App\Entity\Boulder;
use App\Entity\Sector;
use App\Entity\User;
use App\Message\RecalculateBoulderStatistics;
use App\Service\AccountManager;
use App\Service\AscentManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\MessageBusInterface;

class AsyncBoulderStatisticsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private int $boulderId;
    private int $userId;

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
        self::assertInstanceOf(DoctrineTransport::class, static::getContainer()->get('messenger.transport.statistics'));
        $user = (new User())->setEmail('climber@example.com')->setFirstName('Camille')->setLastName('Martin')->setPassword('unused')->setRoles(['ROLE_GRIMPEUR']);
        $sector = (new Sector())->setName('La Dalle');
        $boulder = (new Boulder())->setName('Bloc asynchrone')->setColor('rouge')->setGrade(3)->setSector($sector)->setOpenedAt(new \DateTimeImmutable('-10 days'));
        foreach ([$user, $sector, $boulder] as $entity) {
            $this->manager->persist($entity);
        }
        $this->manager->flush();
        $this->boulderId = $boulder->getId();
        $this->userId = $user->getId();
        $this->client->loginUser($user);
    }

    public function testHttpCreationAndDeletionWaitForTheDoctrineWorker(): void
    {
        $this->client->request('GET', '/boulder/'.$this->boulderId.'/ascent/create');
        $this->client->submitForm('Enregistrer mon ascension', [
            'ascent[completedAt]' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d\TH:i'),
            'ascent[rating]' => 4,
        ]);
        self::assertResponseRedirects();
        self::assertSame(1, $this->queued());
        $this->assertStatistics(0, null);
        $this->assertApiStatistics(0, null);

        $this->consume(1);
        self::assertSame(0, $this->queued());
        $this->assertStatistics(1, 4.0);
        $this->assertApiStatistics(1, 4.0);

        $ascent = $this->manager->getRepository(Ascent::class)->findOneBy([]);
        $crawler = $this->client->request('GET', '/ascent/personnal/'.$ascent->getId());
        $this->client->submit($crawler->selectButton('Supprimer cette ascension')->form());
        self::assertResponseRedirects('/ascent/personnal');
        self::assertSame(1, $this->queued());
        $this->assertStatistics(1, 4.0);
        $this->assertApiStatistics(1, 4.0);

        $this->consume(1);
        $this->assertStatistics(0, null);
        $this->assertApiStatistics(0, null);
    }

    public function testMultipleAscentsAndDuplicateMessagesRecalculateTheCurrentState(): void
    {
        $this->createAscent(2);
        $this->createAscent(5);
        self::assertSame(2, $this->queued());
        $this->assertStatistics(0, null);
        $this->consume(2);
        $this->assertStatistics(2, 3.5);
        self::assertSame(1, $this->manager->find(Boulder::class, $this->boulderId)->getClimberCount());
        static::getContainer()->get(MessageBusInterface::class)->dispatch(new RecalculateBoulderStatistics($this->boulderId));
        $this->consume(1);
        $this->assertStatistics(2, 3.5);
    }

    public function testRolledBackAscentAlsoRollsBackTheQueuedMessage(): void
    {
        $connection = $this->manager->getConnection();
        $connection->beginTransaction();
        $this->createAscent(4);
        self::assertSame(1, $this->queued());
        $connection->rollBack();
        $this->manager->clear();
        self::assertSame(0, $this->queued());
        self::assertSame(0, $this->manager->getRepository(Ascent::class)->count([]));
        $this->assertStatistics(0, null);
    }

    public function testAccountDeletionAlsoQueuesRecalculation(): void
    {
        $this->createAscent(5);
        $this->consume(1);
        $this->assertStatistics(1, 5.0);
        static::getContainer()->get(AccountManager::class)->delete($this->manager->find(User::class, $this->userId));
        self::assertSame(1, $this->queued());
        $this->assertStatistics(1, 5.0);
        $this->consume(1);
        $this->assertStatistics(0, null);
    }

    public function testEditingRatingThroughDoctrineQueuesTheNewAverage(): void
    {
        $ascent = $this->createAscent(2);
        $id = $ascent->getId();
        $this->consume(1);
        $this->manager->find(Ascent::class, $id)->setRating(5);
        $this->manager->flush();
        self::assertSame(1, $this->queued());
        $this->assertStatistics(1, 2.0);
        $this->consume(1);
        $this->assertStatistics(1, 5.0);
    }

    public function testMessagesForDeletedBouldersAreAcknowledged(): void
    {
        $ascent = $this->createAscent(4);
        static::getContainer()->get(AscentManager::class)->delete($ascent);
        $this->manager->remove($this->manager->find(Boulder::class, $this->boulderId));
        $this->manager->flush();
        $this->consume(2);
        self::assertSame(0, $this->queued());
        self::assertSame(0, $this->failed());
    }

    public function testComputedStatisticsAreNotEditableInEasyAdmin(): void
    {
        $user = $this->manager->find(User::class, $this->userId);
        $user->setRoles(['ROLE_ADMIN']);
        $this->manager->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/boulder/'.$this->boulderId.'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[name$="[successCount]"]');
        self::assertSelectorNotExists('[name$="[averageRating]"]');
        self::assertSelectorNotExists('[name$="[climberCount]"]');
    }

    private function createAscent(int $rating): Ascent
    {
        $ascent = (new Ascent())->setBoulder($this->manager->find(Boulder::class, $this->boulderId))
            ->setCompletedAt(new \DateTimeImmutable('-1 day'))->setRating($rating);
        static::getContainer()->get(AscentManager::class)->create($ascent, $this->manager->find(User::class, $this->userId));

        return $ascent;
    }

    private function consume(int $limit): void
    {
        $application = new Application(self::$kernel);
        $command = new CommandTester($application->find('messenger:consume'));
        $command->execute(['receivers' => ['statistics'], '--limit' => $limit, '--time-limit' => 3, '--sleep' => 0, '--no-reset' => true], ['interactive' => false]);
        self::assertSame(0, $command->getStatusCode(), $command->getDisplay());
        self::assertSame(0, $this->failed(), $command->getDisplay());
        $this->manager->clear();
    }

    private function queued(): int
    {
        return (int) $this->manager->getConnection()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'statistics'");
    }

    private function failed(): int
    {
        return (int) $this->manager->getConnection()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'");
    }

    private function assertStatistics(int $count, ?float $average): void
    {
        $this->manager->clear();
        $boulder = $this->manager->find(Boulder::class, $this->boulderId);
        self::assertSame($count, $boulder->getSuccessCount());
        self::assertSame($average, $boulder->getAverageRating());
    }

    private function assertApiStatistics(int $count, ?float $average): void
    {
        $this->client->request('GET', '/api/blocs');
        self::assertResponseIsSuccessful();
        $block = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['blocs'][0];
        self::assertSame($count, $block['successCount']);
        self::assertEquals($average, $block['averageRating']);
    }
}
