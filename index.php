<?php

/**
 * Config section
 */
$cachingTimeout = 604800; // tile server caching timeout in seconds
$remoteTileServer = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'; //remote tile server custom URL template
$debug = false; // logging into cache/debug.log
/**
 * End of config section
 */
define('LIBS_PATH', 'libs/');

require_once (LIBS_PATH . 'api.ubrouting.php');
require_once (LIBS_PATH . 'api.omaeurl.php');
require_once (LIBS_PATH . 'api.kaminaritile.php');

$cache = new KaminariTile($remoteTileServer);
$cache->setDebug($debug);
$cache->setTimeout($cachingTimeout);

if (ubRouting::checkGet($cache::ROUTE_TILE)) {
    $cache->renderTile(ubRouting::get($cache::ROUTE_TILE, 'vf'));
}
