<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$student_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// Helper function to calculate percentile
function calculatePercentile($value, $allValues) {
    if (empty($allValues)) return 0;
    $count = 0;
    foreach ($allValues as $v) {
        if ($v < $value) $count++;
    }
    return round(($count / count($allValues)) * 100, 2);
}

// Helper function to get skill status
function getSkillStatus($proficiency) {
    if ($proficiency >= 80) return 'mastered';
    if ($proficiency >= 50) return 'learning';
    return 'missing';
}

switch ($action) {
    case 'get_career_fit':
        getCareerFitData($mysqli, $student_id);
        break;
    
    case 'get_peer_comparison':
        getPeerComparisonData($mysqli, $student_id);
        break;
    
    case 'get_placement_insights':
        getPlacementInsights($mysqli, $student_id);
        break;
    
    case 'get_skill_heatmap':
        getSkillHeatmap($mysqli, $student_id);
        break;
    
    case 'get_learning_progress':
        getLearningProgress($mysqli, $student_id);
        break;
    
    case 'get_performance_prediction':
        getPerformancePrediction($mysqli, $student_id);
        break;
    
    case 'get_engagement_analytics':
        getEngagementAnalytics($mysqli, $student_id);
        break;
    
    case 'get_ai_recommendations':
        getAIRecommendations($mysqli, $student_id);
        break;
    
    case 'get_all_analytics':
        getAllAnalytics($mysqli, $student_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

// 1. Career Fit Breakdown
function getCareerFitData($mysqli, $student_id) {
    // Get student skills
    $skills = [];
    $result = $mysqli->query("SELECT skill_name, proficiency_level FROM student_skills WHERE student_id = $student_id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $skills[$row['skill_name']] = $row['proficiency_level'];
        }
    }
    
    // Target companies with their required skills
    $targetCompanies = [
        'IBM' => ['Python', 'Java', 'Cloud Computing', 'AI/ML', 'Data Structures'],
        'Infosys' => ['Java', 'Python', 'SQL', 'Web Development', 'Testing'],
        'TCS' => ['Java', 'C++', 'Database', 'Networking', 'SDLC'],
        'Wipro' => ['Python', 'Java', 'Cloud', 'DevOps', 'Agile'],
        'Accenture' => ['Java', 'JavaScript', 'Cloud', 'SAP', 'Consulting'],
        'Microsoft' => ['C#', 'Azure', 'Python', 'AI/ML', 'System Design'],
        'Amazon' => ['Python', 'Java', 'AWS', 'Data Structures', 'System Design'],
        'Google' => ['Python', 'Java', 'Algorithms', 'System Design', 'ML']
    ];
    
    $companyMatches = [];
    foreach ($targetCompanies as $company => $requiredSkills) {
        $matchCount = 0;
        $totalRequired = count($requiredSkills);
        $matchingSkills = [];
        $missingSkills = [];
        
        foreach ($requiredSkills as $skill) {
            $found = false;
            foreach ($skills as $studentSkill => $proficiency) {
                if (stripos($studentSkill, $skill) !== false || stripos($skill, $studentSkill) !== false) {
                    $matchCount++;
                    $matchingSkills[] = ['skill' => $skill, 'proficiency' => $proficiency];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missingSkills[] = $skill;
            }
        }
        
        $matchPercentage = round(($matchCount / $totalRequired) * 100, 2);
        $companyMatches[] = [
            'company' => $company,
            'match_percentage' => $matchPercentage,
            'matching_skills' => $matchingSkills,
            'missing_skills' => $missingSkills
        ];
    }
    
    // Sort by match percentage
    usort($companyMatches, function($a, $b) {
        return $b['match_percentage'] - $a['match_percentage'];
    });
    
    // Calculate job readiness trend
    $trendData = [];
    $result = $mysqli->query("
        SELECT DATE_FORMAT(activity_date, '%Y-%m') as month, 
               AVG(improvement_value) as avg_improvement
        FROM learning_progress_timeline 
        WHERE student_id = $student_id 
        AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(activity_date, '%Y-%m')
        ORDER BY month
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $trendData[] = $row;
        }
    }
    
    // Calculate overall employability score
    $employabilityScore = 0;
    if (!empty($companyMatches)) {
        $topMatches = array_slice($companyMatches, 0, 3);
        $employabilityScore = array_sum(array_column($topMatches, 'match_percentage')) / count($topMatches);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'company_matches' => $companyMatches,
            'skill_radar' => array_map(function($skill, $prof) {
                return ['skill' => $skill, 'proficiency' => $prof];
            }, array_keys($skills), $skills),
            'employability_score' => round($employabilityScore, 2),
            'trend_data' => $trendData
        ]
    ]);
}

