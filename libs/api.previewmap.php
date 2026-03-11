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
        }
        $captionLine = htmlspecialchars($tileUrlTemplate) . $statsHtml;
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KaminariTile</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; font: 16px/1.4 sans-serif; }
        #map { width: 100%; height: 100%; }
        .preview-caption { position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%); z-index: 1000; background: rgba(255,255,255,0.92); padding: 8px 14px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.2); font-size: 13px; color: #333; max-width: 96%; }
        .preview-toolbar { position: absolute; top: 10px; right: 10px; z-index: 1000; display: flex; flex-direction: column; gap: 6px; }
        .preview-toolbar button { width: 36px; height: 36px; padding: 0; border: 1px solid #ccc; border-radius: 6px; background: #fff; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; }
        .preview-toolbar button:hover { background: #f5f5f5; }
        .preview-toolbar button.active { background: #e3f2fd; border-color: #2196f3; }
        .preview-toolbar button svg { width: 20px; height: 20px; }
        .preview-toolbar button.active svg { color: #1565c0; }
        .measure-result { background: rgba(255,255,255,0.92); padding: 8px 12px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.2); font-size: 13px; display: none; }
        .measure-result.visible { display: block; }
        .coords-result { margin-top: 6px; padding: 8px 12px; border-radius: 6px; background: rgba(255,255,255,0.92); box-shadow: 0 1px 4px rgba(0,0,0,0.2); font-size: 12px; font-family: monospace; }
        .coords-result .label { color: #666; margin-bottom: 2px; }
        .coords-result .value { color: #333; }
    </style>
</head>
<body>
    <div id="map"></div>
    <div class="preview-caption">' . $captionLine . '</div>
    <div class="preview-toolbar">
        <button type="button" id="btn-measure" title="Measure distance"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="2" y1="12" x2="22" y2="12"/><line x1="4" y1="9" x2="4" y2="15"/><line x1="8" y1="9" x2="8" y2="15"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="16" y1="9" x2="16" y2="15"/><line x1="20" y1="9" x2="20" y2="15"/></svg></button>
        <button type="button" id="btn-clear-measure" style="display:none;" title="Clear"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></button>
        <div class="measure-result" id="measure-result"></div>
        <div class="coords-result">
            
            <div class="value" id="coords-value">—</div>
        </div>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
(function () {
    var tileUrl = ' . json_encode($tileUrlTemplate) . ';
    var attribution = ' . json_encode($attribution) . ';
    var map = L.map("map").setView(' . json_encode($this->defaultCenter) . ', ' . (int) $this->defaultZoom . ');
    L.tileLayer(tileUrl, {
        attribution: attribution,
        subdomains: "abc",
        maxZoom: 19,
        maxNativeZoom: 19
    }).addTo(map);

    function haversineKm(lat1, lng1, lat2, lng2) {
        var R = 6371;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat/2)*Math.sin(dLat/2) + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)*Math.sin(dLng/2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    var measurePoints = [];
    var measureLine = null;
    var measureMarkers = [];
    var measureMode = false;
    var clickMarker = null;
    var btnMeasure = document.getElementById("btn-measure");
    var btnClear = document.getElementById("btn-clear-measure");
    var resultEl = document.getElementById("measure-result");
    var coordsEl = document.getElementById("coords-value");

    function formatCoord(n) { return n.toFixed(6); }

    function updateCoordsDisplay(lat, lng) {
        coordsEl.textContent = formatCoord(lat) + ", " + formatCoord(lng);
    }

    function setClickMarker(lat, lng) {
        var latlng = L.latLng(lat, lng);
        if (clickMarker) {
            clickMarker.setLatLng(latlng);
        } else {
            clickMarker = L.circleMarker(latlng, {
                radius: 8,
                fillColor: "#e65100",
                color: "#bf360c",
                weight: 2,
                fillOpacity: 1
            }).addTo(map);
        }
    }

    function updateMeasureDisplay() {
        var total = 0;
        for (var i = 1; i < measurePoints.length; i++) {
            total += haversineKm(
                measurePoints[i-1].lat, measurePoints[i-1].lng,
                measurePoints[i].lat, measurePoints[i].lng
            );
        }
        if (measurePoints.length === 0) {
            resultEl.classList.remove("visible");
            btnClear.style.display = "none";
        } else {
            resultEl.classList.add("visible");
            resultEl.textContent = total >= 1 ? (total.toFixed(2) + " km") : ((total * 1000).toFixed(0) + " m");
            btnClear.style.display = "block";
        }
    }

    function clearMeasure() {
        var i;
        for (i = 0; i < measureMarkers.length; i++) { map.removeLayer(measureMarkers[i]); }
        measureMarkers = [];
        if (measureLine) { map.removeLayer(measureLine); measureLine = null; }
        measurePoints = [];
        updateMeasureDisplay();
    }

    function onMapClick(e) {
        updateCoordsDisplay(e.latlng.lat, e.latlng.lng);
        setClickMarker(e.latlng.lat, e.latlng.lng);
        if (!measureMode) return;
        measurePoints.push({ lat: e.latlng.lat, lng: e.latlng.lng });
        measureMarkers.push(L.circleMarker(e.latlng, { radius: 5, fillColor: "#2196f3", color: "#1565c0", weight: 2, fillOpacity: 1 }).addTo(map));
        if (measurePoints.length > 1) {
            if (measureLine) map.removeLayer(measureLine);
            measureLine = L.polyline(measurePoints.map(function(p){ return [p.lat, p.lng]; }), { color: "#2196f3", weight: 3 }).addTo(map);
        }
        updateMeasureDisplay();
    }

    btnMeasure.onclick = function() {
        measureMode = !measureMode;
        btnMeasure.classList.toggle("active", measureMode);
        if (!measureMode) { clearMeasure(); }
    };

    btnClear.onclick = clearMeasure;

    map.on("click", onMapClick);
})();
    </script>
</body>
</html>';
    }
}
