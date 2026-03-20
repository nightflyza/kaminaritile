<?php

/**
 * Preview map: renders a Leaflet map using the current tile cache server as source
 */
class PreviewMap {

    /**
     * Tile layer URL template with {s}, {z}, {x}, {y}
     *
     * @var string
     */
    protected $tileUrlTemplate = '';

    /**
     * Default center [lat, lng]
     *
     * @var array
     */
    protected $defaultCenter = array(50.45, 30.52);

    /**
     * Default zoom level
     *
     * @var int
     */
    protected $defaultZoom = 6;

    /**
     * Cache stats for caption: array('count' => int, 'bytes' => int) or empty
     *
     * @var array
     */
    protected $cacheStats = array();

    /**
     * @param string $tileUrlTemplate URL template, e.g. "https://example.com/?t={s}_{z}_{x}_{y}"
     * @param array $cacheStats optional array('count' => int, 'bytes' => int)
     */
    public function __construct($tileUrlTemplate, $cacheStats = array()) {
        $this->tileUrlTemplate = (string) $tileUrlTemplate;
        $this->cacheStats = is_array($cacheStats) ? $cacheStats : array();
    }

    /**
     * Formats bytes to human-readable size (KB, MB, GB)
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes($bytes) {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Outputs HTML page with Leaflet map and current cache as tile source
     *
     * @return void
     */
    public function render() {
        $attribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            . ' | <a href="https://github.com/nightflyza/kaminaritile">KaminariTile</a>';
        $tileUrlTemplate = $this->tileUrlTemplate;
        $statsHtml = '';
        if (!empty($this->cacheStats) and isset($this->cacheStats['count'])) {
            $cnt = (int) $this->cacheStats['count'];
            $bytes = isset($this->cacheStats['bytes']) ? (int) $this->cacheStats['bytes'] : 0;
            $statsHtml = ' | ' . $cnt . ' ' . ($cnt === 1 ? 'tile' : 'tiles') . ', ' . $this->formatBytes($bytes);
            $hits = isset($this->cacheStats['hits']) ? (int) $this->cacheStats['hits'] : 0;
            $misses = isset($this->cacheStats['misses']) ? (int) $this->cacheStats['misses'] : 0;
            $total = $hits + $misses;
            if ($total > 0) {
                $pct = round(100.0 * $hits / $total);
                $statsHtml .= ' | ' . $pct . '% cache efficiency';
            }
        }
        $captionLine = htmlspecialchars($tileUrlTemplate) . $statsHtml;
        $tplPath = __DIR__ . '/previewmap_tpl.html';
        $html = @file_get_contents($tplPath);

        header('Content-Type: text/html; charset=utf-8');
        if ($html === false) {
            print('<!DOCTYPE html><html><body><h1>Template file not found</h1></body></html>');
            return;
        }

        $replaceMap = array(
            '{{CAPTION_LINE}}' => $captionLine,
            '{{TILE_URL_JSON}}' => json_encode($tileUrlTemplate),
            '{{ATTRIBUTION_JSON}}' => json_encode($attribution),
            '{{DEFAULT_CENTER_JSON}}' => json_encode($this->defaultCenter),
            '{{DEFAULT_ZOOM}}' => (string) ((int) $this->defaultZoom)
        );
        print(strtr($html, $replaceMap));
    }
}
