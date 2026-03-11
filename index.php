<?php

/**
 * Config section
 */

$cachingTimeout = 1209600; // tile server caching timeout in seconds
$remoteTileServer = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'; //remote tile server custom URL template
$forwardReferer = true; // forward client Referer to remote tile server (OSM policy-friendly)
$overrideReferer = ''; // if non-empty and forwardReferer is on, send this instead of real Referer
$forceHttpsBaseUrl = false; // set true when nginx proxies HTTP backend to HTTPS and does not pass X-Forwarded-Proto
$previewMap = true; // show preview map when no tile param
$debug = false; // debug logging into cache/log/debug.log
$userAgent = 'KaminariTileCache/1.0 (https://github.com/nightflyza/kaminaritile)'; // user agent for remote tiles fetching

/**
 * End of config section
 */
define('LIBS_PATH', 'libs/');

require_once (LIBS_PATH . 'api.ubrouting.php');
require_once (LIBS_PATH . 'api.omaeurl.php');
require_once (LIBS_PATH . 'api.kaminaritile.php');
require_once (LIBS_PATH . 'api.previewmap.php');

$cache = new KaminariTile($remoteTileServer);
$cache->setDebug($debug);
$cache->setTimeout($cachingTimeout);
$cache->setForwardReferer($forwardReferer);
$cache->setOverrideReferer($overrideReferer);
$cache->setForceHttpsBaseUrl($forceHttpsBaseUrl);


if (ubRouting::checkGet($cache::ROUTE_TILE)) {
    $cache->renderTile(ubRouting::get($cache::ROUTE_TILE, 'login'));
} else {
    if ($previewMap) {
        $cache->renderPreviewMap();
    }
}
