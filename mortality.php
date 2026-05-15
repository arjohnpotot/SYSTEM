<?php
session_start();

/* ===== LOGIN CHECK ===== */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

/* ===== FIREBASE FETCH ===== */
$firebase_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingMortalityReports.json";
$response = @file_get_contents($firebase_url);
$data = array_values(json_decode($response, true) ?? []);

/* ===== CALCULATE STATISTICS ===== */
$totalMortality = array_sum(array_column($data, 'died'));
$avgMortality = count($data) > 0 ? round($totalMortality / count($data)) : 0;
$uniqueMunicipalities = count(array_unique(array_column($data, 'municipality')));
$uniqueVarieties = count(array_unique(array_column($data, 'variety')));

// Get unique values for filters
$municipalities = array_unique(array_column($data, 'municipality'));
$barangays = array_unique(array_column($data, 'barangay'));
$varieties = array_unique(array_column($data, 'variety'));
sort($municipalities);
sort($barangays);
sort($varieties);

// Extract years from data for year filter
$years = [];
foreach ($data as $record) {
    if (!empty($record['date'])) {
        $year = date('Y', strtotime($record['date']));
        $years[] = $year;
    }
}
$years = array_unique($years);
sort($years);

// Find highest mortality area
$highestMortality = 0;
$highestArea = '';
foreach ($data as $record) {
    $died = (int)($record['died'] ?? 0);
    if ($died > $highestMortality) {
        $highestMortality = $died;
        $highestArea = ($record['municipality'] ?? '') . ' - ' . ($record['barangay'] ?? '');
    }
}

// Prepare data for charts
$chartData = [
    'allData' => $data,
    'years' => $years
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seedling Mortality Records - DENR System</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    color: white;
}

/* ===== MAIN CONTENT ===== */
.main-content {
    margin-left: 280px;
    padding: 30px;
    min-height: 100vh;
}

/* ===== HEADER ===== */
.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.header-content h2 {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 10px;
}

.header-content h2 i {
    margin-right: 10px;
}

.header-content p {
    font-size: 16px;
    opacity: 0.9;
    margin: 0;
}

/* ===== STATS CARDS ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #f56565, #ed8936);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    font-size: 28px;
}

.stat-icon.red { 
    background: linear-gradient(135deg, #f56565, #e53e3e); 
    color: white; 
}
.stat-icon.orange { 
    background: linear-gradient(135deg, #ed8936, #dd6b20); 
    color: white; 
}
.stat-icon.yellow { 
    background: linear-gradient(135deg, #ecc94b, #d69e2e); 
    color: white; 
}
.stat-icon.purple { 
    background: linear-gradient(135deg, #9f7aea, #805ad5); 
    color: white; 
}

.stat-details h3 {
    font-size: 14px;
    color: #718096;
    font-weight: 500;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #2d3748;
    line-height: 1.2;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #a0aec0;
}

/* ===== FILTER SECTION ===== */
.filter-section {
    background: white;
    border-radius: 15px;
    padding: 20px 25px;
    margin-bottom: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.filter-section h5 {
    font-size: 16px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 15px;
}

.filter-section h5 i {
    color: #f56565;
    margin-right: 8px;
}

.filter-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    font-weight: 500;
    font-size: 13px;
    color: #4a5568;
    margin-bottom: 5px;
    display: block;
}

