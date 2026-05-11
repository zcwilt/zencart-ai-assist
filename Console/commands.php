<?php

require_once __DIR__ . '/bootstrap.php';

return [
    \Zencart\Plugins\Console\ZenAiAssist\Commands\DocsFetchCommand::class,
    \Zencart\Plugins\Console\ZenAiAssist\Commands\CatalogBuildCommand::class,
    \Zencart\Plugins\Console\ZenAiAssist\Commands\DocsSearchCommand::class,
    \Zencart\Plugins\Console\ZenAiAssist\Commands\DocsAskCommand::class,
    \Zencart\Plugins\Console\ZenAiAssist\Commands\DocsCompareCommand::class,
    \Zencart\Plugins\Console\ZenAiAssist\Commands\ManifestInspectCommand::class,
    \Zencart\Plugins\Console\ZenAiAssist\Commands\PluginDoctorCommand::class,
    \Zencart\Plugins\Console\ZenAiAssist\Commands\MakePluginCommand::class,
    \Zencart\Plugins\Console\ZenAiAssist\Commands\McpServeCommand::class,
];
