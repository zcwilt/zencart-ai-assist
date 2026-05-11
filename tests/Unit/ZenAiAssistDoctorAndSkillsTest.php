<?php

namespace Tests\PluginLocal\ZenAiAssist\Unit;

use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use Tests\Support\Traits\PluginLocalTestConcerns;
use Tests\Support\zcUnitTestCase;

#[Group('parallel-candidate')]
class ZenAiAssistDoctorAndSkillsTest extends zcUnitTestCase
{
    use PluginLocalTestConcerns;

    public function setUp(): void
    {
        parent::setUp();
        $this->bootPluginLocalTest(__FILE__);
    }

    public function testDoctorUsesDeeperStructureChecksAndSkillServiceReadsTopics(): void
    {
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');
        $pluginRoot = $projectRoot . 'zc_plugins/example/v1.0.0/';

        try {
            $this->writeFile($pluginRoot . 'manifest.php', "<?php\nreturn ['pluginVersion' => 'v1.0.0', 'pluginName' => 'Example', 'pluginDescription' => 'Example plugin', 'pluginAuthor' => 'Tester', 'pluginId' => 0, 'zcVersions' => []];\n");
            $this->writeFile($pluginRoot . 'filenames.php', "<?php\ndefine('FILENAME_EXAMPLE', 'example');\n");
            $this->writeFile($pluginRoot . 'Installer/ScriptedInstaller.php', "<?php\nclass ScriptedInstaller { public function validateInstall() {} public function executeInstall() {} public function executeUninstall() {} }\n");
            $this->writeFile($pluginRoot . 'Installer/languages/english/main.php', "<?php\nreturn [];\n");
            $this->writeFile($pluginRoot . 'catalog/includes/modules/pages/example/header_php.php', "<?php\n");
            $this->writeFile($pluginRoot . 'catalog/includes/languages/english/lang.example.php', "<?php\nreturn [];\n");
            $this->writeFile($pluginRoot . 'catalog/includes/templates/template_default/tpl_example.php', "<?php\n");
            $this->writeFile($pluginRoot . 'admin/example.php', "<?php\n");
            $this->writeFile($pluginRoot . 'admin/includes/languages/english/lang.example.php', "<?php\nreturn [];\n");
            $this->writeFile($pluginRoot . 'admin/includes/languages/english/extra_definitions/lang.example_menu.php', "<?php\nreturn [];\n");
            $this->writeFile($pluginRoot . 'catalog/includes/classes/observers/auto_ExampleObserver.php', "<?php\nclass auto_ExampleObserver {}\n");
            $this->writeFile($pluginRoot . 'resources/skills/plugin-workflow.md', "# Example Plugin Workflow\n\nChecklist\n");

            $inspector = new \ZenAiAssistRuntimeInspector($projectRoot, $pluginRoot);
            $structure = $inspector->inspectPluginStructure($pluginRoot);
            $this->assertSame([], $structure['findings']);
            $this->assertNotEmpty($structure['skill_topics']);

            $doctorInspector = new class($projectRoot, $pluginRoot) extends \ZenAiAssistRuntimeInspector {
                public function listInstalledPlugins(string $statusFilter = 'all'): array
                {
                    return [
                        'runtime_state' => 'available',
                        'status_filter' => $statusFilter,
                        'warnings' => [],
                        'plugins' => [[
                            'unique_key' => 'example',
                            'name' => 'Example',
                            'version' => 'v1.0.0',
                            'status' => 'enabled',
                            'author' => 'Tester',
                            'description' => 'Example plugin',
                            'zc_versions' => '',
                            'manifest_path' => 'zc_plugins/example/v1.0.0/manifest.php',
                        ]],
                    ];
                }
            };
            $doctor = new \ZenAiAssistDoctorService($projectRoot, null, null, $doctorInspector);
            $result = $doctor->diagnose($pluginRoot);
            $this->assertTrue($result['ok']);
            $this->assertSame(0, $result['issue_counts']['error']);

            $skills = new \ZenAiAssistSkillService($pluginRoot . 'resources/skills/');
            $topics = $skills->listTopics();
            $this->assertCount(1, $topics);
            $topic = $skills->readTopic('plugin-workflow');
            $this->assertTrue($topic['found']);
            $this->assertStringContainsString('Example Plugin Workflow', $topic['content']);
        } finally {
            $this->removeDirectory(rtrim($projectRoot, '/\\'));
        }
    }

