function calculatePRS() {
    const loadingIndicator = document.getElementById('loadingIndicator');
    const resultsContainer = document.getElementById('resultsContainer');
    
    loadingIndicator.classList.add('active');
    resultsContainer.classList.remove('active');
    
    fetch('api/company_intelligence_api.php?action=calculate_prs', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        loadingIndicator.classList.remove('active');
        
        if (data.success) {
            displayPRSResults(data);
        } else {
            alert('Error: ' + (data.error || 'Failed to calculate PRS'));
        }
    })
    .catch(error => {
        loadingIndicator.classList.remove('active');
        console.error('Error:', error);
        alert('Failed to calculate PRS. Please try again.');
    });
}

function analyzeMatch() {
    const company = document.getElementById('targetCompany').value;
    
    if (!company) {
        alert('Please select a target company first!');
        return;
    }
    
    const loadingIndicator = document.getElementById('loadingIndicator');
    const resultsContainer = document.getElementById('resultsContainer');
    
    loadingIndicator.classList.add('active');
    resultsContainer.classList.remove('active');
    
    const formData = new FormData();
    formData.append('company_name', company);
    formData.append('resume_id', 1);
    
    fetch('api/company_intelligence_api.php?action=analyze_match', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        loadingIndicator.classList.remove('active');
        
        if (data.success) {
            displayMatchResults(data);
        } else {
            alert('Error: ' + (data.error || 'Failed to analyze match'));
        }
    })
    .catch(error => {
        loadingIndicator.classList.remove('active');
        console.error('Error:', error);
        alert('Failed to analyze match. Please try again.');
    });
}

function displayPRSResults(data) {
    const resultsContainer = document.getElementById('resultsContainer');
    
    const grade = data.grade || 'N/A';
    const score = data.overall_score.toFixed(1);
    
    let html = `
        <div class="match-score">
            <div class="match-score-value">${score}%</div>
            <div class="match-score-label">Placement Readiness Score</div>
            <p style="color: #065f46; margin-top: 10px; font-weight: 600;">Grade: ${grade}</p>
        </div>
        
        <div class="results-grid">
            <div class="result-card">
                <h4><i class="fas fa-chart-bar"></i> Component Scores</h4>
                <ul>
                    <li><i class="fas fa-graduation-cap"></i> Academic: ${data.component_scores.academic.toFixed(1)}%</li>
                    <li><i class="fas fa-code"></i> Skills: ${data.component_scores.skills.toFixed(1)}%</li>
                    <li><i class="fas fa-project-diagram"></i> Projects: ${data.component_scores.projects.toFixed(1)}%</li>
                    <li><i class="fas fa-certificate"></i> Certifications: ${data.component_scores.certifications.toFixed(1)}%</li>
                    <li><i class="fas fa-briefcase"></i> Experience: ${data.component_scores.experience.toFixed(1)}%</li>
                </ul>
            </div>
            
            <div class="result-card">
                <h4><i class="fas fa-star"></i> Your Strengths</h4>
                <ul>
    `;
    
    if (data.strengths && data.strengths.length > 0) {
        data.strengths.forEach(strength => {
            html += `<li><i class="fas fa-check-circle"></i> ${strength}</li>`;
        });
    } else {
        html += `<li style="color: #6b7280;">No strengths identified yet</li>`;
    }
    
    html += `
                </ul>
            </div>
            
            <div class="result-card">
                <h4><i class="fas fa-exclamation-triangle"></i> Areas to Improve</h4>
                <ul>
    `;
    
    if (data.weaknesses && data.weaknesses.length > 0) {
        data.weaknesses.forEach(weakness => {
            html += `<li><i class="fas fa-arrow-up"></i> ${weakness}</li>`;
        });
    } else {
        html += `<li style="color: #10b981;">Great! No major weaknesses found</li>`;
    }
    
    html += `
                </ul>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="career_advisor.php" class="btn btn-primary" style="font-size: 16px; padding: 15px 30px;">
                <i class="fas fa-chart-line"></i> View Full Career Analysis
            </a>
        </div>
    `;
    
    resultsContainer.innerHTML = html;
    resultsContainer.classList.add('active');
}

function displayMatchResults(data) {
    const resultsContainer = document.getElementById('resultsContainer');
    
    const matchPercentage = data.match_percentage.toFixed(1);
    const company = data.company;
    
    let matchColor = '#ef4444';
    let matchLabel = 'Low Match';
    if (matchPercentage >= 80) {
        matchColor = '#10b981';
        matchLabel = 'Excellent Match';
    } else if (matchPercentage >= 60) {
        matchColor = '#f59e0b';
        matchLabel = 'Good Match';
    } else if (matchPercentage >= 40) {
        matchColor = '#f97316';
        matchLabel = 'Fair Match';
    }
    
    let html = `
        <div class="match-score" style="background: linear-gradient(135deg, ${matchColor}22, ${matchColor}44);">
            <div class="match-score-value" style="background: linear-gradient(135deg, ${matchColor}, ${matchColor}dd); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">${matchPercentage}%</div>
            <div class="match-score-label" style="color: ${matchColor};">${matchLabel} with ${company}</div>
            <p style="color: #6b7280; margin-top: 10px;">${data.industry} | ${data.package_range}</p>
        </div>
        
        <div class="results-grid">
            <div class="result-card">
                <h4><i class="fas fa-check-circle"></i> Matched Skills (${data.matched_skills.length})</h4>
                <ul>
    `;
    
    if (data.matched_skills.length > 0) {
        data.matched_skills.slice(0, 8).forEach(skill => {
            html += `<li><i class="fas fa-check" style="color: #10b981;"></i> ${skill}</li>`;
        });
        if (data.matched_skills.length > 8) {
            html += `<li style="color: #6b7280;">+ ${data.matched_skills.length - 8} more...</li>`;
        }
    } else {
        html += `<li style="color: #6b7280;">No matching skills found</li>`;
    }
    
    html += `
                </ul>
            </div>
            
            <div class="result-card">
                <h4><i class="fas fa-times-circle"></i> Missing Skills (${data.missing_skills.length})</h4>
                <ul>
    `;
    
    if (data.missing_skills.length > 0) {
        data.missing_skills.slice(0, 8).forEach(skill => {
            html += `<li><i class="fas fa-plus" style="color: #ef4444;"></i> ${skill}</li>`;
        });
        if (data.missing_skills.length > 8) {
            html += `<li style="color: #6b7280;">+ ${data.missing_skills.length - 8} more...</li>`;
        }
    } else {
        html += `<li style="color: #10b981;">You have all required skills!</li>`;
    }
    
    html += `
                </ul>
            </div>
            
            <div class="result-card">
                <h4><i class="fas fa-lightbulb"></i> AI Recommendations</h4>
                <ul>
    `;
    
    if (data.recommendations && data.recommendations.length > 0) {
        data.recommendations.forEach(rec => {
            html += `<li><i class="fas fa-arrow-right"></i> ${rec}</li>`;
        });
    } else {
        html += `<li style="color: #6b7280;">No recommendations available</li>`;
    }
    
    html += `
                </ul>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="dynamic_resume_builder.php" class="btn btn-primary" style="font-size: 16px; padding: 15px 30px;">
                <i class="fas fa-file-alt"></i> Optimize Resume for ${company}
            </a>
        </div>
    `;
    
    resultsContainer.innerHTML = html;
    resultsContainer.classList.add('active');
}
