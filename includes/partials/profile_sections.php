<!-- Education Section -->
<div class="section" id="educationSection">
    <div class="section-header">
        <h2><i class="fas fa-graduation-cap"></i> Education</h2>
    </div>

    <?php if ($academic_data): ?>
        <div class="item-card">
            <div class="item-title">Bachelor of Engineering</div>
            <div class="item-subtitle"><?php echo htmlspecialchars($academic_data['branch']); ?></div>
            <div class="item-meta">
                Roll No: <?php echo htmlspecialchars($academic_data['roll_number']); ?> | 
                Semester: <?php echo htmlspecialchars($academic_data['semester']); ?> | 
                CGPA: <?php echo number_format($academic_data['cgpa'], 2); ?>
            </div>
            <div class="tags">
                <span class="tag">Backlogs: <?php echo $academic_data['backlogs']; ?></span>
                <span class="tag">Attendance: <?php echo number_format($academic_data['attendance_percentage'], 1); ?>%</span>
                <?php if ($academic_data['is_verified']): ?>
                    <span class="tag" style="background: #d1fae5; color: #065f46;">✓ Verified</span>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-graduation-cap"></i>
            <h3>No education details added</h3>
            <p>Add your academic information to complete your profile</p>
        </div>
    <?php endif; ?>
</div>

<!-- Skills Section -->
<div class="section" id="skillsSection">
    <div class="section-header">
        <h2><i class="fas fa-code"></i> Skills (<?php echo count($skills); ?>)</h2>
    </div>

    <?php if (!empty($skills)): ?>
        <div class="skill-grid">
            <?php foreach ($skills as $skill): 
                $level_percent = 0;
                switch($skill['proficiency_level']) {
                    case 'beginner': $level_percent = 33; break;
                    case 'intermediate': $level_percent = 66; break;
                    case 'advanced': $level_percent = 100; break;
                }
            ?>
                <div class="skill-card">
                    <div class="skill-name"><?php echo htmlspecialchars($skill['skill_name']); ?></div>
                    <div class="skill-level"><?php echo htmlspecialchars($skill['proficiency_level']); ?></div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $level_percent; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-code"></i>
            <h3>No skills added</h3>
            <p>Add your technical and soft skills to showcase your abilities</p>
        </div>
    <?php endif; ?>
</div>

<!-- Projects Section -->
<div class="section" id="projectsSection">
    <div class="section-header">
        <h2><i class="fas fa-project-diagram"></i> Projects (<?php echo count($projects); ?>)</h2>
    </div>

    <?php if (!empty($projects)): ?>
        <?php foreach ($projects as $project): 
            $technologies = json_decode($project['technologies'], true) ?? [];
        ?>
            <div class="item-card">
                <div class="item-title"><?php echo htmlspecialchars($project['project_title']); ?></div>
                <div class="item-subtitle"><?php echo htmlspecialchars($project['role']); ?></div>
                <div class="item-meta">
                    <?php echo date('M Y', strtotime($project['start_date'])); ?> - 
                    <?php echo $project['is_ongoing'] ? 'Present' : date('M Y', strtotime($project['end_date'])); ?> | 
                    Team Size: <?php echo $project['team_size']; ?>
                </div>
                <p class="item-description"><?php echo htmlspecialchars($project['description']); ?></p>
                <?php if ($project['achievements']): ?>
                    <p class="item-description" style="font-weight: 600; margin-top: 10px;">
                        <i class="fas fa-trophy" style="color: #ecc35c;"></i> 
                        <?php echo htmlspecialchars($project['achievements']); ?>
                    </p>
                <?php endif; ?>
                <div class="tags">
                    <?php foreach ($technologies as $tech): ?>
                        <span class="tag"><?php echo htmlspecialchars($tech); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if ($project['github_url']): ?>
                    <div style="margin-top: 15px;">
                        <a href="<?php echo htmlspecialchars($project['github_url']); ?>" target="_blank" 
                           style="color: #5b1f1f; font-weight: 600; text-decoration: none;">
                            <i class="fab fa-github"></i> View on GitHub
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-project-diagram"></i>
            <h3>No projects added</h3>
            <p>Showcase your best projects to stand out to recruiters</p>
        </div>
    <?php endif; ?>
