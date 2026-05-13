<?php
session_start();

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Firebase Realtime Database URLs
$planted_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingPlantedReports.json";
$mortality_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingMortalityReports.json";

// Fetch Planted Data
$planted_response = @file_get_contents($planted_url);
$planted_data = $planted_response ? json_decode($planted_response, true) : [];

// Fetch Mortality Data
$mortality_response = @file_get_contents($mortality_url);
$mortality_data = $mortality_response ? json_decode($mortality_response, true) : [];

// Process Planted Data - Group by barangay
$planted_by_barangay = [];
$planted_values = [];
if (!empty($planted_data)) {
    foreach ($planted_data as $id => $record) {
        if (!is_array($record)) continue;
        
        $barangay = trim($record['barangay'] ?? '');
        $municipality = trim($record['municipality'] ?? '');
        $numSeedlings = (int)($record['numSeedlings'] ?? 0);
        $variety = trim($record['variety'] ?? '');
        
        if (!$barangay || !$municipality) continue;
        
        $key = $municipality . '|' . $barangay;
        
        if (!isset($planted_by_barangay[$key])) {
            $planted_by_barangay[$key] = [
                'municipality' => $municipality,
                'barangay' => $barangay,
                'totalPlanted' => 0,
                'varieties' => []
            ];
        }
        
        $planted_by_barangay[$key]['totalPlanted'] += $numSeedlings;
        $planted_values[] = $numSeedlings;
        if ($variety && !in_array($variety, $planted_by_barangay[$key]['varieties'])) {
            $planted_by_barangay[$key]['varieties'][] = $variety;
        }
    }
}

// Process Mortality Data - Group by barangay
$mortality_by_barangay = [];
$mortality_values = [];
if (!empty($mortality_data)) {
    foreach ($mortality_data as $id => $record) {
        if (!is_array($record)) continue;
        
        $barangay = trim($record['barangay'] ?? '');
        $municipality = trim($record['municipality'] ?? '');
        $died = (int)($record['died'] ?? 0);
        $variety = trim($record['variety'] ?? '');
        
        if (!$barangay || !$municipality) continue;
        
        $key = $municipality . '|' . $barangay;
        
        if (!isset($mortality_by_barangay[$key])) {
            $mortality_by_barangay[$key] = [
                'municipality' => $municipality,
                'barangay' => $barangay,
                'totalMortality' => 0,
                'varieties' => []
            ];
        }
        
        $mortality_by_barangay[$key]['totalMortality'] += $died;
        $mortality_values[] = $died;
        if ($variety && !in_array($variety, $mortality_by_barangay[$key]['varieties'])) {
            $mortality_by_barangay[$key]['varieties'][] = $variety;
        }
    }
}

// Calculate overall statistics
$totalPlanted = array_sum(array_column($planted_by_barangay, 'totalPlanted'));
$totalMortality = array_sum(array_column($mortality_by_barangay, 'totalMortality'));
$survivalCount = $totalPlanted - $totalMortality;
$mortalityRate = $totalPlanted > 0 ? round(($totalMortality / $totalPlanted) * 100, 1) : 0;
$survivalRate = $totalPlanted > 0 ? round((($totalPlanted - $totalMortality) / $totalPlanted) * 100, 1) : 0;

// Get unique locations for stats
$planted_locations = count($planted_by_barangay);
$mortality_locations = count($mortality_by_barangay);

// Calculate classification breaks for thematic mapping
function calculateBreaks($values, $numClasses = 5) {
    if (empty($values)) return [0, 1, 2, 3, 4, 5];
    sort($values);
    $n = count($values);
    $breaks = [];
    for ($i = 0; $i <= $numClasses; $i++) {
        $index = (int)(($i / $numClasses) * ($n - 1));
        $breaks[] = $values[$index] ?? 0;
    }
    return array_unique($breaks);
}

