<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/system/config/{configName}', function (RouteCollectorProxy $group): void {
    $group->get('', 'getConfigValueByNameAPI');
    $group->post('', 'setConfigValueByNameAPI');
    $group->get('/', 'getConfigValueByNameAPI');
    $group->post('/', 'setConfigValueByNameAPI');
})->add(AdminRoleAuthMiddleware::class);

function getConfigValueByNameAPI(Request $request, Response $response, array $args): Response
{
    return SlimUtils::renderJSON($response, ['value' => SystemConfig::getValue($args['configName'])]);
}

function setConfigValueByNameAPI(Request $request, Response $response, array $args): Response
{
    $configName = $args['configName'];
    $input = $request->getParsedBody();
    $value = $input['value'] ?? null;
    $configItem = SystemConfig::getConfigItem($configName);
    if ($configItem && $configItem->getType() === 'password' && ($value === null || $value === '')) {
        return SlimUtils::renderJSON($response, ['value' => SystemConfig::getValue($configName)]);
    }
    SystemConfig::setValue($configName, $value);

    return SlimUtils::renderJSON($response, ['value' => SystemConfig::getValue($configName)]);
}
