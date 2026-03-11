<?php

/**
 * Tile caching server implementation
 */
class KaminariTile {

    /**
     * Default caching timeout in seconds
     *
     * @var int
     */
    protected $cacheTime = 1209600; // 14 days by default

    /**
     * Contains current instance remote tile server URL template
     *
     * @var string
     */
    protected $remoteTileTemplate = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

    /**
     * Instance debug flag
     *
     * @var bool
     */
    protected $debugFlag = false;

    /**
     * User agent for remote tiles fetching
     *
     * @var string
     */
    protected $userAgent = 'KaminariTileCache/1.0 (https://github.com/nightflyza/kaminaritile)';

    /**
     * Forward client Referer to remote tile server when fetching
     *
     * @var bool
     */
    protected $forwardReferer = false;

    /**
     * If non-empty and forwardReferer is on, send this instead of real Referer
     *
     * @var string
     */
    protected $overrideReferer = '';

    /**
     * Referer of current request (set in renderTile, used in getRemoteTile)
     *
     * @var string
     */
    protected $currentReferer = '';

    /**
     * Default caching directory 
     */
    const CACHE_PATH = 'cache/';

    /**
     * Route of tile get requests
     */
    const ROUTE_TILE = 't';

    /**
     * Default tile parts route delimiter
     */
    const ROUTE_DELIMITER = '_';

    /**
     * default logging path
     */
    const LOG_PATH = 'cache/log/debug.log';

    /**
     * Tile parts offsets and other predefined stuff
     */
    const OFFSET_S = 0;
    const OFFSET_Z = 1;
    const OFFSET_X = 2;
    const OFFSET_Y = 3;

//                   ,/
//                 ,'/
//               ,' /
//             ,'  /_____,
//           .'____    ,'  
//                /  ,'
//               / ,'
//              /,'
//             /'

    public function __construct($template) {
        if (!empty($template)) {
            $this->setTemplate($template);
        }
    }

    /**
     * Sets instance debug flag
     * 
     * @param bool $debug
     * 
     * @return void
     */
    public function setDebug($state) {
        $this->debugFlag = $state;
    }

    /**
     * Sets caching timeout for current instance
     * 
     * @param int $timeout
     * 
     * @return void
     */
    public function setTimeout($timeout) {
        if (!empty($timeout)) {
            $this->cacheTime = $timeout;
        }
    }

    /**
     * Sets user agent for current instance
     * 
     * @param string $userAgent
     * 
     * @return void
     */
    public function setUserAgent($userAgent='') {
        if (!empty($userAgent)) {   
            $this->userAgent = $userAgent;
        }
    }

    /**
     * Sets whether to forward client Referer to remote tile server
     *
     * @param bool $state
     *
     * @return void
     */
    public function setForwardReferer($state) {
        $this->forwardReferer = (bool) $state;
    }

    /**
     * Sets Referer override: when forwardReferer is on, send this instead of real Referer
     *
     * @param string $referer
     *
     * @return void
     */
    public function setOverrideReferer($referer) {
        $this->overrideReferer = $referer !== null ? $referer : '';
    }

    /**
     * Sets current instance template
     * 
     * @param string $template remote tile server URL template with {s},{z},{x},{y} macro
     * 
     * @return void
     */
    protected function setTemplate($template) {
        $this->remoteTileTemplate = $template;
    }

