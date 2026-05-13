<?php
session_start();

/* ==============================
   FETCH FIREBASE DATA
============================== */

$firebase_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingPlantedReports.json";

$response = file_get_contents($firebase_url);

if ($response === FALSE) {
    die("Error connecting to Firebase.");
}

$data = json_decode($response, true);

$reports = [];
$raw_reports = []; // Keep raw data for owner info

if (!empty($data)) {
    foreach ($data as $id => $record) {
        $municipality = trim($record['municipality'] ?? '');
        $barangay     = trim($record['barangay'] ?? '');
        $numSeedlings = (int)($record['numSeedlings'] ?? 0);
        $variety      = trim($record['variety'] ?? '');
        $firstName    = trim($record['firstName'] ?? '');
        $lastName     = trim($record['lastName'] ?? '');

        if ($municipality && $barangay) {
            $report_item = [
                'municipality' => ucwords(strtolower($municipality)),
                'barangay'     => ucwords(strtolower($barangay)),
                'numSeedlings' => $numSeedlings,
                'variety'      => $variety,
                'firstName'    => $firstName,
                'lastName'     => $lastName,
                'owner'        => trim($firstName . ' ' . $lastName)
            ];
            
            $raw_reports[] = $report_item;
            
            // For stats, keep simplified version
            $reports[] = [
                'municipality' => $report_item['municipality'],
                'barangay'     => $report_item['barangay'],
                'numSeedlings' => $numSeedlings,
                'variety'      => $variety
            ];
        }
    }
}

// Calculate statistics
$totalSeedlings = array_sum(array_column($reports, 'numSeedlings'));
$uniqueMunicipalities = count(array_unique(array_column($reports, 'municipality')));
$uniqueBarangays = count(array_unique(array_map(function($r) { 
    return $r['municipality'] . '-' . $r['barangay']; 
}, $reports)));
$uniqueVarieties = count(array_unique(array_column($reports, 'variety')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Geographic Seedling Location - DENR System</title>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Turf.js for spatial operations -->
<script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>
<!-- Font Awesome & Google Fonts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

/* ===== MODERN SIDEBAR ===== */
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, #1a1f2e 0%, #2d3748 100%);
    color: white;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.2);
    overflow-y: auto;
    z-index: 1000;
}

.sidebar .logo {
    padding: 30px 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar .logo img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 3px solid #4a90e2;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    transition: transform 0.3s ease;
}

.sidebar .logo img:hover {
    transform: scale(1.05);
}

.sidebar-title {
    font-size: 14px;
    margin-top: 15px;
    color: #a0aec0;
    line-height: 1.6;
}

.sidebar nav {
    padding: 20px 0;
}

.sidebar .nav-link {
    display: flex;
    align-items: center;
    padding: 12px 25px;
    color: #cbd5e0;
    text-decoration: none;
    transition: all 0.3s ease;
    margin: 4px 10px;
    border-radius: 8px;
}

.sidebar .nav-link i {
    width: 24px;
    margin-right: 12px;
    font-size: 18px;
}

.sidebar .nav-link:hover {
    background: rgba(74, 144, 226, 0.2);
    color: white;
    transform: translateX(5px);
}

.sidebar .nav-link.active {
    background: linear-gradient(90deg, #4a90e2 0%, #357abd 100%);
    color: white;
    box-shadow: 0 4px 10px rgba(74, 144, 226, 0.3);
}

.logout-btn {
    display: flex;
    align-items: center;
    margin: 20px;
    padding: 12px 20px;
    background: linear-gradient(90deg, #e53e3e 0%, #c53030 100%);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.logout-btn i {
    width: 24px;
    margin-right: 12px;
}

.logout-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(229, 62, 62, 0.4);
}

/* ===== MAIN CONTENT ===== */
.main-content {
    margin-left: 280px;
    padding: 20px;
    min-height: 100vh;
}

/* ===== STATS CARDS ===== */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 24px;
}

