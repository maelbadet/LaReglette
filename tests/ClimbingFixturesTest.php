<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\ClimbingFixtures;
use App\Entity\Ascent;
use App\Entity\Boulder;
use App\Entity\Sector;
use App\Entity\User;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ClimbingFixturesTest extends KernelTestCase
{
    public function testFixturesCreateConsistentClimbingData(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires pdo_sqlite and DATABASE_URL=sqlite:///:memory:.');
        }
        self::bootKernel();
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        if ('pdo_sqlite' !== $manager->getConnection()->getParams()['driver']) {
            self::markTestSkipped('Requires an isolated SQLite in-memory database.');
        }
        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $loader = new Loader();
        // Add dependent fixtures first to exercise Doctrine's dependency ordering.
        $loader->addFixture(static::getContainer()->get(ClimbingFixtures::class));
        $loader->addFixture(static::getContainer()->get(AppFixtures::class));
        (new ORMExecutor($manager))->execute($loader->getFixtures(), true);
        $manager->clear();

        self::assertSame(24, $manager->getRepository(User::class)->count([]));
        $roles = ['ROLE_ADMIN' => 0, 'ROLE_OUVREUR' => 0, 'ROLE_GRIMPEUR' => 0];
        $climbers = [];
        foreach ($manager->getRepository(User::class)->findAll() as $user) {
            self::assertTrue($user->isVerified());
            self::assertTrue(password_verify('admin@admin.fr' === $user->getEmail() ? 'admin' : 'test', $user->getPassword()));
            foreach (array_keys($roles) as $role) {
                if (\in_array($role, $user->getRoles(), true)) {
                    ++$roles[$role];
                }
            }
            if (\in_array('ROLE_GRIMPEUR', $user->getRoles(), true)) {
                $climbers[] = $user;
            }
        }
        self::assertSame(['ROLE_ADMIN' => 1, 'ROLE_OUVREUR' => 3, 'ROLE_GRIMPEUR' => 20], $roles);
        foreach (['admin@admin.fr', 'open@open.fr', 'test@test.fr'] as $email) {
            self::assertNotNull($manager->getRepository(User::class)->findOneBy(['email' => $email]));
        }
        $sectors = $manager->getRepository(Sector::class)->findAll();
        self::assertCount(4, $sectors);
        $gradeSuccesses = [];
        $gradeCounts = [];
        $dismantled = 0;
        $openerIds = [];
        foreach ($sectors as $sector) {
            self::assertCount(10, $sector->getBoulders());
            foreach ($sector->getBoulders() as $boulder) {
                $grade = $boulder->getGrade();
                $gradeSuccesses[$grade] = ($gradeSuccesses[$grade] ?? 0) + $boulder->getAscents()->count();
                $gradeCounts[$grade] = ($gradeCounts[$grade] ?? 0) + 1;
                if (null !== $boulder->getDismantledAt()) {
                    ++$dismantled;
                    self::assertGreaterThan($boulder->getOpenedAt(), $boulder->getDismantledAt());
                    self::assertLessThanOrEqual(new \DateTimeImmutable(), $boulder->getDismantledAt());
                }
                self::assertNotNull($boulder->getOpenedBy());
                $openerIds[$boulder->getOpenedBy()->getId()] = true;
                self::assertContains('ROLE_OUVREUR', $boulder->getOpenedBy()->getRoles());
                self::assertNotEmpty($boulder->getColor());
                self::assertGreaterThanOrEqual(new \DateTimeImmutable('today -60 days'), $boulder->getOpenedAt());
                self::assertLessThanOrEqual(new \DateTimeImmutable(), $boulder->getOpenedAt());
            }
        }
        self::assertSame(10, $dismantled);
        self::assertCount(3, $openerIds);
        ksort($gradeCounts);
        self::assertSame(range(1, 18), array_keys($gradeCounts));
        for ($grade = 2; $grade <= 18; ++$grade) {
            self::assertLessThanOrEqual($gradeSuccesses[$grade - 1] / $gradeCounts[$grade - 1], $gradeSuccesses[$grade] / $gradeCounts[$grade]);
        }
        self::assertSame(40, $manager->getRepository(Boulder::class)->count([]));
        $ascents = $manager->getRepository(Ascent::class)->findAll();
        self::assertCount(300, $ascents);
        $climberIds = [];
        $pairs = [];
        $validator = static::getContainer()->get(ValidatorInterface::class);
        foreach ($ascents as $ascent) {
            self::assertCount(0, $validator->validate($ascent));
            self::assertContains('ROLE_GRIMPEUR', $ascent->getUser()->getRoles());
            self::assertGreaterThanOrEqual($ascent->getBoulder()->getOpenedAt(), $ascent->getCompletedAt());
            self::assertLessThanOrEqual(new \DateTimeImmutable(), $ascent->getCompletedAt());
            self::assertGreaterThanOrEqual(1, $ascent->getRating());
            self::assertLessThanOrEqual(5, $ascent->getRating());
            if (null !== $ascent->getBoulder()->getDismantledAt()) {
                self::assertLessThan($ascent->getBoulder()->getDismantledAt(), $ascent->getCompletedAt());
            }
            $pair = $ascent->getUser()->getId().':'.$ascent->getBoulder()->getId();
            self::assertArrayNotHasKey($pair, $pairs);
            $pairs[$pair] = true;
            $climberIds[$ascent->getUser()->getId()] = true;
        }
        self::assertCount(20, $climberIds);
        $boulders = $manager->getRepository(Boulder::class);
        $ascentRepository = $manager->getRepository(Ascent::class);
        foreach ($climbers as $climber) {
            self::assertNotEmpty($ascentRepository->getAscentByUser($climber->getId()));
            self::assertNotEmpty($boulders->getBoulderUndoByUSer($climber));
        }
        $inactive = $boulders->getBoulderUnSuccessSinceTimelaps(new \DateTimeImmutable('-2 weeks'));
        self::assertNotEmpty($inactive);
        $inactiveNames = array_map(static fn (Boulder $boulder): string => $boulder->getName(), $inactive);
        self::assertContains('Le Dévers — Bloc 08', $inactiveNames);
        self::assertContains('La Dalle — Bloc 06', $inactiveNames);
    }
}
