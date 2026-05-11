<?php

class ZenAiAssistSkillService
{
    private string $skillsDirectory;

    public function __construct(string $skillsDirectory)
    {
        $this->skillsDirectory = rtrim($skillsDirectory, '/\\') . '/';
    }

    public function listSkills(): array
    {
        $skills = [];

        foreach ($this->loadSkills() as $skill) {
            $skills[] = $this->summarizeSkill($skill);
        }

        usort($skills, static function (array $left, array $right): int {
            return [(string)$left['title'], (string)$left['id']] <=> [(string)$right['title'], (string)$right['id']];
        });

        return $skills;
    }

    public function getSkill(string $skillId): array
    {
        $skillId = trim($skillId);
        if ($skillId === '') {
            return [
                'id' => $skillId,
                'found' => false,
                'message' => 'Skill id is required.',
            ];
        }

        foreach ($this->loadSkills() as $skill) {
            if (($skill['id'] ?? '') !== $skillId) {
                continue;
            }

            $skill['found'] = true;

            return $skill;
        }

        return [
            'id' => $skillId,
            'found' => false,
            'message' => 'Skill not found.',
        ];
    }

    public function matchSkill(string $task, int $limit = 3): array
    {
        $task = trim($task);
        $limit = max(1, $limit);

        if ($task === '') {
            return [
                'task' => $task,
                'matches' => [],
            ];
        }

        $matches = [];
        foreach ($this->loadSkills() as $skill) {
            $score = $this->scoreSkill($skill, $task);
            if ($score <= 0) {
                continue;
            }

            $matches[] = $this->summarizeSkill($skill) + ['score' => $score];
        }

        usort($matches, static function (array $left, array $right): int {
            return ($right['score'] ?? 0) <=> ($left['score'] ?? 0);
        });

        return [
            'task' => $task,
            'matches' => array_slice($matches, 0, $limit),
        ];
    }

