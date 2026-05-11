<?php

class ZenAiAssistDocSourceRegistry
{
    public static function all(): array
    {
        return [
            [
                'url' => 'https://docs.zen-cart.com/dev/',
                'tags' => ['developer-docs'],
            ],
            [
                'url' => 'https://docs.zen-cart.com/dev/plugins/',
                'tags' => ['plugins'],
            ],
            [
                'url' => 'https://docs.zen-cart.com/dev/plugins/encapsulated/',
                'tags' => ['plugins', 'encapsulated-plugins'],
                'required' => false,
            ],
            [
                'url' => 'https://docs.zen-cart.com/dev/plugins/encapsulated/manifests/',
                'tags' => ['plugins', 'manifest'],
                'required' => false,
            ],
            [
                'url' => 'https://docs.zen-cart.com/dev/schema/',
                'tags' => ['schema'],
            ],
        ];
    }
}
