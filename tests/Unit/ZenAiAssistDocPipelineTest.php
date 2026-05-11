<?php

namespace Tests\PluginLocal\ZenAiAssist\Unit;

use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use Tests\Support\Traits\PluginLocalTestConcerns;
use Tests\Support\zcUnitTestCase;

#[Group('parallel-candidate')]
class ZenAiAssistDocPipelineTest extends zcUnitTestCase
{
    use PluginLocalTestConcerns;

    public function setUp(): void
    {
        parent::setUp();
        $this->bootPluginLocalTest(__FILE__);
    }

    public function testFetcherParsesSectionsAndVersions(): void
    {
        $pluginRoot = $this->makeTempDirectory('zen-ai-assist-plugin');
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');

        try {
            mkdir($projectRoot . 'includes', 0775, true);
            mkdir($projectRoot . 'zc_plugins', 0775, true);
            $paths = new \ZenAiAssistPathHelper($pluginRoot, $projectRoot);
            $storage = new \ZenAiAssistJsonStorage();
            $fetcher = new \ZenAiAssistDocFetcher($paths, $storage);

            $method = new ReflectionMethod(\ZenAiAssistDocFetcher::class, 'parseDocument');
            $method->setAccessible(true);

            $document = $method->invoke(
                $fetcher,
                'https://docs.example.test/dev/plugins/',
                '<html><head><title>Plugin Docs</title></head><body><h1>Plugins</h1><p>Supports 3.0.0 and v2.2.</p><h2>Manifest</h2><p>Use manifest.php.</p></body></html>',
                ['plugins'],
                ['last-modified' => 'Thu, 01 Jan 1970 00:00:00 GMT']
            );

            $this->assertSame('Plugin Docs', $document['title']);
            $this->assertNotEmpty($document['sections']);
            $this->assertStringContainsString('3.0.0', implode(' ', $document['version_hints']));

            $chunker = new \ZenAiAssistDocChunker();
            $index = $chunker->buildIndex([$document]);

            $this->assertCount(2, $index['chunks']);
            $this->assertSame(['Plugin Docs', 'Plugins'], $index['chunks'][0]['heading_path']);
            $this->assertStringContainsString('2.2', implode(' ', $index['chunks'][0]['version_hints']));
            $this->assertStringContainsString('/cache/zen-ai-assist/docs-cache/', $paths->docsCacheDirectory());
            $this->assertStringContainsString('/cache/zen-ai-assist/catalogs/', $paths->catalogsDirectory());
        } finally {
            $this->removeDirectory(rtrim($pluginRoot, '/\\'));
            $this->removeDirectory(rtrim($projectRoot, '/\\'));
        }
    }

    public function testFetcherUsesCachedCopyWhenRemoteSourceFails(): void
    {
        $pluginRoot = $this->makeTempDirectory('zen-ai-assist-plugin');
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');

        try {
            mkdir($projectRoot . 'includes', 0775, true);
            mkdir($projectRoot . 'zc_plugins', 0775, true);
            $paths = new \ZenAiAssistPathHelper($pluginRoot, $projectRoot);
            $storage = new \ZenAiAssistJsonStorage();
            $fetcher = new class($paths, $storage) extends \ZenAiAssistDocFetcher {
                protected function download(string $url): array
                {
                    return ['ok' => false, 'error' => 'http-404'];
                }
            };

            $url = 'https://docs.example.test/dev/plugins/encapsulated_plugins/';
            $filePath = $paths->docsCacheDirectory() . $paths->slugForUrl($url) . '.json';
            $storage->writeJsonFile($filePath, [
                'url' => $url,
                'title' => 'Cached Encapsulated Plugins',
            ]);

            $result = $fetcher->fetchOne([
                'url' => $url,
                'tags' => ['plugins', 'encapsulated-plugins'],
                'required' => false,
            ]);

            $this->assertSame('cached', $result['status']);
            $this->assertSame('http-404', $result['reason']);
            $this->assertSame($filePath, $result['file']);
        } finally {
            $this->removeDirectory(rtrim($pluginRoot, '/\\'));
            $this->removeDirectory(rtrim($projectRoot, '/\\'));
        }
    }

    public function testFetcherSkipsOptionalSourceWhenRemoteSourceFailsAndNoCacheExists(): void
    {
        $pluginRoot = $this->makeTempDirectory('zen-ai-assist-plugin');
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');

        try {
            mkdir($projectRoot . 'includes', 0775, true);
            mkdir($projectRoot . 'zc_plugins', 0775, true);
            $paths = new \ZenAiAssistPathHelper($pluginRoot, $projectRoot);
            $storage = new \ZenAiAssistJsonStorage();
            $fetcher = new class($paths, $storage) extends \ZenAiAssistDocFetcher {
                protected function download(string $url): array
                {
                    return ['ok' => false, 'error' => 'http-404'];
                }
            };

            $result = $fetcher->fetchOne([
                'url' => 'https://docs.example.test/dev/plugins/encapsulated_plugins/manifests/',
                'tags' => ['plugins', 'manifest'],
                'required' => false,
            ]);

            $this->assertSame('skipped', $result['status']);
            $this->assertSame('http-404', $result['reason']);
        } finally {
            $this->removeDirectory(rtrim($pluginRoot, '/\\'));
            $this->removeDirectory(rtrim($projectRoot, '/\\'));
        }
    }

    private function makeTempDirectory(string $prefix): string
    {
        $path = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(4));
        mkdir($path, 0775, true);

        return rtrim($path, '/\\') . '/';
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
