<?php
require_once __DIR__ . '/../includes/config.php';

// Get PDO instance from config
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    die("Database connection not available. Please check config.php");
}

// SQL to create job_opportunities table
$sql = "CREATE TABLE IF NOT EXISTS `job_opportunities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `company` VARCHAR(255) NOT NULL,
    `location` VARCHAR(255),
    `description` TEXT,
    `requirements` TEXT,
    `job_type` VARCHAR(50) DEFAULT 'Full-time',
    `salary` VARCHAR(100),
    `application_deadline` DATE,
    `posted_by` INT NOT NULL,
    `posted_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    $pdo->exec($sql);
    echo "Table 'job_opportunities' created successfully or already exists.\n";
    
    // Add foreign key separately if needed
    try {
        $fkSql = "ALTER TABLE `job_opportunities` 
                 ADD CONSTRAINT `fk_job_user` 
                 FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) 
                 ON DELETE CASCADE";
        $pdo->exec($fkSql);
        echo "Foreign key constraint added successfully.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'errno: 150') === false) {
            // Only show error if it's not a duplicate foreign key error
            echo "Note: Could not add foreign key constraint - " . $e->getMessage() . "\n";
        }
    }
    
} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage() . "\n");
}

// List of columns to check/add
$columns = [
    ['name' => 'title', 'type' => 'VARCHAR(255) NOT NULL', 'after' => 'id'],
    ['name' => 'company', 'type' => 'VARCHAR(255) NOT NULL', 'after' => 'title'],
    ['name' => 'location', 'type' => 'VARCHAR(255)', 'after' => 'company'],
    ['name' => 'description', 'type' => 'TEXT', 'after' => 'location'],
    ['name' => 'requirements', 'type' => 'TEXT', 'after' => 'description'],
    ['name' => 'job_type', 'type' => 'VARCHAR(50) DEFAULT "Full-time"', 'after' => 'requirements'],
    ['name' => 'salary', 'type' => 'VARCHAR(100)', 'after' => 'job_type'],
    ['name' => 'application_deadline', 'type' => 'DATE', 'after' => 'salary'],
    ['name' => 'posted_by', 'type' => 'INT NOT NULL', 'after' => 'application_deadline'],
    ['name' => 'posted_date', 'type' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'after' => 'posted_by']
];

// Check and add missing columns
foreach ($columns as $column) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM `job_opportunities` LIKE '{$column['name']}'")->fetch();
        
        if (!$check) {
            $sql = "ALTER TABLE `job_opportunities` 
                   ADD COLUMN `{$column['name']}` {$column['type']} ".($column['after'] ? "AFTER `{$column['after']}`" : "");
            $pdo->exec($sql);
            echo "Added column '{$column['name']}' to job_opportunities table.\n";
        }
    } catch (PDOException $e) {
        echo "Error adding column '{$column['name']}': " . $e->getMessage() . "\n";
    }
}

echo "Database setup completed.\n";
