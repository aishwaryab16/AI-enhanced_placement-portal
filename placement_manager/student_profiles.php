<?php
require_once __DIR__ . '/../includes/config.php';

// Check if user is placement officer
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['placement_officer', 'admin'], true)) {
    header('Location: ../login.php');
    exit;
}

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Fetch all students from the users table
$students = [];
$result = $mysqli->query("SELECT id, full_name, username, email, created_at FROM users WHERE role = 'student' ORDER BY full_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Dummy data for top students and active students - replace with actual logic
$topStudents = array_slice($students, 0, 5); // Top 5 students
$activeStudents = $students; // All students are active for now

?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
/* General Styling */
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #f5f7fa;
    color: #333;
}

.dashboard-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
    background: #fff;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.page-header {
    background: linear-gradient(135deg, #800000, #990000); /* Maroon gradient */
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    text-align: center;
}

.page-header h1 {
    margin: 0;
    font-size: 32px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #f9f9f9;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    text-align: center;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.stat-card h3 {
    color: #800000; /* Maroon */
    font-size: 24px;
    margin-bottom: 10px;
}

.stat-card p {
    color: #666;
    font-size: 16px;
}

.student-lists {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.list-card {
    background: #f9f9f9;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.list-card h2 {
    color: #800000; /* Maroon */
    font-size: 24px;
    margin-bottom: 20px;
    border-bottom: 2px solid #F5F5DC; /* Light Cream border */
    padding-bottom: 10px;
}

.student-table {
    width: 100%;
    border-collapse: collapse;
}

.student-table th,
.student-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.student-table th {
    background: #800000; /* Maroon */
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 14px;
}

.student-table tr:nth-child(even) {
    background: #f2f2f2;
}

.student-table tr:hover {
    background: #F5F5DC; /* Light Cream hover */
    cursor: pointer;
}

.profile-link {
    color: #800000; /* Maroon */
    text-decoration: none;
    font-weight: 600;
}

.profile-link:hover {
    text-decoration: underline;
}
</style>

<div class="dashboard-container">
    <div class="page-header">
        <h1>Student Activity Dashboard</h1>
        <p>Overview of student profiles, activities, and engagement.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Students</h3>
            <p><?php echo count($students); ?></p>
        </div>
        <div class="stat-card">
            <h3>Top Performing</h3>
            <p><?php echo count($topStudents); ?> Students</p>
        </div>
        <div class="stat-card">
            <h3>Most Active</h3>
            <p><?php echo count($activeStudents); ?> Students</p>
        </div>
    </div>

    <div class="student-lists">
        <div class="list-card">
            <h2>Top Students List</h2>
            <?php if (!empty($topStudents)): ?>
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>View Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topStudents as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name'] ?? $student['username']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><a href="student_profile_detail.php?id=<?php echo $student['id']; ?>" class="profile-link">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 20px;">No top students to display.</p>
            <?php endif; ?>
        </div>

        <div class="list-card">
            <h2>Active Students List</h2>
            <?php if (!empty($activeStudents)): ?>
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>View Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeStudents as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name'] ?? $student['username']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><a href="student_profile_detail.php?id=<?php echo $student['id']; ?>" class="profile-link">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 20px;">No active students to display.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Additional Features Section (Placeholder) -->
    <div class="list-card">
        <h2>Additional Student Insights</h2>
        <p style="color: #666; padding: 20px;">This section can be expanded to include features like skill gap analysis, learning path recommendations, engagement metrics, and more specific leaderboards.</p>
    </div>

</div>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
