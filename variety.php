<?php
session_start();

/* =======================
   LOGIN CHECK
======================= */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

/* =======================
   FUNCTION TO FETCH FIREBASE DATA
======================= */
function fetchFirebaseData($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("cURL Error: " . $error);
        return null;
    }
    
    if ($httpCode !== 200) {
        error_log("HTTP Error: " . $httpCode);
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Error: " . json_last_error_msg());
        return null;
    }
    
    return $data;
}

/* =======================
   FIREBASE CRUD FUNCTIONS
======================= */
function pushToFirebase($url, $data) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

function updateFirebase($url, $data, $etag = null) {
    $ch = curl_init();
    $headers = ['Content-Type: application/json'];
    if ($etag) {
        $headers[] = 'If-Match: ' . $etag;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

function deleteFromFirebase($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

/* =======================
   VARIETY MANAGEMENT SYSTEM
======================= */
$firebase_base = "https://validator-b9503-default-rtdb.firebaseio.com";

// Initialize messages
$success_message = '';
$error_message = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $newVariety = [
                    'varietyName' => trim($_POST['varietyName']),
                    'scientificName' => trim($_POST['scientificName']),
                    'category' => trim($_POST['category']),
                    'growthPeriod' => trim($_POST['growthPeriod']),
                    'description' => trim($_POST['description']),
                    'idealClimate' => trim($_POST['idealClimate']),
                    'soilType' => trim($_POST['soilType']),
                    'waterRequirement' => trim($_POST['waterRequirement']),
                    'purpose' => trim($_POST['purpose']),
                    'status' => 'Active',
                    'createdAt' => date('Y-m-d H:i:s'),
                    'updatedAt' => date('Y-m-d H:i:s')
                ];
                
                if (pushToFirebase($firebase_base . "/Varieties.json", $newVariety)) {
                    $success_message = "Variety added successfully!";
                } else {
                    $error_message = "Failed to add variety. Please try again.";
                }
                break;
                
            case 'edit':
                $varietyId = $_POST['varietyId'];
                $updateData = [
                    'varietyName' => trim($_POST['varietyName']),
                    'scientificName' => trim($_POST['scientificName']),
                    'category' => trim($_POST['category']),
                    'growthPeriod' => trim($_POST['growthPeriod']),
                    'description' => trim($_POST['description']),
                    'idealClimate' => trim($_POST['idealClimate']),
                    'soilType' => trim($_POST['soilType']),
                    'waterRequirement' => trim($_POST['waterRequirement']),
                    'purpose' => trim($_POST['purpose']),
                    'updatedAt' => date('Y-m-d H:i:s')
                ];
                
                if (updateFirebase($firebase_base . "/Varieties/{$varietyId}.json", $updateData)) {
                    $success_message = "Variety updated successfully!";
                } else {
                    $error_message = "Failed to update variety. Please try again.";
                }
                break;
                
            case 'delete':
                $varietyId = $_POST['varietyId'];
                if (deleteFromFirebase($firebase_base . "/Varieties/{$varietyId}.json")) {
                    $success_message = "Variety deleted successfully!";
                } else {
                    $error_message = "Failed to delete variety. Please try again.";
                }
                break;
                
            case 'toggle_status':
                $varietyId = $_POST['varietyId'];
                $currentStatus = $_POST['currentStatus'];
                $newStatus = ($currentStatus === 'Active') ? 'Inactive' : 'Active';
                $updateData = [
                    'status' => $newStatus,
                    'updatedAt' => date('Y-m-d H:i:s')
                ];
                
                if (updateFirebase($firebase_base . "/Varieties/{$varietyId}.json", $updateData)) {
                    $success_message = "Variety status updated to {$newStatus}!";
                } else {
                    $error_message = "Failed to update variety status.";
                }
                break;
        }
    }
}

// Fetch all varieties
$varieties_url = $firebase_base . "/Varieties.json";
$varieties_data = fetchFirebaseData($varieties_url);

$varieties = [];
$total_varieties = 0;
$active_varieties = 0;
$inactive_varieties = 0;
$categories = [];

if ($varieties_data && is_array($varieties_data)) {
    foreach ($varieties_data as $key => $variety) {
        if (is_array($variety) && isset($variety['varietyName'])) {
            $varieties[$key] = $variety;
            $varieties[$key]['id'] = $key;
            $total_varieties++;
            
            if (isset($variety['status']) && $variety['status'] === 'Active') {
                $active_varieties++;
            } else {
                $inactive_varieties++;
            }
            
            if (isset($variety['category'])) {
                $cat = $variety['category'];
                if (!isset($categories[$cat])) {
                    $categories[$cat] = 0;
                }
                $categories[$cat]++;
            }
        }
    }
}

