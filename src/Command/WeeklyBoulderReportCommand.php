<?php

namespace App\Command;

use App\Service\WeeklyBoulderReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:blocs:rapport', description: 'Génère le rapport des blocs sans réussite depuis deux semaines, le journalise et l’envoie par e-mail.')]
class WeeklyBoulderReportCommand extends Command
{
    public function __construct(private WeeklyBoulderReport $report)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->report->send();
        (new SymfonyStyle($input, $output))->success(sprintf('Rapport généré et transmis au mailer : %d candidat(s). Aucun bloc démonté.', $count));

        return Command::SUCCESS;
    }
}
