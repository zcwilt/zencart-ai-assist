<?php

class ZenAiAssistDocFetcher
{
    private ZenAiAssistPathHelper $paths;
    private ZenAiAssistJsonStorage $storage;

    public function __construct(ZenAiAssistPathHelper $paths, ZenAiAssistJsonStorage $storage)
    {
        $this->paths = $paths;
        $this->storage = $storage;
    }

    public function fetchAll(array $sources): array
    {
        $results = [];
        $this->paths->ensureDirectory($this->paths->docsCacheDirectory());

        foreach ($sources as $source) {
            $results[] = $this->fetchOne($source);
        }

        return $results;
    }

    public function fetchOne(array $source): array
    {
        $url = (string)($source['url'] ?? '');
        $tags = isset($source['tags']) && is_array($source['tags']) ? array_values($source['tags']) : [];
        $required = array_key_exists('required', $source) ? (bool)$source['required'] : true;
        $filePath = $this->paths->docsCacheDirectory() . $this->paths->slugForUrl($url) . '.json';

        if ($url === '') {
            return ['url' => $url, 'status' => 'skipped', 'reason' => 'empty-url'];
        }

        $response = $this->download($url);
        if ($response['ok'] !== true) {
            $cachedDocument = $this->storage->readJsonFile($filePath);
            if ($cachedDocument !== []) {
                return [
                    'url' => $url,
                    'status' => 'cached',
                    'file' => $filePath,
                    'reason' => $response['error'],
                ];
            }

            if ($required === false) {
                return [
                    'url' => $url,
                    'status' => 'skipped',
                    'reason' => $response['error'],
                ];
            }

            return [
                'url' => $url,
                'status' => 'failed',
                'reason' => $response['error'],
            ];
        }

        $document = $this->parseDocument($url, $response['body'], $tags, $response['headers']);
        $this->storage->writeJsonFile($filePath, $document);

        return [
            'url' => $url,
            'status' => 'ok',
            'file' => $filePath,
            'title' => $document['title'] ?? '',
        ];
    }

    protected function download(string $url): array
    {
        if (function_exists('curl_init')) {
            $headers = [];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'ZenAiAssist/1.0',
                CURLOPT_HEADERFUNCTION => static function ($curl, $headerLine) use (&$headers) {
                    $length = strlen($headerLine);
                    $parts = explode(':', $headerLine, 2);
                    if (count($parts) === 2) {
                        $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }

                    return $length;
                },
            ]);
            $body = curl_exec($ch);
            $error = curl_error($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if (!is_string($body) || $body === '') {
                return ['ok' => false, 'error' => $error !== '' ? $error : 'empty-response'];
            }

            if ($statusCode >= 400) {
                return ['ok' => false, 'error' => 'http-' . $statusCode];
            }

            return ['ok' => true, 'body' => $body, 'headers' => $headers];
        }

        $body = @file_get_contents($url);
        if (!is_string($body) || $body === '') {
            return ['ok' => false, 'error' => 'download-failed'];
        }

        return ['ok' => true, 'body' => $body, 'headers' => []];
    }

    private function parseDocument(string $url, string $html, array $tags, array $headers): array
    {
        $title = $this->extractTitle($html);
        $bodyHtml = $html;
        $text = $this->normalizeWhitespace(strip_tags($html));
        $headings = [];
        $sections = [];

        if (class_exists('DOMDocument')) {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//script|//style|//noscript') ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }

            $body = $dom->getElementsByTagName('body')->item(0);
            if ($body !== null) {
                $bodyHtml = $dom->saveHTML($body) ?: $html;
                $text = $this->normalizeWhitespace($body->textContent);
                $headings = $this->extractHeadings($xpath);
                $sections = $this->extractSections($xpath, $url, $title, $tags);
            }
        }

        if ($title === '' && $headings !== []) {
            $title = (string)($headings[0]['text'] ?? '');
        }

        return [
            'url' => $url,
            'title' => $title,
            'tags' => $tags,
            'fetched_at' => gmdate('c'),
            'last_modified' => $headers['last-modified'] ?? '',
            'version_hints' => $this->detectVersionHints($text),
            'headings' => $headings,
            'sections' => $sections,
            'html' => $bodyHtml,
            'text' => $text,
        ];
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) === 1) {
            return html_entity_decode(trim(strip_tags($matches[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string)$text);
    }

    private function extractHeadings(DOMXPath $xpath): array
    {
        $headings = [];

        foreach ($xpath->query('//h1|//h2|//h3|//h4') ?: [] as $node) {
            $text = $this->normalizeWhitespace($node->textContent);
            if ($text === '') {
                continue;
            }

            $headings[] = [
                'level' => (int)substr(strtolower($node->nodeName), 1),
                'text' => $text,
                'anchor' => trim((string)$node->attributes?->getNamedItem('id')?->nodeValue),
            ];
        }

        return $headings;
    }

    private function extractSections(DOMXPath $xpath, string $url, string $title, array $tags): array
    {
        $sections = [];
        $headingStack = [['level' => 0, 'text' => $title !== '' ? $title : 'Document']];
        $current = [
            'heading_path' => [$title !== '' ? $title : 'Document'],
            'heading_level' => 0,
            'anchor' => '',
            'content_parts' => [],
        ];

        foreach ($xpath->query('//body//*[self::h1 or self::h2 or self::h3 or self::h4 or self::p or self::li or self::pre or self::code or self::table or self::blockquote]') ?: [] as $node) {
            $name = strtolower($node->nodeName);
            $text = $this->normalizeWhitespace($node->textContent);
            if ($text === '') {
                continue;
            }

            if (in_array($name, ['h1', 'h2', 'h3', 'h4'], true)) {
                $this->appendSection($sections, $current, $url, $title, $tags);

                $level = (int)substr($name, 1);
                while (count($headingStack) > 0 && $headingStack[count($headingStack) - 1]['level'] >= $level) {
                    array_pop($headingStack);
                }
                $headingStack[] = ['level' => $level, 'text' => $text];

                $current = [
                    'heading_path' => array_values(array_map(static fn (array $item): string => (string)$item['text'], $headingStack)),
                    'heading_level' => $level,
                    'anchor' => trim((string)$node->attributes?->getNamedItem('id')?->nodeValue),
                    'content_parts' => [],
                ];
                continue;
            }

            $current['content_parts'][] = $text;
        }

        $this->appendSection($sections, $current, $url, $title, $tags);

        return $sections;
    }

    private function appendSection(array &$sections, array $current, string $url, string $title, array $tags): void
    {
        $content = $this->normalizeWhitespace(implode("\n\n", $current['content_parts'] ?? []));
        if ($content === '') {
            return;
        }

        $sections[] = [
            'url' => $url,
            'title' => $title,
            'heading_path' => $current['heading_path'] ?? [$title !== '' ? $title : 'Document'],
            'heading_level' => (int)($current['heading_level'] ?? 0),
            'anchor' => (string)($current['anchor'] ?? ''),
            'tags' => $tags,
            'version_hints' => $this->detectVersionHints($content),
            'excerpt' => mb_substr($content, 0, 240),
            'content' => $content,
        ];
    }

    private function detectVersionHints(string $text): array
    {
        if (!preg_match_all('/\b(?:v)?\d+\.\d+(?:\.\d+)?\b/i', $text, $matches)) {
            return [];
        }

        $versions = array_map(static fn (string $match): string => ltrim(strtolower($match), 'v'), $matches[0]);
        $versions = array_values(array_unique($versions));
        sort($versions);

        return $versions;
    }
}
