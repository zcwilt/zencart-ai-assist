<?php

class ZenAiAssistMcpServer
{
    private const DEFAULT_PROTOCOL_VERSION = '2024-11-05';
    private const TRANSPORT_CONTENT_LENGTH = 'content-length';
    private const TRANSPORT_JSON_LINE = 'json-line';

    private ZenAiAssistPathHelper $paths;
    private ZenAiAssistJsonStorage $storage;
    private ZenAiAssistSearchService $search;
    private ZenAiAssistComparisonService $comparison;
    private ZenAiAssistManifestInspector $manifestInspector;
    private ZenAiAssistInstallerInspector $installerInspector;
    private ZenAiAssistRuntimeInspector $runtimeInspector;
    private ZenAiAssistGuidanceService $guidance;
    private ZenAiAssistSkillService $skills;
    private ZenAiAssistDoctorService $doctor;
    private ZenAiAssistAnswerService $answer;
    private ?string $transportMode = null;

    public function __construct(
        ZenAiAssistPathHelper $paths,
        ?ZenAiAssistJsonStorage $storage = null,
        ?ZenAiAssistSearchService $search = null,
        ?ZenAiAssistComparisonService $comparison = null,
        ?ZenAiAssistManifestInspector $manifestInspector = null,
        ?ZenAiAssistInstallerInspector $installerInspector = null,
        ?ZenAiAssistRuntimeInspector $runtimeInspector = null,
        ?ZenAiAssistGuidanceService $guidance = null,
        ?ZenAiAssistSkillService $skills = null,
        ?ZenAiAssistDoctorService $doctor = null,
        ?ZenAiAssistAnswerService $answer = null
    ) {
        $this->paths = $paths;
        $this->storage = $storage ?? new ZenAiAssistJsonStorage();
        $this->search = $search ?? new ZenAiAssistSearchService();
        $this->comparison = $comparison ?? new ZenAiAssistComparisonService($this->search);
        $this->manifestInspector = $manifestInspector ?? new ZenAiAssistManifestInspector();
        $this->installerInspector = $installerInspector ?? new ZenAiAssistInstallerInspector();
        $this->runtimeInspector = $runtimeInspector ?? new ZenAiAssistRuntimeInspector(
            $this->paths->projectRoot(),
            $this->paths->pluginRoot()
        );
        $this->guidance = $guidance ?? new ZenAiAssistGuidanceService($this->paths->guidanceDirectory());
        $this->skills = $skills ?? new ZenAiAssistSkillService($this->paths->skillsDirectory());
        $this->doctor = $doctor ?? new ZenAiAssistDoctorService(
            $this->paths->projectRoot(),
            $this->manifestInspector,
            $this->installerInspector,
            $this->runtimeInspector
        );
        $this->answer = $answer ?? new ZenAiAssistAnswerService($this->comparison, $this->skills, $this->doctor);
    }

    public function run(): int
    {
        while (($message = $this->readMessage()) !== null) {
            $response = $this->handleMessage($message);
            if ($response !== null) {
                $this->writeMessage($response);
            }
        }

        return 0;
    }

    private function handleMessage(array $message): ?array
    {
        $method = (string)($message['method'] ?? '');
        $id = $message['id'] ?? null;
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        if ($method === '') {
            return $this->error($id, -32600, 'Invalid request.');
        }

        if ($method === 'notifications/initialized') {
            return null;
        }

        if ($method === 'initialize') {
            $protocolVersion = $this->negotiateProtocolVersion($params);

            return $this->success($id, [
                'protocolVersion' => $protocolVersion,
                'serverInfo' => [
                    'name' => 'zen-ai-assist',
                    'version' => '1.0.0-mcp1',
                ],
                'capabilities' => [
                    'tools' => [
                        'listChanged' => false,
                    ],
                ],
            ]);
        }

        if ($method === 'ping') {
            return $this->success($id, ['pong' => true]);
        }

        if ($method === 'tools/list') {
            return $this->success($id, ['tools' => $this->toolDefinitions()]);
        }

        if ($method === 'tools/call') {
            return $this->handleToolCall($id, $params);
        }

        return $this->error($id, -32601, 'Method not found: ' . $method);
    }

