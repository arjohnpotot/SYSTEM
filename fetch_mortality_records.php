    <?php
    $firebase_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingMortalityReports.json";

    $response = file_get_contents($firebase_url);
    $data = json_decode($response, true);

    if ($data === null) {
        echo "Failed to fetch data!";
        exit;
    }
    ?>

<table>
    <thead>
        <tr>
            <th>Date Recorded</th>
            <th>Seedling Variety</th>
            <th>Number of Seedlings Died</th>
            <th>Location</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data as $key => $record) { ?>
            <tr data-id="<?php echo $key; ?>">
                <td><?php echo htmlspecialchars($record['date']); ?></td>
                <td><?php echo htmlspecialchars($record['seedlingVariety']); ?></td>
                <td><?php echo htmlspecialchars($record['seedlingsDied']); ?></td>
                <td><?php echo htmlspecialchars($record['location']); ?></td>
                <td class="action-buttons">
                </td>
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
