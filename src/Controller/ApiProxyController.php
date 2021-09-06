<?php declare(strict_types=1);

namespace IdRef\Controller;

use Omeka\Controller\ApiController;

/**
 * Like ApiController, but session based, so usable without credentials.
 */
class ApiProxyController extends ApiController
{
}
