<?php

namespace Yiendos\MySitesIde\Monitoring\Grafana\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Monitoring\Grafana\Traits\InteractsWithGrafana;

class GrafanaStopCommand extends Command
{
    use InteractsWithGrafana;

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('monitoring:grafana-stop')
            ->setDescription('Stop the Grafana container, leaving the rest of the IDE running')
        ;
    }

    /**
     * Stopped rather than removed, so monitoring:grafana-start brings back
     * the same container. Its data is kept in storage/plugins/grafana/
     * either way.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if (!$this->running()) {
            $io->writeln('Grafana is not running - nothing to stop.');
            return Command::SUCCESS;
        }

        if ($this->compose($output, 'stop grafana') !== 0) {
            $io->error('Grafana did not stop - see above.');
            return Command::FAILURE;
        }

        $io->success('Grafana stopped.');

        return Command::SUCCESS;
    }
}