.stat-icon.green { background: #48bb78; color: white; }
.stat-icon.blue { background: #4299e1; color: white; }
.stat-icon.purple { background: #9f7aea; color: white; }
.stat-icon.orange { background: #ed8936; color: white; }

.stat-info h3 {
    font-size: 14px;
    color: #718096;
    font-weight: 500;
    margin-bottom: 5px;
}

.stat-info .stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
}

/* ===== HEADER ===== */
.header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.header h2 {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 10px;
}

.header p {
    font-size: 16px;
    opacity: 0.9;
}

/* ===== MAP CONTAINER ===== */
.map-wrapper {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

#map {
    height: 550px;
    width: 100%;
    background-color: #a0a0a0;
}

/* ===== MAP CONTROLS ===== */
.map-controls {
    background: white;
    padding: 15px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.control-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #4a5568;
    font-weight: 500;
}

.control-label i {
    color: #4a90e2;
}

.muni-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.muni-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #edf2f7;
    color: #4a5568;
}

.muni-btn i {
    font-size: 14px;
}

.muni-btn:hover {
    background: #4a90e2;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(74, 144, 226, 0.3);
}

.muni-btn.active {
    background: #4a90e2;
    color: white;
}

.muni-btn.all-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.muni-btn.all-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

/* ===== LEGEND ===== */
.legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    gap: 8px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

.legend-color.low { background: #f9b4d4; }
.legend-color.medium { background: #e84393; }
.legend-color.high { background: #b0005e; }
.legend-color.no-data { background: #e0e0e0; }

/* ===== LOADING OVERLAY ===== */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #4a90e2;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.custom-popup .leaflet-popup-content-wrapper {
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.custom-popup .leaflet-popup-tip {
    background: white;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .sidebar {
        width: 80px;
        overflow: visible;
    }
    
    .sidebar .logo img {
        width: 50px;
        height: 50px;
    }
    
    .sidebar-title,
    .nav-link span,
    .logout-btn span {
        display: none;
    }
    
    .nav-link i,
    .logout-btn i {
        margin-right: 0;
        font-size: 20px;
    }
    
    .main-content {
        margin-left: 80px;
    }
    
    .stats-container {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- ===== SIDEBAR ===== -->
<div class="sidebar">
    <div class="logo">
        <a href="Dashboard.php">
            <img src="image/DENR.jpg" alt="DENR Logo">
        </a>
        <div class="sidebar-title">
            Department of Environment<br>and Natural Resources
        </div>
    </div>

    <nav>
        <a href="Dashboard.php" class="nav-link">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="Farmer_Receive.php" class="nav-link">
            <i class="fas fa-users"></i>
            <span>Land Owner Request</span>
        </a>
        <a href="mortality.php" class="nav-link">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Mortality</span>
        </a>
        <a href="SeedlingDistribution.php" class="nav-link">
            <i class="fas fa-seedling"></i>
            <span>Seedling Distribution</span>
        </a>
        <a href="seedlingplanted.php" class="nav-link">
            <i class="fas fa-tree"></i>
            <span>Seedling Planted</span>
        </a>
        <a href="thematicmapping.php" class="nav-link">
            <i class="fas fa-map"></i>
            <span>Thematic Mapping</span>
        </a>
        <a href="geographic_seedling_location.php" class="nav-link active">
            <i class="fas fa-globe-asia"></i>
            <span>Geographic Location</span>
        </a>
         <a href="variety.php" class="nav-link">
            <i class="fas fa-seedling"></i>
            <span>Manage Variety</span>
        </a>
    </nav>

    <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">
    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-tree"></i>
            </div>
            <div class="stat-info">
                <h3>Total Seedlings</h3>
                <div class="stat-number" id="statTotalSeedlings">0</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-city"></i>
            </div>
            <div class="stat-info">
                <h3>Municipalities</h3>
                <div class="stat-number" id="statMunicipalities">0</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-map-pin"></i>
            </div>
            <div class="stat-info">
                <h3>Barangays</h3>
                <div class="stat-number" id="statBarangays">0</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-leaf"></i>
            </div>
            <div class="stat-info">
                <h3>Varieties</h3>
                <div class="stat-number" id="statVarieties">0</div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="header">
        <h2><i class="fas fa-map-marked-alt" style="margin-right: 10px;"></i>Geographic Seedling Location</h2>
        <p>Interactive map showing individual owner plots (Voronoi tessellation - no overlap)</p>
    </div>

    <!-- Map Container -->
    <div class="map-wrapper">
        <div class="map-controls">
            <div class="control-label">
                <i class="fas fa-mouse-pointer"></i>
                <span>Filter by Municipality:</span>
            </div>
            <div class="muni-buttons">
                <button class="muni-btn all-btn" data-muni="all">
                    <i class="fas fa-globe"></i>
                    All Municipalities
                </button>
                <button class="muni-btn" data-muni="Digos City">
                    <i class="fas fa-city"></i>
                    Digos City
                </button>
                <button class="muni-btn" data-muni="Bansalan">
                    <i class="fas fa-city"></i>
                    Bansalan
                </button>
            </div>
            
            <!-- Legend -->
            <div style="margin-left: auto; display: flex; gap: 15px; flex-wrap: wrap;">
                <div class="legend-item">
                    <div class="legend-color low"></div>
                    <span>1-150</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color medium"></div>
                    <span>151-300</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color high"></div>
                    <span>300+</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color no-data"></div>
                    <span>No data</span>
                </div>
            </div>
        </div>
        <div id="map"></div>
    </div>
</div>

<script>
/* ==============================
   NORMALIZE STRING
============================== */
function normalize(text) {
    if (!text) return '';
    return text.toString().trim().toLowerCase().replace(/\s+/g, '');
}

/* ==============================
   PHP DATA TO JS
============================== */
const rawReports = <?php echo json_encode($raw_reports); ?>;

// Update stats
document.getElementById('statTotalSeedlings').innerText = <?php echo number_format($totalSeedlings); ?>;
document.getElementById('statMunicipalities').innerText = <?php echo $uniqueMunicipalities; ?>;
document.getElementById('statBarangays').innerText = <?php echo $uniqueBarangays; ?>;
document.getElementById('statVarieties').innerText = <?php echo $uniqueVarieties; ?>;

/* ==============================
   INITIALIZE MAP
============================== */
var map = L.map('map').setView([6.753, 125.32], 11);

// Base Layers Definition
var osmLight = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
    subdomains: 'abcd',
    maxZoom: 19,
    minZoom: 8
});

var googleSatellite = L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
    attribution: '&copy; Google Satellite',
    maxZoom: 20,
    minZoom: 8
});

var googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
    attribution: '&copy; Google Hybrid',
    maxZoom: 20,
    minZoom: 8
});

var osmTerrain = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://opentopomap.org">OpenTopoMap</a>',
    maxZoom: 17,
    minZoom: 8
});

