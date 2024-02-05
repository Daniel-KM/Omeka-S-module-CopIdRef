<?php declare(strict_types=1);

namespace CopIdRef\Controller;

use Omeka\Controller\ApiController;

/**
 * Like ApiController, but session based, so usable without credentials.
 *
 * @deprecated Remove for Omeka S v4.1.
 */
class ApiProxyController extends ApiController
{
}