    public function testListInstalledPluginsClassifiesBootstrapAndDbConfigFailures(): void
    {
        $bootstrapMissingRoot = $this->makeTempDirectory('zen-ai-assist-project');
        $bootstrapPresentRoot = $this->makeTempDirectory('zen-ai-assist-project');

        try {
            $missingBootstrapInspector = new \ZenAiAssistRuntimeInspector($bootstrapMissingRoot, $bootstrapMissingRoot . 'zc_plugins/example/v1.0.0/');
            $missingBootstrapResult = $missingBootstrapInspector->listInstalledPlugins('all');
            $this->assertSame('bootstrap-missing', $missingBootstrapResult['runtime_state']);
            $this->assertSame('degraded', $missingBootstrapResult['runtime_state_category']);
            $this->assertFalse($missingBootstrapResult['inspection_available']);
            $this->assertStringContainsString('bootstrap is missing', strtolower($missingBootstrapResult['runtime_state_message']));
            $this->assertStringContainsString('bootstrap file is missing', strtolower($missingBootstrapResult['runtime_state_detail']));

            $dbConfigInspector = new class($bootstrapPresentRoot, $bootstrapPresentRoot . 'zc_plugins/example/v1.0.0/') extends \ZenAiAssistRuntimeInspector {
                public function listInstalledPlugins(string $statusFilter = 'all'): array
                {
                    return [
                        'runtime_state' => 'db-config-unavailable',
                        'inspection_available' => false,
                        'runtime_state_message' => 'Store DB configuration is unavailable, so installed plugin inspection cannot query plugin manager state.',
                        'status_filter' => $statusFilter,
                        'warnings' => ['Plugin command discovery disabled: store database configuration is unavailable.'],
                        'plugins' => [],
                    ];
                }
            };
            $dbConfigResult = $dbConfigInspector->listInstalledPlugins('all');

            $this->assertSame('db-config-unavailable', $dbConfigResult['runtime_state']);
            $this->assertSame([], $dbConfigResult['plugins']);
        } finally {
            $this->removeDirectory(rtrim($bootstrapMissingRoot, '/\\'));
            $this->removeDirectory(rtrim($bootstrapPresentRoot, '/\\'));
        }
    }

    public function testRuntimeStateClassifierRecognizesAdditionalFailureModes(): void
    {
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');
        $pluginRoot = $projectRoot . 'zc_plugins/example/v1.0.0/';

        try {
            $inspector = new \ZenAiAssistRuntimeInspector($projectRoot, $pluginRoot);
            $method = new ReflectionMethod(\ZenAiAssistRuntimeInspector::class, 'classifyRuntimeState');
            $method->setAccessible(true);

            $this->assertSame('db-driver-unavailable', $method->invoke($inspector, null, ['Plugin command discovery disabled: the MySQL connector for PHP is unavailable.'], []));
            $this->assertSame('db-connection-failed', $method->invoke($inspector, null, ['Plugin command discovery disabled: unable to connect to the store database.'], []));
            $this->assertSame('repository-helper-unavailable', $method->invoke($inspector, null, ['CLI plugin repository helper is unavailable.'], []));
        } finally {
            $this->removeDirectory(rtrim($projectRoot, '/\\'));
        }
    }

