<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get events for the current month
$events = [];
$all_events = [];
$checkTable = $mysqli->query("SHOW TABLES LIKE 'events'");
if ($checkTable && $checkTable->num_rows > 0) {
    $stmt = $mysqli->prepare("
        SELECT * FROM events 
        WHERE MONTH(event_date) = ? AND YEAR(event_date) = ?
        ORDER BY event_date ASC, event_time ASC
    ");
    $stmt->bind_param('ii', $current_month, $current_year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $day = date('j', strtotime($row['event_date']));
        if (!isset($events[$day])) {
            $events[$day] = [];
        }
        $events[$day][] = $row;
        $all_events[] = $row;
    }
    $stmt->close();
}

// Fetch student data for sidebar
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calendar calculations
$first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day);
$day_of_week = date('w', $first_day);
$month_name = date('F Y', $first_day);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Calendar - Student Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/partials/sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
        }

        /* Hamburger Menu Button */
        .hamburger-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            box-shadow: 0 4px 15px rgba(91, 31, 31, 0.3);
            transition: all 0.3s ease;
        }

        .hamburger-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(91, 31, 31, 0.4);
        }

        .hamburger-btn span {
            width: 24px;
            height: 3px;
            background: white;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .hamburger-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(7px, 7px);
        }

        .hamburger-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Main Content */
        .main-wrapper {
            margin-left: 240px;
            flex: 1;
            padding: 20px;
            padding-top: 90px;
            width: 100%;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #5b1f1f;
        }

        .top-bar-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f3f4f6;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            color: #5b1f1f;
        }

        .icon-btn:hover {
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            color: #5b1f1f;
        }

        .icon-btn .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #5b1f1f;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-add-event {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-add-event:hover {
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            color: #5b1f1f;
            transform: translateY(-2px);
        }

        .calendar-container {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
        }

        .calendar-main {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .month-year {
            font-size: 24px;
            font-weight: 700;
            color: #5b1f1f;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
        }

        .nav-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f3f4f6;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #5b1f1f;
        }

        .nav-btn:hover {
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            color: #5b1f1f;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 10px;
        }

        .weekday {
            text-align: center;
            font-weight: 600;
            color: #5b1f1f;
            padding: 12px;
            font-size: 14px;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        .calendar-day {
            aspect-ratio: 1;
            border-radius: 12px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            background: #f9fafb;
            display: flex;
            flex-direction: column;
            min-height: 100px;
        }

        .calendar-day:hover {
            background: #f3f4f6;
        }

        .calendar-day.empty {
            background: transparent;
            cursor: default;
        }

        .calendar-day.empty:hover {
            background: transparent;
        }

        .calendar-day.has-event {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            color: white;
        }

        .calendar-day.has-event:hover {
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            color: #5b1f1f;
        }

        .calendar-day.today {
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            color: #5b1f1f;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(236, 195, 92, 0.4);
        }

        .calendar-day.today.has-event {
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            color: #5b1f1f;
        }

        .day-number {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .event-indicators {
            display: flex;
            gap: 4px;
            margin-top: auto;
        }

        .event-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .event-dot.blue {
            background: #ecc35c;
        }

        .event-dot.green {
            background: #d4a843;
        }

        .event-dot.yellow {
            background: #ecc35c;
        }

        .event-dot.purple {
            background: #5b1f1f;
        }

        .event-info {
            font-size: 10px;
            margin-top: 4px;
            opacity: 0.9;
        }

        /* Event List Sidebar */
        .event-list-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .event-list-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .event-list-header {
            font-size: 18px;
            font-weight: 700;
            color: #5b1f1f;
            margin-bottom: 20px;
        }

        .event-item {
            padding: 16px;
            background: #f9fafb;
            border-radius: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border-left: 3px solid #5b1f1f;
        }

        .event-item:hover {
            background: linear-gradient(135deg, rgba(236, 195, 92, 0.1), rgba(212, 168, 67, 0.1));
            transform: translateX(4px);
        }

        .event-date {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .event-name {
            font-weight: 700;
            color: #5b1f1f;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .event-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .event-time {
            font-size: 12px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .event-price {
            font-weight: 700;
            color: #5b1f1f;
        }

        .event-progress {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .event-progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            border-radius: 2px;
        }

        .event-tickets {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }

        .more-options {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            background: transparent;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .more-options:hover {
            background: #e5e7eb;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #1f2937;
            font-size: 20px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .close-btn:hover {
            background: #f3f4f6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f9fafb;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #5b1f1f;
            background: white;
        }

        .btn-submit {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(91, 31, 31, 0.3);
        }

        @media (max-width: 1024px) {
            .calendar-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Hamburger Menu Button -->
    <button class="hamburger-btn" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">Events</div>
            <div class="top-bar-right">
                <div id="searchBar" style="display: none; margin-right: 10px;">
                    <input type="text" id="searchInput" placeholder="Search events..." style="padding: 10px 16px; border: 2px solid #e5e7eb; border-radius: 10px; width: 250px; font-size: 14px;">
                </div>
                <button class="icon-btn" onclick="toggleSearch()" title="Search">
                    <i class="fas fa-search"></i>
                </button>
                <button class="icon-btn" onclick="window.location.href='job_opportunities.php'" title="Job Opportunities">
                    <i class="fas fa-briefcase"></i>
                    <span class="badge">2</span>
                </button>
                <button class="icon-btn" onclick="window.location.href='#messages'" title="Messages">
                    <i class="fas fa-envelope"></i>
                    <span class="badge">3</span>
                </button>
                <button class="icon-btn" onclick="toggleNotifications()" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="badge">1</span>
                </button>
                <button class="btn-add-event" onclick="openAddEventModal()">
                    <i class="fas fa-plus"></i>
                    Add Event
                </button>
            </div>
        </div>

        <!-- Notifications Dropdown -->
        <div id="notificationsDropdown" style="display: none; position: absolute; right: 20px; top: 80px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); width: 320px; z-index: 100; padding: 20px;">
            <h3 style="color: #5b1f1f; margin-bottom: 15px; font-size: 18px;">Notifications</h3>
            <div style="max-height: 300px; overflow-y: auto;">
                <div style="padding: 12px; background: #f9fafb; border-radius: 8px; margin-bottom: 10px; border-left: 3px solid #5b1f1f;">
                    <div style="font-weight: 600; color: #5b1f1f; margin-bottom: 4px;">New Event Added</div>
                    <div style="font-size: 13px; color: #6b7280;">A new placement drive has been scheduled</div>
                    <div style="font-size: 11px; color: #9ca3af; margin-top: 4px;">2 hours ago</div>
                </div>
            </div>
        </div>

        <!-- Calendar Container -->
        <div class="calendar-container">
            <!-- Calendar Main -->
            <div class="calendar-main">
                <div class="calendar-header">
                    <div class="month-year"><?php echo $month_name; ?></div>
                    <div class="calendar-nav">
                        <a href="?month=<?php echo $current_month == 1 ? 12 : $current_month - 1; ?>&year=<?php echo $current_month == 1 ? $current_year - 1 : $current_year; ?>" class="nav-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <a href="?month=<?php echo $current_month == 12 ? 1 : $current_month + 1; ?>&year=<?php echo $current_month == 12 ? $current_year + 1 : $current_year; ?>" class="nav-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>

                <div class="calendar-weekdays">
                    <div class="weekday">Monday</div>
                    <div class="weekday">Tuesday</div>
                    <div class="weekday">Wednesday</div>
                    <div class="weekday">Thursday</div>
                    <div class="weekday">Friday</div>
                    <div class="weekday">Saturday</div>
                    <div class="weekday">Sunday</div>
                </div>

                <div class="calendar-days">
                    <?php
                    // Adjust for Monday start (0 = Sunday, 1 = Monday)
                    $adjusted_day_of_week = ($day_of_week == 0) ? 6 : $day_of_week - 1;
                    
                    // Empty cells before first day
                    for ($i = 0; $i < $adjusted_day_of_week; $i++) {
                        echo '<div class="calendar-day empty"></div>';
                    }

                    // Days of the month
                    $today = date('j');
                    $today_month = date('n');
                    $today_year = date('Y');

                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $is_today = ($day == $today && $current_month == $today_month && $current_year == $today_year);
                        $has_events = isset($events[$day]);
                        
                        $classes = ['calendar-day'];
                        if ($is_today) $classes[] = 'today';
                        if ($has_events) $classes[] = 'has-event';
                        
                        echo "<div class='" . implode(' ', $classes) . "' onclick='showDayEvents($day)'>";
                        echo "<div class='day-number'>$day</div>";
                        
                        if ($has_events) {
                            $event_count = count($events[$day]);
                            $first_event = $events[$day][0];
                            $time = $first_event['event_time'] ? date('g:i A', strtotime($first_event['event_time'])) : '';
                            
                            if ($time) {
                                echo "<div class='event-info'>" . htmlspecialchars(substr($first_event['event_title'], 0, 15)) . "</div>";
                                echo "<div class='event-info'>$time</div>";
                            }
                            
                            echo "<div class='event-indicators'>";
                            $colors = ['blue', 'green', 'yellow', 'purple'];
                            for ($i = 0; $i < min($event_count, 4); $i++) {
                                echo "<div class='event-dot {$colors[$i % 4]}'></div>";
                            }
                            echo "</div>";
                        }
                        
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Event List Sidebar -->
            <div class="event-list-sidebar">
                <div class="event-list-card">
                    <div class="event-list-header">Event List</div>
                    <div style="color: #6b7280; font-size: 14px; margin-bottom: 20px;">
                        Lorem ipsum dolor sit amet
                    </div>
                    
                    <?php if (!empty($all_events)): ?>
                        <?php foreach ($all_events as $event): ?>
                            <?php
                            $date = date('M jth, Y', strtotime($event['event_date']));
                            $time = $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : 'All Day';
                            $time_range = $time . ' - ' . ($event['event_time'] ? date('g:i A', strtotime($event['event_time']) + 3600) : '');
                            ?>
                            <div class="event-item">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                    <div class="event-date"><?php echo $date; ?></div>
                                    <button class="more-options">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </div>
                                <div class="event-name"><?php echo htmlspecialchars($event['event_title']); ?></div>
                                <div class="event-meta">
                                    <div class="event-time">
                                        <i class="far fa-clock"></i>
                                        <?php echo $time_range; ?>
                                    </div>
                                    <div class="event-price">$5.0</div>
                                </div>
                                <div class="event-progress">
                                    <div class="event-progress-bar" style="width: <?php echo rand(30, 90); ?>%;"></div>
                                </div>
                                <div class="event-tickets"><?php echo rand(10, 30); ?> ticket left</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                            <i class="far fa-calendar" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                            <p>No events scheduled for this month</p>
                            <button class="btn-submit" onclick="openAddEventModal()" style="margin-top: 16px; width: auto; padding: 10px 20px;">
                                Add Event
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<!-- Add Event Modal -->
<div class="modal" id="addEventModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Personal Event</h2>
            <button class="close-btn" onclick="closeAddEventModal()">×</button>
        </div>
        <form method="POST" action="add_personal_event.php">
            <div class="form-group">
                <label>Event Title *</label>
                <input type="text" name="event_title" required>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="event_date" required>
            </div>
            <div class="form-group">
                <label>Time</label>
                <input type="time" name="event_time">
            </div>
            <div class="form-group">
                <label>Type *</label>
                <select name="event_type" required>
                    <option value="interview">Interview</option>
                    <option value="deadline">Deadline</option>
                    <option value="workshop">Workshop</option>
                    <option value="placement">Placement Drive</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <button type="submit" class="btn-submit">Add Event</button>
        </form>
    </div>
</div>

<script>
// Store events data for JavaScript access
const eventsData = <?php echo json_encode($events); ?>;

function openAddEventModal() {
    document.getElementById('addEventModal').classList.add('active');
}

function closeAddEventModal() {
    document.getElementById('addEventModal').classList.remove('active');
}

// Toggle search bar
function toggleSearch() {
    const searchBar = document.getElementById('searchBar');
    const searchInput = document.getElementById('searchInput');
    
    if (searchBar.style.display === 'none') {
        searchBar.style.display = 'block';
        searchInput.focus();
    } else {
        searchBar.style.display = 'none';
        searchInput.value = '';
        // Clear any search filtering
        filterEvents('');
    }
}

// Toggle notifications dropdown
function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    
    if (dropdown.style.display === 'none') {
        dropdown.style.display = 'block';
    } else {
        dropdown.style.display = 'none';
    }
}

// Filter events based on search
function filterEvents(searchTerm) {
    const eventItems = document.querySelectorAll('.event-item');
    const lowerSearch = searchTerm.toLowerCase();
    
    eventItems.forEach(item => {
        const eventName = item.querySelector('.event-name');
        if (eventName) {
            const text = eventName.textContent.toLowerCase();
            if (text.includes(lowerSearch) || searchTerm === '') {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        }
    });
}

// Add search input listener
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            filterEvents(e.target.value);
        });
    }
    
    // Close notifications when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notificationsDropdown');
        const bellBtn = e.target.closest('.icon-btn[onclick="toggleNotifications()"]');
        
        if (dropdown && dropdown.style.display === 'block' && !dropdown.contains(e.target) && !bellBtn) {
            dropdown.style.display = 'none';
        }
    });
});

