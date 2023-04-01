<?php

/**
 * Config section
 * 
 * OpenStreetMap: https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png
 * Visicom: https://tms{s}.visicom.ua/2.0.0/planet3/base/{z}/{x}/{y}.png?key=YOUR_API_KEY
 */
$remoteTileServer = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

/**
 * End of config section
 */
define('LIBS_PATH', 'libs/');

require_once (LIBS_PATH . 'api.ubrouting.php');
require_once (LIBS_PATH . 'api.omaeurl.php');
require_once (LIBS_PATH . 'api.kominaritile.php');

$cache = new KominariTile($remoteTileServer);
if (ubRouting::checkGet($cache::ROUTE_TILE)) {
    $cache->renderTile(ubRouting::get($cache::ROUTE_TILE, 'vf'));
}
