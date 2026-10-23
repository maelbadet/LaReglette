<?php

namespace App\Tests;

use App\Entity\Ascent;
use App\Entity\Boulder;
use App\Entity\Sector;
use App\Entity\User;
use App\Message\GenerateWeeklyBoulderReport;
use App\MessageHandler\GenerateWeeklyBoulderReportHandler;
use App\Repository\BoulderRepository;
use App\Schedule;
use App\Service\WeeklyBoulderReport;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class WeeklyBoulderReportTest extends KernelTestCase
{
    private EntityManagerInterface $manager;
    private MockClock $clock;
    private Sector $sector;
    private User $user;

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
        $this->user = (new User())->setEmail('climber@example.com')->setFirstName('Camille')->setLastName('Martin')->setPassword('unused');
        $this->manager->persist($this->sector);
        $this->manager->persist($this->user);
    }

    public function testReportUsesQueryThreeWithoutDismantlingAndPreservesEmailBranding(): void
    {
        $never = $this->block('Jamais réussi', '-30 days');
        $old = $this->block('Ancienne réussite', '-30 days');
        $atOpeningBoundary = $this->block('Ouvert depuis exactement deux semaines', '-14 days');
        $this->ascent($old, '-15 days');
        $recent = $this->block('Réussite récente', '-30 days');
        $this->ascent($recent, '-1 day');
        $boundary = $this->block('Réussite à la limite', '-30 days');
        $this->ascent($boundary, '-14 days');
        $this->block('Ouvert récemment', '-13 days');
        $this->block('Démonté', '-30 days')->setDismantledAt($this->clock->now()->modify('-1 day'));
        $this->block('À venir', '+1 day');
        $this->manager->flush();
        $captured = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(static function (TemplatedEmail $email) use (&$captured): void {
            $captured = $email;
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::anything(), self::callback(static fn (array $context): bool => 3 === $context['count'] && 3 === count($context['boulders'])));
        $report = new WeeklyBoulderReport(static::getContainer()->get(BoulderRepository::class), $this->clock, $logger, $mailer, 'team@example.com', 'contact@lareglette.fr');
        (new GenerateWeeklyBoulderReportHandler($report))(new GenerateWeeklyBoulderReport());

        self::assertInstanceOf(TemplatedEmail::class, $captured);
        self::assertSame('team@example.com', $captured->getTo()[0]->getAddress());
        self::assertSame('La Réglette', $captured->getFrom()[0]->getName());
        $context = $captured->getContext();
        self::assertEqualsCanonicalizing(['Jamais réussi', 'Ancienne réussite', 'Ouvert depuis exactement deux semaines'], array_column($context['boulders'], 'name'));
        self::assertStringContainsString('sans réussite depuis 2 semaines', $captured->getSubject());
        self::assertSame('21/10/2026 à 10:00', $context['generatedAt']);
        $twig = static::getContainer()->get(Environment::class);
        $html = $twig->render($captured->getHtmlTemplate(), $context);
        $text = $twig->render($captured->getTextTemplate(), $context);
        self::assertStringContainsString('LA RÉGLETTE', $html);
        self::assertStringContainsString('#e32636', $html);
        self::assertStringContainsString('#0d0d10', $html);
        self::assertStringContainsString('Jamais réussi', $text);
        self::assertStringContainsString('Ancienne réussite', $text);
        self::assertStringNotContainsString('Réussite récente', $html);
        self::assertNull($never->getDismantledAt());
        self::assertNull($old->getDismantledAt());
        self::assertSame(8, $this->manager->getRepository(Boulder::class)->count([]));
    }

    public function testEmptyReportStillHasUsefulHtmlAndText(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(function (TemplatedEmail $email): void {
            $twig = static::getContainer()->get(Environment::class);
            self::assertStringContainsString('Aucun bloc ne correspond', $twig->render($email->getHtmlTemplate(), $email->getContext()));
            self::assertStringContainsString('Aucun bloc ne correspond', $twig->render($email->getTextTemplate(), $email->getContext()));
        });
        $report = new WeeklyBoulderReport(static::getContainer()->get(BoulderRepository::class), $this->clock, $this->createStub(LoggerInterface::class), $mailer, 'team@example.com', 'contact@lareglette.fr');
        self::assertSame(0, $report->send());
    }

    public function testScheduleRunsMondayAtEightParisTimeAcrossDaylightSaving(): void
    {
        $schedule = static::getContainer()->get(Schedule::class)->getSchedule();
        self::assertCount(1, $schedule->getRecurringMessages());
        $message = array_values($schedule->getRecurringMessages())[0];
        $next = $message->getTrigger()->getNextRunDate(new \DateTimeImmutable('2026-10-21 12:00:00 UTC'));
        self::assertSame('2026-10-26 08:00:00 +01:00', $next->format('Y-m-d H:i:s P'));
        $summer = $message->getTrigger()->getNextRunDate(new \DateTimeImmutable('2026-07-01 12:00:00 UTC'));
        self::assertSame('2026-07-06 08:00:00 +02:00', $summer->format('Y-m-d H:i:s P'));
    }

    private function block(string $name, string $opened): Boulder
    {
        $boulder = (new Boulder())->setName($name)->setColor('rouge')->setGrade(3)->setSector($this->sector)->setOpenedAt($this->clock->now()->modify($opened));
        $this->manager->persist($boulder);

        return $boulder;
    }

    private function ascent(Boulder $boulder, string $completed): void
    {
        $ascent = (new Ascent())->setBoulder($boulder)->setUser($this->user)->setCompletedAt($this->clock->now()->modify($completed))->setRating(4);
        $this->manager->persist($ascent);
    }
}
