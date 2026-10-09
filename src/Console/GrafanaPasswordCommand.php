<?php

namespace Yiendos\MySitesIde\Monitoring\Grafana\Console;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Monitoring\Grafana\Traits\InteractsWithGrafana;

class GrafanaPasswordCommand extends Command
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
            ->setName('monitoring:grafana-password')
            ->setDescription('Set the Grafana admin user\'s password')
        ;
    }

    /**
     * GRAFANA_ADMIN_PASSWORD only applies when Grafana creates its database,
     * so this is how the password changes afterwards. It asks for the password
     * when none is given, keeping it out of the shell history, and hands it to
     * `grafana cli` on stdin rather than as an argument, so it never shows up
     * in a process list.
     *
     * Changing it in Grafana's UI works too - nothing here sets it back.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @param string|null $password
     * @return integer
     */
    public function __invoke(
        OutputInterface $output,
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'The new password - asked for when left out')] ?string $password = null,
    ): int {
        if (!$this->running()) {
            $io->error('Grafana is not running - start it with monitoring:grafana-start first.');
            return Command::FAILURE;
        }

        $password ??= $io->askHidden('New password for the Grafana admin user');

        if ($password === null || $password === '') {
            $io->error('No password given - nothing changed.');
            return Command::FAILURE;
        }

        $output->writeln('docker compose exec -T grafana grafana cli admin reset-admin-password --password-from-stdin');

        // grafana cli logs Grafana's whole startup - only worth showing when it fails
        $process = proc_open(
            'docker compose exec -T grafana grafana cli admin reset-admin-password --password-from-stdin 2>&1',
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            $io->error('Could not run docker compose.');
            return Command::FAILURE;
        }

        fwrite($pipes[0], $password . "\n");
        fclose($pipes[0]);
        $log = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        if (proc_close($process) !== 0) {
            $output->write($log);
            $io->error('The password was not changed - see above.');
            return Command::FAILURE;
        }

        $io->success('Grafana\'s admin password is changed - log in as admin.');

        return Command::SUCCESS;
    }
}