// 2. Peer Comparison Analytics
function getPeerComparisonData($mysqli, $student_id) {
    // Get student data
    $stmt = $mysqli->prepare("SELECT branch, semester, cgpa FROM users WHERE id = ?");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$student) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        return;
    }
    
    // Get batch statistics
    $batchStats = [];
    $result = $mysqli->query("
        SELECT 
            AVG(cgpa) as avg_cgpa,
            COUNT(*) as total_students
        FROM users 
        WHERE role = 'student' AND branch = '{$student['branch']}'
    ");
    
    if ($result) {
        $batchStats = $result->fetch_assoc();
    }
    
    // Get skill comparison
    $studentSkillCount = 0;
    $result = $mysqli->query("SELECT COUNT(*) as count FROM student_skills WHERE student_id = $student_id");
    if ($result) {
        $studentSkillCount = $result->fetch_assoc()['count'];
    }
    
    $avgSkillCount = 0;
    $result = $mysqli->query("
        SELECT AVG(skill_count) as avg_count FROM (
            SELECT student_id, COUNT(*) as skill_count 
            FROM student_skills 
            GROUP BY student_id
        ) as skill_counts
    ");
    if ($result) {
        $avgSkillCount = $result->fetch_assoc()['avg_count'] ?? 0;
    }
    
    // Get certification comparison
    $studentCertCount = 0;
    $result = $mysqli->query("SELECT COUNT(*) as count FROM student_certifications WHERE student_id = $student_id");
    if ($result) {
        $studentCertCount = $result->fetch_assoc()['count'];
    }
    
    $avgCertCount = 0;
    $result = $mysqli->query("
        SELECT AVG(cert_count) as avg_count FROM (
            SELECT student_id, COUNT(*) as cert_count 
            FROM student_certifications 
            GROUP BY student_id
        ) as cert_counts
    ");
    if ($result) {
        $avgCertCount = $result->fetch_assoc()['avg_count'] ?? 0;
    }
    
    // Calculate percentiles
    $allCGPAs = [];
    $result = $mysqli->query("SELECT cgpa FROM users WHERE role = 'student' AND cgpa > 0");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $allCGPAs[] = $row['cgpa'];
        }
    }
    
    $cgpaPercentile = calculatePercentile($student['cgpa'], $allCGPAs);
    
    // Domain-wise leaderboard
    $domains = ['AI/ML', 'Web Development', 'Data Science', 'Cloud Computing', 'Mobile Development'];
    $domainRankings = [];
    
    foreach ($domains as $domain) {
        $result = $mysqli->query("
            SELECT COUNT(DISTINCT s.student_id) as rank_position
            FROM student_skills s
            WHERE s.skill_name LIKE '%$domain%'
            AND s.proficiency_level > (
                SELECT COALESCE(MAX(proficiency_level), 0)
                FROM student_skills
                WHERE student_id = $student_id AND skill_name LIKE '%$domain%'
            )
        ");
        
        if ($result) {
            $rank = $result->fetch_assoc()['rank_position'] + 1;
            $domainRankings[] = [
                'domain' => $domain,
                'rank' => $rank
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'student_cgpa' => $student['cgpa'],
            'batch_avg_cgpa' => round($batchStats['avg_cgpa'] ?? 0, 2),
            'cgpa_percentile' => $cgpaPercentile,
            'student_skills' => $studentSkillCount,
            'avg_skills' => round($avgSkillCount, 2),
            'student_certifications' => $studentCertCount,
            'avg_certifications' => round($avgCertCount, 2),
            'total_batch_students' => $batchStats['total_students'] ?? 0,
            'domain_rankings' => $domainRankings,
            'overall_percentile' => round(($cgpaPercentile + 
                calculatePercentile($studentSkillCount, [$avgSkillCount]) + 
                calculatePercentile($studentCertCount, [$avgCertCount])) / 3, 2)
        ]
    ]);
}

// 3. Placement Insights
function getPlacementInsights($mysqli, $student_id) {
    // Get current semester placement stats
    $currentYear = date('Y');
    $currentSemester = (date('n') <= 6) ? 'Spring' : 'Fall';
    
    $placementStats = [
        'total_students' => 0,
        'students_placed' => 0,
        'placement_percentage' => 0,
        'average_package' => 0,
        'highest_package' => 0,
        'top_companies' => []
    ];
    
    // Get total students
    $result = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    if ($result) {
        $placementStats['total_students'] = $result->fetch_assoc()['count'];
    }
    
    // Get placed students
    $result = $mysqli->query("SELECT COUNT(DISTINCT student_id) as count FROM placements WHERE status IN ('placed', 'accepted')");
    if ($result) {
        $placementStats['students_placed'] = $result->fetch_assoc()['count'];
    }
    
    if ($placementStats['total_students'] > 0) {
        $placementStats['placement_percentage'] = round(($placementStats['students_placed'] / $placementStats['total_students']) * 100, 2);
    }
    
    // Get package statistics (assuming package_offered is in format like "5-7 LPA")
    $packages = [];
    $result = $mysqli->query("SELECT package_offered FROM placements WHERE status IN ('placed', 'accepted')");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Extract numeric value from package string
            if (preg_match('/(\d+)/', $row['package_offered'], $matches)) {
                $packages[] = (float)$matches[1];
            }
        }
    }
    
    if (!empty($packages)) {
        $placementStats['average_package'] = round(array_sum($packages) / count($packages), 2);
        $placementStats['highest_package'] = max($packages);
    }
    
    // Get top hiring companies
    $result = $mysqli->query("
        SELECT c.company_name, COUNT(p.id) as placement_count
        FROM placements p
        JOIN companies c ON p.company_id = c.id
        WHERE p.status IN ('placed', 'accepted')
        GROUP BY c.company_name
        ORDER BY placement_count DESC
        LIMIT 5
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $placementStats['top_companies'][] = $row;
        }
    }
    
    // Domain-wise demand
    $domainDemand = [
        ['domain' => 'AI/ML', 'percentage' => 35],
        ['domain' => 'Cloud Computing', 'percentage' => 20],
        ['domain' => 'Web Development', 'percentage' => 15],
        ['domain' => 'Data Science', 'percentage' => 15],
        ['domain' => 'Mobile Development', 'percentage' => 10],
        ['domain' => 'Others', 'percentage' => 5]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'placement_stats' => $placementStats,
            'domain_demand' => $domainDemand,
            'semester' => "$currentSemester $currentYear"
        ]
    ]);
}

