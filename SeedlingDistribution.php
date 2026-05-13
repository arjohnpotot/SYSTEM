<?php
session_start();

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$firebase_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingDistributions.json";
$response = @file_get_contents($firebase_url);
$data = json_decode($response, true);
if (!$data) {
    $data = [];
}

// Calculate statistics
$total_distributions = count($data);
$total_seedlings = array_sum(array_column($data, 'numSeedlings'));
$completed = count(array_filter($data, function($item) { 
    return ($item['status'] ?? '') === 'Completed'; 
}));
$in_progress = count(array_filter($data, function($item) { 
    return ($item['status'] ?? '') === 'In Progress'; 
}));
$pending = count(array_filter($data, function($item) { 
    return ($item['status'] ?? '') === 'Pending'; 
}));

$unique_recipients = count(array_unique(array_map(function($item) {
    return ($item['firstName'] ?? '') . ' ' . ($item['lastName'] ?? '');
}, $data)));

$unique_types = count(array_unique(array_column($data, 'seedlingType')));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seedling Distribution - DENR System</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">

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

.header-actions {
    display: flex;
    gap: 15px;
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
    background: linear-gradient(90deg, #4a90e2, #48bb78);
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

.stat-icon.blue { 
    background: linear-gradient(135deg, #4299e1, #3182ce); 
    color: white; 
}
.stat-icon.green { 
    background: linear-gradient(135deg, #48bb78, #38a169); 
    color: white; 
}
.stat-icon.purple { 
    background: linear-gradient(135deg, #9f7aea, #805ad5); 
    color: white; 
}
.stat-icon.orange { 
    background: linear-gradient(135deg, #ed8936, #dd6b20); 
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

/* ===== STATUS CARDS ===== */
.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.status-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.status-info h4 {
    font-size: 14px;
    color: #718096;
    margin-bottom: 5px;
}

.status-info .status-count {
    font-size: 24px;
    font-weight: 700;
    color: #2d3748;
}

.status-badge {
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.badge-completed {
    background: #c6f6d5;
    color: #22543d;
}

.badge-progress {
    background: #feebc8;
    color: #744210;
}

.badge-pending {
    background: #fed7d7;
    color: #742a2a;
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
    color: #4a90e2;
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

/* ===== STATUS BADGES IN TABLE ===== */
.status-badge-table {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
}

.status-badge-table.completed {
    background: #c6f6d5;
    color: #22543d;
}

.status-badge-table.progress {
    background: #feebc8;
    color: #744210;
}

.status-badge-table.pending {
    background: #fed7d7;
    color: #742a2a;
}

/* ===== BUTTON STYLES ===== */
.btn-generate {
    background: linear-gradient(135deg, #2d3748, #1a202c);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.btn-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    background: linear-gradient(135deg, #1a202c, #2d3748);
    color: white;
}

/* ===== DATA TABLE CUSTOMIZATION ===== */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
    margin-bottom: 15px;
    color: #4a5568;
}

.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 15px;
    margin-left: 10px;
}

.dataTables_wrapper .dataTables_filter input:focus {
    outline: none;
    border-color: #4a90e2;
    box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
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
    background: linear-gradient(135deg, #4a90e2, #357abd);
    border-color: #4a90e2;
    color: white !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #edf2f7;
    border-color: #cbd5e0;
}

/* ===== PRINT STYLES ===== */
@media print {
    .sidebar,
    .stats-grid,
    .status-grid,
    .header-actions,
    .dataTables_filter,
    .dataTables_length,
    .dataTables_paginate,
    .btn-generate {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0;
        padding: 20px;
    }
    
    .table-card {
        box-shadow: none;
        padding: 0;
    }
    
    .table thead th {
        background: #2d3748 !important;
        color: black !important;
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
    
    .status-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .table-header {
        flex-direction: column;
        align-items: stretch;
    }
}

@media (max-width: 480px) {
    .stats-grid,
    .status-grid {
        grid-template-columns: 1fr;
    }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.stat-card, .status-card, .table-card {
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
    background: #4a90e2;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #357abd;
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
        <a href="mortality.php" class="nav-link">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Mortality</span>
        </a>
        <a href="SeedlingDistribution.php" class="nav-link active">
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
                <i class="fas fa-seedling"></i>
                Seedling Distribution Records
            </h2>
            <p>Track and manage seedling distribution to land owners</p>
        </div>
        <div class="header-actions">
           <!-- <button class="btn-generate" onclick="generateReport()">
                <i class="fas fa-file-csv"></i>
                Export to CSV
            </button> -->
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-details">
                <h3>Total Distributions</h3>
                <div class="stat-number"><?php echo number_format($total_distributions); ?></div>
                <div class="stat-label">All time records</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-seedling"></i>
            </div>
            <div class="stat-details">
                <h3>Seedlings Distributed</h3>
                <div class="stat-number"><?php echo number_format($total_seedlings); ?></div>
                <div class="stat-label">Total seedlings</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-user-friends"></i>
            </div>
            <div class="stat-details">
                <h3>Unique Recipients</h3>
                <div class="stat-number"><?php echo $unique_recipients; ?></div>
                <div class="stat-label">Land owners</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-leaf"></i>
            </div>
            <div class="stat-details">
                <h3>Seedling Types</h3>
                <div class="stat-number"><?php echo $unique_types; ?></div>
                <div class="stat-label">Different varieties</div>
            </div>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="status-grid">
        <div class="status-card">
            <div class="status-info">
                <h4>Completed</h4>
                <div class="status-count"><?php echo $completed; ?></div>
            </div>
            <div class="status-badge badge-completed">
                <i class="fas fa-check-circle"></i> <?php echo $completed; ?>
            </div>
        </div>

        <div class="status-card">
            <div class="status-info">
                <h4>In Progress</h4>
                <div class="status-count"><?php echo $in_progress; ?></div>
            </div>
            <div class="status-badge badge-progress">
                <i class="fas fa-spinner"></i> <?php echo $in_progress; ?>
            </div>
        </div>

        <div class="status-card">
            <div class="status-info">
                <h4>Pending</h4>
                <div class="status-count"><?php echo $pending; ?></div>
            </div>
            <div class="status-badge badge-pending">
                <i class="fas fa-clock"></i> <?php echo $pending; ?>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="table-card">
        <div class="table-header">
            <h3>
                <i class="fas fa-list-alt"></i>
                Distribution Records
            </h3>
        </div>

        <table id="distributionTable" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th><i class="fas fa-calendar"></i> Date</th>
                    <th><i class="fas fa-user"></i> First Name</th>
                    <th><i class="fas fa-user"></i> Last Name</th>
                    <th><i class="fas fa-map-marker-alt"></i> Location</th>
                    <th><i class="fas fa-leaf"></i> Seedling Type</th>
                    <th><i class="fas fa-hashtag"></i> Number of Seedlings</th>
                    <th><i class="fas fa-tasks"></i> Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $record): 
                    $status = $record['status'] ?? 'Pending';
                    $statusClass = '';
                    if ($status === 'Completed') $statusClass = 'completed';
                    else if ($status === 'In Progress') $statusClass = 'progress';
                    else $statusClass = 'pending';
                ?>
                <tr>
                    <td><?= htmlspecialchars($record['date'] ?? '') ?></td>
                    <td><?= htmlspecialchars($record['firstName'] ?? '') ?></td>
                    <td><?= htmlspecialchars($record['lastName'] ?? '') ?></td>
                    <td><?= htmlspecialchars($record['location'] ?? '') ?></td>
                    <td><?= htmlspecialchars($record['seedlingType'] ?? '') ?></td>
                    <td class="text-center">
                        <span class="badge bg-info text-white">
                            <?= htmlspecialchars($record['numSeedlings'] ?? '0') ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge-table <?php echo $statusClass; ?>">
                            <?php if ($status === 'Completed'): ?>
                                <i class="fas fa-check-circle"></i>
                            <?php elseif ($status === 'In Progress'): ?>
                                <i class="fas fa-spinner"></i>
                            <?php else: ?>
                                <i class="fas fa-clock"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($status) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#distributionTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        language: {
            search: "Search:",
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
        order: [[0, 'desc']], // Sort by date descending
        columnDefs: [
            { targets: [5], className: 'text-center' } // Center align the seedlings count
        ]
    });

    // Add animation delays
    $('.stat-card, .status-card').each(function(index) {
        $(this).css('animation-delay', (index * 0.1) + 's');
    });
});

// Generate CSV Report (preserving original functionality)
function generateReport() {
    const rows = document.querySelectorAll("table tbody tr");
    let csv = "Date,First Name,Last Name,Location,Seedling Type,Number of Seedlings,Status\n";

    rows.forEach(row => {
        const cols = row.querySelectorAll("td");
        let rowData = [];
        cols.forEach(col => {
            // Clean the text content (remove extra spaces, icons, etc.)
            let text = col.innerText.trim();
            // Remove any extra whitespace
            text = text.replace(/\s+/g, ' ');
            rowData.push(text);
        });
        csv += rowData.join(",") + "\n";
    });

    const link = document.createElement("a");
    link.href = "data:text/csv;charset=utf-8," + encodeURIComponent(csv);
    link.download = "seedling_distribution_report_" + new Date().toISOString().slice(0,10) + ".csv";
    link.click();

    // Show success message (optional)
    alert('Report generated successfully!');
}

// Print functionality
window.onbeforeprint = function() {
    // Any pre-print adjustments
};
</script>

</body>
</html>
