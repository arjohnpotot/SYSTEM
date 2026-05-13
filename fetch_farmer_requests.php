<?php
header("Content-Type: application/json");

// Firebase URL
$firebase_url = "https://validator-b9503-default-rtdb.firebaseio.com/FarmerSeedlingRequests.json";

// Fetch data from Firebase
$response = file_get_contents($firebase_url);
$data = json_decode($response, true);

// Check if data is retrieved successfully
if ($data === null) {
    echo json_encode(["error" => "Failed to fetch data"]);
    exit;
}

$total_seedlings = 0;
$daily_data = []; // Store seedlings received by date

// Loop through the records and aggregate data
foreach ($data as $key => $record) {
    $total_seedlings += (int)$record['seedlingsRequested'];

    // Prepare daily count data
    $date = $record['date'];
    if (!isset($daily_data[$date])) {
        $daily_data[$date] = 0;
    }
    $daily_data[$date] += (int)$record['seedlingsRequested'];
}

// Format data for the graph
$dates = array_keys($daily_data);
$seedlings_count = array_values($daily_data);

// Prepare the response data for chart and total seedlings
$response_data = [
    "total_seedlings" => $total_seedlings,
    "dates" => $dates,
    "seedlings_count" => $seedlings_count
];

// Output the JSON for chart display (you can later use this data for your line chart)
echo json_encode($response_data);
?>
