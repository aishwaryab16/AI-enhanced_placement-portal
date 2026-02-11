<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Fetch companies from company_resources table
$companies = [];
$result = $mysqli->query("SELECT id, company_name, logo_url, industry, location, website, description, contact_person, contact_email, contact_phone, is_active FROM company_resources WHERE is_active = 1 ORDER BY company_name ASC");
if ($result) $companies = $result->fetch_all(MYSQLI_ASSOC);

// Fallback to companies table if company_resources is empty
if (empty($companies)) {
    $result = $mysqli->query("SELECT id, company_name, logo_url, industry, location, website, description, contact_person, contact_email, contact_phone, is_active FROM companies WHERE is_active = 1 ORDER BY company_name ASC");
    if ($result) $companies = $result->fetch_all(MYSQLI_ASSOC);
}

// Final fallback to company_intelligence if both are empty
if (empty($companies)) {
    $result = $mysqli->query("SELECT id, company_name, NULL AS logo_url, industry, location, website, about_company AS description, NULL AS contact_person, NULL AS contact_email, NULL AS contact_phone, 1 AS is_active FROM company_intelligence ORDER BY company_name ASC");
    if ($result) $companies = $result->fetch_all(MYSQLI_ASSOC);
}

?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #ffffff;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

.companies-container { 
    max-width: 900px; 
    margin: 0 auto; 
    padding: 80px 20px 40px; 
    min-height: calc(100vh - 200px);
}

/* Hero Section - Initial Blank State */
.hero-section {
    text-align: center;
    padding: 60px 0;
}

.hero-headline {
    font-size: 3.5rem;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
    line-height: 1.2;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

.hero-headline .ai-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.hero-description {
    font-size: 1.25rem;
    color: #666;
    max-width: 600px;
    margin: 0 auto 50px;
    line-height: 1.6;
}

/* Search Section */
.search-container {
    position: relative;
    margin-bottom: 40px;
}

.search-box {
    display: flex;
    gap: 12px;
    align-items: center;
    max-width: 700px;
    margin: 0 auto;
    background: white;
    border: 2px solid #667eea;
    border-radius: 16px;
    padding: 4px;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.1);
    transition: all 0.3s ease;
}

.search-box:focus-within {
    box-shadow: 0 6px 30px rgba(102, 126, 234, 0.2);
    border-color: #764ba2;
}

.search-input {
    flex: 1;
    padding: 18px 24px;
    border: none;
    background: transparent;
    font-size: 16px;
    color: #1a1a1a;
    outline: none;
}

.search-input::placeholder {
    color: #999;
}

.search-btn {
    padding: 16px 32px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}

.search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.search-btn:active {
    transform: translateY(0);
}

.search-suggestions {
    text-align: center;
    margin-top: 30px;
    color: #999;
    font-size: 14px;
    margin-bottom: 20px;
}

