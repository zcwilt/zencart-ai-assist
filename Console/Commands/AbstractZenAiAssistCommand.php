<?php
/**
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace Zencart\Plugins\Console\ZenAiAssist\Commands;

use Zencart\Console\ConsoleCommand;

abstract class AbstractZenAiAssistCommand extends ConsoleCommand
{
    /**
     * @since ZC v3.0.0
     */
    protected function pluginRoot(): string
    {
        return \ZenAiAssistPathHelper::resolveInstalledPluginRoot(
            $this->physicalPluginRoot(),
            $this->projectRoot()
        ) ?? $this->physicalPluginRoot();
    }

    /**
     * @since ZC v3.0.0
     */
    protected function physicalPluginRoot(): string
    {
        return dirname(__DIR__, 2) . '/';
    }

    /**
     * @since ZC v3.0.0
     */
    protected function projectRoot(): string
    {
        return \ZenAiAssistPathHelper::resolveProjectRoot(null, $this->physicalPluginRoot());
    }

    /**
     * @since ZC v3.0.0
     */
    protected function paths(): \ZenAiAssistPathHelper
    {
        return new \ZenAiAssistPathHelper(
            $this->pluginRoot(),
            $this->projectRoot(),
            $this->physicalPluginRoot()
        );
    }

    /**
     * @since ZC v3.0.0
     */
    protected function storage(): \ZenAiAssistJsonStorage
    {
        return new \ZenAiAssistJsonStorage();
    }
}