var cartoDB = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>, &copy; <a href="https://carto.com/">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
    minZoom: 8
});

// Add default base layer
osmLight.addTo(map);

// Base Layers Object
var baseLayers = {
    "🗺️ Streets (Light)": osmLight,
    "🛰️ Satellite": googleSatellite,
    "🛰️ Satellite with Labels": googleHybrid,
    "⛰️ Terrain": osmTerrain,
    "🗺️ Streets with Labels": cartoDB
};

// Add Layer Control
var layerControl = L.control.layers(baseLayers, null, {
    position: 'topright',
    collapsed: true
}).addTo(map);

map.getContainer().style.backgroundColor = "#808080";

let geoLayer;
let geoJsonData = null;
let targetBounds = null; // Store bounds for Digos and Bansalan

function showLoading() { document.getElementById('loadingOverlay').style.display = 'flex'; }
function hideLoading() { document.getElementById('loadingOverlay').style.display = 'none'; }

function setActiveButton(muni) {
    document.querySelectorAll('.muni-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-muni') === muni) btn.classList.add('active');
    });
}

function getColorBySeedlings(total) {
    if (total === 0 || !total) return "#e0e0e0";
    if (total <= 150) return "#f9b4d4";
    if (total <= 300) return "#e84393";
    return "#b0005e";
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[m]);
}

