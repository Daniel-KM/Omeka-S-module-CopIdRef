<?php declare(strict_types=1);

namespace CopIdRef;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use CopIdRef\Form\ConfigForm;
use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Omeka\Stdlib\Message;

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
    }

    public function handleConfigForm(AbstractController $controller)
    {
        if (!parent::handleConfigForm($controller)) {
            return false;
        }

        $services = $this->getServiceLocator();
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $params = $controller->getRequest()->getPost();

        // Form is already validated in parent, but removed by Generic.
        $post = $params;
        $form->init();
        $form->setData($params);
        $form->isValid();
        $params = $form->getData();

        if (empty($post['sync_records']['process'])) {
            return true;
        }

        $plugins = $services->get('ControllerPluginManager');
        $messenger = $plugins->get('messenger');
        $urlPlugin = $plugins->get('url');

        $args = $post['sync_records'];

        if (empty($args['mode']) || !in_array($args['mode'], ['append', 'replace'])) {
            $message = new Message(
                'Le mode de mise à jour n’est pas indiqué.'
            );
            $messenger->addError($message);
            return true;
        }

        if (empty($args['properties'])) {
            $message = new Message(
                'Les propriétés à mettre à jour ne sont pas indiquées.'
            );
            $messenger->addError($message);
            return true;
        }

        if (empty($args['property_uri'])) {
            $message = new Message(
                'La propriété où se trouve l’uri n’est pas indiquée.'
            );
            $messenger->addError($message);
            return true;
        }

        $query = [];
        parse_str($args['query'] ?? '', $query);
        $args['query'] = $query;

        $args = array_intersect_key($args, array_flip(['mode', 'query', 'properties', 'datatypes', 'property_uri', 'mapping_key']));

        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $job = $dispatcher->dispatch(\CopIdRef\Job\SyncIdRef::class, $args);

        $message = new Message(
            'Mise à jour des ressources via IdRef en arrière-plan (%1$stâche #%2$d%3$s, %4$sjournaux%3$s).', // @translate
            sprintf('<a href="%s">',
                htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
            ),
            $job->getId(),
            '</a>',
            sprintf('<a href="%s">',
                htmlspecialchars($this->isModuleActive('Log')
                    ? $urlPlugin->fromRoute('admin/log', [], ['query' => ['job_id' => $job->getId()]])
                    : $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId(), 'action' => 'log'])
                )
            )
        );
        $message->setEscapeHtml(false);
        $messenger->addSuccess($message);
        return true;
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
