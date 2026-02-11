<?php
require_once __DIR__ . '/../includes/config.php';

// Get PDO instance from config
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    die("Database connection not available. Please check config.php");
}

// SQL to create job_applications table
$sql = "CREATE TABLE IF NOT EXISTS `job_applications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `application_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Applied', 'Under Review', 'Shortlisted', 'Rejected', 'Hired') DEFAULT 'Applied',
    `notes` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_id`) REFERENCES `job_opportunities`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_application` (`job_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    $pdo->exec($sql);
    echo "Table 'job_applications' created successfully or already exists.\n";
    
    // Add applications_count column to job_opportunities if it doesn't exist
    $checkColumn = $pdo->query("SHOW COLUMNS FROM `job_opportunities` LIKE 'applications_count'");
    if ($checkColumn->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `job_opportunities` ADD COLUMN `applications_count` INT DEFAULT 0");
        echo "Added 'applications_count' column to job_opportunities table.\n";
    }
    
    echo "Job applications setup completed.\n";
    
} catch (PDOException $e) {
    die("Error creating job_applications table: " . $e->getMessage() . "\n");
}
?>
