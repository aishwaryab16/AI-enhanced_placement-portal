# Placement Portal System

A comprehensive web-based placement management system designed to streamline the placement process for students, administrators, and placement officers.

## 🏗️ System Overview

The Placement Portal is a multi-role system that facilitates:
- Student profile management and job applications
- Company and job opportunity management
- Interview scheduling and tracking
- Resume building and AI-powered assessments
- Placement drive coordination

## 👥 User Roles & Dashboards

### 1. **Administrator Dashboard**
**Access:** `admin` / `admin123`

**Features:**
- **User Management:** Create, edit, and deactivate user accounts
- **System Configuration:** Manage system settings and preferences
- **Reports & Analytics:** View placement statistics and trends
- **Department Admins:** Oversee specific department activities
- **Golden Points:** Add important placement guidelines and resources
- **Broadcast Messages:** Send announcements to all students

**Key Capabilities:**
- Full system access and control
- Manage all user roles and permissions
- Monitor system performance and usage
- Generate comprehensive reports

---

### 2. **Placement Officer Dashboard**
**Access:** `placement_officer` / `place123`

**Features:**
- **Company Management:** Add and manage company profiles
- **Job Opportunities:** Post and manage job listings
- **Placement Drives:** Organize and coordinate placement events
- **Student Applications:** Review and process job applications
- **Interview Scheduling:** Schedule and manage interview rounds
- **Analytics:** Track placement statistics and success rates

**Key Capabilities:**
- Company relationship management
- Job posting and promotion
- Application screening and shortlisting
- Interview coordination
- Placement drive management

---

### 3. **Internship Officer Dashboard**
**Access:** `internship_officer` / `intern123`

**Features:**
- **Internship Opportunities:** Post and manage internship listings
- **Company Partnerships:** Manage internship provider relationships
- **Student Applications:** Review internship applications
- **Internship Tracking:** Monitor student internship progress
- **Stipend Management:** Track internship compensation details
- **Reports:** Generate internship-specific analytics

**Key Capabilities:**
- Internship opportunity management
- Student-internship matching
- Progress tracking and evaluation
- Stipend and compensation management

---

### 4. **Student Dashboard**
**Access:** `student_user` / `stud123`

**Features:**
- **Profile Management:** Complete and maintain personal profile
- **Resume Builder:** Create professional resumes with AI assistance
- **Job Search:** Browse and apply for job opportunities
- **Internship Search:** Find and apply for internships
- **Skill Assessment:** Take AI-powered skill tests
- **Interview Preparation:** Practice with mock interviews
- **Application Tracking:** Monitor application status
- **Saved Opportunities:** Bookmark jobs and internships

**Key Capabilities:**
- Comprehensive profile management
- AI-powered resume building
- Skill assessment and improvement
- Interview preparation tools
- Application tracking and management

---

### 5. **Department Admin Dashboards**
**Access:** `{department}_admin` / `dept123`

**Available Departments:**
- **CSE Admin:** `cse_admin` - Computer Science and Engineering
- **ECE Admin:** `ece_admin` - Electronics and Communication Engineering
- **EEE Admin:** `eee_admin` - Electrical and Electronics Engineering
- **ME Admin:** `me_admin` - Mechanical Engineering
- **CE Admin:** `ce_admin` - Civil Engineering
- **BCA Admin:** `bca_admin` - Bachelor of Computer Applications
- **BCom Admin:** `bcom_admin` - Bachelor of Commerce

**Features:**
- **Department Students:** Manage students within their department
- **Department-specific Opportunities:** Post relevant jobs/internships
- **Academic Performance:** Track student academic progress
- **Placement Statistics:** Department-specific placement data
- **Resource Management:** Manage department-specific resources

---

## 🚀 Core Features

### **Student Profile Management**
- Personal information and academic details
- Skills, projects, and achievements tracking
- Experience and internship history
- Profile photo and portfolio management
- Social media integration (LinkedIn, GitHub)

### **Resume Builder**
- AI-powered resume generation
- Multiple resume templates
- Skill-based optimization
- Export to PDF format
- Real-time preview and editing

### **Job & Internship Management**
- Comprehensive job posting system
- Advanced search and filtering
- Application tracking system
- Status updates and notifications
- Saved opportunities feature

### **Interview Management**
- Automated interview scheduling
- Multiple interview rounds support
- AI-powered mock interviews
- Interview feedback and scoring
- Attendance tracking

### **AI-Powered Features**
- **Skill Assessment:** Automated skill testing and evaluation
- **Resume Optimization:** AI suggestions for resume improvement
- **Mock Interviews:** Practice interviews with AI feedback
- **Job Matching:** Intelligent job recommendations
- **Language Assessment:** Communication skill evaluation

### **Company Management**
- Company profile creation and management
- Contact information and HR details
- Industry classification and tagging
- Logo and branding management
- Company intelligence data

