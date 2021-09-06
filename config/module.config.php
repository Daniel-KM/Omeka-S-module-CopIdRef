<?php declare(strict_types=1);

namespace IdRef;

return [
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'idref' => [
        'config' => [
            'idref_user_id' => '',
        ],
    ],
];
