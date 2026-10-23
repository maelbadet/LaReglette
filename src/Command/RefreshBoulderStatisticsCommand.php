<?php

namespace App\Command;

use App\Message\RecalculateBoulderStatistics;
use App\Repository\BoulderRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:boulders:refresh-statistics', description: 'Place le recalcul des statistiques de tous les blocs dans la file Messenger.')]
class RefreshBoulderStatisticsCommand extends Command
{
    public function __construct(private BoulderRepository $boulders, private MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = 0;
        foreach ($this->boulders->createQueryBuilder('b')->select('b.id')->getQuery()->toIterable() as $row) {
            $this->bus->dispatch(new RecalculateBoulderStatistics((int) $row['id']));
            ++$count;
        }
        $output->writeln(sprintf('%d recalcul(s) placé(s) dans la file statistics. Lancez messenger:consume statistics pour les traiter.', $count));

        return Command::SUCCESS;
    }
}
