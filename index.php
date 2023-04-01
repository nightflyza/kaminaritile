<?php

define('LIBS_PATH', 'libs/');

require_once (LIBS_PATH . 'api.ubrouting.php');
require_once (LIBS_PATH . 'api.omaeurl.php');
require_once (LIBS_PATH . 'api.kominaritile.php');

$cache = new KominariTile();
if (ubRouting::checkGet($cache::ROUTE_TILE)) {
    $cache->renderTile(ubRouting::get($cache::ROUTE_TILE, 'vf'));
}
