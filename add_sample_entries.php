<?php
set_time_limit(300);

$firebase_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingPlantedReports.json";

$firstNames = ["Juan", "Pedro", "Maria", "Ana", "Jose", "Luis", "Rico", "Mark", "Leo", "Paul"];
$lastNames  = ["Potot", "Namuag", "Nalagon", "Reyes", "Torres", "Flores", "basco", "trinidad"];

$data = [];

for ($i = 1; $i <= 50; $i++) {

    $key = "owner_" . time() . "_" . $i;

    $data[$key] = [
        "firstName"     => $firstNames[array_rand($firstNames)],
        "lastName"      => $lastNames[array_rand($lastNames)],
        "municipality"  => "DigosCity",
        "barangay"      => "Aplaya",
        "numSeedlings"  => rand(100, 300),
        "variety"       => ["Coffee","Cacao","Narra","Mahogany"][array_rand(["Coffee","Cacao","Narra","Mahogany"])],
        "datePlanted"   => date("Y-m-d")
    ];
}

$jsonData = json_encode($data);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $firebase_url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH"); // IMPORTANT
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// SSL fix
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);

if(curl_errno($ch)) {
    echo "Error: " . curl_error($ch);
} else {
    echo "50 owners added successfully!";
}

curl_close($ch);
?>