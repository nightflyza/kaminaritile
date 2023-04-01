<?php

/**
 * Tile caching server implementation
 * 
 * OSM tile usage policy: https://operations.osmfoundation.org/policies/tiles/
 */
class KominariTile {

    /**
     * Default caching timeout in seconds
     *
     * @var int
     */
    protected $cacheTime = 604800; // 7 days by default

    /**
     * User agent for remote tiles fetching
     *
     * @var string
     */
    protected $userAgent = 'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/111.0';

    /**
     * Contains current instance remote tile server URL template
     *
     * @var string
     */
    protected $remoteTileTemplate = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

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
     * Tile parts offsets and other predefined stuff
     */
    const OFFSET_S = 0;
    const OFFSET_Z = 1;
    const OFFSET_X = 2;
    const OFFSET_Y = 3;

    public function __construct($template) {
        if (!empty($template)) {
            $this->setTemplate($template);
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
                //and zPath inside
                mkdir($zPath, 0777);
                chmod($zPath, 0777);
            } else {
                //just check for zPath
                if (!file_exists($zPath)) {
                    mkdir($zPath, 0777);
                    chmod($zPath, 0777);
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
        $receivedTile = $remoteTileServer->response();
        $error = $remoteTileServer->error();
        $httpCode = $remoteTileServer->httpCode();
        if (!$error AND $httpCode == 200) {
            $result = $receivedTile;
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
                } else {
                    $updateCache = true;
                }
            } else {
                $updateCache = true;
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
