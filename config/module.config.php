<?php declare(strict_types=1);

namespace CopIdRef;

return [
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    // Deprecated in Omeka S v4.1.
    'controllers' => [
        'factories' => [
            Controller\ApiProxyController::class => Service\Controller\ApiProxyControllerFactory::class,
        ],
    ],
    // Deprecated in Omeka S v4.1.
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
    'js_translate_strings' => [
        'oFrame.contentWindow Failed?', // @translate
        'Warning: cross-domain request!', // @translate
        'Data from endpoint are empty.', // @translate
        'Unable to determine the resource type.', // @translate
        'Data are missing or incomplete.', // @translate
        'Resource created from api successfully.', // @translate
        '[Untitled]', // @translate
        'Failed creating resource from api.', // @translate
        'Failed to load mapping. Creating a default resource.', // @translate
        'Mapping for resource class is incorrect. Skipped.', // @translate
        'Mapping for resource template is incorrect. Skipped.', // @translate
        'Mapping for property is incorrect. Skipped.', // @translate
    ],
    'copidref' => [
        'config' => [
            // Non utilisé actuellement : l'utilisateur utilise directement la session IdRef.
            'copidref_user_id' => '',
            'copidref_available_resources' => [],
        ],
    ],
];
