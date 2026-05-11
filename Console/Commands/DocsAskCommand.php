<?php
/**
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace Zencart\Plugins\Console\ZenAiAssist\Commands;

use Zencart\Console\ConsoleInput;
use Zencart\Console\ConsoleOutput;

class DocsAskCommand extends AbstractZenAiAssistCommand
{
    public function getName(): string
    {
        return 'ai:docs:ask';
    }

    public function getDescription(): string
    {
        return 'Answer a Zen Cart question using the cached docs and repository catalogs.';
    }

    public function getAliases(): array
    {
        return ['docs:ask'];
    }

    public function getUsageLines(): array
    {
        return [
            'bin/zencart ai:docs:ask <question>',
            'php zc_cli.php ai:docs:ask <question>',
        ];
    }

    public function handle(ConsoleInput $input, ConsoleOutput $output): int
    {
        $question = trim(implode(' ', $input->getArguments()));
        if ($question === '') {
            $output->errorln('Usage: bin/zencart ai:docs:ask <question>');
            return 1;
        }

        $storage = $this->storage();
        $paths = $this->paths();
        $service = new \ZenAiAssistAnswerService();
        $docsIndex = $storage->readJsonFile($paths->docsIndexPath());
        $repoIndex = $storage->readJsonFile($paths->repoIndexPath());
        $answer = $service->answer($docsIndex, $repoIndex, $question);

        $output->writeln('Question: ' . $answer['question']);
        $output->writeln();
        $output->writeln('Documented approach:');
        $output->writeln($answer['documented_approach']);
        $output->writeln();
        $output->writeln('Current repo behavior:');
        $output->writeln($answer['current_repo_behavior']);
        $output->writeln();
        $output->writeln('Mismatch/confidence note:');
        $output->writeln($answer['mismatch_note'] . ' Confidence: ' . $answer['confidence'] . '.');

        foreach (['docs' => 'Top docs evidence', 'repo' => 'Top repo evidence'] as $key => $label) {
            $records = $answer[$key] ?? [];
            if ($records === []) {
                continue;
            }

            $output->writeln();
            $output->writeln($label . ':');
            foreach ($records as $index => $record) {
                $title = (string)($record['title'] ?? ($record['path'] ?? 'Untitled'));
                $output->writeln(sprintf('  %d. %s', $index + 1, $title));
                if (!empty($record['url'])) {
                    $output->writeln('     url: ' . $record['url']);
                }
                if (!empty($record['path'])) {
                    $output->writeln('     path: ' . $record['path']);
                }
            }
        }

        return 0;
    }
}
