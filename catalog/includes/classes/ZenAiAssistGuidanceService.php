<?php

class ZenAiAssistGuidanceService
{
    private string $guidanceDirectory;

    public function __construct(string $guidanceDirectory)
    {
        $this->guidanceDirectory = rtrim($guidanceDirectory, '/\\') . '/';
    }

    public function listTopics(): array
    {
        $topics = [];

        foreach ($this->topicFiles() as $path) {
            $slug = basename($path, '.md');
            $contents = @file($path, FILE_IGNORE_NEW_LINES);
            $title = $slug;

            if (is_array($contents) && isset($contents[0])) {
                $firstLine = trim((string)$contents[0]);
                if (str_starts_with($firstLine, '# ')) {
                    $title = trim(substr($firstLine, 2));
                }
            }

            $topics[] = [
                'topic' => $slug,
                'title' => $title,
                'path' => $path,
            ];
        }

        usort($topics, static function (array $left, array $right): int {
            return [(string)$left['title'], (string)$left['topic']] <=> [(string)$right['title'], (string)$right['topic']];
        });

        return $topics;
    }

    public function readTopic(string $topic): array
    {
        $topic = trim($topic);
        if ($topic === '') {
            return [
                'topic' => $topic,
                'found' => false,
                'message' => 'Topic is required.',
            ];
        }

        $path = $this->guidanceDirectory . $topic . '.md';
        if (!is_file($path)) {
            return [
                'topic' => $topic,
                'found' => false,
                'message' => 'Guidance topic not found.',
            ];
        }

        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            return [
                'topic' => $topic,
                'found' => false,
                'message' => 'Guidance topic could not be read.',
            ];
        }

        return [
            'topic' => $topic,
            'found' => true,
            'path' => $path,
            'content' => $contents,
        ];
    }

    private function topicFiles(): array
    {
        if (!is_dir($this->guidanceDirectory)) {
            return [];
        }

        $files = glob($this->guidanceDirectory . '*.md') ?: [];
        sort($files);

        return $files;
    }
}
