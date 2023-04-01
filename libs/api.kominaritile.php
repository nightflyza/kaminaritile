<?php

class KominariTile {

    /**
     * Default caching timeout in seconds
     *
     * @var int
     */
    protected $cacheTime = 60;

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

    // https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png

    public function __construct() {
        // what?
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
            $result = self::CACHE_PATH . $tileParts[self::OFFSET_S] . '/' . $tileParts[self::OFFSET_Z] . '/' . $tileParts[self::OFFSET_X] . '/' . $tileParts[self::OFFSET_Y];
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
                $expectedCachePath = $this->getTileCachePath($tileParts);
                if (!empty($expectedCachePath)) {
                    if (file_exists($tileFullPath)) {
                        // get from cache
                    } else {
                        // put to cache
                    }
                }
            }
        }

        $tileContent = file_get_contents($tileFullPath);
        print($tileContent);
        die();
    }

}
