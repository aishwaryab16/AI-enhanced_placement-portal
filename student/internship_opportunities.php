<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/internship_backend.php';
require_role('student');

$student_id = $_SESSION['user_id'];
setupInternshipTables($mysqli);

// Fetch student profile details
$student_stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$student_stmt->bind_param('i', $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();
$student_stmt->close();

$student_cgpa = $student['cgpa'] ?? 0;
$student_year = $student['year'] ?? 3;

// Student skills
$skills = [];
$skills_result = $mysqli->query("SELECT skill_name FROM student_skills WHERE student_id = $student_id");
if ($skills_result) {
    while ($row = $skills_result->fetch_assoc()) {
        $skills[] = $row['skill_name'];
    }
}

// Fetch internships and applied data
$internships = getInternshipsWithMatches($mysqli, $student_id, $skills, $student_cgpa, $student_year);
$applied_records = getAppliedInternships($mysqli, $student_id);

$applied_ids = [];
$status_by_id = [];
foreach ($applied_records as $record) {
    $iid = isset($record['internship_id']) ? (int) $record['internship_id'] : 0;
    if ($iid > 0) {
        $applied_ids[] = $iid;
        $status_by_id[$iid] = $record['application_status'] ?? 'Applied';
    }
}
$applied_ids = array_values(array_unique($applied_ids));

$shortlisted_apps = array_values(array_filter($applied_records, function($row) {
    return isset($row['application_status']) && $row['application_status'] === 'Shortlisted';
}));

// Filters
$filter_company = trim($_GET['company'] ?? '');
$filter_role = trim($_GET['role'] ?? '');
$filter_location = trim($_GET['location'] ?? '');
$filter_eligible_only = isset($_GET['eligible_only']);

if ($filter_company || $filter_role || $filter_location || $filter_eligible_only) {
    $internships = array_values(array_filter($internships, function($internship) use ($filter_company, $filter_role, $filter_location, $filter_eligible_only) {
        $company = $internship['company'] ?? '';
        $role = $internship['role'] ?? '';
        $location = $internship['location'] ?? '';
        if ($filter_company && stripos((string) $company, $filter_company) === false) return false;
        if ($filter_role && stripos((string) $role, $filter_role) === false) return false;
        if ($filter_location && stripos((string) $location, $filter_location) === false) return false;
        if ($filter_eligible_only && empty($internship['is_eligible'])) return false;
        return true;
    }));
}

$internship_lookup = [];
foreach ($internships as $internship) {
    if (isset($internship['id'])) {
        $internship_lookup[(int) $internship['id']] = $internship;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Opportunities</title>
    <link rel="stylesheet" href="../includes/partials/sidebar.css">
    <link rel="stylesheet" href="../assets/css/fontawesome-all.min.css">
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
        .main-wrapper {
            margin-left: 240px;
            flex: 1;
            padding: 20px;
        }
        .breadcrumb {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .header-banner {
            background: linear-gradient(135deg, #5b1f1f 0%, #8b3a3a 50%, #ecc35c 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(91, 31, 31, 0.18);
        }
        .header-banner h1 {
            color: white;
            font-size: 34px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .header-banner p {
            color: rgba(255,255,255,0.9);
            font-size: 16px;
        }
        .shortlist-banner {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #92400e;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-input {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1f2937;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #5b1f1f;
            color: white;
        }
        .btn-primary:hover {
            background: #3d1414;
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
        }
        .opportunities-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 20px;
        }
        .opportunities-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .intern-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(91,31,31,0.06);
        }
        .intern-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(91,31,31,0.12);
        }
        .intern-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 18px;
        }
        .company-badge {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: linear-gradient(135deg, #5b1f1f, #ecc35c);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
        }
        .intern-title {
            font-size: 20px;
            font-weight: 700;
            color: #1f2937;
        }
        .company-name {
            color: #6b7280;
            font-size: 14px;
        }
        .match-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
        }
        .match-high { background: #d1fae5; color: #065f46; }
        .match-medium { background: #fef3c7; color: #92400e; }
        .match-low { background: #fee2e2; color: #991b1b; }
        .intern-details {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }
        .intern-detail {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6b7280;
            font-size: 14px;
        }
        .intern-detail i {
            color: #5b1f1f;
        }
        .skill-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }
        .skill-tag {
            padding: 5px 12px;
            background: #f3f4f6;
            border-radius: 6px;
            font-size: 12px;
        }
        .skill-tag.matched {
            background: #d1fae5;
            color: #065f46;
        }
        .intern-description {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 18px;
        }
        .intern-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-apply {
            flex: 1;
            background: #5b1f1f;
            color: white;
            padding: 12px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-apply:hover {
            background: #3d1414;
        }
        .btn-apply:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .btn-save {
            width: 44px;
            height: 44px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #5b1f1f;
        }
        .btn-save.saved {
            background: #5b1f1f;
            color: white;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .status-applied { background: #dbeafe; color: #1e40af; }
        .status-saved { background: #fef3c7; color: #92400e; }
        .status-shortlisted { background: #fef3c7; color: #92400e; }
        .sidebar-widgets {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .widget {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .widget-header {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .application-item {
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .app-company { font-weight: 600; color: #1f2937; margin-bottom: 4px; }
        .app-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            background: #dbeafe;
            color: #1e40af;
        }
        .empty-state {
            text-align: center;
            padding: 28px;
            color: #6b7280;
            background: white;
            border-radius: 12px;
            border: 1px dashed #d1d5db;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
        }
        .modal-close {
            width: 32px;
            height: 32px;
            background: #f3f4f6;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #6b7280;
        }
        .modal-close:hover { background: #e5e7eb; }
        .modal-section { margin-bottom: 20px; }
        .modal-section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .detail-label { font-weight: 600; color: #1f2937; }
        .detail-value { color: #6b7280; }
        @media (max-width: 1200px) {
            .opportunities-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/partials/sidebar.php'; ?>

<div class="main-wrapper">


    <div class="header-banner">
        <h1>🎓 Internship Opportunities</h1>
        <p>Discover internships tailored to your skills, year, and academic profile.</p>
    </div>

    <?php if (!empty($shortlisted_apps)): ?>
        <div class="shortlist-banner">
            <div style="font-weight: 700; margin-bottom: 8px;">⭐ Congratulations! You have shortlisted updates</div>
            <ul style="margin-left: 18px;">
                <?php foreach ($shortlisted_apps as $app): ?>
                    <li>
                        <?php echo htmlspecialchars($app['company'] ?? $app['company_name'] ?? 'Company'); ?> -
                        <?php echo htmlspecialchars($app['internship_role'] ?? $app['role'] ?? $app['internship_title'] ?? 'Role'); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="filters">
        <form method="GET" class="filter-row">
            <input type="text" name="company" placeholder="Company" class="filter-input" value="<?php echo htmlspecialchars($filter_company); ?>">
            <input type="text" name="role" placeholder="Internship Role" class="filter-input" value="<?php echo htmlspecialchars($filter_role); ?>">
            <input type="text" name="location" placeholder="Location" class="filter-input" value="<?php echo htmlspecialchars($filter_location); ?>">
            <label class="filter-checkbox">
                <input type="checkbox" name="eligible_only" <?php echo $filter_eligible_only ? 'checked' : ''; ?>>
                <span>Eligible Only</span>
            </label>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="internship_opportunities.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <?php if (empty($internships)): ?>
        <div class="empty-state">
            <p>No internships match your filters right now. Try adjusting the search or check back soon!</p>
        </div>
    <?php else: ?>
        <div class="opportunities-grid">
            <div class="opportunities-list">
                <?php foreach ($internships as $internship):
                    $iid = (int) ($internship['id'] ?? 0);
                    $is_applied = in_array($iid, $applied_ids, true);
                    $match_score = (int) ($internship['match_score'] ?? 0);
                    $match_class = $match_score >= 80 ? 'match-high' : ($match_score >= 60 ? 'match-medium' : 'match-low');
                    $stipend_min = (float) ($internship['stipend_min'] ?? 0);
                    $stipend_max = (float) ($internship['stipend_max'] ?? 0);
                    $stipend_label = ($stipend_min > 0 || $stipend_max > 0)
                        ? 'Rs ' . number_format($stipend_min, 0) . ' - ' . number_format($stipend_max, 0) . '/month'
                        : 'Stipend not specified';
                    $min_cgpa = (float) ($internship['min_cgpa'] ?? 0);
                    $eligible_years = trim($internship['eligible_years'] ?? '');
                ?>
                <div class="intern-card">
                    <div class="intern-header">
                        <div style="display: flex; gap: 16px; align-items: center;">
                            <div class="company-badge"><?php echo strtoupper(substr($internship['company'], 0, 1)); ?></div>
                            <div>
                                <div class="intern-title">
                                    <?php echo htmlspecialchars($internship['role']); ?>
                                    <?php if ($is_applied): ?>
                                        <span class="status-badge status-applied">Applied ✓</span>
                                    <?php endif; ?>
                                    <?php if (isset($status_by_id[$iid]) && $status_by_id[$iid] === 'Shortlisted'): ?>
                                        <span class="status-badge status-shortlisted">Shortlisted</span>
                                    <?php endif; ?>
                                </div>
                                <div class="company-name">@ <?php echo htmlspecialchars($internship['company']); ?></div>
                            </div>
                        </div>
                        <div class="match-badge <?php echo $match_class; ?>"><?php echo $match_score; ?>% Match</div>
                    </div>

                    <div class="intern-details">
                        <div class="intern-detail"><i class="fas fa-map-marker-alt"></i><?php echo !empty($internship['location']) ? htmlspecialchars($internship['location']) : 'Location not specified'; ?></div>
                        <div class="intern-detail"><i class="fas fa-rupee-sign"></i><?php echo $stipend_label; ?></div>
                        <div class="intern-detail"><i class="fas fa-hourglass"></i><?php echo !empty($internship['duration']) ? htmlspecialchars($internship['duration']) : 'Duration not specified'; ?></div>
                        <div class="intern-detail"><i class="fas fa-calendar-alt"></i><?php echo !empty($internship['deadline']) ? 'Apply by ' . htmlspecialchars($internship['deadline']) : 'Deadline not specified'; ?></div>
                        <div class="intern-detail"><i class="fas fa-graduation-cap"></i>Min CGPA: <?php echo $min_cgpa > 0 ? number_format($min_cgpa, 2) : 'Not specified'; ?></div>
                    </div>

                    <div class="skill-tags">
                        <?php
                        $skill_list = array_filter(array_map('trim', explode(',', $internship['skills_required'] ?? '')));
                        if (!empty($skill_list)) {
                            $matched_lower = array_map('strtolower', $internship['matching_skills'] ?? []);
                            foreach ($skill_list as $skill) {
                                $is_matched = in_array(strtolower($skill), $matched_lower, true);
                                echo '<span class="skill-tag ' . ($is_matched ? 'matched' : '') . '">' . htmlspecialchars($skill) . ($is_matched ? ' ✓' : '') . '</span>';
                            }
                        } else {
                            echo '<span style="color:#6b7280;font-size:14px;">Skills not specified</span>';
                        }
                        ?>
                    </div>

                    <?php if (!empty($internship['description'])): ?>
                        <div class="intern-description">
                            <?php echo htmlspecialchars(mb_strimwidth(strip_tags($internship['description']), 0, 220, '…')); ?>
                        </div>
                    <?php endif; ?>

                    <div class="intern-actions">
                        <button class="btn-apply" style="flex:0.6;background:linear-gradient(135deg,#5b1f1f,#ecc35c);" onclick="viewInternshipDetails(<?php echo $iid; ?>)">
                            <i class="fas fa-info-circle"></i> View Details
                        </button>
                        <button class="btn-apply" data-internship-id="<?php echo $iid; ?>" onclick="openApplyModal(<?php echo $iid; ?>)" <?php echo $is_applied ? 'disabled' : ''; ?>>
                            <?php echo $is_applied ? 'Applied ✓' : 'Apply Now'; ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="sidebar-widgets">
                <div class="widget">
                    <div class="widget-header"><i class="fas fa-tasks"></i> My Internship Applications</div>
                    <?php if (empty($applied_records)): ?>
                        <p style="color:#6b7280;font-size:14px;">No applications yet.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($applied_records, 0, 5) as $app): ?>
                            <div class="application-item">
                                <div class="app-company"><?php echo htmlspecialchars($app['company'] ?? $app['company_name'] ?? 'Company'); ?> - <?php echo htmlspecialchars($app['internship_role'] ?? $app['internship_title'] ?? 'Role'); ?></div>
                                <span class="app-status"><?php echo htmlspecialchars($app['application_status'] ?? 'Applied'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Internship Detail Modal -->
<div id="internshipModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalInternshipTitle">Internship Details</h2>
            <button class="modal-close" onclick="closeInternshipModal()">×</button>
        </div>
        <div id="modalInternshipContent"></div>
    </div>
</div>

<!-- Apply Modal -->
<div class="modal" id="applyModal">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
            <h2 class="modal-title">Confirm Application</h2>
            <button class="modal-close" onclick="closeApplyModal()">×</button>
        </div>
        <div style="padding: 10px 0 0 0;">
            <form id="applyForm">
                <input type="hidden" id="currentInternshipId" name="internship_id">
                <div style="margin-bottom: 20px; color: #374151; font-weight: 600; line-height: 1.6;">
                    <i class="fas fa-file-alt"></i>
                    Your latest generated resume will be attached automatically. Do you want to proceed with this application?
                </div>
                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeApplyModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="applySubmitBtn"><i class="fas fa-paper-plane"></i> Confirm & Apply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const internshipsData = <?php echo json_encode(array_values($internships)); ?>;
    const studentSkills = <?php echo json_encode(array_map('strtolower', $skills)); ?>;

    function viewInternshipDetails(internshipId) {
        const internship = internshipsData.find(int => parseInt(int.id) === parseInt(internshipId));
        if (!internship) return;

        const matchClass = internship.match_score >= 80 ? 'match-high' : (internship.match_score >= 60 ? 'match-medium' : 'match-low');
        const skillList = (internship.skills_required || '').split(',').map(s => s.trim()).filter(Boolean);
        const matchingSkills = (internship.matching_skills || []).map(s => s.toLowerCase());

        let skillsHTML = '';
        if (skillList.length) {
            skillList.forEach(skill => {
                const matched = matchingSkills.includes(skill.toLowerCase());
                skillsHTML += `<span class="skill-tag ${matched ? 'matched' : ''}" style="margin:4px;">${skill}${matched ? ' ✓' : ''}</span>`;
            });
        } else {
            skillsHTML = '<span style="color:#6b7280;">Skills not specified</span>';
        }

        const eligibleHTML = internship.is_eligible
            ? '<span style="color:#10b981;font-weight:600;">✓ You meet the eligibility criteria for this internship.</span>'
            : '<span style="color:#ef4444;font-weight:600;">✗ You may not meet all requirements for this internship.</span>';

        const modalContent = `
            <div class="modal-section">
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
                    <div class="company-badge" style="width:60px;height:60px;font-size:24px;">${(internship.company || 'C')[0].toUpperCase()}</div>
                    <div>
                        <h3 style="font-size:22px;color:#1f2937;margin-bottom:6px;">${internship.role || 'Internship Role'}</h3>
                        <p style="color:#6b7280;">${internship.company || 'Company'}</p>
                    </div>
                    <div class="match-badge ${matchClass}" style="margin-left:auto;">${internship.match_score || 0}% Match</div>
                </div>
            </div>
            <div class="modal-section">
                <div class="modal-section-title"><i class="fas fa-info-circle"></i> Internship Description</div>
                <div style="color:#4b5563;line-height:1.6;">${internship.description || 'Description not provided.'}</div>
            </div>
            <div class="modal-section">
                <div class="modal-section-title"><i class="fas fa-briefcase"></i> Internship Details</div>
                <div class="detail-row"><span class="detail-label">Location</span><span class="detail-value"><i class="fas fa-map-marker-alt"></i> ${internship.location || 'Not specified'}</span></div>
                <div class="detail-row"><span class="detail-label">Stipend Range</span><span class="detail-value"><i class="fas fa-rupee-sign"></i> ${internship.stipend_min > 0 || internship.stipend_max > 0 ? `Rs ${Number(internship.stipend_min).toLocaleString()} - ${Number(internship.stipend_max).toLocaleString()}/month` : 'Not specified'}</span></div>
                <div class="detail-row"><span class="detail-label">Duration</span><span class="detail-value"><i class="fas fa-hourglass"></i> ${internship.duration || 'Not specified'}</span></div>
                <div class="detail-row"><span class="detail-label">Start Date</span><span class="detail-value"><i class="fas fa-calendar-day"></i> ${internship.start_date || 'Not specified'}</span></div>
                <div class="detail-row"><span class="detail-label">Application Deadline</span><span class="detail-value"><i class="fas fa-calendar-alt"></i> ${internship.deadline || 'Not specified'}</span></div>
                <div class="detail-row"><span class="detail-label">Minimum CGPA</span><span class="detail-value">${internship.min_cgpa > 0 ? Number(internship.min_cgpa).toFixed(2) : 'Not specified'}</span></div>
                <div class="detail-row"><span class="detail-label">Eligible Years</span><span class="detail-value">${internship.eligible_years || 'All years'}</span></div>
            </div>
            <div class="modal-section">
                <div class="modal-section-title"><i class="fas fa-code"></i> Required Skills</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">${skillsHTML}</div>
            </div>
            <div class="modal-section">
                <div class="modal-section-title"><i class="fas fa-check-circle"></i> Eligibility & Match</div>
                <div style="color:#4b5563;line-height:1.6;">${eligibleHTML}</div>
                <div style="margin-top:15px;color:#374151;">
                    <strong>Match Breakdown:</strong>
                    <div style="margin-top:10px;">
                        <div style="margin-bottom:6px;">Skills Match: <span style="font-weight:600;color:#5b1f1f;">${internship.skill_match || 0}%</span></div>
                        <div style="margin-bottom:6px;">CGPA Match: <span style="font-weight:600;color:#5b1f1f;">${internship.cgpa_match || 0}%</span></div>
                        <div>Year Match: <span style="font-weight:600;color:#5b1f1f;">${internship.year_match || 0}%</span></div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('modalInternshipTitle').textContent = `${internship.role || 'Internship'} at ${internship.company || 'Company'}`;
        document.getElementById('modalInternshipContent').innerHTML = modalContent;
        document.getElementById('internshipModal').classList.add('active');
    }

    function closeInternshipModal() {
        document.getElementById('internshipModal').classList.remove('active');
    }

    function openApplyModal(id) {
        const modal = document.getElementById('applyModal');
        document.getElementById('currentInternshipId').value = id;
        modal.classList.add('active');
    }

    function closeApplyModal() {
        const modal = document.getElementById('applyModal');
        modal.classList.remove('active');
        document.getElementById('applyForm').reset();
    }

    document.getElementById('applyForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const internshipId = document.getElementById('currentInternshipId').value;

        const params = new URLSearchParams();
        params.append('action', 'apply_internship');
        params.append('internship_id', internshipId);

        const submitBtn = document.getElementById('applySubmitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';

        fetch('apply_internship.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params.toString()
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            if (data.success) {
                alert('Internship application submitted successfully!');
                closeApplyModal();
                window.location.reload();
            } else {
                alert(data.error || 'Failed to submit application.');
            }
        })
        .catch(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            alert('Something went wrong. Please try again.');
        });
    });


    document.getElementById('internshipModal').addEventListener('click', function (e) {
        if (e.target === this) closeInternshipModal();
    });
    document.getElementById('applyModal').addEventListener('click', function (e) {
        if (e.target === this) closeApplyModal();
    });
</script>
</body>
</html>