/* ==============================
   GENERATE VORONOI POLYGONS FOR OWNERS
   NO OVERLAP - Each owner gets a distinct cell
============================== */
function generateOwnerVoronoiPolygons(barangayFeature, entriesInBarangay) {
    const features = [];
    
    // Group entries by owner
    const ownerMap = new Map();
    
    entriesInBarangay.forEach(entry => {
        const ownerKey = entry.owner || '__GENERAL__';
        const displayOwner = entry.owner || 'General Report';
        
        if (!ownerMap.has(ownerKey)) {
            ownerMap.set(ownerKey, {
                owner: displayOwner,
                total: 0,
                varieties: [],
                entries: []
            });
        }
        
        const group = ownerMap.get(ownerKey);
        group.total += entry.numSeedlings;
        group.entries.push(entry);
        if (entry.variety && !group.varieties.includes(entry.variety)) {
            group.varieties.push(entry.variety);
        }
    });
    
    const owners = Array.from(ownerMap.entries());
    
    // If only one owner, return the whole barangay
    if (owners.length === 1) {
        const [key, group] = owners[0];
        const feature = JSON.parse(JSON.stringify(barangayFeature));
        feature.properties = {
            ...feature.properties,
            owner: group.owner,
            totalSeedlings: group.total,
            varieties: group.varieties,
            isOwnerPlot: true
        };
        return [feature];
    }
    
    // Multiple owners: Use Voronoi tessellation
    // Generate random points within the barangay polygon
    const points = [];
    const bbox = turf.bbox(barangayFeature);
    
    // Generate points for each owner
    owners.forEach(([key, group], index) => {
        let point;
        let attempts = 0;
        let validPoint = false;
        
        // Try to generate a point inside the barangay polygon
        while (!validPoint && attempts < 100) {
            const lon = bbox[0] + Math.random() * (bbox[2] - bbox[0]);
            const lat = bbox[1] + Math.random() * (bbox[3] - bbox[1]);
            point = turf.point([lon, lat]);
            
            try {
                if (turf.booleanPointInPolygon(point, barangayFeature)) {
                    validPoint = true;
                }
            } catch (e) {
                // Continue
            }
            attempts++;
        }
        
        // If couldn't generate inside, use centroid with offset
        if (!validPoint) {
            let centroid;
            try {
                centroid = turf.centroid(barangayFeature);
            } catch (e) {
                centroid = turf.point([(bbox[0] + bbox[2]) / 2, (bbox[1] + bbox[3]) / 2]);
            }
            const centerCoords = centroid.geometry.coordinates;
            const angle = (index / owners.length) * Math.PI * 2;
            const offset = 0.002;
            point = turf.point([
                centerCoords[0] + Math.cos(angle) * offset,
                centerCoords[1] + Math.sin(angle) * offset
            ]);
        }
        
        points.push(turf.point(point.geometry.coordinates, {
            ownerKey: key,
            owner: group.owner,
            total: group.total,
            varieties: group.varieties
        }));
    });
    
    // Create Voronoi diagram
    const pointCollection = turf.featureCollection(points);
    
    let voronoiPolygons;
    try {
        // Expand bbox slightly to ensure full coverage
        const expandedBbox = [
            bbox[0] - 0.01,
            bbox[1] - 0.01,
            bbox[2] + 0.01,
            bbox[3] + 0.01
        ];
        voronoiPolygons = turf.voronoi(pointCollection, { bbox: expandedBbox });
    } catch (e) {
        console.error("Voronoi error:", e);
        return [barangayFeature];
    }
    
    // Assign each Voronoi cell to the closest owner point and intersect with barangay
    voronoiPolygons.features.forEach(vFeature => {
        // Find the owner point inside this Voronoi cell
        let ownerData = null;
        
        for (const point of points) {
            try {
                if (turf.booleanPointInPolygon(point, vFeature)) {
                    ownerData = point.properties;
                    break;
                }
            } catch (e) {
                // Skip
            }
        }
        
        // If not found, find closest point
        if (!ownerData) {
            let minDist = Infinity;
            const vCentroid = turf.centroid(vFeature);
            points.forEach(point => {
                const dist = turf.distance(vCentroid, point);
                if (dist < minDist) {
                    minDist = dist;
                    ownerData = point.properties;
                }
            });
        }
        
        if (!ownerData) return;
        
        // Intersect with barangay boundary to keep within limits
        let finalFeature;
        try {
            const intersection = turf.intersect(turf.featureCollection([barangayFeature, vFeature]));
            if (intersection) {
                finalFeature = intersection;
            } else {
                finalFeature = vFeature;
            }
        } catch (e) {
            finalFeature = vFeature;
        }
        
        // Only add if it has area
        try {
            const area = turf.area(finalFeature);
            if (area < 1) return; // Skip tiny polygons
        } catch (e) {}
        
        finalFeature.properties = {
            ...barangayFeature.properties,
            owner: ownerData.owner,
            totalSeedlings: ownerData.total,
            varieties: ownerData.varieties,
            isOwnerPlot: true
        };
        
        features.push(finalFeature);
    });
    
    return features.length > 0 ? features : [barangayFeature];
}

