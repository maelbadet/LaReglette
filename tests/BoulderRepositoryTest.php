<?php

namespace App\Tests;

use App\Entity\Ascent;
use App\Entity\Boulder;
use App\Entity\Sector;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BoulderRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $manager;
    private User $climber;
    private User $other;
    private Sector $sector;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires pdo_sqlite and DATABASE_URL=sqlite:///:memory:.');
        }
        self::bootKernel();
        $this->manager = static::getContainer()->get(EntityManagerInterface::class);
        if ('pdo_sqlite' !== $this->manager->getConnection()->getParams()['driver']) {
            self::markTestSkipped('Requires an isolated SQLite in-memory database.');
        }
        (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());
        $this->now = new \DateTimeImmutable('today');
        $this->climber = (new User())->setEmail('climber@example.com')->setFirstName('Camille')->setLastName('Martin')->setPassword('unused');
        $this->other = (new User())->setEmail('other@example.com')->setFirstName('Autre')->setLastName('Grimpeur')->setPassword('unused');
        $this->sector = (new Sector())->setName('Zone test');
        foreach ([$this->climber, $this->other, $this->sector] as $entity) {
            $this->manager->persist($entity);
        }
    }

    public function testUnfinishedBlocksOnlyExcludeTheSelectedClimbersAscents(): void
    {
        $untouched = $this->block('Jamais réussi');
        $others = $this->block('Réussi par un autre');
        $this->ascent($others, $this->other, '-2 days');
        $this->ascent($others, $this->other, '-1 day');
        $mine = $this->block('Déjà réussi');
        $this->ascent($mine, $this->climber, '-3 days');
        $this->block('Démonté')->setDismantledAt($this->now->modify('-1 day'));
        $this->block('Pas encore ouvert', '+2 days');
        $this->block('Date inconnue')->setOpenedAt(null);
        $scheduled = $this->block('Démontage à venir')->setDismantledAt($this->now->modify('+2 days'));
        $this->manager->flush();

        $repository = $this->manager->getRepository(Boulder::class);
        $results = $repository->getBoulderUndoByUSer($this->climber);
        self::assertCount(3, $results);
        self::assertContains($untouched, $results);
        self::assertContains($others, $results);
        self::assertContains($scheduled, $results);
        self::assertNotContains($mine, $results);
        self::assertContains($mine, $repository->getBoulderUndoByUSer($this->other));
        self::assertNotContains($others, $repository->getBoulderUndoByUSer($this->other));
    }

    public function testInactiveBlocksIncludeOldAscentsAndNeverSucceededBlocks(): void
    {
        $since = $this->now->modify('-14 days');
        $untouched = $this->block('Ancien et jamais réussi');
        $old = $this->block('Anciennes réussites seulement');
        $this->ascent($old, $this->climber, '-20 days');
        $this->ascent($old, $this->other, '-15 days');
        $recent = $this->block('Réussite récente');
        $this->ascent($recent, $this->climber, '-20 days');
        $this->ascent($recent, $this->other, '-1 day');
        $boundary = $this->block('Réussite à la limite');
        $this->ascent($boundary, $this->other, '-14 days');
        $openedAtBoundary = $this->block('Ouvert à la limite', '-14 days');
        $this->block('Trop récent', '-13 days');
        $this->block('Démonté')->setDismantledAt($this->now->modify('-1 day'));
        $this->block('Pas encore ouvert', '+2 days');
        $this->block('Sans date')->setOpenedAt(null);
        $this->manager->flush();

        $results = $this->manager->getRepository(Boulder::class)->getBoulderUnSuccessSinceTimelaps($since);
        self::assertCount(3, $results);
        self::assertContains($untouched, $results);
        self::assertContains($old, $results);
        self::assertContains($openedAtBoundary, $results);
        self::assertNotContains($recent, $results);
        self::assertNotContains($boundary, $results);
    }

    private function block(string $name, string $opened = '-30 days'): Boulder
    {
        $boulder = (new Boulder())->setName($name)->setGrade(3)->setColor('rouge')->setSector($this->sector)->setOpenedBy($this->other)->setOpenedAt($this->now->modify($opened));
        $this->manager->persist($boulder);

        return $boulder;
    }

    private function ascent(Boulder $boulder, User $user, string $completed): void
    {
        $ascent = (new Ascent())->setBoulder($boulder)->setUser($user)->setCompletedAt($this->now->modify($completed))->setRating(4);
        $this->manager->persist($ascent);
    }
}