.filter-group select {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.filter-group select:focus {
    outline: none;
    border-color: #f56565;
    box-shadow: 0 0 0 3px rgba(245, 101, 101, 0.1);
}

.btn-filter-reset {
    background: #e2e8f0;
    color: #4a5568;
    border: none;
    padding: 10px 25px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-filter-reset:hover {
    background: #cbd5e0;
}

.btn-print-report {
    background: linear-gradient(135deg, #2d3748, #1a202c);
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-print-report:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    color: white;
}

.filter-info-message {
    background: #fff5f5;
    border: 1px solid #fed7d7;
    color: #c53030;
    padding: 8px 15px;
    border-radius: 8px;
    font-size: 13px;
    margin-top: 10px;
    display: none;
}

.filter-info-message.show {
    display: block;
}

/* ===== CHART CARDS ===== */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.chart-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.chart-card h3 {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-card h3 i {
    color: #f56565;
}

.chart-container {
    height: 350px;
    position: relative;
}

.year-selector {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

.year-selector select {
    padding: 5px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    background: white;
}

/* ===== TABLE CARD ===== */
.table-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.table-header h3 {
    font-size: 20px;
    font-weight: 600;
    color: #2d3748;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-header h3 i {
    color: #f56565;
}

.filtered-count {
    background: #edf2f7;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 14px;
    color: #4a5568;
}

/* ===== TABLE STYLES ===== */
.table {
    width: 100%;
    margin-bottom: 0;
}

.table thead th {
    background: linear-gradient(135deg, #2d3748, #1a202c);
    color: white;
    font-weight: 500;
    font-size: 14px;
    padding: 15px;
    border: none;
    white-space: nowrap;
}

.table thead th i {
    margin-right: 8px;
    font-size: 12px;
}

.table tbody td {
    padding: 12px 15px;
    vertical-align: middle;
    color: #4a5568;
    font-size: 14px;
    border-bottom: 1px solid #e2e8f0;
}

.table tbody tr:hover {
    background-color: #f7fafc;
}

/* ===== MORTALITY BADGES ===== */
.mortality-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
}

.badge-high {
    background: #fed7d7;
    color: #c53030;
}

.badge-medium {
    background: #feebc8;
    color: #c05621;
}

.badge-low {
    background: #c6f6d5;
    color: #22543d;
}

/* ===== DATA TABLE CUSTOMIZATION ===== */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
    margin-bottom: 15px;
    color: #4a5568;
}

.dataTables_filter {
    display: none;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 8px 15px;
    margin: 0 3px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #4a5568 !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: linear-gradient(135deg, #f56565, #e53e3e);
    border-color: #f56565;
    color: white !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #edf2f7;
    border-color: #cbd5e0;
}

/* ===== PRINT STYLES ===== */
@media print {
    body * {
        visibility: hidden;
    }
    
    .sidebar,
    .stats-grid,
    .charts-grid,
    .filter-section,
    .page-header,
    .dataTables_length,
    .dataTables_paginate,
    .dataTables_info,
    .table-header,
    .filtered-count {
        display: none !important;
    }
    
    #printableArea, 
    #printableArea * {
        visibility: visible;
    }
    
    #printableArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
    }
    
    .main-content {
        margin-left: 0;
        padding: 0;
    }
    
    .table-card {
        box-shadow: none;
        padding: 0;
    }
    
    .table thead th {
        background: #2d3748 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

/* ===== RESPONSIVE ===== */
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
        padding: 20px;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-row {
        flex-direction: column;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.stat-card, .chart-card, .table-card, .filter-section {
    animation: fadeIn 0.5s ease-out;
}

/* ===== CUSTOM SCROLLBAR ===== */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #edf2f7;
}

::-webkit-scrollbar-thumb {
    background: #f56565;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #e53e3e;
}
</style>
</head>

<body>

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
        <a href="mortality.php" class="nav-link active">
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
        <a href="geographic_seedling_location.php" class="nav-link">
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
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h2>
                <i class="fas fa-exclamation-triangle"></i>
                Seedling Mortality Records
            </h2>
            <p>Search and filter mortality records by Municipality or Barangay, then print your selection</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-skull-crossbones"></i>
            </div>
            <div class="stat-details">
                <h3>Total Mortality</h3>
                <div class="stat-number"><?php echo number_format($totalMortality); ?></div>
                <div class="stat-label">Seedlings lost</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-details">
                <h3>Average Mortality</h3>
                <div class="stat-number"><?php echo number_format($avgMortality); ?></div>
                <div class="stat-label">Per record</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon yellow">
                <i class="fas fa-city"></i>
            </div>
            <div class="stat-details">
                <h3>Municipalities</h3>
                <div class="stat-number"><?php echo $uniqueMunicipalities; ?></div>
                <div class="stat-label">Affected areas</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-leaf"></i>
            </div>
            <div class="stat-details">
                <h3>Varieties</h3>
                <div class="stat-number"><?php echo $uniqueVarieties; ?></div>
                <div class="stat-label">Different types</div>
            </div>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <h5><i class="fas fa-filter"></i> Filter Records for Printing</h5>
        <div class="filter-row">
            <div class="filter-group">
                <label><i class="fas fa-city"></i> Municipality</label>
                <select id="filterMunicipality" class="form-select">
                    <option value="">All Municipalities</option>
                    <?php foreach ($municipalities as $municipality): ?>
                        <?php if (!empty($municipality)): ?>
                            <option value="<?= htmlspecialchars($municipality) ?>">
                                <?= htmlspecialchars($municipality) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-map-pin"></i> Barangay</label>
                <select id="filterBarangay" class="form-select">
                    <option value="">All Barangays</option>
                    <?php foreach ($barangays as $barangay): ?>
                        <?php if (!empty($barangay)): ?>
                            <option value="<?= htmlspecialchars($barangay) ?>">
                                <?= htmlspecialchars($barangay) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-leaf"></i> Seedling Variety</label>
                <select id="filterVariety" class="form-select">
                    <option value="">All Varieties</option>
                    <?php foreach ($varieties as $variety): ?>
                        <?php if (!empty($variety)): ?>
                            <option value="<?= htmlspecialchars($variety) ?>">
                                <?= htmlspecialchars($variety) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group" style="flex: 0 0 auto;">
                <label>&nbsp;</label>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-filter-reset" onclick="resetFilters()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button class="btn-print-report" onclick="printFilteredData()">
                        <i class="fas fa-print"></i> Print Filtered Data
                    </button>
                </div>
            </div>
        </div>
        <div class="filter-info-message" id="filterMessage">
            <i class="fas fa-info-circle"></i> 
            <span id="filterMessageText"></span>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="charts-grid">
        <!-- Top 5 Areas Chart -->
        <div class="chart-card">
           <h3 style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">
    <span>
        <i class="fas fa-chart-bar"></i>
        Top 5 Areas with Highest Mortality
    </span>

    <div style="display:flex; gap:10px; align-items:center;">
        
        <!-- YEAR FILTER -->
        <select id="topYearSelect" onchange="initTopAreasChart()" class="form-select form-select-sm">
            <option value="">All Years</option>
            <?php foreach ($years as $year): ?>
                <option value="<?= $year ?>">
                    <?= $year ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- MONTH FILTER -->
        <select id="topMonthSelect" onchange="initTopAreasChart()" class="form-select form-select-sm">
            <option value="">All Months</option>
            <option value="0">January</option>
            <option value="1">February</option>
            <option value="2">March</option>
            <option value="3">April</option>
            <option value="4">May</option>
            <option value="5">June</option>
            <option value="6">July</option>
            <option value="7">August</option>
            <option value="8">September</option>
            <option value="9">October</option>
            <option value="10">November</option>
            <option value="11">December</option>
        </select>

    </div>
</h3>
            <div class="chart-container">
                <canvas id="topAreasChart"></canvas>
            </div>
        </div>

        <!-- Monthly Trend Chart -->
        <div class="chart-card">
            <h3 style="display: flex; justify-content: space-between; align-items: center;">
                <span>
                    <i class="fas fa-chart-line"></i>
                    Monthly Mortality Trend
                </span>
                <div class="year-selector">
                    <label for="yearSelect" style="font-size: 14px; color: #4a5568; margin: 0;">
                        <i class="fas fa-calendar-alt"></i> Year:
                    </label>
                    <select id="yearSelect" onchange="updateMonthlyChart()">
                        <option value="">All Years</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?= $year ?>" <?= $year == date('Y') ? 'selected' : '' ?>>
                                <?= $year ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </h3>
            <div class="chart-container">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="table-card">
        <div class="table-header">
            <h3>
                <i class="fas fa-list-alt"></i>
                Mortality Records
            </h3>
            <span class="filtered-count" id="filteredCount">
                <i class="fas fa-eye"></i> Showing all records
            </span>
        </div>

        <div id="printableArea">
            <table id="mortalityTable" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th><i class="fas fa-calendar"></i> Date</th>
                        <th><i class="fas fa-city"></i> Municipality</th>
                        <th><i class="fas fa-map-pin"></i> Barangay</th>
                        <th><i class="fas fa-leaf"></i> Seedling Variety</th>
                        <th><i class="fas fa-skull"></i> Total Dead Seedlings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data)): ?>
                        <?php foreach ($data as $record): 
                            $died = (int)($record['died'] ?? 0);
                            $badgeClass = $died > 50 ? 'badge-high' : ($died > 20 ? 'badge-medium' : 'badge-low');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($record['date'] ?? '') ?></td>
                            <td class="mun-cell"><?= htmlspecialchars($record['municipality'] ?? '') ?></td>
                            <td class="bgy-cell"><?= htmlspecialchars($record['barangay'] ?? '') ?></td>
                            <td class="variety-cell"><?= htmlspecialchars($record['variety'] ?? '') ?></td>
                            <td>
                                <span class="mortality-badge <?php echo $badgeClass; ?>">
                                    <?= number_format($died) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <i class="fas fa-exclamation-circle" style="color: #f56565; font-size: 24px;"></i>
                                <br>
                                No mortality records found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>

<script>
// Pass PHP data to JavaScript
var allData = <?php echo json_encode($data); ?>;

// Chart instances
var topAreasChart;
var monthlyTrendChart;

// Month names
const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                    'July', 'August', 'September', 'October', 'November', 'December'];

$(document).ready(function() {
    // Initialize DataTable
    var table = $('#mortalityTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search records...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: '<i class="fas fa-angle-double-left"></i>',
                previous: '<i class="fas fa-angle-left"></i>',
                next: '<i class="fas fa-angle-right"></i>',
                last: '<i class="fas fa-angle-double-right"></i>'
            }
        },
        order: [[0, 'desc']]
    });

    // Add custom filtering for Municipality, Barangay, and Variety
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var selectedMun = $('#filterMunicipality').val();
        var selectedBgy = $('#filterBarangay').val();
        var selectedVariety = $('#filterVariety').val();
        
        var rowMun = data[1]; // Municipality column (index 1)
        var rowBgy = data[2]; // Barangay column (index 2)
        var rowVariety = data[3]; // Variety column (index 3)
        
        if (selectedMun && rowMun !== selectedMun) {
            return false;
        }
        
        if (selectedBgy && rowBgy !== selectedBgy) {
            return false;
        }
        
        if (selectedVariety && rowVariety !== selectedVariety) {
            return false;
        }
        
        return true;
    });
    
    // When any filter changes, redraw the table
    $('#filterMunicipality, #filterBarangay, #filterVariety').on('change', function() {
        table.draw();
        updateFilterInfo(table);
        updateCharts(); // Update charts when filters change
    });
    
    // Initial info update
    updateFilterInfo(table);
    
    // Initialize charts
    initTopAreasChart();
    updateMonthlyChart();
});