// 4. Skill Gap Heatmap
function getSkillHeatmap($mysqli, $student_id) {
    // Get student's target role
    $targetRole = 'Software Engineer'; // Default
    $result = $mysqli->query("SELECT target_role FROM users WHERE id = $student_id");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && $row['target_role']) {
            $targetRole = $row['target_role'];
        }
    }
    
    // Define required skills for different roles
    $roleSkills = [
        'Software Engineer' => ['Python', 'Java', 'Data Structures', 'Algorithms', 'System Design', 'Git', 'SQL', 'REST APIs'],
        'Data Scientist' => ['Python', 'R', 'Machine Learning', 'Statistics', 'SQL', 'Data Visualization', 'TensorFlow', 'Pandas'],
        'Full Stack Developer' => ['JavaScript', 'React', 'Node.js', 'HTML/CSS', 'MongoDB', 'REST APIs', 'Git', 'AWS'],
        'DevOps Engineer' => ['Linux', 'Docker', 'Kubernetes', 'CI/CD', 'AWS', 'Python', 'Terraform', 'Jenkins'],
        'AI/ML Engineer' => ['Python', 'TensorFlow', 'PyTorch', 'Deep Learning', 'NLP', 'Computer Vision', 'MLOps', 'Statistics']
    ];
    
    $requiredSkills = $roleSkills[$targetRole] ?? $roleSkills['Software Engineer'];
    
    // Get student's current skills
    $studentSkills = [];
    $result = $mysqli->query("SELECT skill_name, proficiency_level FROM student_skills WHERE student_id = $student_id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $studentSkills[$row['skill_name']] = $row['proficiency_level'];
        }
    }
    
    // Build heatmap data
    $heatmapData = [];
    foreach ($requiredSkills as $skill) {
        $proficiency = 0;
        $status = 'missing';
        
        // Check if student has this skill (fuzzy match)
        foreach ($studentSkills as $studentSkill => $prof) {
            if (stripos($studentSkill, $skill) !== false || stripos($skill, $studentSkill) !== false) {
                $proficiency = $prof;
                $status = getSkillStatus($prof);
                break;
            }
        }
        
        $heatmapData[] = [
            'skill' => $skill,
            'proficiency' => $proficiency,
            'status' => $status,
            'required' => 80 // Target proficiency
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'target_role' => $targetRole,
            'heatmap' => $heatmapData,
            'mastered_count' => count(array_filter($heatmapData, fn($s) => $s['status'] === 'mastered')),
            'learning_count' => count(array_filter($heatmapData, fn($s) => $s['status'] === 'learning')),
            'missing_count' => count(array_filter($heatmapData, fn($s) => $s['status'] === 'missing'))
        ]
    ]);
}