/* ==============================
   LOAD MAP DATA
============================== */
function loadMapData(filterMuni = 'all') {
    showLoading();
    setActiveButton(filterMuni);
    
    if (geoLayer) map.removeLayer(geoLayer);
    
    function processGeoJson(data) {
        geoJsonData = data;
        const allFeatures = [];
        const dataBounds = L.latLngBounds(); // For auto-zoom
        
        data.features.forEach(barangayFeature => {
            const props = barangayFeature.properties;
            const muniName = props.NAME_2 || props.MUNICIPALITY || '';
            const brgyName = props.NAME_3 || props.BARANGAY || '';
            
            // Track bounds for Digos City and Bansalan
            const muniLower = normalize(muniName);
            if (muniLower === 'digos city' || muniLower === 'bansalan') {
                try {
                    const layer = L.geoJSON(barangayFeature);
                    dataBounds.extend(layer.getBounds());
                } catch (e) {}
            }
            
            if (filterMuni !== 'all' && normalize(muniName) !== normalize(filterMuni)) {
                return;
            }
            
            const entriesHere = rawReports.filter(entry => 
                normalize(entry.municipality) === normalize(muniName) && 
                normalize(entry.barangay) === normalize(brgyName)
            );
            
            if (entriesHere.length === 0) {
                const feature = JSON.parse(JSON.stringify(barangayFeature));
                feature.properties = {
                    ...feature.properties,
                    owner: null,
                    totalSeedlings: 0,
                    varieties: [],
                    isOwnerPlot: false,
                    brgyName: brgyName,
                    muniName: muniName
                };
                allFeatures.push(feature);
            } else {
                const ownerFeatures = generateOwnerVoronoiPolygons(barangayFeature, entriesHere);
                ownerFeatures.forEach(f => {
                    f.properties.brgyName = brgyName;
                    f.properties.muniName = muniName;
                    allFeatures.push(f);
                });
            }
        });
        
        geoLayer = L.geoJSON(allFeatures, {
            style: (feature) => ({
                color: "#ffffff",
                weight: 1.2,
                fillColor: getColorBySeedlings(feature.properties.totalSeedlings || 0),
                fillOpacity: 0.85,
                opacity: 0.8
            }),
            onEachFeature: (feature, layer) => {
                const props = feature.properties;
                const total = props.totalSeedlings || 0;
                const varieties = props.varieties || [];
                const owner = props.owner;
                const brgyName = props.brgyName || 'Unknown';
                const muniName = props.muniName || 'Unknown';
                
                let title = brgyName;
                if (owner) title += ` - ${owner}`;
                
                let popupContent = `
                    <div style="font-family: 'Poppins', sans-serif; min-width: 240px;">
                        <h4 style="margin: 0 0 10px 0; color: #2d3748; border-bottom: 2px solid #e84393; padding-bottom: 5px;">
                            <i class="fas fa-map-pin" style="color: #e84393; margin-right: 5px;"></i>${escapeHtml(title)}
                        </h4>
                        <div style="margin: 10px 0;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: #718096;">Municipality:</span>
                                <strong style="color: #2d3748;">${escapeHtml(muniName)}</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: #718096;">Barangay:</span>
                                <strong style="color: #2d3748;">${escapeHtml(brgyName)}</strong>
                            </div>`;
                
                if (owner) {
                    popupContent += `
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: #718096;">Owner:</span>
                                <strong style="color: #2d3748;">${escapeHtml(owner)}</strong>
                            </div>`;
                }
                
                popupContent += `
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: #718096;">Total Seedlings:</span>
                                <strong style="color: #d63384;">${total.toLocaleString()}</strong>
                            </div>
                            <div style="margin-top: 10px;">
                                <span style="color: #718096; display: block; margin-bottom: 5px;">Varieties:</span>
                                <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                    ${varieties.length ? varieties.map(v => 
                                        `<span style="background: #ffe0f0; padding: 3px 8px; border-radius: 12px; font-size: 11px; color: #b0005e;">${escapeHtml(v)}</span>`
                                    ).join('') : '<span style="color: #a0aec0;">None recorded</span>'}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                layer.bindPopup(popupContent, { maxWidth: 320, className: 'custom-popup' });
                
                layer.on('mouseover', function() {
                    this.setStyle({ weight: 2.5, color: '#ff99cc', fillOpacity: 0.95 });
                });
                layer.on('mouseout', function() {
                    this.setStyle({ weight: 1.2, color: "#ffffff", fillOpacity: 0.85 });
                });
            }
        }).addTo(map);
        
        // Set view based on filter
        if (filterMuni === 'all') {
            // For All Municipalities, zoom to Digos City and Bansalan
            if (dataBounds.isValid()) {
                map.fitBounds(dataBounds, { padding: [50, 50] });
            } else {
                map.setView([6.753, 125.32], 11);
            }
        } else {
            // For specific municipality, zoom to its bounds
            if (geoLayer.getBounds().isValid()) {
                map.fitBounds(geoLayer.getBounds(), { padding: [20, 20] });
            } else {
                map.setView([6.753, 125.32], 13);
            }
        }
        
        hideLoading();
        console.log(`Loaded ${allFeatures.length} polygons`);
    }
    
    if (geoJsonData) {
        processGeoJson(geoJsonData);
    } else {
        fetch("level3.json")
            .then(res => res.json())
            .then(processGeoJson)
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
                alert('Error loading map data: ' + error.message);
            });
    }
}

// Event listeners
document.querySelectorAll('.muni-btn').forEach(btn => {
    btn.addEventListener('click', () => loadMapData(btn.getAttribute('data-muni')));
});

// Initial load
window.addEventListener('load', () => {
    setTimeout(() => loadMapData('all'), 500);
});
</script>

</body>
</html>
