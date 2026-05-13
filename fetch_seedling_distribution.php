    <?php
    // Firebase URL for Seedling Distribution
    $firebase_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingDistributions.json";

    $response = file_get_contents($firebase_url);
    $data = json_decode($response, true);

    if ($data === null) {
        echo "Failed to fetch data!";
        exit;
    }
    ?>

    <table>
        <thead>
        
        </thead>
        <tbody>
            <?php foreach ($data as $key => $record) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($record['date'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($record['recipient'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($record['seedlingType'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($record['numSeedlings'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($record['location'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($record['status'] ?? 'Pending'); ?></td> <!-- Default to 'Pending' if missing -->
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <script>
        function deleteRecord(recordId) {
            if (!confirm("Are you sure you want to delete this record?")) return;

            fetch(`delete_record.php?id=${recordId}`, { method: "GET" })
                .then(response => response.text())
                .then(result => {
                    alert(result);
                    location.reload();
                })
                .catch(error => alert("Error deleting record: " + error));
        }
    </script>
