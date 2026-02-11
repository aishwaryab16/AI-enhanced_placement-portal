// Load all analytics data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCareerFitData();
    loadPeerComparison();
    loadPlacementInsights();
    loadSkillHeatmap();
    loadAIRecommendations();
});

// 1. Career Fit Data
async function loadCareerFitData() {
    try {
        const response = await fetch('api/analytics_api.php?action=get_career_fit');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // Update scores
            document.getElementById('employabilityScore').textContent = data.employability_score + '%';
            document.getElementById('jobReadiness').textContent = Math.round(data.employability_score * 0.9) + '%';
            
            // Create pie chart
            const ctx = document.getElementById('careerFitChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.company_matches.slice(0, 5).map(c => c.company),
                    datasets: [{
                        data: data.company_matches.slice(0, 5).map(c => c.match_percentage),
                        backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
            
            // Display company matches
            const matchList = document.getElementById('companyMatchList');
            matchList.innerHTML = data.company_matches.slice(0, 5).map(company => `
                <div class="company-match-item">
                    <div class="company-logo">${company.company.charAt(0)}</div>
                    <div class="company-info">
                        <div class="company-name">${company.company}</div>
                        <div class="company-match">${company.matching_skills.length} matching skills</div>
                    </div>
                    <div class="match-percentage">${company.match_percentage}%</div>
                </div>
            `).join('');
            
            // Skill radar chart
            const radarCtx = document.getElementById('skillRadarChart').getContext('2d');
            const topSkills = data.skill_radar.slice(0, 8);
            new Chart(radarCtx, {
                type: 'radar',
                data: {
                    labels: topSkills.map(s => s.skill),
                    datasets: [{
                        label: 'Your Proficiency',
                        data: topSkills.map(s => s.proficiency),
                        backgroundColor: 'rgba(102, 126, 234, 0.2)',
                        borderColor: '#667eea',
                        pointBackgroundColor: '#667eea'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error loading career fit data:', error);
    }
}

// 2. Peer Comparison
async function loadPeerComparison() {
    try {
        const response = await fetch('api/analytics_api.php?action=get_peer_comparison');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // Create comparison chart
            const ctx = document.getElementById('peerComparisonChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['CGPA', 'Skills', 'Certifications'],
                    datasets: [{
                        label: 'You',
                        data: [data.student_cgpa, data.student_skills, data.student_certifications],
                        backgroundColor: '#667eea'
                    }, {
                        label: 'Batch Average',
                        data: [data.batch_avg_cgpa, data.avg_skills, data.avg_certifications],
                        backgroundColor: '#e5e7eb'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
            
            // Display percentile
            document.getElementById('percentileInfo').innerHTML = `
                <p style="font-size: 18px; color: #1f2937; margin: 15px 0;">
                    <strong>You are in the top <span class="percentile-badge">${100 - data.overall_percentile}%</span></strong>
                </p>
                <p style="font-size: 14px; color: #6b7280;">
                    CGPA Percentile: ${data.cgpa_percentile}%
                </p>
            `;
        }
    } catch (error) {
        console.error('Error loading peer comparison:', error);
    }
}

// 3. Placement Insights
async function loadPlacementInsights() {
    try {
        const response = await fetch('api/analytics_api.php?action=get_placement_insights');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data.placement_stats;
            
            document.getElementById('totalPlaced').textContent = data.students_placed;
            document.getElementById('avgPackage').textContent = data.average_package + ' LPA';
            document.getElementById('placementRate').textContent = data.placement_percentage + '%';
            
            // Domain demand chart
            const ctx = document.getElementById('domainDemandChart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: result.data.domain_demand.map(d => d.domain),
                    datasets: [{
                        data: result.data.domain_demand.map(d => d.percentage),
                        backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b', '#fa709a']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error loading placement insights:', error);
    }
}

// 4. Skill Heatmap
async function loadSkillHeatmap() {
    try {
        const response = await fetch('api/analytics_api.php?action=get_skill_heatmap');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // Update counts
            document.getElementById('masteredCount').textContent = data.mastered_count;
            document.getElementById('learningCount').textContent = data.learning_count;
            document.getElementById('missingCount').textContent = data.missing_count;
            
            // Display heatmap
            const heatmap = document.getElementById('skillHeatmap');
            heatmap.innerHTML = data.heatmap.map(skill => `
                <div class="skill-item ${skill.status}">
                    <div class="skill-name">${skill.skill}</div>
                    <div class="skill-progress">${skill.proficiency}%</div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading skill heatmap:', error);
    }
}

// 5. AI Recommendations
async function loadAIRecommendations() {
    try {
        const response = await fetch('api/analytics_api.php?action=get_ai_recommendations');
        const result = await response.json();
        
        if (result.success) {
            const recommendations = result.data;
            
            const list = document.getElementById('recommendationsList');
            if (recommendations.length === 0) {
                list.innerHTML = '<p style="text-align: center; color: #6b7280; padding: 20px;">No recommendations at this time. Keep learning!</p>';
                return;
            }
            
            list.innerHTML = recommendations.map(rec => `
                <div class="recommendation-item ${rec.priority}">
                    <div class="recommendation-title">
                        <i class="fas fa-lightbulb"></i> ${rec.title}
                    </div>
                    <div class="recommendation-desc">${rec.description}</div>
                    <div class="recommendation-meta">
                        <span><i class="fas fa-flag"></i> Priority: ${rec.priority}</span>
                        ${rec.estimated_time ? `<span><i class="fas fa-clock"></i> ${rec.estimated_time}</span>` : ''}
                        ${rec.impact_score ? `<span><i class="fas fa-chart-line"></i> Impact: ${rec.impact_score}%</span>` : ''}
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading recommendations:', error);
    }
}
