<?php

class ZenAiAssistManifestInspector
{
    public function inspect(string $manifestPath): array
    {
        $requiredKeys = [
            'pluginVersion',
            'pluginName',
            'pluginDescription',
            'pluginAuthor',
            'pluginId',
            'zcVersions',
        ];

        if (!is_file($manifestPath)) {
            return [
                'ok' => false,
                'message' => 'Manifest file not found.',
                'missing' => $requiredKeys,
            ];
        }

        $manifest = require $manifestPath;
        if (!is_array($manifest)) {
            return [
                'ok' => false,
                'message' => 'Manifest did not return an array.',
                'missing' => $requiredKeys,
            ];
        }

        $missing = [];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $manifest)) {
                $missing[] = $key;
            }
        }

        return [
            'ok' => $missing === [],
            'message' => $missing === [] ? 'Manifest contains the baseline fields.' : 'Manifest is missing required fields.',
            'missing' => $missing,
            'manifest' => $manifest,
        ];
    }
}
