<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Command;

use ParagonIE\CipherSweet\Planner\FieldIndexPlanner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'odb:enc:planner',
    description: 'Determine the Blind Index plan for a given field.',
    aliases: ['o:e:pl']
)]
class FieldIndexPlannerCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $estimatedRows = (int) $io->ask('How many rows do you anticipate ?');

        $planner = new FieldIndexPlanner();
        $planner->setEstimatedPopulation($estimatedRows);
        $reco = $planner->recommend();

        $io->success("Please use : ");
        $io->table(['min', 'max'], [[$reco['min'], $reco['max']]]);

        return Command::SUCCESS;
    }
}