</div>

<!-- Certifications Section -->
<div class="section" id="certificationsSection">
    <div class="section-header">
        <h2><i class="fas fa-certificate"></i> Certifications (<?php echo count($certifications); ?>)</h2>
    </div>

    <?php if (!empty($certifications)): ?>
        <?php foreach ($certifications as $cert): 
            $skills_gained = json_decode($cert['skills_gained'], true) ?? [];
        ?>
            <div class="item-card">
                <div class="item-title"><?php echo htmlspecialchars($cert['certification_name']); ?></div>
                <div class="item-subtitle"><?php echo htmlspecialchars($cert['issuing_organization']); ?></div>
                <div class="item-meta">
                    Issued: <?php echo date('M Y', strtotime($cert['issue_date'])); ?>
                    <?php if ($cert['expiry_date']): ?>
                        | Expires: <?php echo date('M Y', strtotime($cert['expiry_date'])); ?>
                    <?php endif; ?>
                </div>
                <?php if ($cert['credential_id']): ?>
                    <p class="item-description">
                        Credential ID: <?php echo htmlspecialchars($cert['credential_id']); ?>
                    </p>
                <?php endif; ?>
                <div class="tags">
                    <?php foreach ($skills_gained as $skill): ?>
                        <span class="tag"><?php echo htmlspecialchars($skill); ?></span>
                    <?php endforeach; ?>
                    <?php if ($cert['is_verified']): ?>
                        <span class="tag" style="background: #d1fae5; color: #065f46;">✓ Verified</span>
                    <?php endif; ?>
                </div>
                <?php if ($cert['credential_url']): ?>
                    <div style="margin-top: 15px;">
                        <a href="<?php echo htmlspecialchars($cert['credential_url']); ?>" target="_blank" 
                           style="color: #5b1f1f; font-weight: 600; text-decoration: none;">
                            <i class="fas fa-external-link-alt"></i> View Certificate
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-certificate"></i>
            <h3>No certifications added</h3>
            <p>Add your professional certifications to boost your profile</p>
        </div>
    <?php endif; ?>
</div>

<!-- Experience Section -->
<div class="section" id="experienceSection">
    <div class="section-header">
        <h2><i class="fas fa-briefcase"></i> Experience (<?php echo count($experiences); ?>)</h2>
    </div>

    <?php if (!empty($experiences)): ?>
        <?php foreach ($experiences as $exp): 
            $achievements = json_decode($exp['achievements'], true) ?? [];
            $skills_used = json_decode($exp['skills_used'], true) ?? [];
        ?>
            <div class="item-card">
                <div class="item-title"><?php echo htmlspecialchars($exp['position']); ?></div>
                <div class="item-subtitle"><?php echo htmlspecialchars($exp['company_name']); ?></div>
                <div class="item-meta">
                    <?php echo date('M Y', strtotime($exp['start_date'])); ?> - 
                    <?php echo $exp['is_current'] ? 'Present' : date('M Y', strtotime($exp['end_date'])); ?> | 
                    <?php echo htmlspecialchars($exp['location']); ?> | 
                    <?php echo ucfirst($exp['employment_type']); ?>
                </div>
                <p class="item-description"><?php echo htmlspecialchars($exp['description']); ?></p>
                <?php if (!empty($achievements)): ?>
                    <div style="margin-top: 15px;">
                        <strong style="color: #5b1f1f;">Key Achievements:</strong>
                        <ul style="margin-top: 10px; margin-left: 20px; color: #78350f;">
                            <?php foreach ($achievements as $achievement): ?>
                                <li style="margin-bottom: 5px;"><?php echo htmlspecialchars($achievement); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="tags">
                    <?php foreach ($skills_used as $skill): ?>
                        <span class="tag"><?php echo htmlspecialchars($skill); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-briefcase"></i>
            <h3>No work experience added</h3>
            <p>Add your internships and work experience to strengthen your profile</p>
        </div>
    <?php endif; ?>
</div>
