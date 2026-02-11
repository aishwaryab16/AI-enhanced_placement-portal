<?php
require_once __DIR__ . '/../includes/config.php';

// Get database connection
$mysqli = $GLOBALS['mysqli'] ?? null;

if (!$mysqli) {
    die("Database connection not available. Please check config.php");
}

// Get column information for job_opportunities table
$result = $mysqli->query("SHOW COLUMNS FROM job_opportunities");

if (!$result) {
    die("Error getting column information: " . $mysqli->error);
}

echo "<h2>Columns in job_opportunities table:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
    echo "</tr>";
}

echo "</table>";

// Show sample data if any exists
$sample_data = $mysqli->query("SELECT * FROM job_opportunities LIMIT 1");
if ($sample_data && $sample_data->num_rows > 0) {
    echo "<h2>Sample Data:</h2>";
    $row = $sample_data->fetch_assoc();
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "<p>No data found in job_opportunities table.</p>";
}
?>
