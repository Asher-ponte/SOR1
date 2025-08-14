<?php
// Database migration script to add closed_date column to observations table
// Run this script once to update your database schema

include 'db_config.php';

echo "Starting database migration...\n";

try {
    // Add closed_date column
    $sql1 = "ALTER TABLE observations ADD COLUMN closed_date DATETIME NULL AFTER status";
    if ($conn->query($sql1)) {
        echo "✓ Added closed_date column to observations table\n";
    } else {
        echo "✗ Error adding closed_date column: " . $conn->error . "\n";
    }

    // Add index for better performance
    $sql2 = "CREATE INDEX idx_observations_closed_date ON observations(closed_date)";
    if ($conn->query($sql2)) {
        echo "✓ Added index for closed_date column\n";
    } else {
        echo "✗ Error adding index (this might already exist): " . $conn->error . "\n";
    }

    // Update existing closed observations to have a closed_date
    $sql3 = "UPDATE observations SET closed_date = timestamp WHERE status = 'Closed' AND closed_date IS NULL";
    $result = $conn->query($sql3);
    if ($result) {
        $affected_rows = $conn->affected_rows;
        echo "✓ Updated $affected_rows existing closed observations with closed_date\n";
    } else {
        echo "✗ Error updating existing closed observations: " . $conn->error . "\n";
    }

    echo "\nMigration completed successfully!\n";
    echo "The 'Days Open' column will now be available in the SOR report.\n";

} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
}

$conn->close();
?> 