<?php

class ZenAiAssistSearchService
{
    public function classifyQuery(string $query): array
    {
        $terms = $this->terms($query);
        $fullQuery = mb_strtolower(trim($query));
        $categories = [];

        $map = [
            'manifest' => ['manifest', 'pluginversion', 'pluginname'],
            'installer' => ['installer', 'scriptedinstaller', 'install', 'uninstall', 'upgrade'],
            'admin' => ['admin', 'menu', 'extra_definitions'],
            'storefront' => ['storefront', 'catalog', 'template', 'header_php'],
            'filename' => ['filename', 'filename_', 'constant', 'constants'],
            'observer' => ['observer', 'observers', 'auto_'],
            'tests' => ['test', 'tests', 'phpunit', 'featureadmin', 'featurestore', 'zcunittestcase'],
            'runtime' => ['runtime', 'bootstrap', 'plugin_doctor', 'installed', 'plugin manager', 'db'],
            'docs' => ['docs', 'documentation', 'guide', 'guidance'],
            'plugin' => ['plugin', 'plugins', 'encapsulated'],
        ];

        foreach ($map as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($fullQuery, $needle)) {
                    $categories[] = $category;
                    break;
                }
            }
        }

        if ($categories === []) {
            $categories[] = 'generic';
        }

        return [
            'query' => $query,
            'terms' => $terms,
            'categories' => array_values(array_unique($categories)),
            'full_query' => $fullQuery,
        ];
    }

    public function searchDocs(array $docsIndex, string $query, int $limit = 10): array
    {
        return $this->searchRecords($docsIndex['chunks'] ?? [], $query, $limit, 'docs');
    }

    public function searchRepo(array $repoIndex, string $query, int $limit = 10): array
    {
        return $this->searchRecords($repoIndex['records'] ?? [], $query, $limit, 'repo');
    }

    public function search(array $docsIndex, array $repoIndex, string $query, int $limit = 10): array
    {
        $results = array_merge(
            $this->searchDocs($docsIndex, $query, PHP_INT_MAX),
            $this->searchRepo($repoIndex, $query, PHP_INT_MAX)
        );

        usort($results, static function (array $left, array $right): int {
            return $right['_score'] <=> $left['_score'];
        });

        return array_slice($results, 0, $limit);
    }

    private function searchRecords(array $records, string $query, int $limit, string $type): array
    {
        $profile = $this->classifyQuery($query);
        $terms = $profile['terms'];
        $results = [];

        foreach ($records as $record) {
            $score = $this->scoreRecord($record, $terms, $type, $profile);
            if ($score <= 0) {
                continue;
            }

            $record['_score'] = $score;
            $results[] = $record;
        }

        usort($results, static function (array $left, array $right): int {
            return $right['_score'] <=> $left['_score'];
        });

        return array_slice($results, 0, $limit);
    }

    private function scoreRecord(array $record, array $terms, string $type, array $profile): int
    {
        $score = 0;
        $title = mb_strtolower((string)($record['title'] ?? ''));
        $content = mb_strtolower((string)($record['content'] ?? ''));
        $excerpt = mb_strtolower((string)($record['excerpt'] ?? ''));
        $path = mb_strtolower((string)($record['path'] ?? ''));
        $heading = mb_strtolower(implode(' ', $record['heading_path'] ?? []));
        $tags = mb_strtolower(implode(' ', $record['tags'] ?? []));
        $symbols = mb_strtolower(implode(' ', $record['symbols'] ?? []));
        $constants = mb_strtolower(implode(' ', $record['constants'] ?? []));
        $pathTokens = mb_strtolower(implode(' ', $record['path_tokens'] ?? []));
        $versions = mb_strtolower(implode(' ', $record['version_hints'] ?? []));
        $role = mb_strtolower((string)($record['role'] ?? ''));
        $page = mb_strtolower((string)($record['page'] ?? ''));
        $pluginKey = mb_strtolower((string)($record['plugin']['key'] ?? ''));
        $side = mb_strtolower((string)($record['side'] ?? ''));
        $relationshipTypes = mb_strtolower(implode(' ', array_map(static function (mixed $relationship): string {
            if (!is_array($relationship)) {
                return '';
            }

            return (string)($relationship['type'] ?? '');
        }, $record['relationships'] ?? [])));
        $queryHints = mb_strtolower(implode(' ', $record['query_hints'] ?? []));
        $fullQuery = mb_strtolower(trim(implode(' ', $terms)));

        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }

            if (str_contains($title, $term)) {
                $score += 10;
            }
            if (str_contains($heading, $term)) {
                $score += 8;
            }
            if (str_contains($tags, $term)) {
                $score += 7;
            }
            if (str_contains($path, $term)) {
                $score += 6;
            }
            if (str_contains($symbols, $term)) {
                $score += 6;
            }
            if (str_contains($constants, $term)) {
                $score += 6;
            }
            if (str_contains($pathTokens, $term)) {
                $score += 5;
            }
            if (str_contains($versions, $term)) {
                $score += 5;
            }
            if (str_contains($role, $term) || str_contains($page, $term) || str_contains($pluginKey, $term)) {
                $score += 5;
            }
            if (str_contains($side, $term) || str_contains($relationshipTypes, $term) || str_contains($queryHints, $term)) {
                $score += 5;
            }
            if (str_contains($excerpt, $term)) {
                $score += 4;
            }
            if (str_contains($content, $term)) {
                $score += 2;
            }
        }

        if ($fullQuery !== '' && (
            str_contains($title, $fullQuery)
            || str_contains($heading, $fullQuery)
            || str_contains($path, $fullQuery)
            || str_contains($content, $fullQuery)
        )) {
            $score += 12;
        }

        if ($type === 'docs') {
            $score += 1;
        }

        $score += $this->profileBoost($profile, $role, $side, $relationshipTypes, $queryHints, $pluginKey, $page);

        return $score;
    }

    private function profileBoost(
        array $profile,
        string $role,
        string $side,
        string $relationshipTypes,
        string $queryHints,
        string $pluginKey,
        string $page
    ): int {
        $score = 0;
        $categories = $profile['categories'] ?? [];

        foreach ($categories as $category) {
            $score += match ($category) {
                'manifest' => $role === 'plugin-manifest' ? 14 : (str_contains($relationshipTypes, 'plugin-filenames') ? 3 : 0),
                'installer' => $role === 'plugin-installer' ? 14 : (str_contains($relationshipTypes, 'plugin-installer') ? 4 : 0),
                'admin' => ($side === 'admin' || $role === 'admin-page-entrypoint') ? 10 : 0,
                'storefront' => ($side === 'catalog' || $role === 'page-module' || $role === 'template') ? 10 : 0,
                'filename' => ($role === 'plugin-filenames' || str_contains($queryHints, 'filename')) ? 12 : 0,
                'observer' => $role === 'observer-class' ? 12 : 0,
                'tests' => str_starts_with($role, 'test-') ? 14 : 0,
                'runtime' => ($role === 'autoload-config' || $role === 'extra-configure' || str_contains($queryHints, 'bootstrap')) ? 9 : 0,
                'plugin' => $pluginKey !== '' ? 6 : 0,
                'docs' => $role === 'documentation' ? 5 : 0,
                default => 0,
            };
        }

        if ($page !== '' && in_array('storefront', $categories, true) && ($role === 'page-module' || $role === 'template' || $role === 'language-file')) {
            $score += 4;
        }

        return $score;
    }

    private function terms(string $query): array
    {
        $pieces = preg_split('/\s+/', mb_strtolower(trim($query))) ?: [];

        return array_values(array_filter($pieces, static fn (string $term): bool => $term !== ''));
    }
}