### **Placement Drives**
- Drive creation and management
- Student eligibility criteria
- Application deadline management
- Drive scheduling and coordination
- Multi-company support

### **Analytics & Reporting**
- Placement statistics and trends
- Student performance metrics
- Company hiring patterns
- Department-wise analytics
- Success rate tracking

---

## 📊 Database Structure

The system uses a comprehensive MySQL database with the following key tables:

### **User Management**
- `users` - Core user information and roles
- `student_additional_info` - Extended student profiles
- `student_skills` - Student skill inventory
- `student_projects` - Academic and personal projects
- `student_experience` - Work and internship experience
- `student_achievements` - Academic and extracurricular achievements
- `student_interests` - Career interests and preferences

### **Academic & Resume**
- `resume_academic_data` - Academic performance data
- `generated_resumes` - AI-generated resume content

### **Jobs & Opportunities**
- `companies` - Company information
- `job_opportunities` - Job postings
- `internship_opportunities` - Internship postings
- `job_applications` - Student job applications
- `internship_applications` - Student internship applications
- `saved_jobs` - Bookmarked jobs
- `saved_internships` - Bookmarked internships

### **Interview Management**
- `interviews` - Job interview schedules
- `internship_interviews` - Internship interview schedules
- `scheduled_interviews` - Interview calendar
- `interview_attendance` - Interview participation tracking
- `final_mock_interview_results` - Mock interview outcomes
- `interview_domains` - Interview specialization areas

### **System Management**
- `placement_drives` - Placement drive events
- `placement_broadcasts` - System announcements
- `events` - Calendar events
- `admin_golden_points` - Important guidelines
- `bot_messages` - Chatbot interactions
- `chapters` - Learning modules
- `modules` - Educational content
- `faqs_assigned` - FAQ assignments

### **AI & Intelligence**
- `company_intelligence` - Company hiring insights
- `question_bank_cache` - Cached assessment questions
- `company_resources` - Company-specific resources

---

## 🛠️ Technical Stack

### **Frontend**
- HTML5, CSS3, JavaScript
- Bootstrap for responsive design
- Chart.js for data visualization
- jQuery for DOM manipulation

### **Backend**
- PHP 8.0+
- MySQL/MariaDB database
- RESTful API architecture

### **AI Integration**
- OpenAI API for intelligent features
- Natural language processing
- Machine learning-based recommendations

### **Security**
- Role-based access control
- Input validation and sanitization
- SQL injection prevention
- XSS protection
- CSRF protection

---

## 🚀 Getting Started

### **Prerequisites**
- XAMPP/WAMP/LAMP stack
- PHP 8.0 or higher
- MySQL/MariaDB
- Web server (Apache/Nginx)

### **Installation**
1. Clone the repository to your web server directory
2. Import the database structure from `database/clean_users.sql`
3. Configure database credentials in `config.php`
4. Set up OpenAI API key in `openai_config.php`
5. Ensure proper file permissions for uploads directory

### **Default Login Credentials**
- **Administrator:** `admin` / `admin123`
- **Placement Officer:** `placement_officer` / `place123`
- **Internship Officer:** `internship_officer` / `intern123`
- **Student:** `student_user` / `stud123`
- **Department Admins:** `{department}_admin` / `dept123`

---

## 📱 Features Deep Dive

### **AI-Powered Resume Builder**
The system includes an intelligent resume builder that:
- Analyzes job descriptions for keyword optimization
- Suggests improvements based on industry standards
- Generates multiple resume formats
- Provides real-time feedback on completeness

### **Skill Assessment System**
Comprehensive skill evaluation featuring:
- Domain-specific technical questions
- Adaptive difficulty based on performance
- Detailed performance analytics
- Skill gap identification
- Personalized learning recommendations

### **Mock Interview Platform**
Advanced interview preparation with:
- AI-powered interview questions
- Real-time feedback on responses
- Multiple interview domains (technical, HR, behavioral)
- Performance scoring and improvement suggestions
- Interview recording and playback

### **Company Intelligence**
Smart company insights including:
- Hiring patterns and preferences
- Skill requirements analysis
- Culture fit indicators
- Salary range benchmarks
- Interview process details

---

## 🔧 Configuration

### **Database Configuration**
Update `config.php` with your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'placement_portal');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### **Email Configuration**
Configure email settings in `config.php`:
```php
define('SMTP_HOST', 'your_smtp_host');
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

### **OpenAI Integration**
Set up AI features in `openai_config.php`:
```php
define('OPENAI_API_KEY', 'YOUR_OPENAI_API_KEY_HERE');
```

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

---

## 📞 Support

For technical support or questions:
- Check the FAQ section
- Review the documentation
- Contact the system administrator

---

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

## 🔄 Version History

- **v1.0.0** - Initial release with core features
- **v1.1.0** - Added AI-powered features
- **v1.2.0** - Enhanced mobile responsiveness
- **v1.3.0** - Improved security features

---

*Last Updated: February 2026*
