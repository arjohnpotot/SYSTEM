<?php
// Firebase API URL (Replace with your Firebase Realtime Database URL)
$firebase_url = "https://your-firebase-database.firebaseio.com/SeedlingPlantedReports.json";

// Fetch data from Firebase
$response = file_get_contents($firebase_url);
$data = json_decode($response, true);

// Check if data is retrieved
if (!$data) {
    echo "<tr><td colspan='4'>No data found</td></tr>";
    exit;
}

// Display data in table rows
foreach ($data as $key => $report) {
    echo "<tr>
            <td>{$report['date']}</td>
            <td>{$report['seedlingVariety']}</td>
            <td>{$report['numSeedlings']}</td>
            <td>{$report['location']}</td>
          </tr>";
}
?>
