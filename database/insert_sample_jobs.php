<?php
require_once __DIR__ . '/../includes/config.php';

// Get PDO instance from config
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    die("Database connection not available. Please check config.php");
}

// Sample job data with correct column names
$jobs = [
    [
        'job_title' => 'Software Engineer',
        'company_name' => 'Tech Solutions Inc.',
        'location' => 'Bangalore, India',
        'job_description' => 'We are looking for a skilled software engineer to join our team.',
        'requirements' => 'B.Tech in Computer Science, 2+ years of experience',
        'job_type' => 'Full-time',
        'salary' => '8-12 LPA',
        'application_deadline' => '2023-12-31',
        'posted_by' => 1, // Assuming user with ID 1 is the admin
        'status' => 'Active',
        'created_at' => date('Y-m-d H:i:s')
    ],
    [
        'job_title' => 'Frontend Developer',
        'company_name' => 'WebCraft Studios',
        'location' => 'Hyderabad, India',
        'job_description' => 'Join our team as a Frontend Developer and build amazing user experiences.',
        'requirements' => 'Experience with React, JavaScript, and CSS',
        'job_type' => 'Full-time',
        'salary' => '6-10 LPA',
        'application_deadline' => '2023-12-15',
        'posted_by' => 1,
        'status' => 'Active',
        'created_at' => date('Y-m-d H:i:s')
    ],
    [
        'job_title' => 'Data Scientist',
        'company_name' => 'Data Insights Ltd',
        'location' => 'Pune, India',
        'job_description' => 'Looking for a Data Scientist to analyze complex data and provide insights.',
        'requirements' => 'Masters in Data Science, Python, Machine Learning',
        'job_type' => 'Full-time',
        'salary' => '10-15 LPA',
        'application_deadline' => '2024-01-15',
        'posted_by' => 1,
        'status' => 'Active',
        'created_at' => date('Y-m-d H:i:s')
    ]
];

try {
    // Prepare the insert statement with correct column names
    $stmt = $pdo->prepare("INSERT INTO job_opportunities 
        (job_title, company_name, location, job_description, requirements, job_type, salary, application_deadline, posted_by, status, created_at) 
        VALUES 
        (:job_title, :company_name, :location, :job_description, :requirements, :job_type, :salary, :application_deadline, :posted_by, :status, :created_at)");
    
    $inserted = 0;
    
    // Insert each job
    foreach ($jobs as $job) {
        try {
            $stmt->execute($job);
            $inserted++;
            echo "Added job: " . $job['title'] . " at " . $job['company'] . "<br>";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo "Skipped duplicate job: " . $job['title'] . " at " . $job['company'] . "<br>";
            } else {
                echo "Error inserting job " . $job['title'] . ": " . $e->getMessage() . "<br>";
            }
        }
    }
    
    echo "<br>Successfully inserted $inserted jobs.";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
