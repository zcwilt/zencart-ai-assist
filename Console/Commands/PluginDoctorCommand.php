<?php
/**
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace Zencart\Plugins\Console\ZenAiAssist\Commands;

use Zencart\Console\ConsoleInput;
use Zencart\Console\ConsoleOutput;

class PluginDoctorCommand extends AbstractZenAiAssistCommand
{
    public function getName(): string
    {
        return 'ai:plugin:doctor';
    }

    public function getDescription(): string
    {
        return 'Run combined Zen AI Assist checks against a plugin root.';
    }

    public function getAliases(): array
    {
        return ['plugin:doctor'];
    }

    public function getUsageLines(): array
    {
        return [
            'bin/zencart ai:plugin:doctor [path]',
            'bin/zencart ai:plugin:doctor --all',
            'php zc_cli.php ai:plugin:doctor [path]',
            'php zc_cli.php ai:plugin:doctor --all',
        ];
    }

    public function handle(ConsoleInput $input, ConsoleOutput $output): int
    {
        if ($input->hasOption('all')) {
            return $this->handleAll($output);
        }

        $path = trim((string)$input->getArgument(0, ''));
        if ($path === '') {
            $path = $this->resolveDefaultPath();
            $output->writeln('Using plugin path: ' . $path);
        }

        $result = $this->createDoctor()->diagnose($path);
        $this->renderDiagnosis($result, $output);

        return $result['ok'] ? 0 : 1;
    }

    protected function createDoctor(): \ZenAiAssistDoctorService
    {
        return new \ZenAiAssistDoctorService($this->paths()->projectRoot());
    }

    /**
     * @return string[]
     */
    protected function discoverPluginRoots(): array
    {
        $manifests = glob(rtrim($this->projectRoot(), '/\\') . '/zc_plugins/*/*/manifest.php') ?: [];
        sort($manifests);

        return array_values(array_map(static fn (string $manifestPath): string => dirname($manifestPath) . '/', $manifests));
    }

    private function handleAll(ConsoleOutput $output): int
    {
        $pluginRoots = $this->discoverPluginRoots();
        if ($pluginRoots === []) {
            $output->writeln('No plugin manifests were found under: ' . rtrim($this->projectRoot(), '/\\') . '/zc_plugins/');

            return 1;
        }

        $output->writeln('Scanning all plugin roots under: ' . rtrim($this->projectRoot(), '/\\') . '/zc_plugins/');

        $doctor = $this->createDoctor();
        $exitCode = 0;

        foreach ($pluginRoots as $index => $pluginRoot) {
            if ($index > 0) {
                $output->writeln('');
            }

            $output->writeln('Using plugin path: ' . $pluginRoot);
            $result = $doctor->diagnose($pluginRoot);
            $this->renderDiagnosis($result, $output);

            if (!($result['ok'] ?? false)) {
                $exitCode = 1;
            }
        }

        return $exitCode;
    }

    private function renderDiagnosis(array $result, ConsoleOutput $output): void
    {
        $installedState = is_array($result['checks']['installed_state'] ?? null) ? $result['checks']['installed_state'] : [];

        $output->writeln(($result['ok'] ? 'OK' : 'FAIL') . ': ' . $result['message']);
        $output->writeln('Plugin: ' . (string)($result['plugin_key'] ?? ''));
        $output->writeln('Version: ' . (string)($result['plugin_version'] ?? ''));
        $issueCounts = is_array($result['issue_counts'] ?? null) ? $result['issue_counts'] : ['error' => 0, 'warning' => 0, 'info' => 0];
        $output->writeln(sprintf(
            'Issue counts: %d error(s), %d warning(s), %d info.',
            (int)($issueCounts['error'] ?? 0),
            (int)($issueCounts['warning'] ?? 0),
            (int)($issueCounts['info'] ?? 0)
        ));
        if (($installedState['status'] ?? '') !== '') {
            $output->writeln('Installed state: ' . (string)$installedState['status']);
        }
        if (($installedState['runtime_state'] ?? '') !== '') {
            $output->writeln('Runtime state: ' . (string)$installedState['runtime_state']);
        }

        $issues = is_array($result['issues'] ?? null) ? $result['issues'] : [];
        if ($issues !== []) {
            foreach ($issues as $issue) {
                $severity = strtoupper((string)($issue['severity'] ?? 'WARNING'));
                $output->writeln($severity . ': ' . (string)($issue['message'] ?? ''));
            }
        } else {
            foreach ($result['findings'] ?? [] as $finding) {
                $output->writeln('Finding: ' . $finding);
            }
        }

        foreach ($result['recommendations'] ?? [] as $recommendation) {
            $output->writeln('Recommendation: ' . $recommendation);
        }
    }

    private function resolveDefaultPath(): string
    {
        $workingDirectory = getcwd();
        if (is_string($workingDirectory) && $this->isResolvablePluginPath($workingDirectory)) {
            return $workingDirectory;
        }

        return $this->pluginRoot();
    }

    private function isResolvablePluginPath(string $path): bool
    {
        $path = rtrim($path, '/\\');

        if ($path === '') {
            return false;
        }

        if (is_file($path . '/manifest.php')) {
            return true;
        }

        return basename($path) === 'Installer' && is_file(dirname($path) . '/manifest.php');
    }
}