    public function testPluginDoctorCommandPrintsInstalledAndRuntimeState(): void
    {
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');
        $pluginRoot = $projectRoot . 'zc_plugins/example/v1.0.0/';

        try {
            $checkoutRoot = dirname(__DIR__, 5);
            require_once $checkoutRoot . '/includes/classes/Console/ConsoleCommand.php';
            require_once $checkoutRoot . '/includes/classes/Console/ConsoleInput.php';
            require_once $checkoutRoot . '/includes/classes/Console/ConsoleOutput.php';
            require_once dirname(__DIR__, 2) . '/Console/Commands/AbstractZenAiAssistCommand.php';
            require_once dirname(__DIR__, 2) . '/Console/Commands/PluginDoctorCommand.php';
            $this->writeExamplePlugin($pluginRoot);

            $command = new class($projectRoot, $pluginRoot) extends \Zencart\Plugins\Console\ZenAiAssist\Commands\PluginDoctorCommand {
                public function __construct(private string $testProjectRoot, private string $testPluginRoot)
                {
                }

                protected function projectRoot(): string
                {
                    return rtrim($this->testProjectRoot, '/\\') . '/';
                }

                protected function pluginRoot(): string
                {
                    return rtrim($this->testPluginRoot, '/\\') . '/';
                }
            };

            $stdout = fopen('php://temp', 'w+');
            $stderr = fopen('php://temp', 'w+');
            $exitCode = $command->handle(
                new \Zencart\Console\ConsoleInput(['zc_cli.php', 'ai:plugin:doctor', $pluginRoot]),
                new \Zencart\Console\ConsoleOutput($stdout, $stderr)
            );

            rewind($stdout);
            $output = stream_get_contents($stdout);
            for ($i = 0; $i < 3; $i++) {
                if (!restore_error_handler()) {
                    break;
                }
            }

            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('Installed state: ', $output);
            $this->assertStringContainsString('Runtime state: ', $output);
        } finally {
            $this->removeDirectory(rtrim($projectRoot, '/\\'));
        }
    }

    public function testPluginDoctorCommandCanScanAllPlugins(): void
    {
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');
        $firstPluginRoot = $projectRoot . 'zc_plugins/example-a/v1.0.0/';
        $secondPluginRoot = $projectRoot . 'zc_plugins/example-b/v2.0.0/';

        try {
            require_once dirname(__DIR__, 5) . '/includes/classes/Console/ConsoleCommand.php';
            require_once dirname(__DIR__, 5) . '/includes/classes/Console/ConsoleInput.php';
            require_once dirname(__DIR__, 5) . '/includes/classes/Console/ConsoleOutput.php';
            require_once dirname(__DIR__, 2) . '/Console/Commands/AbstractZenAiAssistCommand.php';
            require_once dirname(__DIR__, 2) . '/Console/Commands/PluginDoctorCommand.php';

            $this->writeFile($firstPluginRoot . 'manifest.php', "<?php\nreturn [];\n");
            $this->writeFile($secondPluginRoot . 'manifest.php', "<?php\nreturn [];\n");

            $command = new class($projectRoot) extends \Zencart\Plugins\Console\ZenAiAssist\Commands\PluginDoctorCommand {
                public function __construct(private string $testProjectRoot)
                {
                }

                protected function projectRoot(): string
                {
                    return rtrim($this->testProjectRoot, '/\\') . '/';
                }

                protected function createDoctor(): \ZenAiAssistDoctorService
                {
                    return new class($this->projectRoot()) extends \ZenAiAssistDoctorService {
                        public function __construct(private string $testProjectRoot)
                        {
                        }

                        public function diagnose(string $path): array
                        {
                            $pluginKey = basename(dirname(rtrim($path, '/\\')));
                            $ok = $pluginKey === 'example-a';

                            return [
                                'ok' => $ok,
                                'message' => $ok ? 'Plugin passed the current Zen AI Assist doctor checks.' : 'Plugin has one or more issues to address.',
                                'plugin_key' => $pluginKey,
                                'plugin_version' => basename(rtrim($path, '/\\')),
                                'issue_counts' => [
                                    'error' => $ok ? 0 : 1,
                                    'warning' => 0,
                                    'info' => 0,
                                ],
                                'checks' => [
                                    'installed_state' => [
                                        'status' => 'found',
                                        'runtime_state' => 'available',
                                    ],
                                ],
                                'issues' => $ok ? [] : [[
                                    'severity' => 'error',
                                    'message' => 'Synthetic failure.',
                                ]],
                                'findings' => $ok ? [] : ['Synthetic failure.'],
                                'recommendations' => $ok ? ['Looks good.'] : ['Needs attention.'],
                            ];
                        }
                    };
                }
            };

            $stdout = fopen('php://temp', 'w+');
            $stderr = fopen('php://temp', 'w+');
            $exitCode = $command->handle(
                new \Zencart\Console\ConsoleInput(['zc_cli.php', 'ai:plugin:doctor', '--all']),
                new \Zencart\Console\ConsoleOutput($stdout, $stderr)
            );

            rewind($stdout);
            $output = stream_get_contents($stdout);

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('Scanning all plugin roots under: ', $output);
            $this->assertStringContainsString('Using plugin path: ' . $firstPluginRoot, $output);
            $this->assertStringContainsString('Using plugin path: ' . $secondPluginRoot, $output);
            $this->assertStringContainsString('Plugin: example-a', $output);
            $this->assertStringContainsString('Plugin: example-b', $output);
            $this->assertStringContainsString('ERROR: Synthetic failure.', $output);
        } finally {
            $this->removeDirectory(rtrim($projectRoot, '/\\'));
        }
    }

