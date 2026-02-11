<?php
require_once __DIR__ . '/config.php';

// If already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    if ($_SESSION['role'] === 'admin') {
        redirect_to('admin/dashboard.php');
    } elseif ($_SESSION['role'] === 'placement_manager') {
        redirect_to('placement_manager/index.php');
    } else {
        redirect_to('student/profile.php');
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMU Internship/Placement Portal - Your Gateway to Career Success</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #ffffff;
            min-height: 100vh;
            color: #1a1a1a;
            overflow-x: hidden;
        }

        /* Navigation Bar */
        .navbar {
            background: linear-gradient(135deg,rgb(96, 9, 9) 0%,rgb(96, 9, 9) 50%, rgb(96, 9, 9) 100%);
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            font-size: 26px;
            font-weight: bold;
            color: #f5e6d3;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }

        .logo:hover {
            color: #ebd08d;
            transform: scale(1.05);
        }

        .logo i {
            font-size: 28px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-link {
            color: #f5e6d3;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 18px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(245, 230, 211, 0.1);
            color: #ebd08d;
        }

        .login-btn {
            background: linear-gradient(135deg,rgb(249, 229, 115),rgb(238, 192, 107));
            color: #1a1a1a;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            background: linear-gradient(135deg, #FFA500, #FFD700);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
        }

        /* Hero Section */
        .hero {
            padding: 100px 20px 80px;
            text-align: center;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(235, 208, 141, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -15%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(91, 31, 31, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .hero-container {
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ebd08d, #f4e6c3);
            color: #5b1f1f;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(235, 208, 141, 0.3);
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 25px;
            color: #5b1f1f;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .hero h1 .highlight {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.25rem;
            margin-bottom: 45px;
            color: #666;
            line-height: 1.8;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta-btn {
            padding: 16px 35px;
            border-radius: 35px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.15rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .cta-primary {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .cta-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .cta-primary:hover::before {
            left: 100%;
        }

        .cta-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(91, 31, 31, 0.4);
        }

        .cta-secondary {
            background: white;
            color: #5b1f1f;
            border: 2px solid #5b1f1f;
        }

        .cta-secondary:hover {
            background: #5b1f1f;
            color: white;
            transform: translateY(-3px);
        }

        /* Portal Selection Section */
        .portals-section {
            padding: 80px 20px;
            background: #f8f9fa;
        }

        .portals-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .portals-section h2 {
            text-align: center;
            font-size: 2.5rem;
            color: #5b1f1f;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .portals-section > p {
            text-align: center;
            color: #666;
            margin-bottom: 60px;
            font-size: 1.2rem;
        }

        .portals-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 40px;
        }

        .portal-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            cursor: pointer;
            border: 3px solid transparent;
            text-align: center;
        }

        .portal-card:hover {
            transform: translateY(-10px);
            border-color: #f4e6c3;
            box-shadow: 0 10px 30px rgba(91, 31, 31, 0.15);
        }

        .portal-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #f4e6c3, #ffe9a8);
            border-radius: 25px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 20px;
            transition: all 0.3s;
        }

        .portal-icon i {
            color: #5b1f1f;
        }

        .portal-card:hover .portal-icon {
            background: linear-gradient(135deg, #ffe9a8, #f4e6c3);
            transform: scale(1.1);
        }

        .portal-card h3 {
            font-size: 28px;
            color: #5b1f1f;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .portal-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .portal-btn {
            display: inline-block;
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            color: white;
            padding: 14px 35px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
        }

        .portal-btn:hover {
            background: linear-gradient(135deg, #8b3a3a, #5b1f1f);
            transform: scale(1.05);
        }

        /* Features Section */
        .features {
            padding: 80px 20px;
            background: white;
        }

        .features-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .features h2 {
            text-align: center;
            font-size: 2.5rem;
            color: #5b1f1f;
            margin-bottom: 50px;
            font-weight: 700;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 35px;
            text-align: center;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            border-color: #f4e6c3;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f4e6c3, #ffe9a8);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 25px;
            transition: all 0.3s;
        }

        .feature-icon i {
            color: #5b1f1f;
        }

        .feature-card:hover .feature-icon {
            background: linear-gradient(135deg, #ffe9a8, #f4e6c3);
            transform: translateY(-5px);
        }

        .feature-card h3 {
            font-size: 1.6rem;
            color: #5b1f1f;
            margin-bottom: 18px;
            font-weight: 700;
        }

        .feature-card p {
            line-height: 1.8;
            font-size: 1.05rem;
        }

        /* Stats Section */
        .stats-section {
            padding: 80px 20px;
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            color: white;
        }

        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            text-align: center;
        }

        .stat-item {
            padding: 20px;
        }

        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            color: #ebd08d;
            margin-bottom: 10px;
            line-height: 1;
        }

        .stat-label {
            font-size: 1.1rem;
            color: #f4e6c3;
            font-weight: 500;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #3a1515, #5b1f1f);
            color: #f4e6c3;
            text-align: center;
            padding: 30px 20px;
        }

        .footer p {
            font-size: 1.05rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .features-grid,
            .portals-grid {
                grid-template-columns: 1fr;
            }

            .stat-number {
                font-size: 2.5rem;
            }

            .stats-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <img src="assets/images/gmu (1).png" alt="GMU Logo" style="height: 40px; margin-right: 10px; vertical-align: middle;"> GMU Internship/Placement Portal
            </a>
            <div class="nav-links">
                <a href="login.php?role=student" class="login-btn"><i class="fas fa-sign-in-alt"></i> Login</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-badge">🎓 Empowering Future Careers</div>
            <h1>Welcome to <span class="highlight">LAKSHYA</span><br><p>An Internship & Placement Portal of GMU</p></h1>
            <p>Your gateway to career success. Connect students with opportunities, manage placements efficiently, and build a brighter future together.</p>   
    </section>

    <!-- Portal Selection Section -->
    <section id="portals" class="portals-section">
        <div class="portals-container">
            <h2>Choose Your Portal</h2>
            <p>Select your role to access the appropriate dashboard</p>
            <div class="portals-grid">
                <!-- Student Portal -->
                <div class="portal-card" onclick="window.location.href='login.php?role=student'">
                    <div class="portal-icon"><i class="fas fa-user-graduate"></i></div>
                    <h3>Student Portal</h3>
                    <p>Access information, training material, track your progress, and prepare for Internships and placements</p>
                    <a href="login.php?role=student" class="portal-btn">Login as Student →</a>
                </div>
                <!-- Admin Portal -->
                <div class="portal-card" onclick="window.location.href='login.php?role=admin'">
                    <div class="portal-icon"><i class="fas fa-user-shield"></i></div>
                    <h3>Faculty Coordinators</h3>
                    <p>Assign mentors, generate training content and track student progress</p>
                    <a href="login.php?role=admin" class="portal-btn">Login as Coordinator →</a>
                </div>
                <!-- Internship Cell Portal -->
                <div class="portal-card" onclick="window.location.href='login.php?role=admin'">
                    <div class="portal-icon"><i class="fas fa-user-shield"></i></div>
                    <h3>Internship cell</h3>
                    <p>Broadcast Internship opportunities, manage students applications, manage student Internship positioning and Internship analytics.</p>
                    <a href="login.php?role=internship_officer" class="portal-btn">Login as Internship Staff →</a>
                </div>  
                <!-- Placement Cell Portal -->
                <div class="portal-card" onclick="window.location.href='login.php?role=placement'">
                    <div class="portal-icon"><i class="fas fa-building"></i></div>  
                    <h3>Placement Cell</h3>
                    <p> Broadcast Placement opportunities, manage students applications, manage placement drives and placement analytics</p>
                    <a href="login.php?role=placement" class="portal-btn">Login as Placement Staff →</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="features-container">
            <h2>What Lakshya can do?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-book-open"></i></div>
                    <h3>For Students</h3>
                    <p>Access personalized learning content, track your progress, and prepare for your dream career with our comprehensive placement preparation tools.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-users-cog"></i></div>
                    <h3>For Administrators</h3>
                    <p>Manage student data, create assignments, track analytics, and streamline the entire placement process with powerful admin tools.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Analytics & Reports</h3>
                    <p>Get detailed insights into student performance, placement trends, and success rates with comprehensive analytics and reporting.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-robot"></i></div>
                    <h3>AI-Powered Learning</h3>
                    <p>Experience intelligent learning with AI chatbots, automated FAQ generation, and personalized content recommendations.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-envelope"></i></div>
                    <h3>Communication Tools</h3>
                    <p>Stay connected with automated email notifications, announcements, and seamless communication between students and administrators.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                    <h3>Mobile Responsive</h3>
                    <p>Access the platform from anywhere, anytime with our fully responsive design that works perfectly on all devices.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <!-- <section class="stats-section">
        <div class="stats-container">
            <div class="stat-item">
                <div class="stat-number">500+</div>
                <div class="stat-label">Students Placed</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">100+</div>
                <div class="stat-label">Partner Companies</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">95%</div>
                <div class="stat-label">Success Rate</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">12 LPA</div>
                <div class="stat-label">Average Package</div>
            </div>
        </div>
    </section> -->

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> GMU Placement Portal. All rights reserved. | Empowering careers, one student at a time.</p>
    </footer>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Animate stats on scroll
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.stat-item').forEach(item => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(30px)';
            item.style.transition = 'all 0.6s ease';
            observer.observe(item);
        });
    </script>
</body>
</html>