<?php

class ZenAiAssistComparisonService
{
    private ZenAiAssistSearchService $search;

    public function __construct(?ZenAiAssistSearchService $search = null)
    {
        $this->search = $search ?? new ZenAiAssistSearchService();
    }

    public function compare(array $docsIndex, array $repoIndex, string $query, int $limit = 3): array
    {
        $queryProfile = $this->search->classifyQuery($query);
        $docsResults = $this->search->searchDocs($docsIndex, $query, $limit);
        $repoResults = $this->search->searchRepo($repoIndex, $query, $limit);

        return [
            'query' => $query,
            'query_type' => $queryProfile,
            'docs' => $docsResults,
            'repo' => $repoResults,
            'summary' => $this->buildSummary($docsResults, $repoResults),
            'confidence' => $this->confidence($docsResults, $repoResults, $queryProfile),
        ];
    }

    private function buildSummary(array $docsResults, array $repoResults): string
    {
        if ($docsResults === [] && $repoResults === []) {
            return 'No matching docs or code evidence was found.';
        }

        if ($docsResults !== [] && $repoResults !== []) {
            return 'Found both documentation guidance and repository implementation evidence.';
        }

        if ($docsResults !== []) {
            return 'Found documentation guidance, but no matching repository implementation evidence.';
        }

        return 'Found repository implementation evidence, but no matching documentation guidance.';
    }

    private function confidence(array $docsResults, array $repoResults, array $queryProfile): string
    {
        $topDocsScore = (int)($docsResults[0]['_score'] ?? 0);
        $topRepoScore = (int)($repoResults[0]['_score'] ?? 0);
        $categories = $queryProfile['categories'] ?? [];

        if ($docsResults !== [] && $repoResults !== []) {
            if ($topDocsScore >= 20 && $topRepoScore >= 20 && !in_array('generic', $categories, true)) {
                return 'high';
            }

            return 'medium';
        }

        if ($docsResults !== [] || $repoResults !== []) {
            return 'low';
        }

        return 'none';
    }
}
