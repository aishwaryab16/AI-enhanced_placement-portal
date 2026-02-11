<?php
require_once __DIR__ . '/../config.php';
require_role('admin');

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $stmt = $mysqli->prepare("INSERT INTO interview_domains (domain_name, domain_description, icon, difficulty_level, prompt_template, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $stmt->bind_param('sssssi', 
                $_POST['domain_name'], 
                $_POST['domain_description'], 
                $_POST['icon'], 
                $_POST['difficulty_level'], 
                $_POST['prompt_template'],
                $is_active
            );
            if ($stmt->execute()) {
                $message = 'Interview domain added successfully!';
            } else {
                $error = 'Failed to add domain: ' . $stmt->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'edit' && isset($_POST['domain_id'])) {
            $stmt = $mysqli->prepare("UPDATE interview_domains SET domain_name = ?, domain_description = ?, icon = ?, difficulty_level = ?, prompt_template = ?, is_active = ? WHERE id = ?");
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $stmt->bind_param('sssssii', 
                $_POST['domain_name'], 
                $_POST['domain_description'], 
                $_POST['icon'], 
                $_POST['difficulty_level'], 
                $_POST['prompt_template'],
                $is_active,
                $_POST['domain_id']
            );
            if ($stmt->execute()) {
                $message = 'Interview domain updated successfully!';
            } else {
                $error = 'Failed to update domain: ' . $stmt->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'delete' && isset($_POST['domain_id'])) {
            $stmt = $mysqli->prepare("DELETE FROM interview_domains WHERE id = ?");
            $stmt->bind_param('i', $_POST['domain_id']);
            if ($stmt->execute()) {
                $message = 'Interview domain deleted successfully!';
            } else {
                $error = 'Failed to delete domain: ' . $stmt->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'toggle' && isset($_POST['domain_id'])) {
            $stmt = $mysqli->prepare("UPDATE interview_domains SET is_active = NOT is_active WHERE id = ?");
            $stmt->bind_param('i', $_POST['domain_id']);
            if ($stmt->execute()) {
                $message = 'Domain status toggled successfully!';
            }
            $stmt->close();
        }
    }
}

// Fetch all domains
$domains = [];
$result = $mysqli->query("SELECT * FROM interview_domains ORDER BY domain_name ASC");
if ($result) {
    $domains = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}

include __DIR__ . '/../includes/partials/header.php';
?>

<style>
.domains-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.domains-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.add-domain-btn {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
}

.add-domain-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(128, 0, 32, 0.3);
}

.domains-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.domain-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s;
    position: relative;
}

.domain-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.domain-card.inactive {
    opacity: 0.6;
    background: #f5f5f5;
}

.domain-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.domain-icon {
    font-size: 36px;
}

.domain-title {
    flex: 1;
}

.domain-title h3 {
    margin: 0 0 5px 0;
    color: #5b1f1f;
    font-size: 18px;
}