    public function testDoctorDistinguishesMissingPluginFromRuntimeUnavailable(): void
    {
        $runtimeUnavailableRoot = $this->makeTempDirectory('zen-ai-assist-project');
        $missingPluginRoot = $this->makeTempDirectory('zen-ai-assist-project');
        $pluginRoot = $runtimeUnavailableRoot . 'zc_plugins/example/v1.0.0/';
        $missingPluginPath = $missingPluginRoot . 'zc_plugins/example/v1.0.0/';

        try {
            $this->writeExamplePlugin($pluginRoot);
            $runtimeUnavailableInspector = new class($runtimeUnavailableRoot, $pluginRoot) extends \ZenAiAssistRuntimeInspector {
                public function listInstalledPlugins(string $statusFilter = 'all'): array
                {
                    return [
                        'runtime_state' => 'db-config-unavailable',
                        'status_filter' => $statusFilter,
                        'warnings' => ['Plugin command discovery disabled: store database configuration is unavailable.'],
                        'plugins' => [],
                    ];
                }
            };
            $runtimeUnavailableDoctor = new \ZenAiAssistDoctorService($runtimeUnavailableRoot, null, null, $runtimeUnavailableInspector);
            $runtimeUnavailableResult = $runtimeUnavailableDoctor->diagnose($pluginRoot);

            $this->assertSame('runtime-unavailable', $runtimeUnavailableResult['checks']['installed_state']['status']);
            $this->assertSame('db-config-unavailable', $runtimeUnavailableResult['checks']['installed_state']['runtime_state']);
            $this->assertContains('Installed plugin state could not be inspected because the CLI runtime context is unavailable.', $runtimeUnavailableResult['findings']);
            $this->assertSame(0, $runtimeUnavailableResult['issue_counts']['error']);
            $this->assertNotContains('Plugin is not present in plugin manager state.', $runtimeUnavailableResult['findings']);

            $this->writeExamplePlugin($missingPluginPath);
            $missingPluginInspector = new class($missingPluginRoot, $missingPluginPath) extends \ZenAiAssistRuntimeInspector {
                public function listInstalledPlugins(string $statusFilter = 'all'): array
                {
                    return [
                        'runtime_state' => 'available',
                        'status_filter' => $statusFilter,
                        'warnings' => [],
                        'plugins' => [],
                    ];
                }
            };
            $missingPluginDoctor = new \ZenAiAssistDoctorService($missingPluginRoot, null, null, $missingPluginInspector);
            $missingPluginResult = $missingPluginDoctor->diagnose($missingPluginPath);

            $this->assertSame('missing', $missingPluginResult['checks']['installed_state']['status']);
            $this->assertContains('Plugin is not present in plugin manager state.', $missingPluginResult['findings']);
        } finally {
            $this->removeDirectory(rtrim($runtimeUnavailableRoot, '/\\'));
            $this->removeDirectory(rtrim($missingPluginRoot, '/\\'));
        }
    }

