<?php declare(strict_types=1);

namespace CopIdRef\Service\Controller;

use CopIdRef\Controller\ApiProxyController;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ApiProxyControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ApiProxyController(
            $services->get('Omeka\Paginator'),
            $services->get('Omeka\ApiManager')
        );
    }
}
