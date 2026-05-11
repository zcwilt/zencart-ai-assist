<?php

namespace Tests\PluginLocal\ZenAiAssist\Unit;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Traits\PluginLocalTestConcerns;
use Tests\Support\zcUnitTestCase;

#[Group('parallel-candidate')]
class ZenAiAssistRepoCatalogBuilderTest extends zcUnitTestCase
{
    use PluginLocalTestConcerns;

    public function setUp(): void
    {
        parent::setUp();
        $this->bootPluginLocalTest(__FILE__);
    }

    public function testBuilderCapturesPluginAndPageMetadata(): void
    {
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');

        try {
            $this->writeFile($projectRoot . 'includes/application_top.php', "<?php\n");
            $this->writeFile($projectRoot . 'includes/modules/pages/sample/header_php.php', "<?php\nfunction sample_page() {}\n");
            $this->writeFile($projectRoot . 'zc_plugins/demo/v1.0.0/manifest.php', "<?php\nreturn ['pluginVersion' => 'v1.0.0'];\n");
            $this->writeFile($projectRoot . 'zc_plugins/demo/v1.0.0/filenames.php', "<?php\ndefine('FILENAME_DEMO', 'demo');\n");
            $this->writeFile($projectRoot . 'zc_plugins/demo/v1.0.0/Installer/ScriptedInstaller.php', "<?php\nclass ScriptedInstaller {}\n");
            $this->writeFile($projectRoot . 'zc_plugins/demo/v1.0.0/catalog/includes/modules/pages/demo/header_php.php', "<?php\nfunction demo_page() {}\n");
            $this->writeFile($projectRoot . 'zc_plugins/demo/v1.0.0/catalog/includes/languages/english/lang.demo.php', "<?php\nreturn ['TEXT_DEMO' => 'Demo'];\n");
            $this->writeFile($projectRoot . 'zc_plugins/demo/v1.0.0/catalog/includes/templates/template_default/tpl_demo.php', "<?php\n");
            $this->writeFile($projectRoot . 'zc_plugins/demo/v1.0.0/admin/demo.php', "<?php\n");
            $this->writeFile($projectRoot . 'zc_plugins/demo/v1.0.0/admin/includes/languages/english/lang.demo.php', "<?php\nreturn ['HEADING_TITLE' => 'Demo'];\n");
            $this->writeFile($projectRoot . 'zc_plugins/demo/v1.0.0/admin/includes/languages/english/extra_definitions/lang.demo_menu.php', "<?php\nreturn ['BOX_TOOLS_DEMO' => 'Demo'];\n");
            $this->writeFile($projectRoot . 'zc_plugins/demo/v1.0.0/tests/Unit/DemoTest.php', "<?php\nclass DemoTest {}\n");

            $builder = new \ZenAiAssistRepoCatalogBuilder($projectRoot);
            $index = $builder->build();

            $recordsByPath = [];
            foreach ($index['records'] as $record) {
                $recordsByPath[$record['path']] = $record;
            }

            $pageRecord = $recordsByPath['includes/modules/pages/sample/header_php.php'] ?? null;
            $pluginRecord = $recordsByPath['zc_plugins/demo/v1.0.0/manifest.php'] ?? null;
            $pluginPageRecord = $recordsByPath['zc_plugins/demo/v1.0.0/catalog/includes/modules/pages/demo/header_php.php'] ?? null;
            $adminRecord = $recordsByPath['zc_plugins/demo/v1.0.0/admin/demo.php'] ?? null;
            $pluginTestRecord = $recordsByPath['zc_plugins/demo/v1.0.0/tests/Unit/DemoTest.php'] ?? null;

            $this->assertIsArray($pageRecord);
            $this->assertIsArray($pluginRecord);
            $this->assertIsArray($pluginPageRecord);
            $this->assertIsArray($adminRecord);
            $this->assertIsArray($pluginTestRecord);
            $this->assertSame('page-module', $pageRecord['role']);
            $this->assertSame('sample', $pageRecord['page']);
            $this->assertSame('plugin-manifest', $pluginRecord['role']);
            $this->assertSame('demo', $pluginRecord['plugin']['key']);
            $this->assertSame('catalog', $pluginPageRecord['side']);
            $this->assertSame('admin-page-entrypoint', $adminRecord['role']);
            $this->assertSame('test-unit', $pluginTestRecord['role']);
            $this->assertContains('encapsulated-plugin', $pluginRecord['query_hints']);
            $this->assertContains(['type' => 'plugin-installer', 'path' => 'zc_plugins/demo/v1.0.0/Installer/ScriptedInstaller.php'], $pluginRecord['relationships']);
            $this->assertContains(['type' => 'language-file', 'path' => 'zc_plugins/demo/v1.0.0/catalog/includes/languages/english/lang.demo.php'], $pluginPageRecord['relationships']);
        } finally {
            $this->removeDirectory(rtrim($projectRoot, '/\\'));
        }
    }

    private function makeTempDirectory(string $prefix): string
    {
        $path = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(4));
        mkdir($path, 0775, true);

        return rtrim($path, '/\\') . '/';
    }

    private function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $contents);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}
