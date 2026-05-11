<?php

class ZenAiAssistDoctorService
{
    private string $projectRoot;
    private ZenAiAssistManifestInspector $manifestInspector;
    private ZenAiAssistInstallerInspector $installerInspector;
    private ZenAiAssistRuntimeInspector $runtimeInspector;

    public function __construct(
        string $projectRoot,
        ?ZenAiAssistManifestInspector $manifestInspector = null,
        ?ZenAiAssistInstallerInspector $installerInspector = null,
        ?ZenAiAssistRuntimeInspector $runtimeInspector = null
    ) {
        $this->projectRoot = rtrim($projectRoot, '/\\') . '/';
        $this->manifestInspector = $manifestInspector ?? new ZenAiAssistManifestInspector();
        $this->installerInspector = $installerInspector ?? new ZenAiAssistInstallerInspector();
        $this->runtimeInspector = $runtimeInspector ?? new ZenAiAssistRuntimeInspector($this->projectRoot, $this->projectRoot . 'zc_plugins/zen-ai-assist/v1.0.0/');
    }

    public function diagnose(string $path): array
    {
        $pluginRoot = $this->normalizePluginRoot($path);
        if ($pluginRoot === null) {
            $issues = [[
                'severity' => 'error',
                'message' => 'The provided path does not resolve to a plugin root with a manifest.',
            ]];

            return [
                'ok' => false,
                'message' => 'Plugin root could not be resolved.',
                'input' => $path,
                'checks' => [],
                'issues' => $issues,
                'issue_counts' => $this->countIssues($issues),
                'findings' => ['The provided path does not resolve to a plugin root with a manifest.'],
                'recommendations' => ['Pass a plugin root, manifest path, or Installer path.'],
            ];
        }

        $manifestPath = $pluginRoot . 'manifest.php';
        $manifest = $this->manifestInspector->inspect($manifestPath);
        $installer = $this->installerInspector->inspect($pluginRoot);

        [$pluginKey, $pluginVersion] = $this->resolvePluginIdentity($pluginRoot, $manifest);
        $installedState = $this->matchInstalledPlugin($pluginKey, $pluginVersion);
        $filenameLookup = $this->runtimeInspector->lookupFilenameConstant($pluginKey);
        $structure = $this->runtimeInspector->inspectPluginStructure($pluginRoot);

        $findings = [];
        if (!($manifest['ok'] ?? false)) {
            foreach ($manifest['missing'] ?? [] as $missing) {
                $findings[] = 'Manifest is missing `' . $missing . '`.';
            }
        }
        foreach ($installer['findings'] ?? [] as $finding) {
            $findings[] = $finding;
        }
        if (($installedState['status'] ?? null) === 'missing') {
            $findings[] = 'Plugin is not present in plugin manager state.';
        }
        if (($installedState['status'] ?? null) === 'version-mismatch') {
            $findings[] = 'Plugin manager state points to version `' . ($installedState['installed_version'] ?? '') . '` instead of `' . $pluginVersion . '`.';
        }
        if (($installedState['status'] ?? null) === 'runtime-unavailable') {
            $findings[] = 'Installed plugin state could not be inspected because the CLI runtime context is unavailable.';
        }
        foreach ($structure['findings'] ?? [] as $finding) {
            $findings[] = $finding;
        }

        if (
            ($structure['observers'] ?? []) === []
            && ($structure['autoloaders'] ?? []) === []
            && ($structure['extra_files'] ?? []) === []
            && $this->expectsExplicitBootstrapIntegration($pluginRoot, $structure)
        ) {
            $findings[] = 'Plugin does not currently expose observers, autoloaders, or extra configure/data files.';
        }

        if (($structure['skill_topics'] ?? []) === []) {
            $findings[] = 'Plugin does not bundle any task-specific skills under `resources/skills/`.';
        }

        $issues = $this->classifyIssues($findings);
        $issueCounts = $this->countIssues($issues);
        $recommendations = $this->buildRecommendations($manifest, $installer, $installedState, $structure);

        return [
            'ok' => $issueCounts['error'] === 0,
            'message' => $this->buildMessage($issueCounts),
            'plugin_root' => $pluginRoot,
            'plugin_key' => $pluginKey,
            'plugin_version' => $pluginVersion,
            'checks' => [
                'manifest' => $manifest,
                'installer' => $installer,
                'installed_state' => $installedState,
                'filename_lookup' => $filenameLookup,
                'structure' => $structure,
            ],
            'issues' => $issues,
            'issue_counts' => $issueCounts,
            'findings' => $findings,
            'recommendations' => $recommendations,
        ];
    }