    public function testStructuredSkillsCanBeListedMatchedAndValidated(): void
    {
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');
        $pluginRoot = $projectRoot . 'zc_plugins/example/v1.0.0/';

        try {
            $this->writeExamplePlugin($pluginRoot);
            $this->writeFile(
                $pluginRoot . 'resources/skills/catalog.json',
                <<<'JSON'
{
  "skills": [
    {
      "id": "plugin-workflow",
      "title": "Example Plugin Workflow",
      "summary": "Guide work on an encapsulated Zen Cart plugin.",
      "intent": "Keep plugin work inside the expected manifest and installer structure.",
      "tags": ["plugin", "workflow"],
      "when_to_use": ["Creating or fixing a plugin."],
      "workflow_steps": ["Inspect manifest.php and Installer/ first."],
      "validation_rules": [
        {
          "type": "path_exists",
          "root": "plugin",
          "path": "manifest.php",
          "description": "Plugin manifest exists."
        },
        {
          "type": "path_exists",
          "root": "plugin",
          "path": "Installer/ScriptedInstaller.php",
          "description": "Plugin installer exists."
        }
      ],
      "content_file": "plugin-workflow.md"
    }
  ]
}
JSON
            );

            $skills = new \ZenAiAssistSkillService($pluginRoot . 'resources/skills/');

            $listed = $skills->listSkills();
            $this->assertCount(1, $listed);
            $this->assertSame('plugin-workflow', $listed[0]['id']);

            $loaded = $skills->getSkill('plugin-workflow');
            $this->assertTrue($loaded['found']);
            $this->assertSame('Example Plugin Workflow', $loaded['title']);
            $this->assertStringContainsString('Example Plugin Workflow', $loaded['content']);

            $matches = $skills->matchSkill('create or fix a plugin', 1);
            $this->assertCount(1, $matches['matches']);
            $this->assertSame('plugin-workflow', $matches['matches'][0]['id']);

            $validation = $skills->validateSkill('plugin-workflow', ['plugin_root' => $pluginRoot]);
            $this->assertTrue($validation['ok']);
            $this->assertSame(2, $validation['passed']);
            $this->assertSame(0, $validation['failed']);
            $this->assertSame(0, $validation['skipped']);
        } finally {
            $this->removeDirectory(rtrim($projectRoot, '/\\'));
        }
    }

    public function testDoctorFlagsSemanticEncapsulatedPluginProblems(): void
    {
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');
        $pluginRoot = $projectRoot . 'zc_plugins/example/v1.0.0/';

        try {
            $this->writeExamplePlugin($pluginRoot);
            unlink($pluginRoot . 'catalog/includes/classes/observers/auto_ExampleObserver.php');
            $this->writeFile($pluginRoot . 'catalog/includes/classes/observers/ExampleObserver.php', "<?php\nclass ExampleObserver {}\n");
            $this->writeFile($pluginRoot . 'admin/includes/languages/english/extra_definitions/lang.example_menu.php', '');
            $this->writeFile($pluginRoot . 'catalog/includes/languages/english/lang.example.php', '');

            $inspector = new \ZenAiAssistRuntimeInspector($projectRoot, $pluginRoot);
            $structure = $inspector->inspectPluginStructure($pluginRoot);

            $this->assertContains(
                'Catalog page `example` has an unreadable or malformed language file.',
                $structure['findings']
            );
            $this->assertContains(
                'Admin page `example` has a menu-definition file that does not appear to define an encapsulated admin menu label.',
                $structure['findings']
            );
            $this->assertContains(
                'Observer file `zc_plugins/example/v1.0.0/catalog/includes/classes/observers/ExampleObserver.php` does not follow the expected `auto_*.php` naming for encapsulated plugin observers.',
                $structure['findings']
            );
        } finally {
            $this->removeDirectory(rtrim($projectRoot, '/\\'));
        }
    }

