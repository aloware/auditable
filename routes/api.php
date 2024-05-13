<?php

use Aloware\Auditable\Controllers\AuditController;
use Illuminate\Routing\Router;

app('router')->group([
    'controller' => AuditController::class,
    'prefix' => app('config')->get('auditable.route_prefix'),
    'middleware' => app('config')->get('auditable.route_middleware', []),
], function (Router $router) {
    $router->get('audits/{model}/{id}', 'index');
});