// Fetch related data for dashboard stats
$requests_data = fetchFirebaseData($firebase_base . "/FarmerSeedlingRequests.json");
$mortality_data = fetchFirebaseData($firebase_base . "/SeedlingMortalityReports.json");
$distribution_data = fetchFirebaseData($firebase_base . "/SeedlingDistributions.json");
$planted_data = fetchFirebaseData($firebase_base . "/SeedlingPlantedReports.json");

$total_requests = is_array($requests_data) ? count($requests_data) : 0;
$total_mortality = is_array($mortality_data) ? count($mortality_data) : 0;
$total_distributions = is_array($distribution_data) ? count($distribution_data) : 0;
$total_planted = is_array($planted_data) ? count($planted_data) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Variety Management - DENR System</title>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Google Fonts -->
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
    display: flex;
    flex-direction: column;
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
    flex: 1;
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
    padding: 30px;
    min-height: 100vh;
}

/* ===== WELCOME HEADER ===== */
.welcome-header {
    background: white;
    color: #2d3748;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.welcome-header h2 {
    font-size: 28px;
    font-weight: 600;
}

.welcome-header h2 i {
    margin-right: 10px;
    color: #48bb78;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-family: 'Poppins', sans-serif;
}

.btn-primary {
    background: linear-gradient(90deg, #4a90e2 0%, #357abd 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(74, 144, 226, 0.4);
}

.btn-success {
    background: linear-gradient(90deg, #48bb78 0%, #38a169 100%);
    color: white;
}

.btn-danger {
    background: linear-gradient(90deg, #f56565 0%, #e53e3e 100%);
    color: white;
}

.btn-warning {
    background: linear-gradient(90deg, #ed8936 0%, #dd6b20 100%);
    color: white;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 12px;
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
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
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
    background: linear-gradient(90deg, #4a90e2, #9f7aea);
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
.stat-icon.orange { background: #ed8936; color: white; }
.stat-icon.purple { background: #9f7aea; color: white; }
.stat-icon.red { background: #f56565; color: white; }

.stat-details {
    flex: 1;
}

.stat-details h3 {
    font-size: 14px;
    color: #718096;
    font-weight: 500;
    margin-bottom: 5px;
}

.stat-details .stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
}

/* ===== ALERT MESSAGES ===== */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    animation: slideDown 0.3s ease;
}

.alert-success {
    background: #c6f6d5;
    border: 1px solid #48bb78;
    color: #22543d;
}

.alert-danger {
    background: #fed7d7;
    border: 1px solid #f56565;
    color: #742a2a;
}

@keyframes slideDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* ===== TABLES ===== */
.table-container {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
    margin-bottom: 30px;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #edf2f7;
}

.table-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-box {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-box input {
    padding: 10px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Poppins', sans-serif;
    width: 250px;
    transition: border-color 0.3s;
}

.search-box input:focus {
    outline: none;
    border-color: #4a90e2;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: #f7fafc;
}

th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #4a5568;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    padding: 15px;
    border-top: 1px solid #e2e8f0;
    font-size: 14px;
    color: #4a5568;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-active {
    background: #c6f6d5;
    color: #22543d;
}

.status-inactive {
    background: #fed7d7;
    color: #742a2a;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

/* ===== MODAL ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 2000;
    justify-content: center;
    align-items: center;
    animation: fadeIn 0.3s ease;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 15px;
    padding: 30px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #edf2f7;
}

.modal-header h2 {
    font-size: 20px;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #a0aec0;
    transition: color 0.3s;
}

.close-btn:hover {
    color: #4a5568;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* ===== FORM STYLES ===== */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #4a5568;
    font-size: 14px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Poppins', sans-serif;
    transition: border-color 0.3s;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #4a90e2;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 2px solid #edf2f7;
}

/* ===== CHARTS SECTION ===== */
.charts-section {
    margin-bottom: 30px;
}

.chart-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
}

.chart-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.chart-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #edf2f7;
}

.chart-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 8px;
}

canvas {
    max-height: 300px;
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
    
    .main-content {
        margin-left: 80px;
        padding: 20px;
    }
    
    .chart-row {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .welcome-header {
        flex-direction: column;
        gap: 15px;
    }
}
</style>
</head>

<body>

<!-- ===== SIDEBAR ===== -->
<div class="sidebar">
    <div class="logo">
        <a href="dashboard.php">
            <img src="image/DENR.jpg" alt="DENR Logo">
        </a>
        <div class="sidebar-title">
            Department of Environment<br>and Natural Resources
        </div>
    </div>

    <nav>
        <a href="dashboard.php" class="nav-link active">
            <i class="fas fa-seedling"></i>
            <span>Variety Management</span>
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
            <i class="fas fa-truck"></i>
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
    
    <!-- Welcome Header -->
    <div class="welcome-header">
        <h2>
            <i class="fas fa-seedling"></i>
            Variety Management System
        </h2>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus-circle"></i> Add New Variety
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-leaf"></i>
            </div>
            <div class="stat-details">
                <h3>Total Varieties</h3>
                <div class="stat-number"><?php echo $total_varieties; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <h3>Active Varieties</h3>
                <div class="stat-number"><?php echo $active_varieties; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-file-signature"></i>
            </div>
            <div class="stat-details">
                <h3>Total Requests</h3>
                <div class="stat-number"><?php echo $total_requests; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="stat-details">
                <h3>Categories</h3>
                <div class="stat-number"><?php echo count($categories); ?></div>
            </div>
        </div>
    </div>

    <!-- Variety Table -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Variety List</h3>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search varieties..." onkeyup="filterTable()">
            </div>
        </div>
        <table id="varietyTable">
            <thead>
                <tr>
                    <th>Variety Name</th>
                    <th>Scientific Name</th>
                    <th>Category</th>
                    <th>Growth Period</th>
                    <th>Water Requirement</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($varieties)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 30px;">
                        <i class="fas fa-seedling" style="font-size: 48px; color: #cbd5e0; display: block; margin-bottom: 10px;"></i>
                        No varieties found. Add your first variety!
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($varieties as $id => $variety): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($variety['varietyName'] ?? 'N/A'); ?></strong></td>
                        <td><em><?php echo htmlspecialchars($variety['scientificName'] ?? 'N/A'); ?></em></td>
                        <td><?php echo htmlspecialchars($variety['category'] ?? 'Uncategorized'); ?></td>
                        <td><?php echo htmlspecialchars($variety['growthPeriod'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($variety['waterRequirement'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="status-badge <?php echo ($variety['status'] ?? 'Active') === 'Active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $variety['status'] ?? 'Active'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-warning btn-sm" onclick="openEditModal('<?php echo $id; ?>')" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-<?php echo ($variety['status'] ?? 'Active') === 'Active' ? 'danger' : 'success'; ?>" 
                                        onclick="toggleStatus('<?php echo $id; ?>', '<?php echo $variety['status'] ?? 'Active'; ?>')"
                                        title="<?php echo ($variety['status'] ?? 'Active') === 'Active' ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas fa-<?php echo ($variety['status'] ?? 'Active') === 'Active' ? 'ban' : 'check'; ?>"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="confirmDelete('<?php echo $id; ?>', '<?php echo htmlspecialchars($variety['varietyName'] ?? ''); ?>')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
        <div class="chart-row">
            <!-- Category Distribution Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie"></i> Variety Categories</h3>
                </div>
                <?php if (!empty($categories)): ?>
                <canvas id="categoryChart"></canvas>
                <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #a0aec0;">
                    <i class="fas fa-chart-pie" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                    No data available
                </div>
                <?php endif; ?>
            </div>

            <!-- System Overview -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-bar"></i> System Overview</h3>
                </div>
                <canvas id="overviewChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ===== ADD/EDIT MODAL ===== -->
<div id="varietyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">
                <i class="fas fa-plus-circle"></i> Add New Variety
            </h2>
            <button class="close-btn" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="varietyForm" method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="varietyId" id="varietyId">
            
            <div class="form-group">
                <label for="varietyName">Variety Name *</label>
                <input type="text" id="varietyName" name="varietyName" required placeholder="Enter variety name">
            </div>
            
            <div class="form-group">
                <label for="scientificName">Scientific Name</label>
                <input type="text" id="scientificName" name="scientificName" placeholder="Enter scientific name">
            </div>
            
            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select Category</option>
                    <option value="Fruit Tree">Fruit Tree</option>
                    <option value="Forest Tree">Forest Tree</option>
                    <option value="Ornamental">Ornamental</option>
                    <option value="Medicinal">Medicinal</option>
                    <option value="Vegetable">Vegetable</option>
                    <option value="Cash Crop">Cash Crop</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="growthPeriod">Growth Period</label>
                <input type="text" id="growthPeriod" name="growthPeriod" placeholder="e.g., 3-6 months, 1-2 years">
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Enter variety description"></textarea>
            </div>
            
            <div class="form-group">
                <label for="idealClimate">Ideal Climate</label>
                <input type="text" id="idealClimate" name="idealClimate" placeholder="e.g., Tropical, Subtropical">
            </div>
            
            <div class="form-group">
                <label for="soilType">Soil Type</label>
                <input type="text" id="soilType" name="soilType" placeholder="e.g., Loamy, Sandy, Clay">
            </div>
            
            <div class="form-group">
                <label for="waterRequirement">Water Requirement</label>
                <select id="waterRequirement" name="waterRequirement">
                    <option value="">Select Water Requirement</option>
                    <option value="Low">Low</option>
                    <option value="Moderate">Moderate</option>
                    <option value="High">High</option>
                    <option value="Very High">Very High</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="purpose">Purpose</label>
                <input type="text" id="purpose" name="purpose" placeholder="e.g., Reforestation, Fruit Production">
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Variety
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== DELETE CONFIRMATION MODAL ===== -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <div class="modal-header">
            <h2><i class="fas fa-exclamation-triangle" style="color: #f56565;"></i> Confirm Delete</h2>
            <button class="close-btn" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <p style="margin-bottom: 20px; color: #4a5568;">
            Are you sure you want to delete <strong id="deleteVarietyName"></strong>?
            This action cannot be undone.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="varietyId" id="deleteVarietyId">
            <div class="form-actions" style="justify-content: center;">
                <button type="button" class="btn" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== STATUS TOGGLE FORM ===== -->
<form id="statusForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="varietyId" id="statusVarietyId">
    <input type="hidden" name="currentStatus" id="statusCurrentStatus">
</form>

<script>
// Store variety data for editing
const varietiesData = <?php echo json_encode($varieties); ?>;

// Modal functions
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Variety';
    document.getElementById('formAction').value = 'add';
    document.getElementById('varietyId').value = '';
    document.getElementById('varietyForm').reset();
    document.getElementById('varietyModal').classList.add('show');
}

function openEditModal(varietyId) {
    const variety = varietiesData[varietyId];
    if (!variety) return;
    
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Variety';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('varietyId').value = varietyId;
    
    // Fill form fields
    document.getElementById('varietyName').value = variety.varietyName || '';
    document.getElementById('scientificName').value = variety.scientificName || '';
    document.getElementById('category').value = variety.category || '';
    document.getElementById('growthPeriod').value = variety.growthPeriod || '';
    document.getElementById('description').value = variety.description || '';
    document.getElementById('idealClimate').value = variety.idealClimate || '';
    document.getElementById('soilType').value = variety.soilType || '';
    document.getElementById('waterRequirement').value = variety.waterRequirement || '';
    document.getElementById('purpose').value = variety.purpose || '';
    
    document.getElementById('varietyModal').classList.add('show');
}

function closeModal() {
    document.getElementById('varietyModal').classList.remove('show');
}

function confirmDelete(varietyId, varietyName) {
    document.getElementById('deleteVarietyId').value = varietyId;
    document.getElementById('deleteVarietyName').textContent = varietyName;
    document.getElementById('deleteModal').classList.add('show');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
}

function toggleStatus(varietyId, currentStatus) {
    document.getElementById('statusVarietyId').value = varietyId;
    document.getElementById('statusCurrentStatus').value = currentStatus;
    document.getElementById('statusForm').submit();
}

// Search/filter function
function filterTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('varietyTable');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        let found = false;
        const cells = rows[i].getElementsByTagName('td');
        
        for (let j = 0; j < cells.length - 1; j++) { // Exclude actions column
            const cellText = cells[j].textContent || cells[j].innerText;
            if (cellText.toLowerCase().indexOf(filter) > -1) {
                found = true;
                break;
            }
        }
        
        rows[i].style.display = found ? '' : 'none';
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const addModal = document.getElementById('varietyModal');
    const deleteModal = document.getElementById('deleteModal');
    
    if (event.target === addModal) {
        closeModal();
    }
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}

// Charts
<?php if (!empty($categories)): ?>
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_keys($categories)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_values($categories)); ?>,
            backgroundColor: ['#48bb78', '#4299e1', '#ed8936', '#9f7aea', '#f56565', '#38b2ac'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

// Overview Chart
new Chart(document.getElementById('overviewChart'), {
    type: 'bar',
    data: {
        labels: ['Varieties', 'Requests', 'Mortality', 'Distributions', 'Planted'],
        datasets: [{
            label: 'System Overview',
            data: [
                <?php echo $total_varieties; ?>, 
                <?php echo $total_requests; ?>, 
                <?php echo $total_mortality; ?>, 
                <?php echo $total_distributions; ?>, 
                <?php echo $total_planted; ?>
            ],
            backgroundColor: ['#48bb78', '#4299e1', '#f56565', '#ed8936', '#9f7aea'],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
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
</script>

</body>
</html>