$planted_breaks = calculateBreaks($planted_values);
$mortality_breaks = calculateBreaks($mortality_values);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Choropleth Map - Seedling Distribution | DENR System</title>

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <!-- Turf.js -->
  <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: #f0f2f5;
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
      border: 3px solid #9f7aea;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
      transition: transform 0.3s ease;
      object-fit: cover;
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
      background: rgba(159, 122, 234, 0.2);
      color: white;
      transform: translateX(5px);
    }

    .sidebar .nav-link.active {
      background: linear-gradient(90deg, #9f7aea 0%, #805ad5 100%);
      color: white;
      box-shadow: 0 4px 10px rgba(159, 122, 234, 0.3);
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
      color: white;
    }

    /* ===== MAIN CONTENT ===== */
    .main-content {
      margin-left: 280px;
      padding: 24px;
      min-height: 100vh;
    }

    /* ===== HEADER ===== */
    .page-header {
      background: white;
      color: #1a1f2e;
      padding: 24px 30px;
      border-radius: 16px;
      margin-bottom: 24px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
      border: 1px solid #e9ecef;
    }

    .header-content h2 {
      font-size: 24px;
      font-weight: 600;
      margin-bottom: 6px;
      color: #1a1f2e;
    }

    .header-content h2 i {
      margin-right: 10px;
      color: #9f7aea;
    }

    .header-content p {
      font-size: 14px;
      color: #6c757d;
      margin: 0;
    }

    /* ===== STATS CARDS ===== */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      border: 1px solid #e9ecef;
    }

    .stat-card:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      border-color: #dee2e6;
    }

    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 16px;
      font-size: 20px;
      background: #f8f9fa;
    }

    .stat-icon.green { color: #38a169; }
    .stat-icon.red { color: #e53e3e; }
    .stat-icon.blue { color: #3182ce; }
    .stat-icon.purple { color: #805ad5; }
    .stat-icon.orange { color: #dd6b20; }
    .stat-icon.teal { color: #319795; }

    .stat-details h3 {
      font-size: 12px;
      color: #6c757d;
      font-weight: 500;
      margin-bottom: 4px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .stat-number {
      font-size: 24px;
      font-weight: 700;
      color: #1a1f2e;
      line-height: 1.2;
    }

    .stat-unit {
      font-size: 12px;
      color: #6c757d;
      font-weight: 400;
      margin-left: 4px;
    }

    /* ===== MAP CARD ===== */
    .map-card {
      background: white;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
      border: 1px solid #e9ecef;
    }

    .map-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      flex-wrap: wrap;
      gap: 16px;
    }

    .map-header h3 {
      font-size: 18px;
      font-weight: 600;
      color: #1a1f2e;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .map-header h3 i {
      color: #9f7aea;
    }

    /* ===== DATA TYPE TOGGLE ===== */
    .data-toggle {
      display: flex;
      gap: 4px;
      background: #f1f3f5;
      padding: 4px;
      border-radius: 10px;
    }

    .toggle-btn {
      padding: 8px 16px;
      border: none;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 6px;
      background: transparent;
      color: #495057;
    }

    .toggle-btn i {
      font-size: 13px;
    }

    .toggle-btn.active {
      background: white;
      color: #1a1f2e;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    }

    .toggle-btn.planted.active { color: #2b6e3b; }
    .toggle-btn.planted.active i { color: #38a169; }
    .toggle-btn.mortality.active { color: #c53030; }
    .toggle-btn.mortality.active i { color: #e53e3e; }

    .map-controls {
      display: flex;
      gap: 8px;
    }

    .map-btn {
      padding: 8px 12px;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 6px;
      background: white;
      color: #495057;
    }

    .map-btn:hover {
      background: #f8f9fa;
      border-color: #adb5bd;
    }

    #map {
      width: 100%;
      height: 520px;
      border-radius: 12px;
      border: 1px solid #e9ecef;
      z-index: 1;
    }

    /* ===== LEGEND (Geography Style) ===== */
    .legend-container {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-top: 16px;
      flex-wrap: wrap;
      gap: 16px;
    }

    .legend {
      background: #f8f9fa;
      padding: 16px 20px;
      border-radius: 10px;
      border: 1px solid #e9ecef;
    }

    .legend h4 {
      font-size: 13px;
      font-weight: 600;
      color: #1a1f2e;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 6px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .legend-items {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .legend-row {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .legend-color-box {
      width: 32px;
      height: 20px;
      border-radius: 4px;
      border: 1px solid rgba(0,0,0,0.1);
    }

    .legend-label {
      font-size: 12px;
      color: #495057;
    }

    /* ===== RATE SUMMARY BOX ===== */
    .rate-summary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 10px;
      padding: 16px 20px;
      color: white;
      min-width: 300px;
    }

    .rate-summary h4 {
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 6px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      opacity: 0.9;
    }

    .rate-stats {
      display: flex;
      justify-content: space-around;
      text-align: center;
    }

    .rate-item {
      display: flex;
      flex-direction: column;
    }

    .rate-label {
      font-size: 11px;
      opacity: 0.8;
      margin-bottom: 4px;
    }

    .rate-value {
      font-size: 28px;
      font-weight: 700;
    }

    .rate-formula {
      font-size: 10px;
      opacity: 0.7;
      margin-top: 8px;
      text-align: center;
    }

    .info-panel {
      background: #f8f9fa;
      border-radius: 10px;
      padding: 16px 20px;
      border: 1px solid #e9ecef;
      max-width: 350px;
    }

    .info-panel p {
      margin: 0 0 8px 0;
      color: #495057;
      font-size: 13px;
    }

    .info-panel p:last-child {
      margin-bottom: 0;
    }

    .info-panel i {
      color: #9f7aea;
      margin-right: 6px;
      width: 16px;
    }

    .attribution {
      font-size: 11px;
      color: #adb5bd;
      margin-top: 12px;
      text-align: right;
    }

    /* ===== HOVER TOOLTIP ===== */
    .map-tooltip {
      background: white;
      border: none;
      border-radius: 8px;
      padding: 10px 14px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      font-family: 'Inter', sans-serif;
      font-size: 13px;
      border-left: 3px solid #9f7aea;
    }

    .map-tooltip strong {
      color: #1a1f2e;
      display: block;
      margin-bottom: 4px;
    }

    .map-tooltip .value {
      color: #495057;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1400px) {
      .stats-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    @media (max-width: 1200px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 768px) {
      .sidebar {
        width: 80px;
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
        padding: 16px;
      }
      
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .map-header {
        flex-direction: column;
        align-items: flex-start;
      }
    }

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
      width: 40px;
      height: 40px;
      border: 3px solid #e9ecef;
      border-top: 3px solid #9f7aea;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* ===== BARANGAY LABEL ===== */
    .barangay-label {
      background: rgba(255, 255, 255, 0.95);
      color: #1a1f2e;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 600;
      white-space: nowrap;
      border: 1px solid #dee2e6;
      box-shadow: 0 1px 4px rgba(0,0,0,0.1);
      pointer-events: none;
      text-transform: uppercase;
      letter-spacing: 0.2px;
    }

    /* ===== CUSTOM POPUP ===== */
    .custom-popup .leaflet-popup-content-wrapper {
      border-radius: 12px;
      padding: 0;
      overflow: hidden;
      box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }

    .custom-popup .leaflet-popup-content {
      margin: 0;
      padding: 16px;
      font-family: 'Inter', sans-serif;
    }

    .custom-popup .leaflet-popup-tip {
      background: white;
    }

    .popup-header {
      margin: -16px -16px 12px -16px;
      padding: 12px 16px;
      background: #f8f9fa;
      border-bottom: 1px solid #e9ecef;
    }

    .popup-header h4 {
      margin: 0;
      font-size: 15px;
      font-weight: 600;
      color: #1a1f2e;
    }
  </style>
</head>

<body>
  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
  </div>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="logo">
      <a href="dashboard.php"><img src="image/DENR.jpg" alt="DENR" /></a>
      <h3 class="sidebar-title">Department of Environment<br>and Natural Resources</h3>
    </div>
    <nav>
      <a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
      <a href="Farmer_Receive.php" class="nav-link"><i class="fas fa-users"></i><span>Land Owner Request</span></a>
      <a href="mortality.php" class="nav-link"><i class="fas fa-exclamation-triangle"></i><span>Mortality</span></a>
      <a href="SeedlingDistribution.php" class="nav-link"><i class="fas fa-seedling"></i><span>Seedling Distribution</span></a>
      <a href="seedlingplanted.php" class="nav-link"><i class="fas fa-tree"></i><span>Seedling Planted</span></a>
      <a href="thematicmapping.php" class="nav-link active"><i class="fas fa-map"></i><span>Thematic Map</span></a>
      <a href="geographic_seedling_location.php" class="nav-link"><i class="fas fa-globe-asia"></i><span>Geographic Location</span></a>
       <a href="variety.php" class="nav-link">
            <i class="fas fa-seedling"></i>
            <span>Manage Variety</span>
        </a>
    </nav>
    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <h2><i class="fas fa-chart-map"></i>Choropleth Map: Seedling Distribution</h2>
        <p>Bansalan & Digos City, Davao del Sur | Data source: DENR Field Reports</p>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon green">
          <i class="fas fa-tree"></i>
        </div>
        <div class="stat-details">
          <h3>Total Planted</h3>
          <div class="stat-number"><?php echo number_format($totalPlanted); ?> <span class="stat-unit">seedlings</span></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon red">
          <i class="fas fa-skull"></i>
        </div>
        <div class="stat-details">
          <h3>Mortality</h3>
          <div class="stat-number"><?php echo number_format($totalMortality); ?> <span class="stat-unit">seedlings</span></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon blue">
          <i class="fas fa-heart"></i>
        </div>
        <div class="stat-details">
          <h3>Survived</h3>
          <div class="stat-number"><?php echo number_format($survivalCount); ?> <span class="stat-unit">seedlings</span></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon orange">
          <i class="fas fa-percent"></i>
        </div>
        <div class="stat-details">
          <h3>Mortality Rate</h3>
          <div class="stat-number"><?php echo $mortalityRate; ?>%</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon teal">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-details">
          <h3>Survival Rate</h3>
          <div class="stat-number"><?php echo $survivalRate; ?>%</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon purple">
          <i class="fas fa-map-pin"></i>
        </div>
        <div class="stat-details">
          <h3>Active Barangays</h3>
          <div class="stat-number"><?php echo max($planted_locations, $mortality_locations); ?></div>
        </div>
      </div>
    </div>

    <!-- Map Card -->
    <div class="map-card">
      <div class="map-header">
        <h3><i class="fas fa-layer-group"></i><span id="mapTitle">Seedling Planted Density</span></h3>
        <div style="display: flex; gap: 12px; align-items: center;">
          <div class="data-toggle">
            <button class="toggle-btn planted active" id="togglePlanted" onclick="switchDataType('planted')">
              <i class="fas fa-tree"></i> Planted
            </button>
            <button class="toggle-btn mortality" id="toggleMortality" onclick="switchDataType('mortality')">
              <i class="fas fa-skull"></i> Mortality
            </button>
          </div>
          <div class="map-controls">
            <button class="map-btn" onclick="resetMap()"><i class="fas fa-expand"></i> Reset</button>
            <button class="map-btn" onclick="toggleLabels()"><i class="fas fa-font"></i> Labels</button>
          </div>
        </div>
      </div>
      
      <div id="map"></div>

      <!-- Legend & Rate Summary -->
      <div class="legend-container">
        <div class="legend" id="legendPlanted">
          <h4><i class="fas fa-palette" style="color: #2b6e3b;"></i>Seedlings Planted (per barangay)</h4>
          <div class="legend-items" id="plantedLegendItems"></div>
        </div>
        <div class="legend" id="legendMortality" style="display: none;">
          <h4><i class="fas fa-palette" style="color: #c53030;"></i>Mortality (per Municipality-barangay)</h4>
          <div class="legend-items" id="mortalityLegendItems"></div>
        </div>
        
        <!-- Rate Summary Box -->
        <div class="rate-summary">
          <h4><i class="fas fa-calculator"></i> Mortality & Survival Analysis</h4>
          <div class="rate-stats">
            <div class="rate-item">
              <span class="rate-label">Mortality Rate</span>
              <span class="rate-value"><?php echo $mortalityRate; ?>%</span>
            </div>
            <div class="rate-item">
              <span class="rate-label">Survival Rate</span>
              <span class="rate-value"><?php echo $survivalRate; ?>%</span>
            </div>
          </div>
          <div class="rate-formula">
            <i class="fas fa-divide"></i> 
            Mortality Rate = (<?php echo number_format($totalMortality); ?> ÷ <?php echo number_format($totalPlanted); ?>) × 100% | 
            Survival Rate = 100% - <?php echo $mortalityRate; ?>%
          </div>
        </div>
        
        <div class="info-panel">
          <p><i class="fas fa-info-circle"></i> <strong>Choropleth Map</strong></p>
          <p><i class="fas fa-mouse-pointer"></i> Hover over barangays to see values. Click for detailed information.</p>
          <p><i class="fas fa-chart-bar"></i> Colors represent data classification using quantile breaks.</p>
          <p><i class="fas fa-map"></i> <span id="dataSummary">Showing seedling planting data across 2 municipalities.</span></p>
        </div>
      </div>
      <div class="attribution">
        <i class="far fa-copyright"></i> DENR Region XI | Basemap: OpenStreetMap contributors
      </div>
    </div>
  </div>

<script>
// Pass PHP data to JavaScript
const plantedData = <?php echo json_encode($planted_by_barangay); ?>;
const mortalityData = <?php echo json_encode($mortality_by_barangay); ?>;

// Current data type
let currentDataType = 'planted';

// Map variables
let map;
let barangayLayer;
let geoJsonData = null;
let labelsVisible = true;
let labelMarkers = [];
let tooltip = null;

// Barangays list for filtering
const Barangays = ["Alegre","Alta Vista","Anonang","Bitaug","Bonifacio","Buenavista","Darapuay","Dolo","Eman","Kinuskusan","Libertad","Linawan","Mabuhay","Mabunga","Managa","Marber","New Clarin","Poblacion Uno","Poblacion Dos","Rizal","Santo Niño","Sibayan","Tinongtongan","Tubod","Union","Balabag","Goma","Aplaya","Bato","Kapatagan","Binaton","Cogon","Dulangan","Kiagot","Mahayahay","Ruparan","San Jose","San Miguel","San Roque","Sinawilan","Soong","Tiguman","Zone 1","Zone 2","Zone 3"];

// Color schemes (ColorBrewer-inspired)
const plantedColors = ['#edf8e9', '#bae4b3', '#74c476', '#31a354', '#006d2c'];
const mortalityColors = ['#fee5d9', '#fcae91', '#fb6a4a', '#de2d26', '#a50f15'];

// Initialize map
function initMap() {
    map = L.map('map').setView([6.78, 125.35], 12);
    
    // Geography-style basemap
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);
    
    // Add scale control
    L.control.scale({ imperial: false, metric: true, position: 'bottomleft' }).addTo(map);
    
    // Initialize tooltip
    tooltip = L.tooltip({ className: 'map-tooltip', direction: 'top', sticky: true });
    
    // Load GeoJSON
    loadGeoJson();
}

// Normalize string for comparison
function normalize(str) {
    if (!str) return '';
    return str.toString().trim().toLowerCase().replace(/\s+/g, '');
}

// Get color based on value and breaks
function getColor(value, breaks, colors) {
    if (value === 0) return '#f0f0f0';
    for (let i = 0; i < breaks.length - 1; i++) {
        if (value <= breaks[i + 1]) {
            return colors[i];
        }
    }
    return colors[colors.length - 1];
}

// Calculate breaks from data
function calculateBreaks(data, numClasses = 5) {
    const values = Object.values(data).map(d => currentDataType === 'planted' ? d.totalPlanted : d.totalMortality).filter(v => v > 0);
    if (values.length === 0) return [0, 1, 2, 3, 4, 5];
    
    values.sort((a, b) => a - b);
    const breaks = [];
    const n = values.length;
    
    for (let i = 0; i <= numClasses; i++) {
        const index = Math.min(Math.floor((i / numClasses) * (n - 1)), n - 1);
        breaks.push(values[index]);
    }
    
    return [...new Set(breaks)];
}

// Generate legend
function generateLegend(breaks, colors, containerId, unit = 'seedlings') {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    let html = '';
    for (let i = 0; i < breaks.length - 1; i++) {
        const label = i === breaks.length - 2 ? 
            `${breaks[i].toLocaleString()}+ ${unit}` : 
            `${breaks[i].toLocaleString()} - ${breaks[i + 1].toLocaleString()} ${unit}`;
        
        html += `
            <div class="legend-row">
                <div class="legend-color-box" style="background: ${colors[i]};"></div>
                <span class="legend-label">${label}</span>
            </div>
        `;
    }
    
    html += `
        <div class="legend-row">
            <div class="legend-color-box" style="background: #f0f0f0; border: 1px dashed #ccc;"></div>
            <span class="legend-label">No data / 0 ${unit}</span>
        </div>
    `;
    
    container.innerHTML = html;
}

// Filter barangays
function filterBarangays(feature) {
    const muniName = feature.properties.NAME_2 || feature.properties.MUNICIPALITY || '';
    const brgyName = feature.properties.NAME_3 || feature.properties.BARANGAY || '';
    
    const muniLower = normalize(muniName);
    return (muniLower === 'bansalan' || muniLower === 'digos city' || muniLower === 'digoscity') &&
           Barangays.some(b => normalize(b) === normalize(brgyName));
}

// Load GeoJSON
function loadGeoJson() {
    showLoading();
    
    fetch("level3.json")
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            geoJsonData = data;
            renderMap();
            hideLoading();
        })
        .catch(error => {
            console.error('Error loading GeoJSON:', error);
            hideLoading();
            document.getElementById('map').innerHTML = '<div style="text-align: center; padding: 50px; color: #e53e3e;">Error loading map data. Please try again later.</div>';
        });
}

// Render map with current data type
function renderMap() {
    if (barangayLayer) {
        map.removeLayer(barangayLayer);
    }
    
    labelMarkers.forEach(marker => map.removeLayer(marker));
    labelMarkers = [];
    
    const selectedBarangays = [];
    const dataSource = currentDataType === 'planted' ? plantedData : mortalityData;
    const colors = currentDataType === 'planted' ? plantedColors : mortalityColors;
    
    const breaks = calculateBreaks(dataSource);
    const legendContainer = currentDataType === 'planted' ? 'plantedLegendItems' : 'mortalityLegendItems';
    const unit = currentDataType === 'planted' ? 'seedlings' : 'died';
    generateLegend(breaks, colors, legendContainer, unit);
    
    barangayLayer = L.geoJSON(geoJsonData, {
        filter: filterBarangays,
        style: function(feature) {
            const muniName = feature.properties.NAME_2 || feature.properties.MUNICIPALITY || '';
            const brgyName = feature.properties.NAME_3 || feature.properties.BARANGAY || '';
            
            let total = 0;
            for (const key in dataSource) {
                const item = dataSource[key];
                if (normalize(item.municipality) === normalize(muniName) && 
                    normalize(item.barangay) === normalize(brgyName)) {
                    total = currentDataType === 'planted' ? item.totalPlanted : item.totalMortality;
                    break;
                }
            }
            
            const fillColor = getColor(total, breaks, colors);
            
            return {
                color: "#fff",
                weight: 1,
                fillColor: fillColor,
                fillOpacity: 0.85,
                opacity: 0.6
            };
        },
        onEachFeature: function(feature, layer) {
            const muniName = feature.properties.NAME_2 || feature.properties.MUNICIPALITY || '';
            const brgyName = feature.properties.NAME_3 || feature.properties.BARANGAY || '';
            
            let barangayInfo = null;
            for (const key in dataSource) {
                const item = dataSource[key];
                if (normalize(item.municipality) === normalize(muniName) && 
                    normalize(item.barangay) === normalize(brgyName)) {
                    barangayInfo = item;
                    break;
                }
            }
            
            const total = barangayInfo ? 
                (currentDataType === 'planted' ? barangayInfo.totalPlanted : barangayInfo.totalMortality) : 0;
            const varieties = barangayInfo ? barangayInfo.varieties : [];
            
            layer.on('mouseover', function(e) {
                const label = currentDataType === 'planted' ? 'Planted' : 'Mortality';
                const value = total.toLocaleString();
                const unit = currentDataType === 'planted' ? 'seedlings' : 'died';
                
                tooltip.setContent(`
                    <strong>${brgyName}</strong>
                    <span class="value">${muniName}</span><br>
                    <span class="value"><strong>${label}:</strong> ${value} ${unit}</span>
                `);
                layer.bindTooltip(tooltip).openTooltip(e.latlng);
                
                this.setStyle({ weight: 2, fillOpacity: 1, opacity: 1 });
            });
            
            layer.on('mouseout', function() {
                layer.unbindTooltip();
                this.setStyle({ weight: 1, fillOpacity: 0.85, opacity: 0.6 });
            });
            
            const dataLabel = currentDataType === 'planted' ? 'Seedlings Planted' : 'Mortality';
            const colorClass = currentDataType === 'planted' ? '#2b6e3b' : '#c53030';
            
            let popupContent = `
                <div class="popup-header">
                    <h4><i class="fas fa-map-pin" style="color: ${colorClass}; margin-right: 6px;"></i>${brgyName}</h4>
                </div>
                <div style="padding: 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #6c757d;">Municipality:</span>
                        <strong>${muniName}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #6c757d;">${dataLabel}:</span>
                        <strong style="color: ${colorClass};">${total.toLocaleString()}</strong>
                    </div>`;
            
            if (varieties.length > 0) {
                popupContent += `
                    <div style="margin-top: 10px;">
                        <span style="color: #6c757d; display: block; margin-bottom: 5px;">Varieties:</span>
                        <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                            ${varieties.map(v => 
                                `<span style="background: ${colorClass}10; padding: 3px 8px; border-radius: 12px; font-size: 11px; color: ${colorClass};">${v}</span>`
                            ).join('')}
                        </div>
                    </div>`;
            } else {
                popupContent += `
                    <div style="margin-top: 10px; color: #adb5bd; font-style: italic;">
                        No variety data available
                    </div>`;
            }
            
            popupContent += `</div>`;
            
            layer.bindPopup(popupContent, { maxWidth: 300, className: 'custom-popup' });
            
            try {
                const centroid = turf.centroid(feature);
                const coords = centroid.geometry.coordinates;
                const label = L.marker([coords[1], coords[0]], {
                    icon: L.divIcon({
                        className: "barangay-label",
                        html: `<span>${brgyName}</span>`
                    }),
                    interactive: false,
                    zIndexOffset: 1000
                });
                
                if (labelsVisible) {
                    label.addTo(map);
                }
                labelMarkers.push(label);
            } catch (e) {
                console.warn('Could not add label for', brgyName);
            }
            
            selectedBarangays.push(feature);
        }
    }).addTo(map);
    
    if (selectedBarangays.length > 0) {
        try {
            const world = turf.polygon([[[-180, -90], [180, -90], [180, 90], [-180, 90], [-180, -90]]]);
            let combined = selectedBarangays.reduce((acc, f) => acc ? turf.union(acc, f) : f, null);
            if (combined) {
                const mask = turf.difference(world, combined);
                L.geoJSON(mask, { 
                    style: { fillColor: "#1a1f2e", color: "transparent", weight: 0, fillOpacity: 0.25 },
                    interactive: false
                }).addTo(map);
            }
        } catch (e) {
            console.warn('Masking error:', e);
        }
        
        map.fitBounds(barangayLayer.getBounds(), { padding: [20, 20] });
    }
    
    const totalBarangays = Object.keys(dataSource).length;
    const totalValue = currentDataType === 'planted' ? 
        Object.values(plantedData).reduce((s, d) => s + d.totalPlanted, 0) : 
        Object.values(mortalityData).reduce((s, d) => s + d.totalMortality, 0);
    
    document.getElementById('dataSummary').textContent = 
        `${totalBarangays} barangays with data. Total: ${totalValue.toLocaleString()} ${currentDataType === 'planted' ? 'seedlings planted' : 'mortality'}.`;
}

function switchDataType(type) {
    currentDataType = type;
    
    document.getElementById('togglePlanted').classList.toggle('active', type === 'planted');
    document.getElementById('toggleMortality').classList.toggle('active', type === 'mortality');
    
    document.getElementById('legendPlanted').style.display = type === 'planted' ? 'block' : 'none';
    document.getElementById('legendMortality').style.display = type === 'mortality' ? 'block' : 'none';
    
    document.getElementById('mapTitle').textContent = type === 'planted' ? 'Seedling Planted Density' : 'Mortality Density';
    
    if (geoJsonData) {
        renderMap();
    }
}

function resetMap() {
    if (barangayLayer) {
        map.fitBounds(barangayLayer.getBounds(), { padding: [20, 20] });
    }
}

function toggleLabels() {
    labelsVisible = !labelsVisible;
    labelMarkers.forEach(marker => {
        if (labelsVisible) {
            marker.addTo(map);
        } else {
            map.removeLayer(marker);
        }
    });
}

function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

window.addEventListener('load', () => {
    initMap();
});
</script>
</body>
</html>
