<?php

class ZenAiAssistAnswerService
{
    private ZenAiAssistComparisonService $comparison;
    private ?ZenAiAssistSkillService $skills;
    private ?ZenAiAssistDoctorService $doctor;

    public function __construct(
        ?ZenAiAssistComparisonService $comparison = null,
        ?ZenAiAssistSkillService $skills = null,
        ?ZenAiAssistDoctorService $doctor = null
    )
    {
        $this->comparison = $comparison ?? new ZenAiAssistComparisonService();
        $this->skills = $skills;
        $this->doctor = $doctor;
    }

    public function answer(array $docsIndex, array $repoIndex, string $question, int $limit = 3): array
    {
        $comparison = $this->comparison->compare($docsIndex, $repoIndex, $question, $limit);

        return [
            'question' => $question,
            'query_type' => $comparison['query_type'] ?? ['categories' => ['generic']],
            'documented_approach' => $this->summarizeDocs($comparison['docs'] ?? []),
            'current_repo_behavior' => $this->summarizeRepo($comparison['repo'] ?? []),
            'mismatch_note' => $this->buildMismatchNote($comparison),
            'confidence' => (string)($comparison['confidence'] ?? 'none'),
            'docs' => $comparison['docs'] ?? [],
            'repo' => $comparison['repo'] ?? [],
        ];
    }

    public function answerWithSkillContext(
        array $docsIndex,
        array $repoIndex,
        string $question,
        int $limit = 3,
        int $skillLimit = 3,
        ?string $pluginRoot = null
    ): array
    {
        $answer = $this->answer($docsIndex, $repoIndex, $question, $limit);
        $skillMatches = $this->skills?->matchSkill($question, $skillLimit) ?? ['task' => $question, 'matches' => []];
        $recommendedSkill = $skillMatches['matches'][0] ?? null;
        $loadedSkill = null;

        if (is_array($recommendedSkill) && !empty($recommendedSkill['id']) && $this->skills !== null) {
            $loadedSkill = $this->skills->getSkill((string)$recommendedSkill['id']);
        }

        $pluginContext = $this->buildPluginContext($loadedSkill, $pluginRoot);

        return $answer + [
            'recommended_skill' => $recommendedSkill,
            'recommended_skill_detail' => $loadedSkill,
            'skill_matches' => $skillMatches['matches'] ?? [],
            'workflow_hint' => $this->buildWorkflowHint($loadedSkill),
            'plugin_context' => $pluginContext,
            'recommended_next_steps' => $this->buildRecommendedNextSteps($loadedSkill, $pluginContext, $answer),
        ];
    }

    private function summarizeDocs(array $records): string
    {
        if ($records === []) {
            return 'No matching official documentation evidence was found in the local Zen AI Assist cache.';
        }

        $record = $records[0];
        $title = (string)($record['title'] ?? 'Untitled docs record');
        $heading = $this->headingText($record);
        $excerpt = $this->shortText((string)($record['excerpt'] ?? ($record['content'] ?? '')));
        $url = (string)($record['url'] ?? '');

        $parts = ['Top docs match: ' . $title . ($heading === '' ? '' : ' (' . $heading . ')') . '.'];
        if ($excerpt !== '') {
            $parts[] = $excerpt;
        }
        if ($url !== '') {
            $parts[] = 'Source: ' . $url;
        }

        return implode(' ', $parts);
    }

    private function summarizeRepo(array $records): string
    {
        if ($records === []) {
            return 'No matching repository evidence was found in the local Zen Cart catalog.';
        }

        $record = $records[0];
        $path = (string)($record['path'] ?? 'unknown path');
        $title = (string)($record['title'] ?? basename($path));
        $excerpt = $this->shortText((string)($record['excerpt'] ?? ($record['content'] ?? '')));

        $parts = ['Top repo match: ' . $title . ' at ' . $path . '.'];
        if ($excerpt !== '') {
            $parts[] = $excerpt;
        }

        return implode(' ', $parts);
    }

    private function buildMismatchNote(array $comparison): string
    {
        $docs = $comparison['docs'] ?? [];
        $repo = $comparison['repo'] ?? [];
        $categories = $comparison['query_type']['categories'] ?? ['generic'];
        $categoryText = implode(', ', array_map('strval', $categories));

        if ($docs !== [] && $repo !== []) {
            return 'Both docs and code evidence were found for a `' . $categoryText . '` query. Prefer docs for intended conventions and code for the current runtime behavior.';
        }

        if ($docs !== []) {
            return 'Documentation evidence exists without a matching repo hit for a `' . $categoryText . '` query. The implementation may live outside the indexed paths or may not exist yet.';
        }

        if ($repo !== []) {
            return 'Repository evidence exists without a matching docs hit for a `' . $categoryText . '` query. Treat the current code as runtime truth and verify whether the docs are incomplete or outdated.';
        }

        return 'No docs or code evidence was found for this question in the current local catalogs.';
    }

