<?php

class ZenAiAssistRepoCatalogBuilder
{
    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/\\') . '/';
    }

    public function build(): array
    {
        $records = [];
        foreach ($this->targetPaths() as $relativePath) {
            $absolutePath = $this->projectRoot . $relativePath;
            if (is_dir($absolutePath)) {
                foreach ($this->scanDirectory($absolutePath) as $filePath) {
                    $record = $this->recordForFile($filePath);
                    if ($record !== null) {
                        $records[] = $record;
                    }
                }
                continue;
            }

            if (is_file($absolutePath)) {
                $record = $this->recordForFile($absolutePath);
                if ($record !== null) {
                    $records[] = $record;
                }
            }
        }

        return [
            'generated_at' => gmdate('c'),
            'records' => $records,
        ];
    }

    private function targetPaths(): array
    {
        return [
            'includes/application_top.php',
            'includes/classes',
            'includes/init_includes',
            'includes/modules/pages',
            'includes/templates',
            'admin/includes/classes',
            'admin/includes/init_includes',
            'docs',
            'zc_plugins',
        ];
    }

    private function scanDirectory(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $path = $fileInfo->getPathname();
            if ($this->shouldSkip($path)) {
                continue;
            }

            if (!preg_match('/\.(php|md|txt|html)$/i', $path)) {
                continue;
            }

            $files[] = $path;
        }

        return $files;
    }

    private function shouldSkip(string $path): bool
    {
        foreach (['vendor/', '.git/', 'node_modules/', 'resources/docs-cache/', 'resources/catalogs/', 'cache/zen-ai-assist/'] as $needle) {
            if (str_contains($path, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function recordForFile(string $path): ?array
    {
        $contents = @file_get_contents($path);
        if (!is_string($contents) || trim($contents) === '') {
            return null;
        }

        $relativePath = ltrim(str_replace($this->projectRoot, '', $path), '/');
        $symbols = $this->extractSymbols($contents);
        $metadata = $this->classifyPath($relativePath, $contents);

        return [
            'type' => 'repo',
            'path' => $relativePath,
            'title' => basename($path),
            'symbols' => $symbols,
            'constants' => $this->extractConstants($contents),
            'line_count' => substr_count($contents, "\n") + 1,
            'path_tokens' => $this->pathTokens($relativePath),
            'excerpt' => $this->excerpt($contents),
            'content' => $contents,
        ] + $metadata;
    }

    private function extractSymbols(string $contents): array
    {
        $symbols = [];

        if (preg_match_all('/^\s*(class|trait|interface)\s+([A-Za-z0-9_]+)/m', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $symbols[] = $match[2];
            }
        }

        if (preg_match_all('/^\s*function\s+([A-Za-z0-9_]+)/m', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $symbols[] = $match[1];
            }
        }

        if (preg_match_all('/^\s*(public|protected|private)\s+function\s+([A-Za-z0-9_]+)/m', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $symbols[] = $match[2];
            }
        }

        return array_values(array_unique($symbols));
    }

    private function excerpt(string $contents): string
    {
        $contents = preg_replace('/\s+/u', ' ', $contents);

        return mb_substr(trim((string)$contents), 0, 240);
    }

    private function extractConstants(string $contents): array
    {
        if (!preg_match_all('/\b([A-Z][A-Z0-9_]{2,})\b/', $contents, $matches)) {
            return [];
        }

        $constants = array_values(array_unique($matches[1]));
        sort($constants);

        return array_slice($constants, 0, 50);
    }

    private function pathTokens(string $relativePath): array
    {
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower($relativePath)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));

        return array_values(array_unique($tokens));
    }

    private function classifyPath(string $relativePath, string $contents): array
    {
        $role = 'source-file';
        $plugin = null;
        $page = null;
        $side = 'shared';
        $relationships = [];
        $queryHints = [];

        if (preg_match('#^zc_plugins/([^/]+)/([^/]+)/#', $relativePath, $matches) === 1) {
            $plugin = [
                'key' => $matches[1],
                'version' => $matches[2],
            ];
            $role = 'plugin-file';
        }

        if (str_contains($relativePath, '/catalog/')) {
            $side = 'catalog';
        } elseif (str_contains($relativePath, '/admin/')) {
            $side = 'admin';
        } elseif (str_contains($relativePath, '/tests/')) {
            $side = 'tests';
        }

        if (str_contains($relativePath, '/manifest.php')) {
            $role = 'plugin-manifest';
            $relationships = $this->manifestRelationships($relativePath);
        } elseif (preg_match('#^zc_plugins/[^/]+/[^/]+/filenames\.php$#', $relativePath) === 1) {
            $role = 'plugin-filenames';
        } elseif (preg_match('#^zc_plugins/[^/]+/[^/]+/admin/([^/]+)\.php$#', $relativePath, $matches) === 1) {
            $role = 'admin-page-entrypoint';
            $page = strtolower($matches[1]);
            $relationships = $this->adminPageRelationships($relativePath, $page);
        } elseif (str_contains($relativePath, '/Installer/')) {
            $role = 'plugin-installer';
        } elseif (preg_match('#(?:^|/)includes/modules/pages/([^/]+)/#', $relativePath, $matches) === 1) {
            $role = 'page-module';
            $page = $matches[1];
            $relationships = $this->pageModuleRelationships($relativePath, $page);
        } elseif (preg_match('#tpl_([a-z0-9_]+)#i', basename($relativePath), $matches) === 1) {
            $role = 'template';
            $page = strtolower($matches[1]);
        } elseif (preg_match('#lang\.([a-z0-9_]+)\.php$#i', basename($relativePath), $matches) === 1) {
            $role = 'language-file';
            $page = strtolower($matches[1]);
        } elseif (preg_match('#/tests/Unit/.*Test\.php$#', $relativePath) === 1) {
            $role = 'test-unit';
        } elseif (preg_match('#/tests/FeatureStore/.*Test\.php$#', $relativePath) === 1) {
            $role = 'test-feature-store';
        } elseif (preg_match('#/tests/FeatureAdmin/.*Test\.php$#', $relativePath) === 1) {
            $role = 'test-feature-admin';
        } elseif (preg_match('#/tests/bootstrap\.php$#', $relativePath) === 1) {
            $role = 'test-bootstrap';
        } elseif (preg_match('#/tests/plugin-test\.php$#', $relativePath) === 1) {
            $role = 'test-metadata';
        } elseif (str_contains($relativePath, '/observers/')) {
            $role = 'observer-class';
        } elseif (str_contains($relativePath, 'auto_loaders')) {
            $role = 'autoload-config';
        } elseif (str_contains($relativePath, 'extra_datafiles')) {
            $role = 'extra-datafile';
        } elseif (str_contains($relativePath, 'extra_configures')) {
            $role = 'extra-configure';
        } elseif (preg_match('/\.md$/i', $relativePath)) {
            $role = 'documentation';
        }

        $queryHints = $this->queryHints($role, $plugin, $page, $side, $relationships, $relativePath);

        return [
            'role' => $role,
            'plugin' => $plugin,
            'page' => $page,
            'side' => $side,
            'relationships' => $relationships,
            'query_hints' => $queryHints,
            'has_output_protection' => str_contains($contents, 'zen_output_string_protected'),
        ];
    }

    private function manifestRelationships(string $relativePath): array
    {
        $root = dirname($relativePath);
        $relationships = [];

        foreach ([
            'plugin-filenames' => $root . '/filenames.php',
            'plugin-installer' => $root . '/Installer/ScriptedInstaller.php',
            'plugin-tests' => $root . '/tests/plugin-test.php',
        ] as $type => $path) {
            if (is_file($this->projectRoot . $path)) {
                $relationships[] = ['type' => $type, 'path' => $path];
            }
        }

        return $relationships;
    }

    private function pageModuleRelationships(string $relativePath, string $page): array
    {
        $relationships = [];
        $base = preg_match('#^zc_plugins/[^/]+/[^/]+/#', $relativePath, $matches) === 1 ? $matches[0] : '';

        $candidates = [];
        if ($base !== '') {
            $candidates = [
                'language-file' => $base . 'catalog/includes/languages/english/lang.' . $page . '.php',
                'template-file' => $base . 'catalog/includes/templates/template_default/tpl_' . $page . '.php',
                'plugin-filenames' => $base . 'filenames.php',
            ];
        } else {
            $candidates = [
                'language-file' => 'includes/languages/english/lang.' . $page . '.php',
            ];
        }

        foreach ($candidates as $type => $path) {
            if (is_file($this->projectRoot . $path)) {
                $relationships[] = ['type' => $type, 'path' => $path];
            }
        }

        return $relationships;
    }

    private function adminPageRelationships(string $relativePath, string $page): array
    {
        $root = preg_match('#^(zc_plugins/[^/]+/[^/]+/)#', $relativePath, $matches) === 1 ? $matches[1] : '';
        if ($root === '') {
            return [];
        }

        $relationships = [];
        foreach ([
            'language-file' => $root . 'admin/includes/languages/english/lang.' . $page . '.php',
            'menu-definition' => $root . 'admin/includes/languages/english/extra_definitions/lang.' . $page . '_menu.php',
            'plugin-installer' => $root . 'Installer/ScriptedInstaller.php',
        ] as $type => $path) {
            if (is_file($this->projectRoot . $path)) {
                $relationships[] = ['type' => $type, 'path' => $path];
            }
        }

        return $relationships;
    }

    private function queryHints(string $role, ?array $plugin, ?string $page, string $side, array $relationships, string $relativePath): array
    {
        $hints = [$role, $side];

        if ($plugin !== null) {
            $hints[] = $plugin['key'];
            $hints[] = $plugin['version'];
            $hints[] = 'encapsulated-plugin';
        }

        if (is_string($page) && $page !== '') {
            $hints[] = $page;
        }

        foreach ($relationships as $relationship) {
            if (!is_array($relationship)) {
                continue;
            }

            $type = trim((string)($relationship['type'] ?? ''));
            if ($type !== '') {
                $hints[] = $type;
            }
        }

        $hints[] = basename($relativePath, '.php');

        $hints = array_values(array_unique(array_filter(array_map(static function (mixed $hint): string {
            return trim(strtolower((string)$hint));
        }, $hints), static fn (string $hint): bool => $hint !== '')));

        return $hints;
    }
}
