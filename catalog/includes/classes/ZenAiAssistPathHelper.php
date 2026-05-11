<?php

class ZenAiAssistPathHelper
{
    private string $pluginRoot;
    private string $projectRoot;
    private string $physicalPluginRoot;

    public function __construct(string $pluginRoot, ?string $projectRoot = null, ?string $physicalPluginRoot = null)
    {
        $this->pluginRoot = rtrim($pluginRoot, '/\\') . '/';
        $this->physicalPluginRoot = rtrim((string)($physicalPluginRoot ?? $pluginRoot), '/\\') . '/';
        $this->projectRoot = self::resolveProjectRoot($projectRoot, $this->pluginRoot);
    }

    public static function fromCurrentFile(string $currentFile, ?string $projectRoot = null): self
    {
        $pluginRoot = dirname($currentFile, 2) . '/';

        return new self($pluginRoot, $projectRoot);
    }

    public static function resolveInstalledPluginRoot(string $physicalPluginRoot, ?string $projectRoot = null): ?string
    {
        $manifestPath = realpath(rtrim($physicalPluginRoot, '/\\') . '/manifest.php');
        if ($manifestPath === false) {
            return null;
        }

        $projectRoot = self::resolveProjectRoot($projectRoot, rtrim($physicalPluginRoot, '/\\') . '/');
        $pluginsDirectory = $projectRoot . 'zc_plugins/';
        if (!is_dir($pluginsDirectory)) {
            return null;
        }

        foreach (glob($pluginsDirectory . '*/' . '*/manifest.php') ?: [] as $candidateManifestPath) {
            $resolvedCandidateManifestPath = realpath($candidateManifestPath);
            if ($resolvedCandidateManifestPath === false || $resolvedCandidateManifestPath !== $manifestPath) {
                continue;
            }

            return dirname($candidateManifestPath) . '/';
        }

        return null;
    }

    public static function resolveProjectRoot(?string $projectRoot = null, ?string $pluginRoot = null): string
    {
        $candidates = [];

        if (is_string($projectRoot) && trim($projectRoot) !== '') {
            $candidates[] = $projectRoot;
        }

        foreach (['ZEN_AI_ASSIST_PROJECT_ROOT', 'ZEN_BOOST_PROJECT_ROOT'] as $projectRootEnvVar) {
            $envProjectRoot = getenv($projectRootEnvVar);
            if (is_string($envProjectRoot) && trim($envProjectRoot) !== '') {
                $candidates[] = $envProjectRoot;
            }
        }

        if (defined('DIR_FS_CATALOG') && is_string(DIR_FS_CATALOG) && DIR_FS_CATALOG !== '') {
            $candidates[] = DIR_FS_CATALOG;
        }

        $workingDirectory = getcwd();
        if (is_string($workingDirectory) && $workingDirectory !== '') {
            $candidates[] = $workingDirectory;
        }

        if (is_string($pluginRoot) && trim($pluginRoot) !== '') {
            $candidates[] = dirname(rtrim($pluginRoot, '/\\'), 3);
        }

        foreach ($candidates as $candidate) {
            $normalized = self::normalizeDirectory($candidate);
            if ($normalized === null) {
                continue;
            }

            if (is_dir($normalized . 'zc_plugins') && is_dir($normalized . 'includes')) {
                return $normalized;
            }
        }

        if (is_string($pluginRoot) && trim($pluginRoot) !== '') {
            return rtrim(dirname(rtrim($pluginRoot, '/\\'), 3), '/\\') . '/';
        }

        return './';
    }

    public function pluginRoot(): string
    {
        return $this->pluginRoot;
    }

    public function physicalPluginRoot(): string
    {
        return $this->physicalPluginRoot;
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    public function docsCacheDirectory(): string
    {
        return $this->cacheRoot() . 'docs-cache/';
    }

    public function catalogsDirectory(): string
    {
        return $this->cacheRoot() . 'catalogs/';
    }

    public function guidanceDirectory(): string
    {
        return $this->pluginRoot . 'resources/guidance/';
    }

    public function skillsDirectory(): string
    {
        return $this->pluginRoot . 'resources/skills/';
    }

    public function docsIndexPath(): string
    {
        return $this->catalogsDirectory() . 'docs-index.json';
    }

    public function repoIndexPath(): string
    {
        return $this->catalogsDirectory() . 'repo-index.json';
    }

    public function cacheRoot(): string
    {
        if (defined('DIR_FS_SQL_CACHE') && is_string(DIR_FS_SQL_CACHE) && DIR_FS_SQL_CACHE !== '') {
            return rtrim(DIR_FS_SQL_CACHE, '/\\') . '/zen-ai-assist/';
        }

        return $this->projectRoot . 'cache/zen-ai-assist/';
    }

    public function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    public function slugForUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? 'index';
        $path = trim($path, '/');
        $path = $path === '' ? 'index' : preg_replace('/[^a-z0-9]+/i', '-', $path);

        return strtolower(trim((string)$path, '-')) . '-' . substr(sha1($url), 0, 10);
    }

    public function listJsonFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, '/\\') . '/*.json');

        return $files === false ? [] : $files;
    }

    private static function normalizeDirectory(string $directory): ?string
    {
        $resolved = realpath($directory);
        if ($resolved === false || !is_dir($resolved)) {
            return null;
        }

        return rtrim($resolved, '/\\') . '/';
    }
}
