<?php

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    /**
     * @since ZC v3.0.0
     */
    protected function validateInstall(): bool
    {
        if ($this->isConsoleAvailable()) {
            return true;
        }

        $message = defined('ERROR_ZEN_AI_ASSIST_CONSOLE_REQUIRED')
            ? ERROR_ZEN_AI_ASSIST_CONSOLE_REQUIRED
            : 'Zen AI Assist requires the shared Zen Cart command console (`bin/zencart`) and its core console classes. Install a Zen Cart build that includes the console framework before installing this plugin.';

        $this->errorContainer->addError(0, $message, false, $message);

        return false;
    }

    protected function executeInstall()
    {
        $this->migrateLegacyGeneratedDataToSharedCache();
        zen_deregister_admin_pages(['toolsZenAiAssist']);
        zen_register_admin_page('toolsZenAiAssist', 'BOX_TOOLS_ZEN_AI_ASSIST', 'FILENAME_ZEN_AI_ASSIST', '', 'tools', 'Y', 200);

        return true;
    }

    protected function executeUninstall()
    {
        zen_deregister_admin_pages(['toolsZenAiAssist']);

        return true;
    }

    /**
     * @since ZC v3.0.0
     */
    protected function isConsoleAvailable(): bool
    {
        $requiredPaths = [
            DIR_FS_CATALOG . 'bin/zencart',
            DIR_FS_CATALOG . 'zc_cli.php',
            DIR_FS_CATALOG . 'includes/classes/Console/ConsoleKernel.php',
            DIR_FS_CATALOG . 'includes/classes/Console/ConsoleCommand.php',
            DIR_FS_CATALOG . 'includes/classes/Console/PluginCommandDiscovery.php',
        ];

        foreach ($requiredPaths as $path) {
            if (!is_file($path)) {
                return false;
            }
        }

        return true;
    }

    protected function migrateLegacyGeneratedDataToSharedCache(): void
    {
        $versionRoot = rtrim(__DIR__, '/\\') . '/../';
        $familyRoot = dirname($versionRoot) . '/';
        $sharedCacheRoot = $this->cacheRoot();

        $migrations = [
            rtrim($versionRoot, '/\\') . '/resources/docs-cache/' => $sharedCacheRoot . 'docs-cache/',
            rtrim($versionRoot, '/\\') . '/resources/catalogs/' => $sharedCacheRoot . 'catalogs/',
            $familyRoot . '.cache/docs-cache/' => $sharedCacheRoot . 'docs-cache/',
            $familyRoot . '.cache/catalogs/' => $sharedCacheRoot . 'catalogs/',
        ];

        foreach ($migrations as $source => $destination) {
            $this->copyMissingFiles($source, $destination);
        }
    }

    protected function copyMissingFiles(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0775, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $relativePath = substr($fileInfo->getPathname(), strlen($source));
            if ($relativePath === false) {
                continue;
            }

            $targetPath = rtrim($destination, '/\\') . '/' . ltrim($relativePath, '/\\');

            if ($fileInfo->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0775, true);
                }

                continue;
            }

            if (!is_file($targetPath)) {
                copy($fileInfo->getPathname(), $targetPath);
            }
        }
    }

    protected function cacheRoot(): string
    {
        if (defined('DIR_FS_SQL_CACHE') && is_string(DIR_FS_SQL_CACHE) && DIR_FS_SQL_CACHE !== '') {
            return rtrim(DIR_FS_SQL_CACHE, '/\\') . '/zen-ai-assist/';
        }

        return rtrim(DIR_FS_CATALOG, '/\\') . '/cache/zen-ai-assist/';
    }
}