// Update filter info message
function updateFilterInfo(table) {
    var mun = $('#filterMunicipality').val();
    var bgy = $('#filterBarangay').val();
    var variety = $('#filterVariety').val();
    
    var filteredRows = table.rows({ filter: 'applied' }).count();
    var totalRows = table.rows().count();
    
    var messageParts = [];
    if (mun) messageParts.push('Municipality: <strong>' + mun + '</strong>');
    if (bgy) messageParts.push('Barangay: <strong>' + bgy + '</strong>');
    if (variety) messageParts.push('Variety: <strong>' + variety + '</strong>');
    
    if (messageParts.length > 0) {
        $('#filterMessage').addClass('show');
        $('#filterMessageText').html('Filtering by: ' + messageParts.join(' | '));
        $('#filteredCount').html('<i class="fas fa-filter"></i> Showing <strong>' + filteredRows + '</strong> of ' + totalRows + ' records');
    } else {
        $('#filterMessage').removeClass('show');
        $('#filteredCount').html('<i class="fas fa-eye"></i> Showing all <strong>' + totalRows + '</strong> records');
    }
}

// Reset all filters
function resetFilters() {
    $('#filterMunicipality').val('');
    $('#filterBarangay').val('');
    $('#filterVariety').val('');
    $('#mortalityTable').DataTable().draw();
    updateFilterInfo($('#mortalityTable').DataTable());
    updateCharts();
}