    private function headingText(array $record): string
    {
        $headingPath = $record['heading_path'] ?? [];
        if (!is_array($headingPath) || $headingPath === []) {
            return '';
        }

        return implode(' > ', array_map('strval', $headingPath));
    }

    private function shortText(string $text, int $limit = 220): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 3)) . '...';
    }

    private function buildWorkflowHint(?array $skill): string
    {
        if (!is_array($skill) || !($skill['found'] ?? false)) {
            return 'No specific Zen AI Assist workflow skill matched this question. Fall back to the docs and repo evidence.';
        }

        $title = (string)($skill['title'] ?? ($skill['id'] ?? 'Matched skill'));
        $workflowSteps = is_array($skill['workflow_steps'] ?? null) ? $skill['workflow_steps'] : [];

        if ($workflowSteps === []) {
            return 'Recommended skill: ' . $title . '.';
        }

        $firstStep = trim((string)$workflowSteps[0]);
        if ($firstStep === '') {
            return 'Recommended skill: ' . $title . '.';
        }

        return 'Recommended skill: ' . $title . '. Start with: ' . $firstStep;
    }

    private function buildRecommendedNextSteps(?array $skill, array $pluginContext, array $answer): array
    {
        $steps = [];

        if (is_array($skill) && ($skill['found'] ?? false)) {
            foreach (array_slice($skill['workflow_steps'] ?? [], 0, 3) as $step) {
                $step = trim((string)$step);
                if ($step !== '') {
                    $steps[] = $step;
                }
            }
        }

        if (($pluginContext['status'] ?? '') === 'plugin-root-required') {
            $steps[] = 'Provide `plugin_root` so Zen AI Assist can attach encapsulated-plugin doctor and installed-plugin runtime context.';
        } elseif (($pluginContext['status'] ?? '') === 'attached') {
            $steps[] = 'Use the attached `plugin_doctor` findings and installed-plugin state before changing encapsulated plugin bootstrap wiring.';
        }

        if (($answer['docs'] ?? []) === []) {
            $steps[] = 'Verify whether the needed convention is missing from the cached docs set or lives outside the current documentation snapshot.';
        }

        if (($answer['repo'] ?? []) === []) {
            $steps[] = 'Inspect whether the implementation lives outside the indexed repo paths or has not been added yet.';
        }

        return array_values(array_unique($steps));
    }

    private function buildPluginContext(?array $skill, ?string $pluginRoot): array
    {
        if (!$this->isPluginOrientedSkill($skill)) {
            return [
                'status' => 'not-applicable',
                'message' => 'The matched skill does not require plugin runtime inspection.',
            ];
        }

        $pluginRoot = is_string($pluginRoot) ? trim($pluginRoot) : '';
        if ($pluginRoot === '') {
            return [
                'status' => 'plugin-root-required',
                'message' => 'The matched skill is plugin-oriented. Provide `plugin_root` to attach plugin doctor and installed-plugin context.',
            ];
        }

        if ($this->doctor === null) {
            return [
                'status' => 'doctor-unavailable',
                'plugin_root' => $pluginRoot,
                'message' => 'Plugin runtime inspection is not configured for this answer flow.',
            ];
        }

        $doctor = $this->doctor->diagnose($pluginRoot);

        return [
            'status' => 'attached',
            'plugin_root' => $pluginRoot,
            'message' => 'Plugin doctor and installed-plugin context were attached because the matched skill is plugin-oriented.',
            'installed_state' => $doctor['checks']['installed_state'] ?? null,
            'doctor' => $doctor,
        ];
    }

    private function isPluginOrientedSkill(?array $skill): bool
    {
        if (!is_array($skill) || !($skill['found'] ?? false)) {
            return false;
        }

        $tags = is_array($skill['tags'] ?? null) ? array_map('strtolower', array_map('strval', $skill['tags'])) : [];
        foreach (['plugin', 'admin', 'storefront', 'installer', 'observer', 'doctor'] as $tag) {
            if (in_array($tag, $tags, true)) {
                return true;
            }
        }

        $rules = is_array($skill['validation_rules'] ?? null) ? $skill['validation_rules'] : [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (strtolower((string)($rule['root'] ?? '')) === 'plugin') {
                return true;
            }
        }

        return false;
    }
}
