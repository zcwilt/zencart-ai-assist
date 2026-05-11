<?php
/**
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace Zencart\Plugins\Console\ZenAiAssist\Commands;

use Zencart\Console\ConsoleInput;
use Zencart\Console\ConsoleOutput;

class ManifestInspectCommand extends AbstractZenAiAssistCommand
{
    /**
     * @since ZC v3.0.0
     */
    public function getName(): string
    {
        return 'ai:manifest:inspect';
    }

    /**
     * @since ZC v3.0.0
     */
    public function getDescription(): string
    {
        return 'Inspect a plugin manifest for the baseline required fields.';
    }

    /**
     * @since ZC v3.0.0
     */
    public function getAliases(): array
    {
        return ['manifest:inspect'];
    }

    /**
     * @since ZC v3.0.0
     */
    public function getUsageLines(): array
    {
        return [
            'bin/zencart ai:manifest:inspect <path>',
            'php zc_cli.php ai:manifest:inspect <path>',
        ];
    }

    /**
     * @since ZC v3.0.0
     */
    public function handle(ConsoleInput $input, ConsoleOutput $output): int
    {
        $manifestPath = $input->getArgument(0, '');
        if ($manifestPath === '') {
            $output->errorln('Usage: bin/zencart ai:manifest:inspect <path>');
            return 1;
        }

        $inspector = new \ZenAiAssistManifestInspector();
        $result = $inspector->inspect($manifestPath);
        $output->writeln(($result['ok'] ? 'OK' : 'FAIL') . ': ' . $result['message']);

        if (!empty($result['missing'])) {
            $output->writeln('Missing: ' . implode(', ', $result['missing']));
        }

        return $result['ok'] ? 0 : 1;
    }
}
