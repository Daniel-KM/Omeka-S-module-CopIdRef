<?php declare(strict_types=1);

namespace CopIdRef;

use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Settings\Settings $settings
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
// $config = $services->get('Config');
$api = $plugins->get('api');

if (version_compare($oldVersion, '3.3.0.6', '<')) {
    $messenger = new Messenger();
    $message = new Message(
        'Une option permet désormais de limiter les types de ressources à chercher.' // @translate
    );
    $messenger->addSuccess($message);
}