    private function normalizePluginRoot(string $path): ?string
    {
        $resolved = realpath($path);
        if ($resolved === false) {
            return null;
        }

        if (is_file($resolved)) {
            $resolved = dirname($resolved);
        }

        $resolved = rtrim($resolved, '/\\') . '/';
        if (basename(rtrim($resolved, '/\\')) === 'Installer') {
            $resolved = dirname(rtrim($resolved, '/\\')) . '/';
        }

        if (is_file($resolved . 'manifest.php')) {
            return $resolved;
        }

        return null;
    }

    private function matchInstalledPlugin(string $pluginKey, string $pluginVersion): array
    {
        $plugins = $this->runtimeInspector->listInstalledPlugins('all');
        $warnings = $plugins['warnings'] ?? [];
        $pluginRows = $plugins['plugins'] ?? [];
        $runtimeState = (string)($plugins['runtime_state'] ?? 'repository-unavailable');

        if ($runtimeState !== 'available') {
            return [
                'status' => 'runtime-unavailable',
                'runtime_state' => $runtimeState,
                'runtime_state_category' => (string)($plugins['runtime_state_category'] ?? 'degraded'),
                'runtime_state_detail' => (string)($plugins['runtime_state_detail'] ?? ''),
                'warnings' => $warnings,
            ];
        }

        foreach ($pluginRows as $plugin) {
            if (($plugin['unique_key'] ?? '') !== $pluginKey) {
                continue;
            }

            $installedVersion = (string)($plugin['version'] ?? '');

            return [
                'status' => $installedVersion === $pluginVersion ? 'found' : 'version-mismatch',
                'plugin' => $plugin,
                'runtime_state' => $runtimeState,
                'runtime_state_category' => (string)($plugins['runtime_state_category'] ?? 'available'),
                'runtime_state_detail' => (string)($plugins['runtime_state_detail'] ?? ''),
                'warnings' => $warnings,
                'installed_version' => $installedVersion,
            ];
        }

        return [
            'status' => 'missing',
            'runtime_state' => $runtimeState,
            'runtime_state_category' => (string)($plugins['runtime_state_category'] ?? 'available'),
            'runtime_state_detail' => (string)($plugins['runtime_state_detail'] ?? ''),
            'warnings' => $warnings,
        ];
    }

