<?php

declare(strict_types=1);

namespace Hibla\SchemaManager\Console\Traits;

use Hibla\QueryBuilder\Utilities\ConfigResolver;
use Rcalicdan\ConfigLoader\Config;

trait LoadsSeederConfiguration
{
    /**
     * Get the resolved seeder configuration.
     *
     * @return array{
     *     seeds_path: string,
     *     recursive: bool,
     *     connection_paths: array<string, string>
     * }
     */
    private function getSeederConfig(?string $connection = null): array
    {
        $defaults = $this->getDefaultSeederConfig();
        $loadedConfig = $this->loadSeederConfigSafely($connection);
        $finalConfig = array_merge($defaults, $loadedConfig);

        return [
            'seeds_path' => \is_string($finalConfig['seeds_path'] ?? null) ? $finalConfig['seeds_path'] : $defaults['seeds_path'],
            'recursive' => isset($finalConfig['recursive']) ? (bool) $finalConfig['recursive'] : $defaults['recursive'],
            'connection_paths' => $this->normalizeConnectionPaths($finalConfig['connection_paths'] ?? null, $defaults['connection_paths']),
        ];
    }

    /**
     * Safely load the seeder configuration.
     *
     * @return array<string, mixed>
     */
    private function loadSeederConfigSafely(?string $connection): array
    {
        try {
            $config = ConfigResolver::getSeedersConfig();

            if (! \is_array($config)) {
                return [];
            }

            /** @var array<string, mixed> $baseConfig */
            $baseConfig = $config;

            if ($connection === null) {
                return $baseConfig;
            }

            $connections = $baseConfig['connections'] ?? null;
            if (! \is_array($connections)) {
                return $baseConfig;
            }

            $connectionConfig = $connections[$connection];
            if (! \is_array($connectionConfig)) {
                return $baseConfig;
            }

            /** @var array<string, mixed> */
            return array_merge($baseConfig, $connectionConfig);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Normalize connection paths.
     *
     * @param mixed $connectionPaths
     * @param array<string, string> $defaults
     *
     * @return array<string, string>
     */
    private function normalizeConnectionPaths($connectionPaths, array $defaults): array
    {
        if (! \is_array($connectionPaths)) {
            return $defaults;
        }

        $normalized = [];
        foreach ($connectionPaths as $key => $value) {
            if (\is_string($key) && \is_string($value)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @return array{
     *     seeds_path: string,
     *     recursive: bool,
     *     connection_paths: array<string, string>
     * }
     */
    private function getDefaultSeederConfig(): array
    {
        $projectRoot = isset($this->projectRoot) && \is_string($this->projectRoot)
            ? $this->projectRoot
            : '.';

        return [
            'seeds_path' => $projectRoot . '/database/seeders',
            'recursive' => true,
            'connection_paths' => [],
        ];
    }

    /**
     * Get the seeds path for a specific connection.
     * Supports subdirectories for connection-specific organization.
     */
    private function getSeedsPath(?string $connection = null): string
    {
        $config = $this->getSeederConfig($connection);
        $basePath = $config['seeds_path'];

        $projectRoot = isset($this->projectRoot) && \is_string($this->projectRoot)
            ? $this->projectRoot
            : '.';

        $realRoot = Config::getRootPath();

        if ($realRoot !== null && $projectRoot !== $realRoot) {
            $basePath = str_replace($realRoot, $projectRoot, $basePath);
        }

        if (! $this->isAbsolutePath($basePath)) {
            $basePath = $projectRoot . '/' . ltrim($basePath, '/');
        }

        if ($connection === null) {
            return $basePath;
        }

        $connectionPaths = $config['connection_paths'];

        if (isset($connectionPaths[$connection])) {
            $subPath = $connectionPaths[$connection];
            if (\is_string($subPath) && $subPath !== '') {
                return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . trim($subPath, '/\\');
            }
        }

        return $basePath;
    }

    private function isRecursiveEnabled(?string $connection = null): bool
    {
        $config = $this->getSeederConfig($connection);

        return $config['recursive'];
    }

    /**
     * Get all seeder files recursively or non-recursively.
     *
     * @return list<string>
     */
    private function getAllSeederFiles(?string $connection = null): array
    {
        $seedsPath = $this->getSeedsPath($connection);

        if (! is_dir($seedsPath)) {
            return [];
        }

        if ($this->isRecursiveEnabled($connection)) {
            return $this->getSeederFilesRecursive($seedsPath);
        }

        return $this->getSeederFilesFlat($seedsPath);
    }

    /**
     * Get seeder files from flat directory.
     *
     * @return list<string>
     */
    private function getSeederFilesFlat(string $directory): array
    {
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }

    /**
     * Get seeder files recursively.
     *
     * @return list<string>
     */
    private function getSeederFilesRecursive(string $directory): array
    {
        $files = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        } catch (\Throwable $e) {
            return $this->getSeederFilesFlat($directory);
        }

        sort($files);

        return $files;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/') {
            return true;
        }

        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1) {
            return true;
        }

        return false;
    }
}
