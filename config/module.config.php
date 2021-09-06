<?php declare(strict_types=1);

namespace IdRef;

return [
    'controllers' => [
        'factories' => [
            Controller\ApiProxyController::class => Service\Controller\ApiProxyControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'api-proxy' => [
                'type' => \Laminas\Router\Http\Literal::class,
                'options' => [
                    'route' => '/api-proxy',
                    'defaults' => [
                        '__API__' => false,
                        'controller' => Controller\ApiProxyController::class,
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'default' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '[/:resource[/:id]]',
                            'constraints' => [
                                'resource' => '[a-zA-Z0-9_-]+',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
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
