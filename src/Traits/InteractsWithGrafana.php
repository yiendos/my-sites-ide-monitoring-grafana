<?php

namespace Yiendos\MySitesIde\Monitoring\Grafana\Traits;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drives the grafana compose service from the host. Every `docker compose`
 * call runs from the IDE root, as the my-sites-ide CLI is run there.
 */
trait InteractsWithGrafana
{
    /**
     * Whether the grafana container is up
     *
     * @return bool
     */
    protected function running(): bool
    {
        return trim((string) shell_exec('docker compose ps -q --status running grafana 2>/dev/null')) !== '';
    }

    /**
     * Runs `docker compose <arguments>`, echoing it first like the core commands do
     *
     * @param OutputInterface $output
     * @param string $arguments
     * @return int the exit code
     */
    protected function compose(OutputInterface $output, string $arguments): int
    {
        $output->writeLn("docker compose {$arguments}");
        passthru("docker compose {$arguments}", $code);

        return $code;
    }
}
