<?php
/**
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace Zencart\Plugins\Console\ZenAiAssist\Commands;

use Zencart\Console\ConsoleInput;
use Zencart\Console\ConsoleOutput;

class McpServeCommand extends AbstractZenAiAssistCommand
{
    /**
     * @since ZC v3.0.0
     */
    public function getName(): string
    {
        return 'ai:mcp:serve';
    }

    /**
     * @since ZC v3.0.0
     */
    public function getDescription(): string
    {
        return 'Run the Zen AI Assist MCP server over stdio.';
    }

    /**
     * @since ZC v3.0.0
     */
    public function getAliases(): array
    {
        return ['mcp:serve'];
    }

    /**
     * @since ZC v3.0.0
     */
    public function getUsageLines(): array
    {
        return [
            'bin/zencart ai:mcp:serve',
            'php zc_cli.php ai:mcp:serve',
        ];
    }

    /**
     * @since ZC v3.0.0
     */
    public function handle(ConsoleInput $input, ConsoleOutput $output): int
    {
        unset($input, $output);

        $server = new \ZenAiAssistMcpServer($this->paths());

        return $server->run();
    }
}