// Show event details when clicking on a day
function showDayEvents(day) {
    if (!eventsData[day] || eventsData[day].length === 0) {
        openAddEventModal();
        return;
    }
    
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.id = 'dayEventsModal';
    
    let eventsHTML = '';
    eventsData[day].forEach(event => {
        const time = event.event_time ? new Date('2000-01-01 ' + event.event_time).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'}) : 'All Day';
        
        eventsHTML += `
            <div style="margin-bottom: 20px; padding: 20px; background: #f9fafb; border-radius: 12px; border-left: 4px solid #5b1f1f;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                    <h3 style="margin: 0; color: #1f2937; font-size: 18px;">${event.event_title}</h3>
                    <span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #dbeafe; color: #1e40af;">${event.event_type}</span>
                </div>
                <div style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">
                    <i class="far fa-clock"></i> <strong>Time:</strong> ${time}
                </div>
                ${event.location ? `<div style="font-size: 14px; color: #6b7280; margin-bottom: 8px;"><i class="fas fa-map-marker-alt"></i> <strong>Location:</strong> ${event.location}</div>` : ''}
                ${event.description ? `<div style="font-size: 14px; color: #6b7280; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb;">${event.description}</div>` : ''}
            </div>
        `;
    });
    
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Events on <?php echo date('F'); ?> ${day}, <?php echo $current_year; ?></h2>
                <button class="close-btn" onclick="closeDayEventsModal()">×</button>
            </div>
            <div style="max-height: 500px; overflow-y: auto;">
                ${eventsHTML}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function closeDayEventsModal() {
    const modal = document.getElementById('dayEventsModal');
    if (modal) {
        modal.remove();
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        if (e.target.id === 'addEventModal') {
            closeAddEventModal();
        } else if (e.target.id === 'dayEventsModal') {
            closeDayEventsModal();
        }
    }
});

// Hamburger Menu Toggle
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function toggleSidebar() {
    hamburgerBtn.classList.toggle('active');
    sidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
}

hamburgerBtn.addEventListener('click', toggleSidebar);
sidebarOverlay.addEventListener('click', toggleSidebar);

const navLinks = sidebar.querySelectorAll('.nav-item, .logout-btn');
navLinks.forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 1024) {
            toggleSidebar();
        }
    });
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
        toggleSidebar();
    }
});
</script>

</body>
</html>
