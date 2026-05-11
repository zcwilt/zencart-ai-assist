<?php
/**
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace Zencart\Plugins\Console\ZenAiAssist\Commands;

use Zencart\Console\ConsoleInput;
use Zencart\Console\ConsoleOutput;

class DocsSearchCommand extends AbstractZenAiAssistCommand
{
    /**
     * @since ZC v3.0.0
     */
    public function getName(): string
    {
        return 'ai:docs:search';
    }

    /**
     * @since ZC v3.0.0
     */
    public function getDescription(): string
    {
        return 'Search the cached documentation and repository catalogs.';
    }

    /**
     * @since ZC v3.0.0
     */
    public function getAliases(): array
    {
        return ['docs:search'];
    }

    /**
     * @since ZC v3.0.0
     */
    public function getUsageLines(): array
    {
        return [
            'bin/zencart ai:docs:search <terms>',
            'php zc_cli.php ai:docs:search <terms>',
        ];
    }

    /**
     * @since ZC v3.0.0
     */
    public function handle(ConsoleInput $input, ConsoleOutput $output): int
    {
        $query = trim(implode(' ', $input->getArguments()));
        if ($query === '') {
            $output->errorln('Usage: bin/zencart ai:docs:search <terms>');
            return 1;
        }

        $storage = $this->storage();
        $paths = $this->paths();
        $search = new \ZenAiAssistSearchService();
        $docsIndex = $storage->readJsonFile($paths->docsIndexPath());
        $repoIndex = $storage->readJsonFile($paths->repoIndexPath());
        $results = $search->search($docsIndex, $repoIndex, $query);

        if ($results === []) {
            $output->writeln('No results found.');
            return 0;
        }

        foreach ($results as $index => $result) {
            $output->writeln(sprintf(
                '%d. [%s] %s',
                $index + 1,
                strtoupper((string)($result['type'] ?? 'record')),
                (string)($result['title'] ?? ($result['path'] ?? 'Untitled'))
            ));
            if (!empty($result['url'])) {
                $output->writeln('   url: ' . $result['url']);
            }
            if (!empty($result['path'])) {
                $output->writeln('   path: ' . $result['path']);
            }
            if (!empty($result['heading_path'])) {
                $output->writeln('   heading: ' . implode(' > ', $result['heading_path']));
            }
            $output->writeln('   score: ' . (int)($result['_score'] ?? 0));
            $output->writeln('   excerpt: ' . trim((string)($result['excerpt'] ?? '')));
        }

        return 0;
    }
}
