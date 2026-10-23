<?php

namespace App\Command;

use App\Entity\Boulder;
use App\Repository\BoulderRepository;
use App\Service\BoulderManager;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:blocs:demontage', description: 'Liste et démonte, après confirmation, les blocs en place depuis plus de N semaines.')]
class DismantleBouldersCommand extends Command
{
    public function __construct(
        private BoulderRepository $boulders,
        private BoulderManager $manager,
        private ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('semaines', null, InputOption::VALUE_REQUIRED, 'Ancienneté minimale en semaines', '6')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afficher la liste sans démonter les blocs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $weeks = filter_var($input->getOption('semaines'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $weeks) {
            $io->error('L’option --semaines doit être un entier strictement positif.');

            return Command::INVALID;
        }
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        try {
            $cutoff = $now->sub(new \DateInterval(sprintf('P%dW', $weeks)));
        } catch (\Exception) {
            $io->error('La durée indiquée avec --semaines est trop grande.');

            return Command::INVALID;
        }
        $boulders = $this->boulders->findAvailableOpenedBefore($cutoff, $now);
        $io->title(sprintf('Blocs en place depuis plus de %d semaine(s)', $weeks));
        $io->text('Ouverture antérieure au ' . $cutoff->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i') . ' (heure de Paris).');
        if ([] === $boulders) {
            $io->success('Aucun bloc à démonter pour cette ancienneté.');

            return Command::SUCCESS;
        }
        $io->table(['ID', 'Bloc', 'Secteur', 'Couleur', 'Cotation', 'Ouverture'], array_map(
            static fn(Boulder $boulder): array => [
                $boulder->getId(),
                $boulder->getName(),
                $boulder->getSector()->getName(),
                $boulder->getColor(),
                $boulder->getGradeLabel(),
                $boulder->getOpenedAt()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y H:i'),
            ],
            $boulders,
        ));
        if ($input->getOption('dry-run')) {
            $io->note(sprintf('Simulation : %d bloc(s) sélectionné(s). Aucune modification.', count($boulders)));

            return Command::SUCCESS;
        }
        if (!$input->isInteractive()) {
            $io->warning('Confirmation interactive requise. Relancez sans --no-interaction ou utilisez --dry-run.');

            return Command::FAILURE;
        }
        if (!$io->confirm(sprintf('Démonter ces %d bloc(s) ? Leur historique sera conservé.', count($boulders)), false)) {
            $io->note('Démontage annulé. Aucune modification.');

            return Command::SUCCESS;
        }
        $count = $this->manager->dismantleMany($boulders, $this->clock->now());
        $io->success(sprintf('%d bloc(s) démonté(s). Les ascensions et les statistiques sont conservées.', $count));

        return Command::SUCCESS;
    }
}
