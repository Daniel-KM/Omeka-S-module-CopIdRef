<?php declare(strict_types=1);

namespace CopIdRef;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $this->getServiceLocator()->get('Omeka\Acl')
            ->allow(
                null,
                [Controller\ApiProxyController::class]
            );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.layout',
            [$this, 'addAdminResourceHeaders']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.layout',
            [$this, 'addAdminResourceHeaders']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.layout',
            [$this, 'addAdminResourceHeaders']
        );
        // For simplicity, some modules that use resource form are added here.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Annotation',
            'view.layout',
            [$this, 'addAdminResourceHeaders']
        );
        $sharedEventManager->attach(
            \Article\Controller\Admin\ArticleController::class,
            'view.layout',
            [$this, 'addAdminResourceHeaders']
        );
    }

    public function addAdminResourceHeaders(Event $event): void
    {
        /** @var \Laminas\View\Renderer\PhpRenderer $view */
        $view = $event->getTarget();

        $action = $view->params()->fromRoute('action');
        if (!in_array($action, ['add', 'edit'])) {
            return;
        }

        $plugins = $view->getHelperPluginManager();
        $setting = $plugins->get('setting');
        $assetUrl = $plugins->get('assetUrl');

        // Liste officielle tirée de l'exemple. Les valeurs sont les clés.
        /** @link http://documentation.abes.fr/aideidrefdeveloppeur/index.html#installation */
        $defaultAvailableIdRefResources = [
            'Nom de personne',
            'Nom de collectivité',
            'Nom commun',
            'Nom géographique',
            'Famille',
            'Titre',
            'Auteur-Titre',
            'Nom de marque',
            'Ppn',
            'Rcr',
            'Tout',
        ];
        $availableIdRefResources = $setting('copidref_available_resources') ?: $defaultAvailableIdRefResources;
        $script = 'const availableIdRefResources = ' . json_encode($availableIdRefResources, 320) . ';';

        $view->headLink()
            ->appendStylesheet($assetUrl('css/idref-admin.css', 'CopIdRef'))
            ->appendStylesheet($assetUrl('css/idref-sub-modal.css', 'CopIdRef'));
        $view->headScript()
            ->appendScript($script)
            ->appendFile($assetUrl('js/idref-sub-modal.js', 'CopIdRef'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('js/idref-admin.js', 'CopIdRef'), 'text/javascript', ['defer' => 'defer']);
    }
}