    private function resolvePluginIdentity(string $pluginRoot, array $manifest): array
    {
        $normalizedRoot = rtrim($pluginRoot, '/\\');
        $segments = preg_split('#[\\\\/]#', $normalizedRoot) ?: [];
        $zcPluginsIndex = array_search('zc_plugins', $segments, true);

        if ($zcPluginsIndex !== false && isset($segments[$zcPluginsIndex + 1], $segments[$zcPluginsIndex + 2])) {
            return [$segments[$zcPluginsIndex + 1], $segments[$zcPluginsIndex + 2]];
        }

        $manifestArray = is_array($manifest['manifest'] ?? null) ? $manifest['manifest'] : [];
        $pluginVersion = (string)($manifestArray['pluginVersion'] ?? basename($normalizedRoot));
        $pluginName = (string)($manifestArray['pluginName'] ?? basename($normalizedRoot));
        $pluginKey = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $pluginName) ?? '', '-'));

        if ($pluginKey === '') {
            $pluginKey = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', basename($normalizedRoot)) ?? '', '-'));
        }

        return [$pluginKey, $pluginVersion];
    }

    private function buildRecommendations(array $manifest, array $installer, array $installedState, array $structure): array
    {
        $recommendations = [];

        if (!($manifest['ok'] ?? false)) {
            $recommendations[] = 'Complete the manifest baseline fields before relying on plugin manager workflows.';
        }
        if (!($installer['ok'] ?? false)) {
            $recommendations[] = 'Add the missing installer structure and language files so install and uninstall behavior is explicit.';
        }
        if (($installedState['status'] ?? '') === 'missing') {
            $recommendations[] = 'Install or enable the plugin through Plugin Manager if you want bootstrap discovery and runtime loading.';
        }
        if (($installedState['status'] ?? '') === 'runtime-unavailable') {
            $recommendations[] = $this->runtimeUnavailableRecommendation((string)($installedState['runtime_state'] ?? 'repository-unavailable'));
        }
        if (($structure['findings'] ?? []) !== []) {
            $recommendations[] = 'Add the missing page, language, or template files so the plugin surface is complete.';
        }
        if (
            ($structure['observers'] ?? []) === []
            && ($structure['autoloaders'] ?? []) === []
            && ($structure['extra_files'] ?? []) === []
            && $this->expectsExplicitBootstrapIntegration($this->normalizePluginRootFromChecks($structure), $structure)
        ) {
            $recommendations[] = 'If the plugin changes bootstrap behavior, add observers or loader files so the integration points are explicit.';
        }
        if (($structure['skill_topics'] ?? []) === []) {
            $recommendations[] = 'Bundle at least one task-focused skill so agents can follow a repeatable plugin workflow.';
        }
        if ($recommendations === []) {
            $recommendations[] = 'Next, verify page modules, language files, and any runtime logs for the plugin in a live checkout.';
        }

        return array_values(array_unique($recommendations));
    }

    private function runtimeUnavailableRecommendation(string $runtimeState): string
    {
        return match ($runtimeState) {
            'bootstrap-missing', 'bootstrap-unavailable' => 'Verify the Zen Cart CLI bootstrap is present and loadable if you want installed-plugin state checks in doctor output.',
            'db-config-unavailable' => 'Add working store DB configuration if you want installed-plugin state checks in doctor output.',
            'db-driver-unavailable' => 'Enable the PHP MySQL driver used by the Zen Cart CLI runtime if you want installed-plugin state checks in doctor output.',
            'db-connection-failed' => 'Fix store DB connectivity if you want installed-plugin state checks in doctor output.',
            'repository-helper-unavailable' => 'Restore the CLI plugin repository helper if you want installed-plugin state checks in doctor output.',
            default => 'Verify CLI bootstrap and DB connectivity if you want installed-plugin state checks in doctor output.',
        };
    }

    private function expectsExplicitBootstrapIntegration(?string $pluginRoot, array $structure): bool
    {
        if ($pluginRoot === null) {
            return true;
        }

        if (is_file(rtrim($pluginRoot, '/\\') . '/Console/commands.php')) {
            return false;
        }

        return !empty($structure['catalog_pages']) || !empty($structure['admin_pages']);
    }

    private function normalizePluginRootFromChecks(array $structure): ?string
    {
        $pluginRoot = $structure['plugin_root'] ?? null;

        return is_string($pluginRoot) && $pluginRoot !== '' ? $pluginRoot : null;
    }

    private function classifyIssues(array $findings): array
    {
        $issues = [];

        foreach ($findings as $finding) {
            $message = trim((string)$finding);
            if ($message === '') {
                continue;
            }

            $issues[] = [
                'severity' => $this->classifyFindingSeverity($message),
                'message' => $message,
            ];
        }

        return $issues;
    }

    private function classifyFindingSeverity(string $finding): string
    {
        $patterns = [
            'error' => [
                '/^manifest is missing /i',
                '/does not resolve to a plugin root/i',
                '/ is missing `header_php\.php`\./i',
                '/ is missing `catalog\/includes\/languages\/english\/lang\.[^`]+\.php`\./i',
                '/ is missing a matching template file\./i',
                '/ has no matching `FILENAME_\*` definition/i',
                '/has an unreadable or malformed language file\./i',
            ],
            'warning' => [
                '/^missing installer directory\./i',
                '/^no installer php files were found/i',
                '/^no installer language files were found/i',
                '/plugin is not present in plugin manager state\./i',
                '/plugin manager state points to version /i',
                '/installed plugin state could not be inspected/i',
                '/is missing `admin\/includes\/languages\/english\/extra_definitions\/lang\.[^`]+_menu\.php`\./i',
                '/menu-definition file that does not appear to define an encapsulated admin menu label\./i',
                '/does not follow the expected `auto_\*\.php` naming/i',
            ],
            'info' => [
                '/does not currently expose observers, autoloaders, or extra configure\/data files\./i',
                '/does not bundle any task-specific skills under `resources\/skills\/`\./i',
            ],
        ];

        foreach ($patterns as $severity => $rules) {
            foreach ($rules as $rule) {
                if (preg_match($rule, $finding) === 1) {
                    return $severity;
                }
            }
        }

        return 'warning';
    }

    private function countIssues(array $issues): array
    {
        $counts = ['error' => 0, 'warning' => 0, 'info' => 0];

        foreach ($issues as $issue) {
            $severity = (string)($issue['severity'] ?? 'warning');
            if (!array_key_exists($severity, $counts)) {
                continue;
            }

            $counts[$severity]++;
        }

        return $counts;
    }

    private function buildMessage(array $issueCounts): string
    {
        if ($issueCounts['error'] === 0 && $issueCounts['warning'] === 0 && $issueCounts['info'] === 0) {
            return 'Plugin passed the current Zen AI Assist doctor checks.';
        }

        if ($issueCounts['error'] > 0) {
            return 'Plugin has one or more errors to address.';
        }

        if ($issueCounts['warning'] > 0) {
            return 'Plugin passed with warnings.';
        }

        return 'Plugin passed with informational notes.';
    }
}