    public function validateSkill(string $skillId, array $context = []): array
    {
        $skill = $this->getSkill($skillId);
        if (!($skill['found'] ?? false)) {
            return [
                'ok' => false,
                'skill' => $skill,
                'checks' => [],
                'summary' => 'Skill could not be validated because it was not found.',
            ];
        }

        $rules = is_array($skill['validation_rules'] ?? null) ? $skill['validation_rules'] : [];
        $checks = [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $checks[] = $this->evaluateRule($rule, $context);
        }

        $passed = count(array_filter($checks, static fn (array $check): bool => ($check['status'] ?? '') === 'passed'));
        $failed = count(array_filter($checks, static fn (array $check): bool => ($check['status'] ?? '') === 'failed'));
        $skipped = count(array_filter($checks, static fn (array $check): bool => ($check['status'] ?? '') === 'skipped'));

        if ($checks === []) {
            $summary = 'Skill does not define validation rules yet.';
        } elseif ($failed > 0) {
            $summary = 'One or more skill validation checks failed.';
        } elseif ($passed > 0 && $skipped === 0) {
            $summary = 'All skill validation checks passed.';
        } else {
            $summary = 'Skill validation checks are incomplete because some checks were skipped.';
        }

        return [
            'ok' => $checks !== [] && $failed === 0 && $skipped === 0,
            'skill' => $skill,
            'checks' => $checks,
            'summary' => $summary,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    public function listTopics(): array
    {
        $topics = [];

        foreach ($this->listSkills() as $skill) {
            $topics[] = [
                'topic' => $skill['id'],
                'title' => $skill['title'],
                'path' => $skill['path'],
            ];
        }

        return $topics;
    }

    public function readTopic(string $topic): array
    {
        $skill = $this->getSkill($topic);
        if (!($skill['found'] ?? false)) {
            return [
                'topic' => $topic,
                'found' => false,
                'message' => (string)($skill['message'] ?? 'Skill topic not found.'),
            ];
        }

        return [
            'topic' => $topic,
            'found' => true,
            'path' => $skill['path'] ?? null,
            'content' => $skill['content'] ?? '',
            'skill' => $skill,
        ];
    }

    private function loadSkills(): array
    {
        $catalogSkills = $this->loadCatalogSkills();
        $catalogSkillIds = array_map(static fn (array $skill): string => (string)($skill['id'] ?? ''), $catalogSkills);
        $fallbackSkills = $this->loadFallbackMarkdownSkills($catalogSkillIds);

        return array_merge($catalogSkills, $fallbackSkills);
    }

    private function loadCatalogSkills(): array
    {
        $path = $this->skillsDirectory . 'catalog.json';
        if (!is_file($path)) {
            return [];
        }

        $contents = @file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return [];
        }

        $skills = is_array($decoded['skills'] ?? null) ? $decoded['skills'] : [];
        $normalized = [];

        foreach ($skills as $skill) {
            if (!is_array($skill)) {
                continue;
            }

            $skillId = trim((string)($skill['id'] ?? ''));
            if ($skillId === '') {
                continue;
            }

            $contentFile = trim((string)($skill['content_file'] ?? ''));
            $contentPath = $contentFile === '' ? null : $this->skillsDirectory . ltrim($contentFile, '/\\');
            $content = '';
            if ($contentPath !== null && is_file($contentPath)) {
                $loaded = @file_get_contents($contentPath);
                if (is_string($loaded)) {
                    $content = $loaded;
                }
            }

            $normalized[] = [
                'id' => $skillId,
                'title' => trim((string)($skill['title'] ?? $skillId)),
                'summary' => trim((string)($skill['summary'] ?? '')),
                'intent' => trim((string)($skill['intent'] ?? '')),
                'tags' => $this->normalizeList($skill['tags'] ?? []),
                'when_to_use' => $this->normalizeList($skill['when_to_use'] ?? []),
                'required_context' => $this->normalizeList($skill['required_context'] ?? []),
                'source_refs' => $this->normalizeList($skill['source_refs'] ?? []),
                'workflow_steps' => $this->normalizeList($skill['workflow_steps'] ?? []),
                'validation_steps' => $this->normalizeList($skill['validation_steps'] ?? []),
                'anti_patterns' => $this->normalizeList($skill['anti_patterns'] ?? []),
                'expected_outputs' => $this->normalizeList($skill['expected_outputs'] ?? []),
                'validation_rules' => is_array($skill['validation_rules'] ?? null) ? array_values($skill['validation_rules']) : [],
                'content_file' => $contentFile === '' ? null : $contentFile,
                'path' => $contentPath,
                'content' => $content,
            ];
        }

        return $normalized;
    }

    private function loadFallbackMarkdownSkills(array $catalogSkillIds): array
    {
        if (!is_dir($this->skillsDirectory)) {
            return [];
        }

        $fallbackSkills = [];
        $knownSkillIds = array_fill_keys($catalogSkillIds, true);

        foreach (glob($this->skillsDirectory . '*.md') ?: [] as $path) {
            $skillId = basename($path, '.md');
            if (isset($knownSkillIds[$skillId])) {
                continue;
            }

            $contents = @file_get_contents($path);
            if (!is_string($contents)) {
                continue;
            }

            $title = $skillId;
            $summary = '';
            $lines = preg_split('/\R/', $contents) ?: [];
            if (isset($lines[0]) && str_starts_with(trim((string)$lines[0]), '# ')) {
                $title = trim(substr(trim((string)$lines[0]), 2));
            }

            foreach ($lines as $line) {
                $trimmed = trim((string)$line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }

                $summary = $trimmed;
                break;
            }

            $fallbackSkills[] = [
                'id' => $skillId,
                'title' => $title,
                'summary' => $summary,
                'intent' => '',
                'tags' => [],
                'when_to_use' => [],
                'required_context' => [],
                'source_refs' => [],
                'workflow_steps' => [],
                'validation_steps' => [],
                'anti_patterns' => [],
                'expected_outputs' => [],
                'validation_rules' => [],
                'content_file' => basename($path),
                'path' => $path,
                'content' => $contents,
            ];
        }

        return $fallbackSkills;
    }

    private function summarizeSkill(array $skill): array
    {
        return [
            'id' => (string)($skill['id'] ?? ''),
            'title' => (string)($skill['title'] ?? ''),
            'summary' => (string)($skill['summary'] ?? ''),
            'tags' => $this->normalizeList($skill['tags'] ?? []),
            'path' => $skill['path'] ?? null,
        ];
    }

    private function scoreSkill(array $skill, string $task): int
    {
        $terms = $this->terms($task);
        $fullTask = mb_strtolower(trim($task));
        $score = 0;

        $fields = [
            'id' => 8,
            'title' => 10,
            'summary' => 8,
            'intent' => 8,
            'tags' => 7,
            'when_to_use' => 6,
            'workflow_steps' => 4,
            'expected_outputs' => 5,
            'content' => 2,
        ];

        foreach ($fields as $field => $weight) {
            $haystack = $this->skillText($skill[$field] ?? '');

            foreach ($terms as $term) {
                if ($term !== '' && str_contains($haystack, $term)) {
                    $score += $weight;
                }
            }

            if ($fullTask !== '' && str_contains($haystack, $fullTask)) {
                $score += $weight + 4;
            }
        }

        return $score;
    }

    private function evaluateRule(array $rule, array $context): array
    {
        $type = trim((string)($rule['type'] ?? ''));
        $description = trim((string)($rule['description'] ?? ''));
        $root = $this->resolveValidationRoot((string)($rule['root'] ?? 'plugin'), $context);

        if ($root === null) {
            return [
                'type' => $type,
                'description' => $description,
                'status' => 'skipped',
                'message' => 'No matching root path was provided for this validation rule.',
            ];
        }

        return match ($type) {
            'path_exists' => $this->evaluatePathExistsRule($rule, $root, $description),
            'glob_exists' => $this->evaluateGlobExistsRule($rule, $root, $description),
            default => [
                'type' => $type,
                'description' => $description,
                'status' => 'skipped',
                'message' => 'Unsupported validation rule type.',
            ],
        };
    }

    private function evaluatePathExistsRule(array $rule, string $root, string $description): array
    {
        $path = trim((string)($rule['path'] ?? ''));
        if ($path === '') {
            return [
                'type' => 'path_exists',
                'description' => $description,
                'status' => 'skipped',
                'message' => 'Validation rule is missing a path.',
            ];
        }

        $absolutePath = $root . ltrim($path, '/\\');
        $exists = file_exists($absolutePath);

        return [
            'type' => 'path_exists',
            'description' => $description,
            'status' => $exists ? 'passed' : 'failed',
            'path' => $path,
            'resolved_path' => $absolutePath,
            'message' => $exists ? 'Required path exists.' : 'Required path is missing.',
        ];
    }

    private function evaluateGlobExistsRule(array $rule, string $root, string $description): array
    {
        $pattern = trim((string)($rule['pattern'] ?? ''));
        if ($pattern === '') {
            return [
                'type' => 'glob_exists',
                'description' => $description,
                'status' => 'skipped',
                'message' => 'Validation rule is missing a pattern.',
            ];
        }

        $absolutePattern = $root . ltrim($pattern, '/\\');
        $matches = glob($absolutePattern, GLOB_BRACE) ?: [];

        return [
            'type' => 'glob_exists',
            'description' => $description,
            'status' => $matches !== [] ? 'passed' : 'failed',
            'pattern' => $pattern,
            'resolved_pattern' => $absolutePattern,
            'matches' => array_values($matches),
            'message' => $matches !== [] ? 'At least one matching path exists.' : 'No matching paths were found.',
        ];
    }

    private function resolveValidationRoot(string $rootType, array $context): ?string
    {
        $rootType = strtolower(trim($rootType));

        $root = match ($rootType) {
            'plugin' => $context['plugin_root'] ?? null,
            'project' => $context['project_root'] ?? null,
            'root', 'custom' => $context['root_path'] ?? null,
            default => null,
        };

        if (!is_string($root) || trim($root) === '') {
            return null;
        }

        return rtrim($root, '/\\') . '/';
    }

    private function normalizeList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $normalized[] = $item;
            }
        }

        return array_values($normalized);
    }

    private function skillText(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(' ', array_map('strval', $value));
        }

        return mb_strtolower(trim((string)$value));
    }

    private function terms(string $query): array
    {
        $pieces = preg_split('/\s+/', mb_strtolower(trim($query))) ?: [];

        return array_values(array_filter($pieces, static fn (string $term): bool => $term !== ''));
    }
}
