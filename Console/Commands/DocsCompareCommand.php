<?php
/**
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace Zencart\Plugins\Console\ZenAiAssist\Commands;

use Zencart\Console\ConsoleInput;
use Zencart\Console\ConsoleOutput;

class DocsCompareCommand extends AbstractZenAiAssistCommand
{
    public function getName(): string
    {
        return 'ai:docs:compare';
    }

    public function getDescription(): string
    {
        return 'Compare cached documentation guidance against current repository behavior.';
    }

    public function getAliases(): array
    {
        return ['docs:compare'];
    }

    public function getUsageLines(): array
    {
        return [
            'bin/zencart ai:docs:compare <question>',
            'php zc_cli.php ai:docs:compare <question>',
        ];
    }

    public function handle(ConsoleInput $input, ConsoleOutput $output): int
    {
        $query = trim(implode(' ', $input->getArguments()));
        if ($query === '') {
            $output->errorln('Usage: bin/zencart ai:docs:compare <question>');
            return 1;
        }

        $storage = $this->storage();
        $paths = $this->paths();
        $comparison = new \ZenAiAssistComparisonService();
        $docsIndex = $storage->readJsonFile($paths->docsIndexPath());
        $repoIndex = $storage->readJsonFile($paths->repoIndexPath());
        $result = $comparison->compare($docsIndex, $repoIndex, $query);

        $output->writeln('Query: ' . $result['query']);
        $output->writeln('Summary: ' . $result['summary']);
        $output->writeln('Confidence: ' . $result['confidence']);

        foreach (['docs' => 'Documentation evidence', 'repo' => 'Repository evidence'] as $key => $label) {
            $records = $result[$key] ?? [];
            $output->writeln();
            $output->writeln($label . ':');

            if ($records === []) {
                $output->writeln('  none');
                continue;
            }

            foreach ($records as $index => $record) {
                $title = (string)($record['title'] ?? ($record['path'] ?? 'Untitled'));
                $output->writeln(sprintf('  %d. %s', $index + 1, $title));
                if (!empty($record['url'])) {
                    $output->writeln('     url: ' . $record['url']);
                }
                if (!empty($record['path'])) {
                    $output->writeln('     path: ' . $record['path']);
                }
                if (!empty($record['heading_path'])) {
                    $output->writeln('     heading: ' . implode(' > ', $record['heading_path']));
                }
                $excerpt = trim((string)($record['excerpt'] ?? ''));
                if ($excerpt !== '') {
                    $output->writeln('     excerpt: ' . $excerpt);
                }
            }
        }

        return 0;
    }
}
