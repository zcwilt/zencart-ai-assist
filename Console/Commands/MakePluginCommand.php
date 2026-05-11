<?php
/**
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace Zencart\Plugins\Console\ZenAiAssist\Commands;

use Zencart\Console\ConsoleInput;
use Zencart\Console\ConsoleOutput;

class MakePluginCommand extends AbstractZenAiAssistCommand
{
    public function getName(): string
    {
        return 'ai:make:plugin';
    }

    public function getDescription(): string
    {
        return 'Scaffold a minimal encapsulated Zen Cart plugin structure.';
    }

    public function getAliases(): array
    {
        return ['make:plugin'];
    }

    public function getUsageLines(): array
    {
        return [
            'bin/zencart ai:make:plugin <unique-key> [version] [--name=<display-name>] [--author=<author>] [--description=<description>]',
            'php zc_cli.php ai:make:plugin <unique-key> [version]',
        ];
    }

    public function handle(ConsoleInput $input, ConsoleOutput $output): int
    {
        $uniqueKey = trim((string)$input->getArgument(0, ''));
        if ($uniqueKey === '') {
            $output->errorln('Usage: bin/zencart ai:make:plugin <unique-key> [version]');
            return 1;
        }

        $version = trim((string)$input->getArgument(1, 'v1.0.0'));
        $scaffolder = new \ZenAiAssistPluginScaffolder();
        $result = $scaffolder->scaffold($this->paths()->projectRoot(), $uniqueKey, $version, [
            'name' => (string)$input->getOption('name', ''),
            'author' => (string)$input->getOption('author', ''),
            'description' => (string)$input->getOption('description', ''),
        ]);

        $output->writeln(($result['ok'] ? 'OK' : 'FAIL') . ': ' . $result['message']);
        if (!empty($result['plugin_root'])) {
            $output->writeln('Plugin root: ' . $result['plugin_root']);
        }
        if (!empty($result['normalized_key']) && $result['normalized_key'] !== $uniqueKey) {
            $output->writeln('Normalized key: ' . $result['normalized_key']);
        }
        foreach ($result['files'] ?? [] as $file) {
            $output->writeln('Created: ' . $file);
        }

        return $result['ok'] ? 0 : 1;
    }
}