.difficulty-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.difficulty-badge.beginner { background: #e8f5e9; color: #2e7d32; }
.difficulty-badge.intermediate { background: #fff3e0; color: #f57c00; }
.difficulty-badge.advanced { background: #ffebee; color: #c62828; }
.difficulty-badge.expert { background: #f3e5f5; color: #6a1b9a; }

.domain-description {
    color: #666;
    font-size: 14px;
    margin-bottom: 15px;
    line-height: 1.5;
}

.domain-prompt {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 6px;
    font-size: 12px;
    color: #666;
    max-height: 100px;
    overflow-y: auto;
    margin-bottom: 15px;
    font-family: monospace;
    white-space: pre-wrap;
}

.domain-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-edit, .btn-toggle, .btn-delete {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}

.btn-edit {
    background: #2196f3;
    color: white;
}

.btn-toggle {
    background: #ff9800;
    color: white;
}

.btn-delete {
    background: #dc3545;
    color: white;
}

.domain-modal {
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
    overflow-y: auto;
}

.domain-modal.active {
    display: flex;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.form-group textarea {
    min-height: 200px;
    resize: vertical;
    font-family: monospace;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.btn-primary {
    background: #5b1f1f;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.btn-secondary {
    background: #ccc;
    color: #333;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.status-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.status-badge.active {
    background: #d4edda;
    color: #155724;
}

.status-badge.inactive {
    background: #f8d7da;
    color: #721c24;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-group input[type="checkbox"] {
    width: auto;
}
</style>

<div class="domains-container">
    <div class="domains-header">
        <h1>🎯 Manage Interview Domains</h1>
        <button class="add-domain-btn" onclick="openAddModal()">+ Add New Domain</button>
    </div>

    <?php if ($message): ?>
        <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="domains-grid">
        <?php foreach ($domains as $domain): ?>
            <div class="domain-card <?php echo $domain['is_active'] ? '' : 'inactive'; ?>">
                <span class="status-badge <?php echo $domain['is_active'] ? 'active' : 'inactive'; ?>">
                    <?php echo $domain['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
                
                <div class="domain-header">
                    <div class="domain-icon"><?php echo htmlspecialchars($domain['icon']); ?></div>
                    <div class="domain-title">
                        <h3><?php echo htmlspecialchars($domain['domain_name']); ?></h3>
                        <span class="difficulty-badge <?php echo $domain['difficulty_level']; ?>">
                            <?php echo ucfirst($domain['difficulty_level']); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($domain['domain_description']): ?>
                    <div class="domain-description"><?php echo htmlspecialchars($domain['domain_description']); ?></div>
                <?php endif; ?>
                
                <div class="domain-prompt">
                    <?php echo htmlspecialchars(substr($domain['prompt_template'], 0, 200)) . '...'; ?>
                </div>
                
                <div class="domain-actions">
                    <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($domain); ?>)'>
                        ✏️ Edit
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="domain_id" value="<?php echo $domain['id']; ?>">
                        <button type="submit" class="btn-toggle">
                            <?php echo $domain['is_active'] ? '🔴 Deactivate' : '🟢 Activate'; ?>
                        </button>
                    </form>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this domain?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="domain_id" value="<?php echo $domain['id']; ?>">
                        <button type="submit" class="btn-delete">🗑️ Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($domains)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #999;">
                <p style="font-size: 48px; margin-bottom: 10px;">🎯</p>
                <p>No interview domains configured yet.</p>
                <p><a href="../setup_interview_domains.php">Run setup</a> to add default domains.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Domain Modal -->
<div class="domain-modal" id="domainModal">
    <div class="modal-content">
        <h2 id="modalTitle">Add New Interview Domain</h2>
        <form method="POST" id="domainForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="domain_id" id="domainId">
            
            <div class="form-group">
                <label>Domain Name *</label>
                <input type="text" name="domain_name" id="domainName" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="domain_description" id="domainDescription" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Icon (Emoji)</label>
                <input type="text" name="icon" id="domainIcon" value="💼" maxlength="10">
            </div>
            
            <div class="form-group">
                <label>Difficulty Level *</label>
                <select name="difficulty_level" id="difficultyLevel" required>
                    <option value="beginner">Beginner</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="advanced">Advanced</option>
                    <option value="expert">Expert</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>AI Prompt Template *</label>
                <textarea name="prompt_template" id="promptTemplate" required placeholder="You are an expert HR interviewer...

Use {resume_context} placeholder to insert candidate's profile.
Use [ANSWER] placeholder where candidate's answer will be inserted."></textarea>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="isActive" checked>
                    <label for="isActive" style="margin: 0;">Active (available for interviews)</label>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Domain</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Interview Domain';
    document.getElementById('formAction').value = 'add';
    document.getElementById('domainForm').reset();
    document.getElementById('domainModal').classList.add('active');
}

function openEditModal(domain) {
    document.getElementById('modalTitle').textContent = 'Edit Interview Domain';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('domainId').value = domain.id;
    document.getElementById('domainName').value = domain.domain_name;
    document.getElementById('domainDescription').value = domain.domain_description || '';
    document.getElementById('domainIcon').value = domain.icon;
    document.getElementById('difficultyLevel').value = domain.difficulty_level;
    document.getElementById('promptTemplate').value = domain.prompt_template;
    document.getElementById('isActive').checked = domain.is_active == 1;
    document.getElementById('domainModal').classList.add('active');
}

function closeModal() {
    document.getElementById('domainModal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('domainModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