    public function testDoctorDoesNotFailConsoleFocusedPluginWithoutBootstrapHooks(): void
    {
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');
        $pluginRoot = $projectRoot . 'zc_plugins/example/v1.0.0/';

        try {
            $this->writeFile($pluginRoot . 'manifest.php', "<?php\nreturn ['pluginVersion' => 'v1.0.0', 'pluginName' => 'Example', 'pluginDescription' => 'Example plugin', 'pluginAuthor' => 'Tester', 'pluginId' => 0, 'zcVersions' => []];\n");
            $this->writeFile($pluginRoot . 'Installer/ScriptedInstaller.php', "<?php\nclass ScriptedInstaller { public function validateInstall() {} public function executeInstall() {} public function executeUninstall() {} }\n");
            $this->writeFile($pluginRoot . 'Installer/languages/english/main.php', "<?php\nreturn [];\n");
            $this->writeFile($pluginRoot . 'Console/commands.php', "<?php\nreturn [];\n");
            $this->writeFile($pluginRoot . 'resources/skills/plugin-workflow.md', "# Example Plugin Workflow\n\nChecklist\n");

            $doctorInspector = new class($projectRoot, $pluginRoot) extends \ZenAiAssistRuntimeInspector {
                public function listInstalledPlugins(string $statusFilter = 'all'): array
                {
                    return [
                        'runtime_state' => 'available',
                        'status_filter' => $statusFilter,
                        'warnings' => [],
                        'plugins' => [[
                            'unique_key' => 'example',
                            'name' => 'Example',
                            'version' => 'v1.0.0',
                            'status' => 'enabled',
                            'author' => 'Tester',
                            'description' => 'Example plugin',
                            'zc_versions' => '',
                            'manifest_path' => 'zc_plugins/example/v1.0.0/manifest.php',
                        ]],
                    ];
                }
            };

            $doctor = new \ZenAiAssistDoctorService($projectRoot, null, null, $doctorInspector);
            $result = $doctor->diagnose($pluginRoot);

            $this->assertTrue($result['ok']);
            $this->assertSame(0, $result['issue_counts']['error']);
            $this->assertNotContains(
                'Plugin does not currently expose observers, autoloaders, or extra configure/data files.',
                $result['findings']
            );
        } finally {
            $this->removeDirectory(rtrim($projectRoot, '/\\'));
        }
    }

