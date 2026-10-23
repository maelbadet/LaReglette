<?php

namespace App\Tests;

use App\Command\DismantleBouldersCommand;
use App\Entity\Ascent;
use App\Entity\Boulder;
use App\Entity\Sector;
use App\Entity\User;
use App\Repository\BoulderRepository;
use App\Service\BoulderManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DismantleBouldersCommandTest extends KernelTestCase
{
    private EntityManagerInterface $manager;
    private MockClock $clock;
    private Sector $sector;
    private int $oldId;
    private int $plannedId;
    private int $boundaryId;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires pdo_sqlite.');
        }
        self::bootKernel();
        $this->manager = static::getContainer()->get(EntityManagerInterface::class);
        $params = $this->manager->getConnection()->getParams();
        if ('pdo_sqlite' !== $params['driver'] || !($params['memory'] ?? false)) {
            self::markTestSkipped('Requires isolated SQLite memory database.');
        }
        (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());
        $this->clock = new MockClock('2026-10-21 08:00:00 UTC');
        $this->sector = (new Sector())->setName('La Dalle');
        $this->manager->persist($this->sector);
        $old = $this->block('Ancien candidat', '-7 weeks')->setSuccessCount(1)->setAverageRating(4.0);
        $boundary = $this->block('Exactement six semaines', '-6 weeks');
        $planned = $this->block('Démontage prévu', '-9 weeks')->setDismantledAt($this->clock->now()->modify('+1 day'));
        $this->block('Encore récent', '-5 weeks');
        $this->block('Déjà démonté', '-10 weeks')->setDismantledAt($this->clock->now()->modify('-1 day'));
        $this->block('Ouverture à venir', '+1 day');
        $this->block('Sans date', '-10 weeks')->setOpenedAt(null);
        $user = (new User())->setEmail('climber@example.com')->setFirstName('Camille')->setLastName('Martin')->setPassword('unused');
        $this->manager->persist($user);
        $ascent = (new Ascent())->setBoulder($old)->setUser($user)->setCompletedAt($this->clock->now()->modify('-1 day'))->setRating(4);
        $this->manager->persist($ascent);
        $this->manager->flush();
        $this->oldId = $old->getId();
        $this->plannedId = $planned->getId();
        $this->boundaryId = $boundary->getId();
    }

    public function testDryRunListsOnlyStrictlyOlderAvailableBlocks(): void
    {
        $command = $this->command();
        self::assertSame(Command::SUCCESS, $command->execute(['--dry-run' => true], ['interactive' => false]));
        $output = $command->getDisplay();
        self::assertStringContainsString('Ancien candidat', $output);
        self::assertStringContainsString('Démontage prévu', $output);
        self::assertStringContainsString('Secteur', $output);
        self::assertStringContainsString('La Dalle', $output);
        foreach (['Exactement six semaines', 'Encore récent', 'Déjà démonté', 'Ouverture à venir', 'Sans date'] as $excluded) {
            self::assertStringNotContainsString($excluded, $output);
        }
        $this->manager->clear();
        self::assertNull($this->manager->find(Boulder::class, $this->oldId)->getDismantledAt());
        self::assertGreaterThan($this->clock->now(), $this->manager->find(Boulder::class, $this->plannedId)->getDismantledAt());
    }

    public function testConfirmationDismantlesSelectedBlocksAndPreservesHistory(): void
    {
        $command = $this->command();
        $command->setInputs(['yes']);
        self::assertSame(Command::SUCCESS, $command->execute([], ['interactive' => true]));
        $this->manager->clear();
        foreach ([$this->oldId, $this->plannedId] as $id) {
            self::assertEquals($this->clock->now(), $this->manager->find(Boulder::class, $id)->getDismantledAt());
        }
        self::assertNull($this->manager->find(Boulder::class, $this->boundaryId)->getDismantledAt());
        self::assertSame(7, $this->manager->getRepository(Boulder::class)->count([]));
        self::assertSame(1, $this->manager->getRepository(Ascent::class)->count([]));
        self::assertSame(1, $this->manager->find(Boulder::class, $this->oldId)->getSuccessCount());
        self::assertSame(4.0, $this->manager->find(Boulder::class, $this->oldId)->getAverageRating());
    }

    public function testRefusalAndDefaultAnswerMakeNoChanges(): void
    {
        foreach (['no', ''] as $answer) {
            $command = $this->command();
            $command->setInputs([$answer]);
            self::assertSame(Command::SUCCESS, $command->execute([], ['interactive' => true]));
            self::assertStringContainsString('annulé', $command->getDisplay());
            $this->manager->clear();
            self::assertNull($this->manager->find(Boulder::class, $this->oldId)->getDismantledAt());
        }
    }

    public function testNonInteractiveExecutionCannotDismantle(): void
    {
        self::assertSame(Command::FAILURE, $this->command()->execute([], ['interactive' => false]));
        $this->manager->clear();
        self::assertNull($this->manager->find(Boulder::class, $this->oldId)->getDismantledAt());
    }

    public function testWeeksOptionAndEmptySelection(): void
    {
        $command = $this->command();
        self::assertSame(Command::SUCCESS, $command->execute(['--semaines' => 8, '--dry-run' => true], ['interactive' => false]));
        self::assertStringContainsString('Démontage prévu', $command->getDisplay());
        self::assertStringNotContainsString('Ancien candidat', $command->getDisplay());
        $empty = $this->command();
        self::assertSame(Command::SUCCESS, $empty->execute(['--semaines' => 52], ['interactive' => false]));
        self::assertStringContainsString('Aucun bloc', $empty->getDisplay());
    }

    public function testInvalidWeeksAreRejectedWithoutChanges(): void
    {
        foreach (['0', '-1', '1.5', 'abc', '', '9999999999999999999999999'] as $weeks) {
            self::assertSame(Command::INVALID, $this->command()->execute(['--semaines' => $weeks], ['interactive' => false]));
        }
        $this->manager->clear();
        self::assertNull($this->manager->find(Boulder::class, $this->oldId)->getDismantledAt());
    }

    private function command(): CommandTester
    {
        return new CommandTester(new DismantleBouldersCommand(
            static::getContainer()->get(BoulderRepository::class),
            static::getContainer()->get(BoulderManager::class),
            $this->clock,
        ));
    }

    private function block(string $name, string $opened): Boulder
    {
        $boulder = (new Boulder())->setName($name)->setColor('rouge')->setGrade(3)->setSector($this->sector)->setOpenedAt($this->clock->now()->modify($opened));
        $this->manager->persist($boulder);

        return $boulder;
    }
}