/* Suggested Topics/Categories */
.suggested-topics {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
    margin-top: 30px;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.topic-chip {
    padding: 10px 20px;
    background: #f5f5f5;
    border: 1px solid #e0e0e0;
    border-radius: 24px;
    font-size: 14px;
    color: #666;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 500;
}

.topic-chip:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

/* Results Section - Hidden Initially */
.results-section {
    display: none;
    margin-top: 50px;
}

.results-section.active {
    display: block;
}
.search-loading {
    display: none;
    text-align: center;
    padding: 60px 30px;
    color: #667eea;
}

.search-loading.active {
    display: block;
}

.search-loading i {
    font-size: 32px;
    animation: spin 1s linear infinite;
    display: block;
    margin-bottom: 15px;
    color: #667eea;
}

.search-loading p {
    color: #666;
    font-size: 16px;
    margin-top: 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.search-results-info {
    background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
    padding: 20px 24px;
    border-radius: 12px;
    margin-bottom: 30px;
    display: none;
    justify-content: space-between;
    align-items: center;
    border: 2px solid #667eea;
}

.search-results-info.active {
    display: flex;
}

.search-results-info span {
    color: #333;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}

.clear-search-btn {
    background: #ef4444;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.clear-search-btn:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}
.companies-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); 
    gap: 24px; 
}
.company-card { 
    background: #fff; 
    padding: 24px; 
    border-radius: 15px; 
    box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
    border: 2px solid transparent;
    transition: all 0.3s ease;
}
.company-card:hover {
    border-color: #f4e6c3;
    box-shadow: 0 8px 24px rgba(91, 31, 31, 0.15);
    transform: translateY(-4px);
}
.company-header {
    display: flex;
    gap: 15px;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f8f9fa;
}
.company-logo-wrapper {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    overflow: hidden;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #eee;
    flex-shrink: 0;
}
.company-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 8px;
}
.company-logo-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 32px;
    color: #5b1f1f;
    background: linear-gradient(135deg, #f4e6c3, #ffe9a8);
}
.company-info { flex: 1; }
.company-name { 
    font-size: 20px; 
    font-weight: 700; 
    color: #5b1f1f; 
    margin-bottom: 4px; 
}
.company-industry { 
    color: #666; 
    font-size: 14px; 
    margin-bottom: 0;
}
.status-pill { 
    padding: 6px 12px; 
    border-radius: 20px; 
    font-size: 12px; 
    font-weight: 600;
    white-space: nowrap;
}
.status-active { 
    background: #e8f5e9; 
    color: #2e7d32; 
}
.status-inactive { 
    background: #ffebee; 
    color: #c62828; 
}
.company-details {
    margin-top: 16px;
}
.detail-row { 
    display: flex; 
    gap: 10px; 
    align-items: flex-start; 
    color: #444; 
    font-size: 14px; 
    margin: 10px 0;
    line-height: 1.6;
}
.detail-row i {
    color: #5b1f1f;
    width: 18px;
    margin-top: 2px;
}
.detail-row a {
    color: #666;
    text-decoration: none;
    word-break: break-all;
    pointer-events: none;
}
.detail-row a:hover {
    text-decoration: none;
}
.company-description {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #f0f0f0;
    color: #555;
    font-size: 14px;
    line-height: 1.6;
}
.company-actions { 
    margin-top: 18px; 
    padding-top: 18px;
    border-top: 1px solid #f0f0f0;
    display: none;
}
.btn-visit { 
    display: inline-block;
    background: linear-gradient(135deg, #5b1f1f, #8b3a3a); 
    color: white; 
    padding: 10px 20px; 
    border-radius: 8px; 
    text-decoration: none; 
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s;
    display: none;
}
.btn-visit:hover {
    background: linear-gradient(135deg, #8b3a3a, #5b1f1f);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(91, 31, 31, 0.3);
}
.btn-visit i {
    margin-right: 6px;
}
.empty-state {
    grid-column: 1/-1;
    text-align: center;
    padding: 60px 20px;
    color: #999;
}
.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}
</style>

<div class="companies-container">
    <!-- Hero Section - Initial Blank State -->
    <div class="hero-section" id="heroSection">
        <h1 class="hero-headline">
            <span>Discover companies by searching</span>
            <div class="ai-icon">
                <i class="fas fa-magic"></i>
            </div>
        </h1>
        <p class="hero-description">
            Search narrows down companies based on your criteria and creates a personalized list 
            based on industry, location, and your career preferences.
        </p>
        
        <div class="search-container">
            <div class="search-box">
                <input 
                    type="text" 
                    id="companySearch" 
                    class="search-input" 
                    placeholder="I want to learn about companies in..."
                    autocomplete="off"
                />
                <button class="search-btn" onclick="searchCompanies()" id="searchButton">
                    <span>Search Companies</span>
                    <i class="fas fa-arrow-up" style="transform: rotate(45deg);"></i>
                </button>
            </div>
            
            <p class="search-suggestions">or choose from most searched categories</p>
            
            <div class="suggested-topics">
                <div class="topic-chip" onclick="searchByCategory('Technology Companies')">Technology</div>
                <div class="topic-chip" onclick="searchByCategory('Consulting Firms')">Consulting</div>
                <div class="topic-chip" onclick="searchByCategory('Finance & Banking')">Finance</div>
                <div class="topic-chip" onclick="searchByCategory('Healthcare Companies')">Healthcare</div>
                <div class="topic-chip" onclick="searchByCategory('E-commerce Companies')">E-commerce</div>
                <div class="topic-chip" onclick="searchByCategory('Startups')">Startups</div>
                <div class="topic-chip" onclick="searchByCategory('Multinational Corporations')">Multinational</div>
                <div class="topic-chip" onclick="searchByCategory('Manufacturing Companies')">Manufacturing</div>
            </div>
        </div>
    </div>

    <!-- Results Section - Hidden Initially -->
    <div class="results-section" id="resultsSection">
        <div class="search-loading" id="searchLoading">
            <i class="fas fa-spinner"></i>
            <p>Searching with AI...</p>
        </div>
        
        <div id="searchResultsInfo" class="search-results-info">
            <span>
                <i class="fas fa-info-circle"></i>
                <span id="searchResultsText"></span>
            </span>
            <button class="clear-search-btn" onclick="clearSearch()">
                <i class="fas fa-times"></i> Clear Search
            </button>
        </div>

        <div class="companies-grid" id="companiesGrid">
            <!-- Companies will be dynamically inserted here -->
        </div>
    </div>
</div>

<script>
let isSearchActive = false;
let originalState = {
    heroVisible: true,
    resultsVisible: false
};

async function searchCompanies() {
    const query = document.getElementById('companySearch').value.trim();
    
    if (!query) {
        alert('Please enter a search query');
        return;
    }
    
    // Hide hero section and show results
    document.getElementById('heroSection').style.display = 'none';
    document.getElementById('resultsSection').classList.add('active');
    isSearchActive = true;
    
    // Show loading
    document.getElementById('searchLoading').classList.add('active');
    document.getElementById('searchResultsInfo').classList.remove('active');
    
    try {
        const response = await fetch('../api/search_companies_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ query: query })
        });
        
        const data = await response.json();
        
        // Hide loading
        document.getElementById('searchLoading').classList.remove('active');
        
        if (data.success && data.companies && data.companies.length > 0) {
            displayCompanies(data.companies, data.message);
        } else {
            displayCompanies([], data.message || 'No companies found matching your search');
        }
    } catch (error) {
        console.error('Search error:', error);
        document.getElementById('searchLoading').classList.remove('active');
        alert('Error searching companies. Please try again.');
    }
}

function searchByCategory(category) {
    document.getElementById('companySearch').value = category;
    searchCompanies();
}

function displayCompanies(companies, message) {
    const grid = document.getElementById('companiesGrid');
    const infoDiv = document.getElementById('searchResultsInfo');
    const infoText = document.getElementById('searchResultsText');
    
    // Update info message
    infoText.textContent = message || `Found ${companies.length} company(ies)`;
    infoDiv.classList.add('active');
    
    if (companies.length === 0) {
        grid.innerHTML = `
            <div class="empty-state" style="grid-column: 1/-1;">
                <i class="fas fa-search"></i>
                <p style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">No Companies Found</p>
                <p>Try different search terms or <a href="#" onclick="clearSearch(); return false;" style="color: #667eea; text-decoration: underline;">clear search</a></p>
            </div>
        `;
        return;
    }
    
    // Generate company cards HTML
    let html = '';
    companies.forEach(company => {
        const firstLetter = company.company_name ? company.company_name.charAt(0).toUpperCase() : '?';
        const description = (company.description || '').substring(0, 200);
        const industry = company.industry || 'Technology';
        const isAISuggested = company.is_ai_suggested || false;
        const hasId = company.id && company.id !== 0;
        
        // Build URL: use id if available, otherwise use name for AI-suggested companies
        let detailUrl = '';
        if (hasId) {
            detailUrl = `company_details.php?id=${company.id}`;
        } else if (isAISuggested && company.company_name) {
            detailUrl = `company_details.php?name=${encodeURIComponent(company.company_name)}&ai_suggested=1`;
        }
        
        html += `
            <div class="company-card" data-url="${detailUrl}" style="cursor: ${detailUrl ? 'pointer' : 'default'};">
                <div class="company-header">
                    <div class="company-logo-wrapper">
                        ${company.logo_url ? `
                            <img src="${escapeHtml(company.logo_url)}" 
                                 alt="${escapeHtml(company.company_name)} logo" 
                                 class="company-logo"
                                 onerror="this.parentElement.innerHTML='<div class=\\'company-logo-placeholder\\'>${firstLetter}</div>'" />
                        ` : `
                            <div class="company-logo-placeholder">
                                ${firstLetter}
                            </div>
                        `}
                    </div>
                    <div class="company-info">
                        <div class="company-name">${escapeHtml(company.company_name)}</div>
                        <div class="company-industry">${escapeHtml(industry)}</div>
                    </div>
                    <div>
                        ${isAISuggested ? `
                            <div class="status-pill" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                                <i class="fas fa-magic"></i> AI Suggested
                            </div>
                        ` : `
                            <div class="status-pill status-active">
                                Active
                            </div>
                        `}
                    </div>
                </div>
                <div class="company-details">
                    ${company.location ? `
                        <div class="detail-row">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>${escapeHtml(company.location)}</span>
                        </div>
                    ` : ''}
                    ${company.website ? `
                        <div class="detail-row">
                            <i class="fas fa-globe"></i>
                            <a href="${escapeHtml(company.website)}" target="_blank">
                                ${escapeHtml(company.website)}
                            </a>
                        </div>
                    ` : ''}
                    ${company.contact_person ? `
                        <div class="detail-row">
                            <i class="fas fa-user"></i>
                            <span>${escapeHtml(company.contact_person)}</span>
                        </div>
                    ` : ''}
                    ${company.contact_email ? `
                        <div class="detail-row">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:${escapeHtml(company.contact_email)}">
                                ${escapeHtml(company.contact_email)}
                            </a>
                        </div>
                    ` : ''}
                    ${company.contact_phone ? `
                        <div class="detail-row">
                            <i class="fas fa-phone"></i>
                            <span>${escapeHtml(company.contact_phone)}</span>
                        </div>
                    ` : ''}
                    ${description ? `
                        <div class="company-description">
                            ${escapeHtml(description)}${description.length >= 200 ? '...' : ''}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    });
    
    grid.innerHTML = html;

    // Delegate click handling to the grid to ensure navigation works reliably
    // even when clicking on nested elements inside the card.
    if (!grid.__cardsClickBound) {
        grid.addEventListener('click', function(e) {
            const card = e.target.closest('.company-card');
            if (!card || !grid.contains(card)) return;
            const url = card.getAttribute('data-url');
            if (url) {
                window.location.href = url;
            }
        });
        grid.__cardsClickBound = true;
    }
}

function clearSearch() {
    document.getElementById('companySearch').value = '';
    document.getElementById('companiesGrid').innerHTML = '';
    document.getElementById('searchResultsInfo').classList.remove('active');
    document.getElementById('resultsSection').classList.remove('active');
    document.getElementById('heroSection').style.display = 'block';
    isSearchActive = false;
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Allow Enter key to trigger search
document.getElementById('companySearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchCompanies();
    }
});

// Focus on search input when page loads
window.addEventListener('load', function() {
    document.getElementById('companySearch').focus();
});
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
