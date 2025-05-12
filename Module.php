<?php declare(strict_types=1);

namespace CopIdRef;

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\TraitModule;
use CopIdRef\Form\ConfigForm;
use Common\Stdlib\PsrMessage;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;

/**
 * CopIdRef
 *
 * @copyright Daniel Berthereau, 2021-2024
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.67')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.67'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

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
        if (!$this->handleConfigFormAuto($controller)) {
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
            $message = new PsrMessage(
                'Le mode de mise à jour n’est pas indiqué.'
            );
            $messenger->addError($message);
            return true;
        }

        if (empty($args['properties'])) {
            $message = new PsrMessage(
                'Les propriétés à mettre à jour ne sont pas indiquées.'
            );
            $messenger->addError($message);
            return true;
        }

        if (empty($args['property_uri'])) {
            $message = new PsrMessage(
                'La propriété où se trouve l’uri n’est pas indiquée.'
            );
            $messenger->addError($message);
            return true;
        }

        $args['query'] ??= '';
        if ($args['query']) {
            $pos = mb_strpos($args['query'], '?');
            if ($pos !== false) {
                $args['query'] = mb_substr($args['query'], $pos + 1);
                $message = new PsrMessage(
                    'Il est inutile d’indiquer la première partie de la requête.' // @translate
                );
                $messenger->addWarning($message);
            }
        }

        $query = [];
        parse_str($args['query'] ?? '', $query);
        $args['query'] = $query;

        $args = array_intersect_key($args, array_flip(['mode', 'query', 'properties', 'datatypes', 'property_uri', 'mapping_key']));

        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $job = $dispatcher->dispatch(\CopIdRef\Job\SyncIdRef::class, $args);

        $message = new PsrMessage(
            'Mise à jour des ressources via IdRef en arrière-plan ({link_job}tâche #{job_id}{link_end}, {link_log}journaux{link_end}).', // @translate
            [
                'link_job' => sprintf('<a href="%s">',
                    htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                ),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => sprintf('<a href="%s">',
                    htmlspecialchars($this->isModuleActive('Log')
                        ? $urlPlugin->fromRoute('admin/log', [], ['query' => ['job_id' => $job->getId()]])
                        : $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId(), 'action' => 'log'])
                    )
                )
            ]
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
