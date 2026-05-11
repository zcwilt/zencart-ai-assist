<?php

class ZenAiAssistInstallerInspector
{
    public function inspect(string $path): array
    {
        $pluginRoot = $this->normalizePluginRoot($path);
        if ($pluginRoot === null) {
            return [
                'ok' => false,
                'message' => 'Plugin root or installer path not found.',
                'plugin_root' => $path,
                'findings' => ['The provided path does not exist.'],
            ];
        }

        $manifestPath = $pluginRoot . 'manifest.php';
        $installerDirectory = $pluginRoot . 'Installer/';
        $installerFiles = $this->listPhpFiles($installerDirectory);
        $languageFiles = $this->listPhpFiles($installerDirectory . 'languages/');

        $findings = [];
        if (!is_file($manifestPath)) {
            $findings[] = 'Missing manifest.php at the plugin root.';
        }
        if (!is_dir($installerDirectory)) {
            $findings[] = 'Missing Installer directory.';
        }
        if ($installerFiles === []) {
            $findings[] = 'No installer PHP files were found under Installer/.';
        }
        if ($languageFiles === []) {
            $findings[] = 'No installer language files were found under Installer/languages/.';
        }

        $hooks = $this->detectHooks($installerFiles);

        return [
            'ok' => $findings === [],
            'message' => $findings === []
                ? 'Installer structure looks complete for the current baseline checks.'
                : 'Installer structure is missing one or more expected pieces.',
            'plugin_root' => $pluginRoot,
            'manifest_path' => is_file($manifestPath) ? $manifestPath : null,
            'installer_files' => $installerFiles,
            'language_files' => $languageFiles,
            'hooks' => $hooks,
            'findings' => $findings,
        ];
    }

    private function normalizePluginRoot(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $resolved = realpath($path);
        if ($resolved === false) {
            return null;
        }

        if (is_file($resolved)) {
            $resolved = dirname($resolved);
        }

        $trimmed = rtrim($resolved, '/\\') . '/';
        if (basename(rtrim($trimmed, '/\\')) === 'Installer') {
            return dirname(rtrim($trimmed, '/\\')) . '/';
        }

        if (is_file($trimmed . 'manifest.php')) {
            return $trimmed;
        }

        return null;
    }

    private function listPhpFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || !preg_match('/\.php$/i', $fileInfo->getFilename())) {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }

        sort($files);

        return $files;
    }

    private function detectHooks(array $files): array
    {
        $hooks = [];

        foreach ($files as $file) {
            $contents = @file_get_contents($file);
            if (!is_string($contents) || $contents === '') {
                continue;
            }

            foreach (['validateInstall', 'executeInstall', 'executeUninstall', 'executeUpgrade'] as $hook) {
                if (str_contains($contents, $hook)) {
                    $hooks[$hook][] = $file;
                }
            }
        }

        ksort($hooks);

        return $hooks;
    }
}
