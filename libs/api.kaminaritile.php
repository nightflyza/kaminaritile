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
    protected $cacheTime = 604800; // 7 days by default

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
    protected $userAgent = 'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/111.0';

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
    const LOG_PATH = 'cache/debug.log';

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
     * Logs some data to debug log
     * 
     * @param string $data
     * 
     * @return void
     */
    protected function logEvent($data) {
        if ($this->debugFlag) {
            file_put_contents(self::LOG_PATH, date("Y-m-d H:i:s") . ': ' . $data . PHP_EOL, FILE_APPEND);
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
        $remoteTileServer->setUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/111.0');
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
        $tileFullPath = 'cache/noimage.jpg';
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
        print($tileContent);
        die();
    }

}