// Get filtered data for charts
function getFilteredData() {
    var selectedMun = $('#filterMunicipality').val();
    var selectedBgy = $('#filterBarangay').val();
    var selectedVariety = $('#filterVariety').val();
    
    return allData.filter(function(record) {
        if (selectedMun && record.municipality !== selectedMun) return false;
        if (selectedBgy && record.barangay !== selectedBgy) return false;
        if (selectedVariety && record.variety !== selectedVariety) return false;
        return true;
    });
}

// Initialize Top 5 Areas Chart
function initTopAreasChart() {

    var filteredData = getFilteredData();

    var selectedYear = $('#topYearSelect').val();
    var selectedMonth = $('#topMonthSelect').val();

    // FILTER DATA BY YEAR AND MONTH
    filteredData = filteredData.filter(function(record) {

        if (!record.date) return false;

        var dateStr = record.date;
        var parts;
        var year, month;

        // YYYY-MM-DD
        if (dateStr.includes('-')) {

            parts = dateStr.split('-');

            if (parts[0].length === 4) {
                year = parseInt(parts[0]);
                month = parseInt(parts[1]) - 1;
            } else {
                year = parseInt(parts[2]);
                month = parseInt(parts[1]) - 1;
            }

        }
        // MM/DD/YYYY
        else if (dateStr.includes('/')) {

            parts = dateStr.split('/');

            year = parseInt(parts[2]);
            month = parseInt(parts[0]) - 1;
        }

        if (isNaN(year) || isNaN(month)) return false;

        // FILTER YEAR
        if (selectedYear && year.toString() !== selectedYear) {
            return false;
        }

        // FILTER MONTH
        if (selectedMonth !== "" && month.toString() !== selectedMonth) {
            return false;
        }

        return true;
    });

    // AGGREGATE BY AREA
    var areaMortality = {};

    filteredData.forEach(function(record) {

        var area =
            (record.municipality || 'Unknown') +
            ' - ' +
            (record.barangay || 'Unknown');

        var died = parseInt(record.died) || 0;

        areaMortality[area] =
            (areaMortality[area] || 0) + died;
    });

    // SORT TOP 5
    var sortedAreas = Object.entries(areaMortality)
        .sort(function(a, b) {
            return b[1] - a[1];
        })
        .slice(0, 5);

    var labels = sortedAreas.map(function(item) {
        return item[0];
    });

    var values = sortedAreas.map(function(item) {
        return item[1];
    });

    if (labels.length === 0) {
        labels = ['No data'];
        values = [0];
    }

    var ctx = document.getElementById('topAreasChart').getContext('2d');

    if (topAreasChart) {
        topAreasChart.destroy();
    }

    topAreasChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Mortality',
                data: values,
                backgroundColor: [
                    'rgba(245, 101, 101, 0.8)',
                    'rgba(237, 137, 54, 0.8)',
                    'rgba(236, 201, 75, 0.8)',
                    'rgba(159, 122, 234, 0.8)',
                    'rgba(72, 187, 120, 0.8)'
                ],
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Update Monthly Trend Chart
function updateMonthlyChart() {
    var selectedYear = $('#yearSelect').val();
    var filteredData = getFilteredData();
    
    // Initialize monthly data
    var monthlyData = {};
    monthNames.forEach(function(month, index) {
        monthlyData[index] = 0;
    });
    
    // Aggregate mortality by month
    filteredData.forEach(function(record) {
        if (record.date) {
            // Parse date safely
var dateStr = record.date;

// Skip if empty
if (!dateStr) return;

// Convert date properly
var parts;
var year, month;

// If format is YYYY-MM-DD
if (dateStr.includes('-')) {
    parts = dateStr.split('-');
    
    if (parts[0].length === 4) {
        // YYYY-MM-DD
        year = parseInt(parts[0]);
        month = parseInt(parts[1]) - 1;
    } else {
        // DD-MM-YYYY
        year = parseInt(parts[2]);
        month = parseInt(parts[1]) - 1;
    }
}

// If format is MM/DD/YYYY
else if (dateStr.includes('/')) {
    parts = dateStr.split('/');
    year = parseInt(parts[2]);
    month = parseInt(parts[0]) - 1;
}

// Skip invalid dates
if (isNaN(year) || isNaN(month)) return;
            
            // Filter by selected year
            if (!selectedYear || year.toString() === selectedYear) {
                var died = parseInt(record.died) || 0;
                monthlyData[month] += died;
            }
        }
    });
    
    var ctx = document.getElementById('monthlyTrendChart').getContext('2d');
    
    // Destroy existing chart if it exists
    if (monthlyTrendChart) {
        monthlyTrendChart.destroy();
    }
    
    // Check if there's any data
    var hasData = Object.values(monthlyData).some(function(value) { return value > 0; });
    
    monthlyTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: monthNames,
            datasets: [{
                label: selectedYear ? 'Mortality in ' + selectedYear : 'Total Mortality',
                data: Object.values(monthlyData),
                borderColor: '#e53e3e',
                backgroundColor: 'rgba(229, 62, 62, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#e53e3e',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Mortality: ' + context.parsed.y.toLocaleString() + ' seedlings';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#e2e8f0'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString();
                        }
                    },
                    title: {
                        display: true,
                        text: 'Number of Dead Seedlings',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Month',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                }
            }
        }
    });
    
    // If no data, add annotation
    if (!hasData && !selectedYear) {
        // Show message that there's no data
        console.log('No mortality data available for the selected criteria');
    }
}

