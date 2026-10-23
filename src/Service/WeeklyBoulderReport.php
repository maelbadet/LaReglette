<?php

namespace App\Service;

use App\Entity\Boulder;
use App\Repository\BoulderRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class WeeklyBoulderReport
{
    public function __construct(
        private BoulderRepository $boulders,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private MailerInterface $mailer,
        #[Autowire(env: 'BOULDER_REPORT_TO')] private string $recipient,
        #[Autowire(env: 'BOULDER_REPORT_FROM')] private string $sender,
    ) {}

    public function send(): int
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $since = $now->sub(new \DateInterval('P2W'));
        $boulders = $this->boulders->getBoulderUnSuccessSinceTimelaps($since, $now);
        // Store scalar values so queued e-mails never serialize Doctrine entities.
        $rows = array_map(static fn(Boulder $boulder): array => [
            'id' => $boulder->getId(),
            'name' => $boulder->getName(),
            'sector' => $boulder->getSector()->getName(),
            'color' => $boulder->getColor(),
            'grade' => $boulder->getGradeLabel(),
            'openedAt' => $boulder->getOpenedAt()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y'),
        ], $boulders);
        $paris = new \DateTimeZone('Europe/Paris');
        $context = ['generatedAt' => $now->setTimezone($paris)->format('d/m/Y à H:i'), 'since' => $since->setTimezone($paris)->format('d/m/Y à H:i'), 'boulders' => $rows];
        $this->logger->info('Rapport hebdomadaire des blocs en place sans réussite depuis au moins deux semaines : {count} candidat(s).', [
            'count' => count($rows),
            'since' => $since->format(\DateTimeInterface::ATOM),
            'boulders' => $rows,
        ]);
        $this->mailer->send((new TemplatedEmail())
            ->from(new Address($this->sender, 'La Réglette'))
            ->to($this->recipient)
            ->subject(sprintf('Blocs sans réussite depuis 2 semaines · %d candidat(s) · La Réglette', count($rows)))
            ->htmlTemplate('email/weekly_boulder_report.html.twig')
            ->textTemplate('email/weekly_boulder_report.txt.twig')
            ->context($context));

        return count($rows);
    }
}