    public function testAnswerServiceCanComposeSkillContextWithDocsAndRepoEvidence(): void
    {
        $projectRoot = $this->makeTempDirectory('zen-ai-assist-project');
        $pluginRoot = $projectRoot . 'zc_plugins/example/v1.0.0/';

        try {
            $this->writeExamplePlugin($pluginRoot);
            $this->writeFile(
                $pluginRoot . 'resources/skills/catalog.json',
                <<<'JSON'
{
  "skills": [
                    {
                      "id": "wire-language-files",
                      "title": "Wire Language Files",
                      "summary": "Add the expected Zen Cart language files.",
                      "intent": "Guide language-file wiring for pages and admin pages.",
                      "tags": ["language", "plugin", "admin", "storefront"],
                      "when_to_use": ["A page or admin page needs language definitions."],
                      "workflow_steps": ["Place admin page strings under admin/includes/languages/english/."],
                      "validation_rules": [
                        {
                          "type": "path_exists",
                          "root": "plugin",
                          "path": "manifest.php",
                          "description": "Plugin manifest exists."
                        }
                      ],
                      "content_file": "wire-language-files.md"
                    }
                  ]
                }
JSON
            );
            $this->writeFile($pluginRoot . 'resources/skills/wire-language-files.md', "# Wire Language Files\n\nUse the expected Zen Cart language paths.\n");

            $skills = new \ZenAiAssistSkillService($pluginRoot . 'resources/skills/');
            $doctor = new class extends \ZenAiAssistDoctorService {
                public function __construct()
                {
                }

                public function diagnose(string $path): array
                {
                    return [
                        'ok' => true,
                        'message' => 'Plugin passed the current Zen AI Assist doctor checks.',
                        'plugin_root' => $path,
                        'issue_counts' => [
                            'error' => 0,
                            'warning' => 0,
                            'info' => 0,
                        ],
                        'checks' => [
                            'installed_state' => [
                                'status' => 'found',
                                'runtime_state' => 'available',
                            ],
                        ],
                        'issues' => [],
                        'findings' => [],
                        'recommendations' => [],
                    ];
                }
            };
            $service = new \ZenAiAssistAnswerService(new \ZenAiAssistComparisonService(), $skills, $doctor);

            $docsIndex = [
                'chunks' => [[
                    'title' => 'Admin language docs',
                    'heading_path' => ['Plugins', 'Admin'],
                    'excerpt' => 'Admin pages should load matching language definitions.',
                    'url' => 'https://docs.example.test/admin-language',
                    'content' => 'Admin pages should load matching language definitions.',
                    'tags' => ['admin', 'language'],
                ]],
            ];
            $repoIndex = [
                'records' => [[
                    'title' => 'Example admin language file',
                    'path' => 'zc_plugins/example/v1.0.0/admin/includes/languages/english/lang.example.php',
                    'excerpt' => 'Language definitions for the Example admin page.',
                    'content' => 'Language definitions for the Example admin page.',
                    'path_tokens' => ['admin', 'languages', 'english'],
                ]],
            ];

            $answer = $service->answerWithSkillContext($docsIndex, $repoIndex, 'wire admin language files', 2, 2, $pluginRoot);

            $this->assertSame('wire-language-files', $answer['recommended_skill']['id']);
            $this->assertTrue($answer['recommended_skill_detail']['found']);
            $this->assertStringContainsString('Wire Language Files', $answer['workflow_hint']);
            $this->assertNotEmpty($answer['docs']);
            $this->assertNotEmpty($answer['repo']);
            $this->assertContains('admin', $answer['query_type']['categories']);
            $this->assertSame('attached', $answer['plugin_context']['status']);
            $this->assertSame('found', $answer['plugin_context']['installed_state']['status']);
            $this->assertTrue($answer['plugin_context']['doctor']['ok']);
            $this->assertNotEmpty($answer['recommended_next_steps']);
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

    private function writeExamplePlugin(string $pluginRoot): void
    {
        $this->writeFile($pluginRoot . 'manifest.php', "<?php\nreturn ['pluginVersion' => 'v1.0.0', 'pluginName' => 'Example', 'pluginDescription' => 'Example plugin', 'pluginAuthor' => 'Tester', 'pluginId' => 0, 'zcVersions' => []];\n");
        $this->writeFile($pluginRoot . 'filenames.php', "<?php\ndefine('FILENAME_EXAMPLE', 'example');\n");
        $this->writeFile($pluginRoot . 'Installer/ScriptedInstaller.php', "<?php\nclass ScriptedInstaller { public function validateInstall() {} public function executeInstall() {} public function executeUninstall() {} }\n");
        $this->writeFile($pluginRoot . 'Installer/languages/english/main.php', "<?php\nreturn [];\n");
        $this->writeFile($pluginRoot . 'catalog/includes/modules/pages/example/header_php.php', "<?php\n");
        $this->writeFile($pluginRoot . 'catalog/includes/languages/english/lang.example.php', "<?php\nreturn ['TEXT_EXAMPLE' => 'Example'];\n");
        $this->writeFile($pluginRoot . 'catalog/includes/templates/template_default/tpl_example.php', "<?php\n");
        $this->writeFile($pluginRoot . 'admin/example.php', "<?php\n");
        $this->writeFile($pluginRoot . 'admin/includes/languages/english/lang.example.php', "<?php\nreturn ['HEADING_TITLE' => 'Example'];\n");
        $this->writeFile($pluginRoot . 'admin/includes/languages/english/extra_definitions/lang.example_menu.php', "<?php\nreturn ['BOX_TOOLS_EXAMPLE' => 'Example'];\n");
        $this->writeFile($pluginRoot . 'catalog/includes/classes/observers/auto_ExampleObserver.php', "<?php\nclass auto_ExampleObserver {}\n");
        $this->writeFile($pluginRoot . 'resources/skills/plugin-workflow.md', "# Example Plugin Workflow\n\nChecklist\n");
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