// Update all charts
function updateCharts() {
    initTopAreasChart();
    updateMonthlyChart();
}

// Print only the filtered data
function printFilteredData() {
    var table = $('#mortalityTable').DataTable();
    
    // Get filter values for the report header
    var mun = $('#filterMunicipality').val() || 'All Municipalities';
    var bgy = $('#filterBarangay').val() || 'All Barangays';
    var variety = $('#filterVariety').val() || 'All Varieties';
    
    // Get filtered data from the table
    var filteredData = [];
    var totalMortality = 0;
    
    table.rows({ filter: 'applied' }).every(function() {
        var row = this.data();
        // Extract number from the badge HTML
        var diedText = row[4].replace(/<[^>]*>/g, '').replace(/,/g, '');
        var died = parseInt(diedText) || 0;
        totalMortality += died;
        
        filteredData.push({
            date: row[0],
            municipality: row[1],
            barangay: row[2],
            variety: row[3],
            died: died
        });
    });
    
    // Create print window
    var printWindow = window.open('', '_blank', 'width=1200,height=800');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>DENR - Seedling Mortality Report</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    padding: 30px; 
                    color: #333;
                }
                .report-header { 
                    text-align: center; 
                    margin-bottom: 20px; 
                    border-bottom: 2px solid #c53030; 
                    padding-bottom: 15px; 
                }
                .report-header h3 { 
                    font-size: 18px; 
                    margin-bottom: 5px; 
                    color: #c53030;
                }
                .report-header h4 { 
                    font-size: 14px; 
                    color: #555; 
                }
                .filter-box { 
                    background: #fff5f5; 
                    padding: 10px 15px; 
                    margin-bottom: 20px; 
                    border-left: 3px solid #c53030;
                    font-size: 13px;
                }
                .summary-box { 
                    background: #fff5f5; 
                    padding: 12px 20px; 
                    margin-bottom: 20px; 
                    display: flex; 
                    justify-content: space-around;
                    border-radius: 5px;
                    border: 1px solid #fed7d7;
                }
                .summary-box div {
                    font-weight: bold;
                    color: #c53030;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    font-size: 13px;
                }
                thead th { 
                    background: #2d3748 !important; 
                    color: white !important; 
                    padding: 10px 8px; 
                    text-align: left;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                tbody td { 
                    padding: 8px; 
                    border-bottom: 1px solid #ddd; 
                }
                tbody tr:nth-child(even) { 
                    background: #f9f9f9; 
                }
                .high-mortality {
                    color: #c53030;
                    font-weight: bold;
                }
                .footer { 
                    margin-top: 25px; 
                    text-align: center; 
                    font-size: 11px; 
                    color: #777; 
                    border-top: 1px solid #ddd; 
                    padding-top: 12px; 
                }
                @media print {
                    body { padding: 15px; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="report-header">
                <h3>DEPARTMENT OF ENVIRONMENT AND NATURAL RESOURCES</h3>
                <h4>Seedling Mortality Report</h4>
                <p style="font-size: 12px; color: #666;">
                    Generated: ${new Date().toLocaleDateString('en-US', { 
                        year: 'numeric', month: 'long', day: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                    })}
                </p>
            </div>
            
            <div class="filter-box">
                <strong>Filters Applied:</strong> 
                Municipality: ${mun} | Barangay: ${bgy} | Variety: ${variety}
            </div>
            
            <div class="summary-box">
                <div>Total Records: ${filteredData.length}</div>
                <div>Total Mortality: ${totalMortality.toLocaleString()} seedlings</div>
                <div>Average: ${filteredData.length > 0 ? Math.round(totalMortality / filteredData.length).toLocaleString() : 0} per record</div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Municipality</th>
                        <th>Barangay</th>
                        <th>Seedling Variety</th>
                        <th>Total Dead</th>
                    </tr>
                </thead>
                <tbody>
    `);
    
    if (filteredData.length === 0) {
        printWindow.document.write(`
            <tr>
                <td colspan="6" style="text-align: center; padding: 20px; color: #999;">
                    No mortality records found with the selected filters
                </td>
            </tr>
        `);
    } else {
        filteredData.forEach(function(row, index) {
            var mortalityClass = row.died > 50 ? 'high-mortality' : '';
            printWindow.document.write(`
                <tr>
                    <td>${index + 1}</td>
                    <td>${row.date}</td>
                    <td>${row.municipality}</td>
                    <td>${row.barangay}</td>
                    <td>${row.variety}</td>
                    <td style="text-align: center;" class="${mortalityClass}">${row.died.toLocaleString()}</td>
                </tr>
            `);
        });
    }
    
    printWindow.document.write(`
                </tbody>
            </table>
            
            <div class="footer">
                <p>This is a computer-generated report from DENR Seedling Management System</p>
                <p>© ${new Date().getFullYear()} Department of Environment and Natural Resources</p>
            </div>
            
            <div class="no-print" style="text-align: center; margin-top: 25px;">
                <button onclick="window.print()" style="padding: 12px 35px; font-size: 15px; background: #c53030; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    🖨️ Print This Report
                </button>
                <button onclick="window.close()" style="padding: 12px 35px; font-size: 15px; background: #999; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                    Close
                </button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

// Loading animation
$(window).on('load', function() {
    $('.stat-card, .chart-card, .table-card, .filter-section').each(function(index) {
        $(this).css('animation-delay', (index * 0.1) + 's');
    });
});
</script>

</body>
</html>
