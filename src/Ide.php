<?php

namespace Yiendos\MySitesIde\Monitoring\Grafana;

use RuntimeException;

/**
 * What this plugin needs to know about the IDE around it: where its files
 * live, and which other monitoring plugins are installed. Everything Grafana
 * keeps between containers goes in the IDE's storage/plugins/grafana/,
 * mounted at /storage ("storage": true in composer.json).
 *
 * The IDE root comes from IDE_ROOT, which the my-sites-ide bootstrap sets
 * before any plugin command runs (and which docker-compose.yml interpolates).
 */
final class Ide
{
    public const STORAGE = 'storage/plugins/grafana';

    /**
     * Plugins\Discover's record of the installed plugins
     */
    private const PLUGINS = '_dev/cache/plugins.php';

    /**
     * The my-sites-ide project root
     *
     * @return string
     */
    public static function root(): string
    {
        $root = getenv('IDE_ROOT');

        if ($root === false || $root === '') {
            throw new RuntimeException('IDE_ROOT is not set - run this command through the my-sites-ide CLI.');
        }

        return rtrim($root, '/');
    }

    /**
     * A path in the plugin's storage on the host, e.g. conf, created on first
     * use - Docker would otherwise create the bind mount itself, owned by root
     * on Linux hosts
     *
     * @param string $path
     * @return string
     */
    public static function storage(string $path = ''): string
    {
        $storage = self::root() . '/' . self::STORAGE;

        if (!is_dir($storage)) {
            mkdir($storage, 0755, true);
        }

        return $path === '' ? $storage : "{$storage}/{$path}";
    }

    /**
     * A file shipped with this package, e.g. dashboards/prometheus
     *
     * @param string $file
     * @return string
     */
    public static function package(string $file = ''): string
    {
        return dirname(__DIR__) . ($file === '' ? '' : "/{$file}");
    }

    /**
     * Whether an installed plugin provides the compose service, e.g. loki -
     * read from Plugins\Discover's cache rather than a composer dependency, so
     * the monitoring plugins work in any combination
     *
     * @param string $service
     * @return bool
     */
    public static function installed(string $service): bool
    {
        $cache = self::root() . '/' . self::PLUGINS;

        if (!is_file($cache)) {
            return false;
        }

        foreach ((array) require $cache as $plugin) {
            if (in_array($service, $plugin['services'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Writes a generated file into storage, reporting whether it changed - a
     * running container only reads its config when it starts
     *
     * @param string $path
     * @param string $contents
     * @return bool
     */
    public static function write(string $path, string $contents): bool
    {
        $file = self::storage($path);

        if (is_file($file) && file_get_contents($file) === $contents) {
            return false;
        }

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, $contents);

        return true;
    }
}