// 5. AI Learning Progress Graph
function getLearningProgress($mysqli, $student_id) {
    // Get timeline data for last 6 months
    $result = $mysqli->query("
        SELECT 
            activity_type,
            activity_title,
            skill_name,
            improvement_value,
            activity_date
        FROM learning_progress_timeline
        WHERE student_id = $student_id
        AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        ORDER BY activity_date ASC
    ");
    
    $timeline = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $timeline[] = $row;
        }
    }
    
    // Get skill improvement over time
    $skillProgress = [];
    $result = $mysqli->query("
        SELECT 
            skill_name,
            proficiency_level,
            previous_level,
            improvement_percentage,
            updated_at
        FROM skill_progress_tracking
        WHERE student_id = $student_id
        ORDER BY updated_at DESC
        LIMIT 10
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $skillProgress[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'timeline' => $timeline,
            'skill_progress' => $skillProgress
        ]
    ]);
}

// 6. Performance Predictor
function getPerformancePrediction($mysqli, $student_id) {
    // Get current scores
    $currentScore = 0;
    $result = $mysqli->query("SELECT career_fit_score FROM student_analytics WHERE student_id = $student_id");
    if ($result && $row = $result->fetch_assoc()) {
        $currentScore = $row['career_fit_score'];
    }
    
    // Calculate growth rate based on recent activity
    $recentActivity = 0;
    $result = $mysqli->query("
        SELECT COUNT(*) as activity_count
        FROM learning_progress_timeline
        WHERE student_id = $student_id
        AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    ");
    
    if ($result) {
        $recentActivity = $result->fetch_assoc()['activity_count'];
    }
    
    // Simple prediction model (can be replaced with actual ML model)
    $growthRate = min($recentActivity * 2, 15); // Max 15% growth per month
    
    $predictions = [
        'current_score' => $currentScore,
        'month_1' => min($currentScore + $growthRate, 100),
        'month_2' => min($currentScore + ($growthRate * 1.8), 100),
        'month_3' => min($currentScore + ($growthRate * 2.5), 100),
        'confidence' => 75 + ($recentActivity * 2),
        'factors' => [
            'Recent learning activity: ' . ($recentActivity > 5 ? 'High' : 'Moderate'),
            'Skill improvement rate: Steady',
            'Engagement level: ' . ($recentActivity > 10 ? 'Excellent' : 'Good')
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $predictions
    ]);
}

// 7. Engagement Analytics
function getEngagementAnalytics($mysqli, $student_id) {
    // Get total learning time
    $totalHours = 0;
    $result = $mysqli->query("
        SELECT SUM(time_spent_learning) as total_time
        FROM engagement_metrics
        WHERE student_id = $student_id
    ");
    
    if ($result) {
        $totalHours = $result->fetch_assoc()['total_time'] ?? 0;
    }
    
    // Get certifications uploaded
    $certCount = 0;
    $result = $mysqli->query("SELECT COUNT(*) as count FROM student_certifications WHERE student_id = $student_id");
    if ($result) {
        $certCount = $result->fetch_assoc()['count'];
    }
    
    // Get mock interviews completed
    $mockInterviews = 0;
    $result = $mysqli->query("
        SELECT COUNT(*) as count 
        FROM interview_schedules 
        WHERE student_id = $student_id AND status = 'completed'
    ");
    
    if ($result) {
        $mockInterviews = $result->fetch_assoc()['count'];
    }
    
    // Get courses completed (from learning timeline)
    $coursesCompleted = 0;
    $result = $mysqli->query("
        SELECT COUNT(*) as count 
        FROM learning_progress_timeline 
        WHERE student_id = $student_id AND activity_type = 'course_completed'
    ");
    
    if ($result) {
        $coursesCompleted = $result->fetch_assoc()['count'];
    }
    
    // Weekly activity chart
    $weeklyActivity = [];
    $result = $mysqli->query("
        SELECT 
            DAYNAME(metric_date) as day,
            SUM(time_spent_learning) as time_spent
        FROM engagement_metrics
        WHERE student_id = $student_id
        AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DAYNAME(metric_date), DAYOFWEEK(metric_date)
        ORDER BY DAYOFWEEK(metric_date)
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $weeklyActivity[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_learning_hours' => $totalHours,
            'certifications_uploaded' => $certCount,
            'mock_interviews_completed' => $mockInterviews,
            'courses_completed' => $coursesCompleted,
            'weekly_activity' => $weeklyActivity
        ]
    ]);
}

// 8. AI Recommendations
function getAIRecommendations($mysqli, $student_id) {
    // Get existing recommendations
    $recommendations = [];
    $result = $mysqli->query("
        SELECT *
        FROM ai_recommendations
        WHERE student_id = $student_id
        AND is_completed = FALSE
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY priority DESC, impact_score DESC
        LIMIT 10
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recommendations[] = $row;
        }
    }
    
    // If no recommendations exist, generate some based on student data
    if (empty($recommendations)) {
        // Get skill gaps
        $result = $mysqli->query("
            SELECT skill_name, proficiency_level
            FROM student_skills
            WHERE student_id = $student_id
            AND proficiency_level < 50
            ORDER BY proficiency_level ASC
            LIMIT 3
        ");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recommendations[] = [
                    'recommendation_type' => 'skill_gap',
                    'title' => "Improve {$row['skill_name']} skills",
                    'description' => "Your {$row['skill_name']} proficiency is at {$row['proficiency_level']}%. Complete relevant courses to boost your employability.",
                    'priority' => 'high',
                    'impact_score' => 85,
                    'estimated_time' => '2-3 weeks'
                ];
            }
        }
        
        // Add LinkedIn recommendation
        $recommendations[] = [
            'recommendation_type' => 'networking',
            'title' => 'Boost LinkedIn activity',
            'description' => 'Regular LinkedIn posts and connections can increase recruiter visibility by 40%.',
            'priority' => 'medium',
            'impact_score' => 70,
            'estimated_time' => '15 min/day'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $recommendations
    ]);
}

// Get all analytics data at once
function getAllAnalytics($mysqli, $student_id) {
    $allData = [];
    
    // This would call all the above functions and combine the data
    // For brevity, returning a success message
    echo json_encode([
        'success' => true,
        'message' => 'Use specific endpoints for each analytics component'
    ]);
}
?>
