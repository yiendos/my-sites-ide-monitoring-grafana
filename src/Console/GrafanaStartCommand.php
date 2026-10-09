<?php

namespace Yiendos\MySitesIde\Monitoring\Grafana\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Monitoring\Grafana\Dashboards;
use Yiendos\MySitesIde\Monitoring\Grafana\Datasources;
use Yiendos\MySitesIde\Monitoring\Grafana\Ide;
use Yiendos\MySitesIde\Monitoring\Grafana\Traits\InteractsWithGrafana;

class GrafanaStartCommand extends Command
{
    use InteractsWithGrafana;

    /**
     * Where the data sources are written, under storage/plugins/grafana/
     */
    private const DATASOURCES = 'provisioning/datasources/my-sites-ide.yaml';

    /**
     * Where the dashboard provider is written, under storage/plugins/grafana/
     */
    private const DASHBOARDS = 'provisioning/dashboards/my-sites-ide.yaml';

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('monitoring:grafana-start')
            ->setDescription('Write Grafana\'s data sources and dashboards for the monitoring plugins installed, then start Grafana')
        ;
    }

    /**
     * Writes the data sources and dashboards first, from the monitoring
     * plugins installed right now - so run it again after adding or removing
     * one. Grafana only reads its provisioning when it starts, so a running
     * Grafana is restarted when the data sources or the dashboard provider
     * changed; it picks up the dashboards themselves as they change.
     *
     * `up -d --build` is a no-op for an up-to-date container, recreates one
     * whose compose config changed (e.g. a new GRAFANA_PORT), and builds the
     * image after an update to the Dockerfile.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $datasources = Datasources::fromIde();
        $dashboards = Dashboards::fromIde();

        // Grafana logs an error for each provisioning folder that's missing
        foreach (['data', Dashboards::STORAGE, 'provisioning/datasources', 'provisioning/dashboards', 'provisioning/plugins', 'provisioning/alerting'] as $folder) {
            if (!is_dir(Ide::storage($folder))) {
                mkdir(Ide::storage($folder), 0755, true);
            }
        }

        $changed = Ide::write(self::DATASOURCES, $datasources->yaml());
        $changed = Ide::write(self::DASHBOARDS, $dashboards->yaml()) || $changed;
        $dashboards->sync();
        $wasRunning = $this->running();

        $io->writeln($datasources->names() === []
            ? 'No data sources - none of the prometheus, loki or tempo plugins are installed'
            : 'Data sources: ' . implode(', ', $datasources->names()));
        $io->writeln($dashboards->folders() === []
            ? 'No dashboards - they come with the prometheus plugin'
            : 'Dashboards: ' . implode(', ', array_map(fn (string $folder): string => "{$folder} folder", $dashboards->folders())));

        if ($this->compose($output, 'up -d --build grafana') !== 0) {
            $io->error('Grafana did not start - see above.');
            return Command::FAILURE;
        }

        if ($changed && $wasRunning && $this->compose($output, 'restart grafana') !== 0) {
            $io->error('Grafana did not restart with the new provisioning - see above.');
            return Command::FAILURE;
        }

        $io->success('Grafana started - http://localhost:' . (getenv('GRAFANA_PORT') ?: '3000'));

        return Command::SUCCESS;
    }
}