    private function handleToolCall(mixed $id, array $params): array
    {
        $name = (string)($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        try {
            $result = match ($name) {
                'search_docs' => $this->callSearchDocs($arguments),
                'search_repo' => $this->callSearchRepo($arguments),
                'compare_docs_to_code' => $this->callCompareDocsToCode($arguments),
                'inspect_plugin_manifest' => $this->callInspectPluginManifest($arguments),
                'inspect_plugin_installer' => $this->callInspectPluginInstaller($arguments),
                'inspect_bootstrap_loaders' => $this->callInspectBootstrapLoaders(),
                'lookup_filename_constant' => $this->callLookupFilenameConstant($arguments),
                'list_page_modules' => $this->callListPageModules($arguments),
                'read_recent_logs' => $this->callReadRecentLogs($arguments),
                'list_installed_plugins' => $this->callListInstalledPlugins($arguments),
                'list_guidance_topics' => $this->callListGuidanceTopics(),
                'read_guidance_topic' => $this->callReadGuidanceTopic($arguments),
                'list_skill_topics' => $this->callListSkillTopics(),
                'read_skill_topic' => $this->callReadSkillTopic($arguments),
                'list_skills' => $this->callListSkills(),
                'get_skill' => $this->callGetSkill($arguments),
                'match_skill_for_task' => $this->callMatchSkillForTask($arguments),
                'validate_work_against_skill' => $this->callValidateWorkAgainstSkill($arguments),
                'ask_with_skill_context' => $this->callAskWithSkillContext($arguments),
                'plugin_doctor' => $this->callPluginDoctor($arguments),
                default => throw new InvalidArgumentException('Unknown tool: ' . $name),
            };
        } catch (Throwable $exception) {
            return $this->error($id, -32000, $exception->getMessage());
        }

        return $this->success($id, [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]],
            'structuredContent' => $result,
        ]);
    }

    private function callSearchDocs(array $arguments): array
    {
        $query = trim((string)($arguments['query'] ?? ''));
        $limit = max(1, (int)($arguments['limit'] ?? 5));

        if ($query === '') {
            throw new InvalidArgumentException('search_docs requires a non-empty query.');
        }

        $docsIndex = $this->storage->readJsonFile($this->paths->docsIndexPath());

        return [
            'query' => $query,
            'results' => $this->search->searchDocs($docsIndex, $query, $limit),
        ];
    }

    private function callSearchRepo(array $arguments): array
    {
        $query = trim((string)($arguments['query'] ?? ''));
        $limit = max(1, (int)($arguments['limit'] ?? 5));

        if ($query === '') {
            throw new InvalidArgumentException('search_repo requires a non-empty query.');
        }

        $repoIndex = $this->storage->readJsonFile($this->paths->repoIndexPath());

        return [
            'query' => $query,
            'results' => $this->search->searchRepo($repoIndex, $query, $limit),
        ];
    }

    private function callCompareDocsToCode(array $arguments): array
    {
        $query = trim((string)($arguments['query'] ?? ''));
        $limit = max(1, (int)($arguments['limit'] ?? 3));

        if ($query === '') {
            throw new InvalidArgumentException('compare_docs_to_code requires a non-empty query.');
        }

        $docsIndex = $this->storage->readJsonFile($this->paths->docsIndexPath());
        $repoIndex = $this->storage->readJsonFile($this->paths->repoIndexPath());

        return $this->comparison->compare($docsIndex, $repoIndex, $query, $limit);
    }

    private function callInspectPluginManifest(array $arguments): array
    {
        $path = trim((string)($arguments['path'] ?? ''));
        if ($path === '') {
            throw new InvalidArgumentException('inspect_plugin_manifest requires a path.');
        }

        return $this->manifestInspector->inspect($path);
    }

    private function callInspectPluginInstaller(array $arguments): array
    {
        $path = trim((string)($arguments['path'] ?? ''));
        if ($path === '') {
            throw new InvalidArgumentException('inspect_plugin_installer requires a path.');
        }

        return $this->installerInspector->inspect($path);
    }

    private function callInspectBootstrapLoaders(): array
    {
        return $this->runtimeInspector->inspectBootstrapLoaders();
    }

    private function callLookupFilenameConstant(array $arguments): array
    {
        $query = trim((string)($arguments['query'] ?? ''));
        if ($query === '') {
            throw new InvalidArgumentException('lookup_filename_constant requires a query.');
        }

        return $this->runtimeInspector->lookupFilenameConstant($query);
    }

    private function callListPageModules(array $arguments): array
    {
        $page = trim((string)($arguments['page'] ?? ''));
        if ($page === '') {
            throw new InvalidArgumentException('list_page_modules requires a page.');
        }

        return $this->runtimeInspector->listPageModules($page);
    }

    private function callReadRecentLogs(array $arguments): array
    {
        $pattern = trim((string)($arguments['pattern'] ?? ''));
        $lineLimit = max(1, (int)($arguments['line_limit'] ?? 40));
        $fileLimit = max(1, (int)($arguments['file_limit'] ?? 5));

        return $this->runtimeInspector->readRecentLogs($pattern, $lineLimit, $fileLimit);
    }

    private function callListInstalledPlugins(array $arguments): array
    {
        $status = trim((string)($arguments['status'] ?? 'all'));

        return $this->runtimeInspector->listInstalledPlugins($status);
    }

    private function callListGuidanceTopics(): array
    {
        return [
            'topics' => $this->guidance->listTopics(),
        ];
    }

    private function callReadGuidanceTopic(array $arguments): array
    {
        $topic = trim((string)($arguments['topic'] ?? ''));
        if ($topic === '') {
            throw new InvalidArgumentException('read_guidance_topic requires a topic.');
        }

        return $this->guidance->readTopic($topic);
    }

    private function callListSkillTopics(): array
    {
        return [
            'topics' => $this->skills->listTopics(),
        ];
    }

    private function callReadSkillTopic(array $arguments): array
    {
        $topic = trim((string)($arguments['topic'] ?? ''));
        if ($topic === '') {
            throw new InvalidArgumentException('read_skill_topic requires a topic.');
        }

        return $this->skills->readTopic($topic);
    }

    private function callListSkills(): array
    {
        return [
            'skills' => $this->skills->listSkills(),
        ];
    }

    private function callGetSkill(array $arguments): array
    {
        $skillId = trim((string)($arguments['skill'] ?? ($arguments['id'] ?? '')));
        if ($skillId === '') {
            throw new InvalidArgumentException('get_skill requires a skill id.');
        }

        return $this->skills->getSkill($skillId);
    }

    private function callMatchSkillForTask(array $arguments): array
    {
        $task = trim((string)($arguments['task'] ?? ''));
        $limit = max(1, (int)($arguments['limit'] ?? 3));

        if ($task === '') {
            throw new InvalidArgumentException('match_skill_for_task requires a task.');
        }

        return $this->skills->matchSkill($task, $limit);
    }

    private function callValidateWorkAgainstSkill(array $arguments): array
    {
        $skillId = trim((string)($arguments['skill'] ?? ($arguments['id'] ?? '')));
        if ($skillId === '') {
            throw new InvalidArgumentException('validate_work_against_skill requires a skill id.');
        }

        $context = [];
        foreach (['plugin_root', 'project_root', 'root_path'] as $key) {
            $value = trim((string)($arguments[$key] ?? ''));
            if ($value !== '') {
                $context[$key] = $value;
            }
        }

        return $this->skills->validateSkill($skillId, $context);
    }

    private function callAskWithSkillContext(array $arguments): array
    {
        $question = trim((string)($arguments['question'] ?? ($arguments['query'] ?? '')));
        $limit = max(1, (int)($arguments['limit'] ?? 3));
        $skillLimit = max(1, (int)($arguments['skill_limit'] ?? 3));
        $pluginRoot = trim((string)($arguments['plugin_root'] ?? ($arguments['path'] ?? '')));

        if ($question === '') {
            throw new InvalidArgumentException('ask_with_skill_context requires a question.');
        }

        $docsIndex = $this->storage->readJsonFile($this->paths->docsIndexPath());
        $repoIndex = $this->storage->readJsonFile($this->paths->repoIndexPath());

        return $this->answer->answerWithSkillContext($docsIndex, $repoIndex, $question, $limit, $skillLimit, $pluginRoot === '' ? null : $pluginRoot);
    }

    private function callPluginDoctor(array $arguments): array
    {
        $path = trim((string)($arguments['path'] ?? ''));
        if ($path === '') {
            throw new InvalidArgumentException('plugin_doctor requires a path.');
        }

        return $this->doctor->diagnose($path);
    }

    private function toolDefinitions(): array
    {
        return [
            [
                'name' => 'search_docs',
                'description' => 'Search the cached Zen Cart documentation index.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'search_repo',
                'description' => 'Search the local Zen Cart repository catalog.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'compare_docs_to_code',
                'description' => 'Return both docs and local code evidence for a Zen Cart question.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'inspect_plugin_manifest',
                'description' => 'Inspect a Zen Cart plugin manifest for baseline required fields.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'inspect_plugin_installer',
                'description' => 'Inspect a Zen Cart plugin installer directory for baseline expected files and hooks.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'inspect_bootstrap_loaders',
                'description' => 'List core and plugin loader inputs used during Zen Cart bootstrap.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new stdClass(),
                ],
            ],
            [
                'name' => 'lookup_filename_constant',
                'description' => 'Find FILENAME_* constant definitions by constant name or page basename.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'list_page_modules',
                'description' => 'List the module files and template candidates for a Zen Cart page.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'page' => ['type' => 'string'],
                    ],
                    'required' => ['page'],
                ],
            ],
            [
                'name' => 'read_recent_logs',
                'description' => 'Read the tail of the most recent Zen Cart log files, optionally filtered by filename pattern.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'pattern' => ['type' => 'string'],
                        'line_limit' => ['type' => 'integer', 'minimum' => 1],
                        'file_limit' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
            ],
            [
                'name' => 'list_installed_plugins',
                'description' => 'List plugins from Zen Cart plugin manager state, optionally filtered by status.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'list_guidance_topics',
                'description' => 'List bundled Zen Cart guidance topics for agents.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new stdClass(),
                ],
            ],
            [
                'name' => 'read_guidance_topic',
                'description' => 'Read a bundled Zen Cart guidance topic by slug.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => ['type' => 'string'],
                    ],
                    'required' => ['topic'],
                ],
            ],
            [
                'name' => 'list_skill_topics',
                'description' => 'List bundled task-specific Zen Cart skills for agents.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new stdClass(),
                ],
            ],
            [
                'name' => 'read_skill_topic',
                'description' => 'Read a bundled task-specific Zen Cart skill by slug.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => ['type' => 'string'],
                    ],
                    'required' => ['topic'],
                ],
            ],
            [
                'name' => 'list_skills',
                'description' => 'List structured Zen Cart workflow skills bundled with Zen AI Assist.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new stdClass(),
                ],
            ],
            [
                'name' => 'get_skill',
                'description' => 'Read one structured Zen Cart workflow skill by id.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'skill' => ['type' => 'string'],
                    ],
                    'required' => ['skill'],
                ],
            ],
            [
                'name' => 'match_skill_for_task',
                'description' => 'Return the best matching Zen Cart workflow skills for a task description.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'task' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['task'],
                ],
            ],
            [
                'name' => 'validate_work_against_skill',
                'description' => 'Run a skill-defined validation checklist against a plugin or project path.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'skill' => ['type' => 'string'],
                        'plugin_root' => ['type' => 'string'],
                        'project_root' => ['type' => 'string'],
                        'root_path' => ['type' => 'string'],
                    ],
                    'required' => ['skill'],
                ],
            ],
            [
                'name' => 'ask_with_skill_context',
                'description' => 'Answer a Zen Cart task question using docs, repo evidence, and the best matching workflow skill.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'question' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1],
                        'skill_limit' => ['type' => 'integer', 'minimum' => 1],
                        'plugin_root' => ['type' => 'string'],
                    ],
                    'required' => ['question'],
                ],
            ],
            [
                'name' => 'plugin_doctor',
                'description' => 'Run combined Zen AI Assist checks against a plugin root or manifest path.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                    ],
                    'required' => ['path'],
                ],
            ],
        ];
    }

    private function negotiateProtocolVersion(array $params): string
    {
        $protocolVersion = trim((string)($params['protocolVersion'] ?? ''));

        return $protocolVersion !== '' ? $protocolVersion : self::DEFAULT_PROTOCOL_VERSION;
    }

    private function readMessage(): ?array
    {
        while (($line = fgets(STDIN)) !== false) {
            $trimmed = rtrim($line, "\r\n");
            if ($trimmed === '') {
                continue;
            }

            if ($this->looksLikeJsonMessage($trimmed)) {
                $this->transportMode = self::TRANSPORT_JSON_LINE;

                $decoded = json_decode($trimmed, true);
                if (!is_array($decoded)) {
                    return null;
                }

                return $decoded;
            }

            $headers = $this->readHeaderBlock($trimmed);
            if ($headers === []) {
                continue;
            }

            $this->transportMode = self::TRANSPORT_CONTENT_LENGTH;

            $length = (int)($headers['content-length'] ?? 0);
            if ($length <= 0) {
                return null;
            }

            $body = '';
            while (strlen($body) < $length) {
                $chunk = fread(STDIN, $length - strlen($body));
                if ($chunk === false || $chunk === '') {
                    break;
                }

                $body .= $chunk;
            }

            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function writeMessage(array $message): void
    {
        $body = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($body)) {
            return;
        }

        if ($this->transportMode === self::TRANSPORT_JSON_LINE) {
            fwrite(STDOUT, $body . PHP_EOL);
            fflush(STDOUT);

            return;
        }

        fwrite(STDOUT, 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body);
        fflush(STDOUT);
    }

    private function success(mixed $id, array $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    private function error(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    private function looksLikeJsonMessage(string $line): bool
    {
        $firstCharacter = $line[0] ?? '';

        return $firstCharacter === '{' || $firstCharacter === '[';
    }

    private function readHeaderBlock(string $firstLine): array
    {
        $headers = [];
        $line = $firstLine;

        while (true) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, null);
            if ($name !== null && $value !== null) {
                $headers[strtolower(trim($name))] = trim($value);
            }

            $nextLine = fgets(STDIN);
            if ($nextLine === false) {
                break;
            }

            $line = rtrim($nextLine, "\r\n");
            if ($line === '') {
                break;
            }
        }

        return $headers;
    }
}