    /**
     * Ensures log directory exists, then appends a line to debug log
     *
     * @return void
     */
    protected function ensureLogDir() {
        $logDir = dirname(self::LOG_PATH);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
            @chmod($logDir, 0777);
        }
    }

    /**
     * Logs some data to debug log (date, client IP, referer if any, then message)
     *
     * @param string $data
     * @param string $remoteTile
     *
     * @return void
     */
    protected function logEvent($data, $remoteTile='') {
        if ($this->debugFlag) {
            $this->ensureLogDir();
            $clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';
            $referer = $this->currentReferer !== '' ? $this->currentReferer : '-';
            $remoteTileLog =(!empty($remoteTile)) ? ' [' . $remoteTile . ']' : ''; 
            $line = date('Y-m-d H:i:s') . ': ' . $clientIp . ' (' . $referer . ') ' . $data . $remoteTileLog . PHP_EOL;
            file_put_contents(self::LOG_PATH, $line, FILE_APPEND);
        }
    }

    /**
     * Returns tile expected cache path
     * 
     * @param array $tileParts
     * 
     * @return string
     */
    protected function getTileCachePath($tileParts) {
        $result = '';
        if (isset($tileParts[self::OFFSET_Y])) {
            $result = self::CACHE_PATH . $tileParts[self::OFFSET_S] . '/' . $tileParts[self::OFFSET_Z] . '/' . $tileParts[self::OFFSET_X] . '_' . $tileParts[self::OFFSET_Y];
        }
        return($result);
    }

    /**
     * Allocates cache storage path
     * 
     * @param int $s
     * @param int $z
     * @param int $x
     * @param string $y
     * 
     * @throws Exception
     */
    protected function allocateStorage($s, $z, $x, $y) {
        $sPath = self::CACHE_PATH . $s;
        $zPath = self::CACHE_PATH . $s . '/' . $z;
        if (is_writable(self::CACHE_PATH)) {
            if (!file_exists($sPath)) {
                //creating sPath
                mkdir($sPath, 0777);
                chmod($sPath, 0777);
                $this->logEvent('ALLOC CACHE PATH ' . $sPath);
                //and zPath inside
                mkdir($zPath, 0777);
                chmod($zPath, 0777);
                $this->logEvent('ALLOC CACHE PATH ' . $zPath);
            } else {
                //just check for zPath
                if (!file_exists($zPath)) {
                    mkdir($zPath, 0777);
                    chmod($zPath, 0777);
                    $this->logEvent('ALLOC CACHE PATH ' . $zPath);
                }
            }
        } else {
            throw new Exception('EX_CACHE_NOT_WRITABLE');
        }
    }

    /**
     * Receives tile from remote tileserver
     * 
     * @param int $s
     * @param int $z
     * @param int $x
     * @param string $y
     * 
     * @return string
     */
    protected function getRemoteTile($s, $z, $x, $y) {
        $result = '';
        $fullImageUrl = $this->remoteTileTemplate;
        $fullImageUrl = str_replace('{s}', $s, $fullImageUrl);
        $fullImageUrl = str_replace('{z}', $z, $fullImageUrl);
        $fullImageUrl = str_replace('{x}', $x, $fullImageUrl);
        $fullImageUrl = str_replace('{y}', $y, $fullImageUrl);
        $remoteTileServer = new OmaeUrl($fullImageUrl);
        $remoteTileServer->setUserAgent($this->userAgent);
        if ($this->forwardReferer) {
            $refererToSend = $this->overrideReferer !== '' ? $this->overrideReferer : $this->currentReferer;
            if ($refererToSend !== '') {
                $remoteTileServer->setReferrer($refererToSend);
            }
        }
        
        $this->logEvent('GET REMOTE TILE ' . $fullImageUrl);
        $receivedTile = $remoteTileServer->response();
        $error = $remoteTileServer->error();
        $httpCode = $remoteTileServer->httpCode();
        if (!$error AND $httpCode == 200) {
            $result = $receivedTile;
            if ($this->debugFlag) {
                $this->logEvent('GET REMOTE TILE SUCCESS');
            }
        } else {
            $this->logEvent('GET REMOTE TILE FAILED HTTP CODE ' . $httpCode);
        }
        return($result);
    }

    /**
     * Returns full cached tile path
     * 
     * @param array $tileParts
     * 
     * @return string/void
     */
    protected function getCachedTile($tileParts) {
        $result = '';
        $expectedTilePath = $this->getTileCachePath($tileParts);
        if ($expectedTilePath) {
            //already cached?
            $cacheTime = time() - $this->cacheTime;
            $updateCache = false;
            if (file_exists($expectedTilePath)) {
                $updateCache = false;
                if ((filemtime($expectedTilePath) > $cacheTime)) {
                    $updateCache = false;
                    $this->logEvent('CACHE HIT');
                } else {
                    $updateCache = true;
                    unlink($expectedTilePath); //cache cleanup
                    $this->logEvent('CACHE EXPIRED');
                }
            } else {
                $updateCache = true;
                $this->logEvent('CACHE MISS');
            }

            //cache updating here
            if ($updateCache) {
                $s = $tileParts[self::OFFSET_S];
                $z = $tileParts[self::OFFSET_Z];
                $x = $tileParts[self::OFFSET_X];
                $y = $tileParts[self::OFFSET_Y];
                $this->allocateStorage($s, $z, $x, $y);
                $remoteTile = $this->getRemoteTile($s, $z, $x, $y);
                //received something?
                if ($remoteTile) {
                    //updating cache
                    file_put_contents($expectedTilePath, $remoteTile);
                    //returning full path
                    $result = $expectedTilePath;
                }
            } else {
                //cache is valid, return its path
                $result = $expectedTilePath;
            }
        }
        return($result);
    }

    /**
     * Parses tile request string and renders it
     * 
     * @param string $tileId
     * 
     * @return void
     */
    public function renderTile($tileId) {
        $this->currentReferer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
        $tileFullPath = 'cache/noimage.png';
        $this->logEvent('REQUEST TILE' , $tileId);
        if (!empty($tileId)) {
            $tileParts = explode(self::ROUTE_DELIMITER, $tileId);
            if (sizeof($tileParts) == 4) {
                $cachedTilePath = $this->getCachedTile($tileParts);
                //already cached?
                if ($cachedTilePath) {
                    $tileFullPath = $cachedTilePath;
                }
            }
        }

        //rendering tile
        $tileContent = file_get_contents($tileFullPath);
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($tileContent));
        header('Cache-Control: public, max-age=' . (int) $this->cacheTime);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + (int) $this->cacheTime) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($tileFullPath)) . ' GMT');
        print($tileContent);
        die();
    }


    /**
     * Returns cache statistics (tile count and total size in bytes), excluding log dir and noimage
     *
     * @return array array('count' => int, 'bytes' => int)
     */
    public function getCacheStats() {
        $count = 0;
        $bytes = 0;
        $cachePath = self::CACHE_PATH;
        $logPrefix = $cachePath . 'log' . DIRECTORY_SEPARATOR;
        if (!is_dir($cachePath)) {
            return array('count' => 0, 'bytes' => 0);
        }
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cachePath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $fi) {
                if (!$fi->isFile()) {
                    continue;
                }
                $path = $fi->getPathname();
                if (strpos($path, $logPrefix) !== false or $fi->getFilename() === 'noimage.png') {
                    continue;
                }
                $count++;
                $bytes += $fi->getSize();
            }
        } catch (Exception $e) {
            // ignore
        }
        return array('count' => $count, 'bytes' => $bytes);
    }

    /**
     * Returns base URL of the current script (no query string) for tile layer
     *
     * @return string
     */
    protected function getTileLayerBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '/';
        return $protocol . '://' . $host . $uri;
    }

    /**
     * Renders preview map (Leaflet) using current caching server as tile source
     *
     * @return void
     */
    public function renderPreviewMap() {
        $baseUrl = $this->getTileLayerBaseUrl();
        $tileUrlTemplate = $baseUrl . '?' . self::ROUTE_TILE . '={s}' . self::ROUTE_DELIMITER . '{z}' . self::ROUTE_DELIMITER . '{x}' . self::ROUTE_DELIMITER . '{y}';
        $cacheStats = $this->getCacheStats();
        $preview = new PreviewMap($tileUrlTemplate, $cacheStats);
        $preview->render();
    }

}
