<?php
require_once __DIR__ . '/../config.php';
require_role('admin');

// Ensure tables exist (idempotent)
$errors = [];
$messages = [];

$sqlAdminGolden = "CREATE TABLE IF NOT EXISTS admin_golden_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  content TEXT NOT NULL,
  chapter_id INT DEFAULT NULL,
  module_id INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_agp_chapter_mc FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
  CONSTRAINT fk_agp_module_mc FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sqlFAQs = "CREATE TABLE IF NOT EXISTS faqs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(500) NOT NULL,
  answer TEXT NOT NULL,
  chapter_id INT DEFAULT NULL,
  module_id INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  display_order INT DEFAULT 0,
  CONSTRAINT fk_faq_chapter FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
  CONSTRAINT fk_faq_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
  CONSTRAINT fk_faq_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sqlChapters = "CREATE TABLE IF NOT EXISTS chapters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  description TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sqlModules = "CREATE TABLE IF NOT EXISTS modules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chapter_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  youtube_url VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_modules_chapter FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!$mysqli->query($sqlChapters)) { $errors[] = 'Create chapters failed: ' . $mysqli->error; }
if (!$mysqli->query($sqlModules)) { $errors[] = 'Create modules failed: ' . $mysqli->error; }
if (!$mysqli->query($sqlAdminGolden)) { $errors[] = 'Create admin_golden_points failed: ' . $mysqli->error; }
if (!$mysqli->query($sqlFAQs)) { $errors[] = 'Create faqs failed: ' . $mysqli->error; }

// Ensure faqs_assigned exists (for optional direct assignment)
$mysqli->query("CREATE TABLE IF NOT EXISTS faqs_assigned (
  id INT AUTO_INCREMENT PRIMARY KEY,
  faq_id INT NOT NULL,
  student_id INT NOT NULL,
  chapter_id INT DEFAULT NULL,
  module_id INT DEFAULT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_faq_student (faq_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// Handle create chapter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_chapter') {
    $title = trim($_POST['chapter_title'] ?? '');
    $desc = trim($_POST['chapter_description'] ?? '');
    $continueManage = isset($_POST['continue_manage']) && $_POST['continue_manage'] === '1';
    if ($title === '') {
        $errors[] = 'Chapter title is required.';
    } else {
        $stmt = $mysqli->prepare('INSERT INTO chapters (title, description) VALUES (?, ?)');
        if ($stmt) {
            $stmt->bind_param('ss', $title, $desc);
			if ($stmt->execute()) {
				$messages[] = 'Chapter created.';
				$newId = (int)$mysqli->insert_id;
				if ($continueManage && $newId > 0) {
					redirect_to('manage_content.php?open_chapter=' . $newId);
				}
			}
            else { $errors[] = 'Failed to create chapter: ' . $stmt->error; }
            $stmt->close();
        } else { $errors[] = 'Prepare failed: ' . $mysqli->error; }
    }
}

// Handle create module
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_module') {
    $chapterId = (int)($_POST['module_chapter_id'] ?? 0);
    $title = trim($_POST['module_title'] ?? '');
    $url = trim($_POST['module_youtube_url'] ?? '');
    $desc = trim($_POST['module_description'] ?? '');
    if ($chapterId <= 0 || $title === '') {
        $errors[] = 'Module title and chapter are required.';
    } else {
        $stmt = $mysqli->prepare('INSERT INTO modules (chapter_id, title, youtube_url, description) VALUES (?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('isss', $chapterId, $title, $url, $desc);
            if ($stmt->execute()) {
                $messages[] = 'Module created.';
                // Auto-assign the newly created module to students who have chapter-level assignment
                $newModuleId = (int)$mysqli->insert_id;
                if ($newModuleId > 0) {
                    $adminId = (int)($_SESSION['user_id'] ?? 0);
                    $bulk = $mysqli->prepare('INSERT INTO assignments (student_id, chapter_id, module_id, assigned_by)
                        SELECT a.student_id, m.chapter_id, m.id, ?
                        FROM modules m
                        INNER JOIN assignments a ON a.chapter_id = m.chapter_id AND a.module_id IS NULL
                        WHERE m.id = ?
                          AND NOT EXISTS (
                              SELECT 1 FROM assignments a2 WHERE a2.student_id = a.student_id AND a2.module_id = m.id
                          )');
                    if ($bulk) {
                        $bulk->bind_param('ii', $adminId, $newModuleId);
                        $bulk->execute();
                        $bulk->close();
                    }
                }
            }
            else { $errors[] = 'Failed to create module: ' . $stmt->error; }
            $stmt->close();
        } else { $errors[] = 'Prepare failed: ' . $mysqli->error; }
    }
}

// Handle inline create golden point
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_golden_point_inline') {
	$title = trim($_POST['gp_title'] ?? '');
	$content = trim($_POST['gp_content'] ?? '');
	$chapterId = (int)($_POST['gp_chapter_id'] ?? 0);
	$moduleId = isset($_POST['gp_module_id']) && $_POST['gp_module_id'] !== '' ? (int)$_POST['gp_module_id'] : null;
	if ($title === '' || $content === '' || $chapterId <= 0) {
		$errors[] = 'Golden point requires title, content and chapter.';
	} else {
		$stmt = $mysqli->prepare('INSERT INTO admin_golden_points (title, content, chapter_id, module_id, created_by) VALUES (?, ?, ?, ?, ?)');
		if ($stmt) {
			$creator = (int)($_SESSION['user_id'] ?? 0);
			$stmt->bind_param('ssiii', $title, $content, $chapterId, $moduleId, $creator);
			if ($stmt->execute()) { $messages[] = 'Golden point added.'; }
			else { $errors[] = 'Failed to add golden point: ' . $stmt->error; }
			$stmt->close();
		} else { $errors[] = 'Prepare failed: ' . $mysqli->error; }
	}
}

// Handle create FAQ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_faq') {
	$question = trim($_POST['faq_question'] ?? '');
	$answer = trim($_POST['faq_answer'] ?? '');
	$chapterId = isset($_POST['faq_chapter_id']) && $_POST['faq_chapter_id'] !== '' ? (int)$_POST['faq_chapter_id'] : null;
	$moduleId = isset($_POST['faq_module_id']) && $_POST['faq_module_id'] !== '' ? (int)$_POST['faq_module_id'] : null;
	$displayOrder = (int)($_POST['faq_display_order'] ?? 0);
	
	if ($question === '' || $answer === '') {
		$errors[] = 'FAQ question and answer are required.';
	} elseif ($chapterId <= 0 && $moduleId <= 0) {
		$errors[] = 'FAQ must be associated with either a chapter or module.';
	} else {
		$stmt = $mysqli->prepare('INSERT INTO faqs (question, answer, chapter_id, module_id, created_by, display_order) VALUES (?, ?, ?, ?, ?, ?)');
		if ($stmt) {
			$creator = (int)($_SESSION['user_id'] ?? 0);
			$stmt->bind_param('ssiiii', $question, $answer, $chapterId, $moduleId, $creator, $displayOrder);
			if ($stmt->execute()) { $messages[] = 'FAQ added successfully.'; }
			else { $errors[] = 'Failed to add FAQ: ' . $stmt->error; }
			$stmt->close();
		} else { $errors[] = 'Prepare failed: ' . $mysqli->error; }
	}
}

// Load chapters and modules
$chapters = [];
$res = $mysqli->query('SELECT id, title, description, created_at FROM chapters ORDER BY id DESC');
if ($res) {
    while ($row = $res->fetch_assoc()) { 
        $chapters[] = $row; 
    }
    $res->free();
}

// Map chapter_id => modules
$modulesByChapter = [];
$res2 = $mysqli->query('SELECT id, chapter_id, title, youtube_url, description, created_at FROM modules ORDER BY id DESC');
if ($res2) {
    while ($m = $res2->fetch_assoc()) {
        $cid = (int)$m['chapter_id'];
        if (!isset($modulesByChapter[$cid])) { $modulesByChapter[$cid] = []; }
        $modulesByChapter[$cid][] = $m;
    }
    $res2->free();
}

// Load students list (for assigning FAQs directly)
$students = [];
$resS = $mysqli->query("SELECT id, username, full_name FROM users WHERE role='student' ORDER BY username ASC");
if ($resS) { while ($r = $resS->fetch_assoc()) { $students[] = $r; } $resS->free(); }

include __DIR__ . '/../includes/partials/header.php';
?>

<div class="card glass fade-in">
    <a href="index.php" style="float:right;">&larr; Back to Dashboard</a>
    <h2 style="margin-top:0;">Chapters & Modules</h2>
    <p style="color:#7a6e63;">Create chapters and add modules with YouTube links and descriptions.</p>
</div>

<!-- Quick Actions: Three Cards -->
<div class="mt-24">
  <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
    <div class="card glass link" id="openManualCreate" style="cursor:pointer; padding:24px;">
      <h3 style="margin:0 0 8px 0;">Created by Me</h3>
      <p style="margin:0; color:var(--muted);">Create chapter, then add modules, golden points, and YouTube links.</p>
    </div>
    <div class="card glass link" id="openAIQuickCreate" style="cursor:pointer; padding:24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
      <h3 style="margin:0 0 8px 0; color:white;">Created by AI</h3>
      <p style="margin:0; color:rgba(255,255,255,0.85);">Enter names; we auto-create chapter and module. Add YouTube later.</p>
    </div>
    <div class="card glass link" id="openAIFaqModal" style="cursor:pointer; padding:24px; background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white;">
      <h3 style="margin:0 0 8px 0; color:white;">FAQs by AI</h3>
      <p style="margin:0; color:rgba(255,255,255,0.9);">Generate questions for a chapter/module. Assignment happens in Create Assignment.</p>
    </div>
  </div>
  <div style="margin-top:8px; color:var(--muted); font-size:13px;">Use these quick actions, or scroll to manage existing chapters.</div>
  <hr style="margin:16px 0; border:none; border-top:1px solid var(--border);">
</div>

<?php if (!empty($messages)): ?>
<div class="alert success mt-16">
    <ul>
        <?php foreach ($messages as $m): ?><li><?php echo htmlspecialchars($m); ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert error mt-16">
    <ul>
        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Chapters list removed; manage chapters in manage_manual.php -->

<!-- Removed inline Chapter management modal; now handled in manage_manual.php -->

<!-- Removed New Chapter modal; manual creation is in manage_manual.php -->

<!-- Admin AI Assistant Modal -->
<div id="adminAIAssistantModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
    <div class="card glass" style="width:min(900px, 95vw); max-height:90vh; overflow:auto; position:relative;">
        <button id="closeAdminAIModal" class="btn" style="position:absolute; top:12px; right:12px;">✕</button>
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
            <div style="width:48px; height:48px; border-radius:50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display:flex; align-items:center; justify-content:center; color:white; font-size:24px;">🤖</div>
            <div>
                <h3 style="margin:0; font-size:24px; font-weight:700;">AI Content Generator</h3>
                <p style="margin:4px 0 0 0; color:var(--muted);">Generate comprehensive content for chapters and modules</p>
            </div>
        </div>
        
        <div id="adminAIChatContainer" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 16px; background: var(--bg-secondary);">
            <div id="adminAIMessages" style="min-height: 150px;">
                <div class="ai-message" style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 14px;">🤖</div>
                    <div style="flex: 1;">
                        <div style="background: var(--bg-primary); padding: 12px; border-radius: 12px; border: 1px solid var(--border);">
                            <p style="margin: 0; color: var(--text);">Hello Admin! I can help you generate comprehensive content for chapters and modules. Just provide the chapter name and/or module name, and I'll create detailed educational content with learning objectives, assessment methods, and resources.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="ai-input-container" style="display: flex; gap: 8px; margin-bottom: 20px;">
            <input type="text" id="adminAIChapterInput" placeholder="Chapter name (optional)" style="flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-primary); color: var(--text);">
            <input type="text" id="adminAIModuleInput" placeholder="Module name (optional)" style="flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-primary); color: var(--text);">
            <button id="adminAIGenerateBtn" class="btn" style="padding: 12px 20px; white-space: nowrap; background: var(--gradient-primary);">Generate</button>
        </div>
        
        <div id="adminAIResults" style="margin-top: 20px; display: none;">
            <div class="ai-result-section" style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <h4 style="margin: 0 0 12px 0; color: var(--text); font-size: 18px;">Generated Content</h4>
                <div id="adminAIGeneratedContent"></div>
            </div>
            
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button id="adminAIUseContentBtn" class="btn" style="background: var(--gradient-primary);">Use This Content</button>
                <button id="adminAIRegenerateBtn" class="btn" style="background: var(--bg-secondary); color: var(--text); border: 1px solid var(--border);">Regenerate</button>
            </div>
        </div>
    </div>
</div>

<script>

(function(){
	// Quick action cards only
	var manualBtn = document.getElementById('openManualCreate');
	if (manualBtn) manualBtn.addEventListener('click', function(){ window.location.href = '../manage_manual.php'; });
	var aiQuick = document.getElementById('openAIQuickCreate');
	if (aiQuick) aiQuick.addEventListener('click', function(){ window.location.href = '../manage_ai.php'; });
	var aiFaqBtn = document.getElementById('openAIFaqModal');
	if (aiFaqBtn) aiFaqBtn.addEventListener('click', function(){ var m=document.getElementById('aiFaqModal'); if(m){ m.style.display='flex'; } });
	
	// Admin AI Assistant functionality
	let currentAdminAIContent = null;
	
	// Open AI Assistant Modal
	var openAdminAIBtn = document.getElementById('openAdminAIAssistant');
	if (openAdminAIBtn) {
		openAdminAIBtn.addEventListener('click', function(){
			document.getElementById('adminAIAssistantModal').style.display = 'flex';
		});
	}
	
	// Close AI Assistant Modal
	var closeAdminAIBtn = document.getElementById('closeAdminAIModal');
	if (closeAdminAIBtn) {
		closeAdminAIBtn.addEventListener('click', function(){
			closeModal('adminAIAssistantModal');
		});
	}
	
	// Generate AI Content
	var adminAIGenerateBtn = document.getElementById('adminAIGenerateBtn');
	if (adminAIGenerateBtn) {
		adminAIGenerateBtn.addEventListener('click', function(){
		const chapterName = document.getElementById('adminAIChapterInput').value.trim();
		const moduleName = document.getElementById('adminAIModuleInput').value.trim();
		
		if (!chapterName && !moduleName) {
			alert('Please enter at least a chapter name or module name.');
			return;
		}
		
		// Show loading state
		const btn = document.getElementById('adminAIGenerateBtn');
		const originalText = btn.textContent;
		btn.textContent = 'Generating...';
		btn.disabled = true;
		
		// Add user message to chat
		addAdminAIMessage('user', `Generate content for: ${chapterName ? 'Chapter: ' + chapterName : ''} ${moduleName ? 'Module: ' + moduleName : ''}`);
		
		// Call AI API
		fetch('../api/ai_chatbot.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({
				chapter_name: chapterName,
				module_name: moduleName
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				currentAdminAIContent = data.data;
				displayAdminAIContent(data.data);
				document.getElementById('adminAIResults').style.display = 'block';
			} else {
				addAdminAIMessage('ai', 'Sorry, I encountered an error: ' + (data.error || 'Unknown error'));
			}
		})
		.catch(error => {
			addAdminAIMessage('ai', 'Sorry, I encountered an error: ' + error.message);
		})
		.finally(() => {
			btn.textContent = originalText;
			btn.disabled = false;
		});
		});
	}
	
	// Use Generated Content
	var adminAIUseContentBtn = document.getElementById('adminAIUseContentBtn');
	if (adminAIUseContentBtn) {
		adminAIUseContentBtn.addEventListener('click', function(){
			if (currentAdminAIContent) {
				// Auto-fill the chapter and module forms with generated content
				if (currentAdminAIContent.chapter) {
					document.getElementById('nc_title').value = currentAdminAIContent.chapter.title;
					document.getElementById('nc_desc').value = currentAdminAIContent.chapter.description;
				}
				if (currentAdminAIContent.module) {
					// Store module content for later use when creating modules
					sessionStorage.setItem('adminAIModuleContent', JSON.stringify(currentAdminAIContent.module));
				}
				
				// Close AI modal and open appropriate form
				closeModal('adminAIAssistantModal');
				if (currentAdminAIContent.chapter) {
					openNewChapterModal();
				}
			}
		});
	}
	
	// Regenerate Content
	var adminAIRegenerateBtn = document.getElementById('adminAIRegenerateBtn');
	if (adminAIRegenerateBtn) {
		adminAIRegenerateBtn.addEventListener('click', function(){
			var genBtn = document.getElementById('adminAIGenerateBtn');
			if (genBtn) genBtn.click();
		});
	}
	
	function addAdminAIMessage(type, message) {
		const messagesContainer = document.getElementById('adminAIMessages');
		const messageDiv = document.createElement('div');
		messageDiv.className = 'ai-message';
		messageDiv.style.cssText = 'display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px;';
		
		if (type === 'user') {
			messageDiv.innerHTML = `
				<div style="flex: 1; text-align: right;">
					<div style="background: var(--gradient-primary); color: white; padding: 12px; border-radius: 12px; display: inline-block; max-width: 80%;">
						<p style="margin: 0;">${message}</p>
					</div>
				</div>
				<div style="width: 32px; height: 32px; border-radius: 50%; background: var(--gradient-primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 14px;">👤</div>
			`;
		} else {
			messageDiv.innerHTML = `
				<div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 14px;">🤖</div>
				<div style="flex: 1;">
					<div style="background: var(--bg-primary); padding: 12px; border-radius: 12px; border: 1px solid var(--border);">
						<p style="margin: 0; color: var(--text);">${message}</p>
					</div>
				</div>
			`;
		}
		
		messagesContainer.appendChild(messageDiv);
		messagesContainer.scrollTop = messagesContainer.scrollHeight;
	}
	
	function displayAdminAIContent(content) {
		const container = document.getElementById('adminAIGeneratedContent');
		let html = '';
		
		if (content.chapter) {
			html += '<div style="margin-bottom: 20px; padding: 16px; background: var(--bg-primary); border-radius: 8px; border: 1px solid var(--border);">';
			html += '<h5 style="color: var(--text); margin: 0 0 10px 0; font-size: 18px;">📚 Chapter: ' + content.chapter.title + '</h5>';
			html += '<p style="margin: 0 0 15px 0; color: var(--muted); line-height: 1.5;">' + content.chapter.description + '</p>';
			
			html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 15px;">';
			html += '<div><strong>Learning Objectives:</strong><ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 14px;">';
			content.chapter.learning_objectives.forEach(objective => {
				html += '<li style="margin: 4px 0;">' + objective + '</li>';
			});
			html += '</ul></div>';
			
			html += '<div><strong>Assessment Methods:</strong><ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 14px;">';
			content.chapter.assessment_methods.forEach(method => {
				html += '<li style="margin: 4px 0;">' + method + '</li>';
			});
			html += '</ul></div></div>';
			
			html += '<div style="background: var(--bg-secondary); padding: 12px; border-radius: 6px; margin-top: 10px;">';
			html += '<strong>Key Points:</strong><ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 14px;">';
			content.chapter.key_points.forEach(point => {
				html += '<li style="margin: 4px 0;">' + point + '</li>';
			});
			html += '</ul></div></div>';
		}
		
		if (content.module) {
			html += '<div style="margin-bottom: 20px; padding: 16px; background: var(--bg-primary); border-radius: 8px; border: 1px solid var(--border);">';
			html += '<h5 style="color: var(--text); margin: 0 0 10px 0; font-size: 18px;">🎯 Module: ' + content.module.title + '</h5>';
			html += '<p style="margin: 0 0 15px 0; color: var(--muted); line-height: 1.5;">' + content.module.description + '</p>';
			
			html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 15px;">';
			html += '<div><strong>Content Sections:</strong><ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 14px;">';
			content.module.content_sections.forEach(section => {
				html += '<li style="margin: 4px 0;">' + section + '</li>';
			});
			html += '</ul></div>';
			
			html += '<div><strong>Learning Activities:</strong><ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 14px;">';
			content.module.learning_activities.forEach(activity => {
				html += '<li style="margin: 4px 0;">' + activity + '</li>';
			});
			html += '</ul></div></div>';
			
			html += '<div style="background: var(--bg-secondary); padding: 12px; border-radius: 6px; margin-top: 10px;">';
			html += '<strong>Assessment Criteria:</strong><ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 14px;">';
			content.module.assessment_criteria.forEach(criteria => {
				html += '<li style="margin: 4px 0;">' + criteria + '</li>';
			});
			html += '</ul></div></div>';
		}
		
		container.innerHTML = html;
	}

	// Auto-open chapter modal when redirected after creating chapter
	if (openParam && Number(openParam) > 0) {
		var card = document.querySelector('.open-chapter[data-chapter-id="' + Number(openParam) + '"]');
		if (card) {
			openChapterModal(String(openParam), card.querySelector('h4') ? card.querySelector('h4').textContent : '');
		}
	}

	// AI FAQ Generation functionality
	var generateAIFAQsBtn = document.getElementById('generateAIFAQs');
	if (generateAIFAQsBtn) {
		generateAIFAQsBtn.addEventListener('click', function() {
		var chapterId = document.getElementById('faq_chapter_id').value;
		var moduleId = document.getElementById('ai_faq_module_id').value;
		var numQuestions = document.getElementById('ai_faq_count').value;
		
		console.log('AI FAQ Generation Debug:', {
			chapterId: chapterId,
			moduleId: moduleId,
			numQuestions: numQuestions
		});
		
		if (!chapterId) {
			alert('Please select a chapter first');
			return;
		}
		
		// Show loading state
		document.getElementById('ai_faq_loading').style.display = 'block';
		document.getElementById('ai_faq_results').style.display = 'none';
		document.getElementById('generateAIFAQs').disabled = true;
		
		// Generate FAQs
		fetch('../ai_faq_generator.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({
				chapter_id: chapterId,
				module_id: moduleId || null,
				num_questions: parseInt(numQuestions)
			})
		})
		.then(response => {
			console.log('AI FAQ Generator Response Status:', response.status);
			return response.json();
		})
		.then(data => {
			console.log('AI FAQ Generator Response Data:', data);
			document.getElementById('ai_faq_loading').style.display = 'none';
			document.getElementById('generateAIFAQs').disabled = false;
			
			if (data.success) {
				displayGeneratedFAQs(data.data);
			} else {
				alert('Error generating FAQs: ' + (data.error || 'Unknown error'));
			}
		})
		.catch(error => {
			console.error('AI FAQ Generator Error:', error);
			document.getElementById('ai_faq_loading').style.display = 'none';
			document.getElementById('generateAIFAQs').disabled = false;
			alert('Error generating FAQs: ' + error.message);
		});
		});
	}
	
	function displayGeneratedFAQs(data) {
		var resultsDiv = document.getElementById('ai_faq_results');
		var html = '<div style="background: var(--bg-secondary); padding: 16px; border-radius: 8px; border: 1px solid var(--border);">';
		html += '<h5 style="margin: 0 0 12px 0; color: var(--text);">🤖 Generated FAQs (' + data.generated_count + ')</h5>';
		
		data.faqs.forEach(function(faq, index) {
			html += '<div style="margin-bottom: 12px; padding: 12px; background: var(--bg-primary); border-radius: 6px; border-left: 3px solid var(--primary);">';
			html += '<div style="font-weight: 600; margin-bottom: 6px; color: var(--text);">Q' + (index + 1) + ': ' + escapeHtml(faq.question) + '</div>';
			html += '<div style="color: var(--muted); line-height: 1.5;">A: ' + escapeHtml(faq.answer) + '</div>';
			html += '</div>';
		});
		
		html += '<div style="margin-top: 16px; display: flex; gap: 8px;">';
		html += '<button class="btn" id="saveFAQsBtn" style="background: var(--success);">💾 Save All FAQs</button>';
		html += '<button class="btn" onclick="document.getElementById(\'ai_faq_results\').style.display=\'none\'" style="background: var(--muted);">❌ Cancel</button>';
		html += '</div>';
		html += '</div>';
		
		resultsDiv.innerHTML = html;
		resultsDiv.style.display = 'block';
		
		// Add event listener for save button
		document.getElementById('saveFAQsBtn').addEventListener('click', function() {
			saveGeneratedFAQs(data);
		});
	}
	
	function saveGeneratedFAQs(data) {
		if (!confirm('Are you sure you want to save all ' + data.faqs.length + ' generated FAQs?')) {
			return;
		}
		
		console.log('Saving FAQs with data:', data);
		
		fetch('../api/ai_faq_saver.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({
				faqs: data.faqs,
				chapter_id: data.chapter.id,
				module_id: data.module ? data.module.id : null
			})
		})
		.then(response => {
			console.log('FAQ Saver Response Status:', response.status);
			return response.json();
		})
		.then(result => {
			console.log('FAQ Saver Response Data:', result);
			if (result.success) {
				alert('Successfully saved ' + result.saved_count + ' FAQ(s)!');
				document.getElementById('ai_faq_results').style.display = 'none';
				// Refresh the page to show updated FAQs
				location.reload();
			} else {
				alert('Error saving FAQs: ' + (result.error || 'Unknown error'));
			}
		})
		.catch(error => {
			console.error('FAQ Saver Error:', error);
			alert('Error saving FAQs: ' + error.message);
		});
	}
	
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}
})();
</script>

<!-- Manual Create Modal -->
<div id="manualCreateModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
  <div class="card glass" style="width:min(800px, 95vw); max-height:90vh; overflow:auto; position:relative;">
    <button class="btn" onclick="document.getElementById('manualCreateModal').style.display='none'" style="position:absolute; top:12px; right:12px;">✕</button>
    <h3 style="margin:0 0 12px 0;">Created by Me</h3>
    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 16px;">
      <div class="card glass" style="margin:0;">
        <h4 style="margin:0 0 8px 0;">Add Chapter</h4>
        <form method="post">
          <input type="hidden" name="action" value="create_chapter">
          <div class="field">
            <label>Title</label>
            <input type="text" name="chapter_title" required>
          </div>
          <div class="field">
            <label>Description</label>
            <input type="text" name="chapter_description">
          </div>
          <button class="btn" type="submit">Add Chapter</button>
        </form>
      </div>
      <div class="card glass" style="margin:0;">
        <h4 style="margin:0 0 8px 0;">Add Module</h4>
        <form method="post">
          <input type="hidden" name="action" value="create_module">
          <div class="field">
            <label>Chapter</label>
            <select name="module_chapter_id" required>
              <option value="">-- Select Chapter --</option>
              <?php foreach ($chapters as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Title</label>
            <input type="text" name="module_title" required>
          </div>
          <div class="field">
            <label>YouTube URL</label>
            <input type="text" name="module_youtube_url" placeholder="https://www.youtube.com/watch?v=...">
          </div>
          <div class="field">
            <label>Description</label>
            <input type="text" name="module_description">
          </div>
          <button class="btn" type="submit">Add Module</button>
        </form>
      </div>
    </div>
  </div>
  
  
</div>

<!-- AI FAQs Modal -->
<div id="aiFaqModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
  <div class="card glass" style="width:min(600px, 92vw); position:relative;">
    <button class="btn" onclick="document.getElementById('aiFaqModal').style.display='none'" style="position:absolute; top:12px; right:12px;">✕</button>
    <h3 style="margin:0 0 12px 0;">FAQs by AI</h3>
    <form method="post">
      <input type="hidden" name="action" value="generate_ai_faqs">
      <div class="field">
        <label>Chapter</label>
        <select name="gaif_chapter_id" id="gaif_chapter_id" required>
          <option value="">-- Select Chapter --</option>
          <?php foreach ($chapters as $c): ?>
            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Module (optional)</label>
        <select name="gaif_module_id" id="gaif_module_id">
          <option value="">-- Chapter Wide --</option>
        </select>
      </div>
      <div class="field">
        <label>Number of FAQs</label>
        <select name="gaif_count">
          <option value="3">3</option>
          <option value="5" selected>5</option>
          <option value="8">8</option>
          <option value="10">10</option>
        </select>
      </div>
      <button class="btn" type="submit" style="background:linear-gradient(135deg, #ff9800 0%, #f57c00 100%);">Generate FAQs</button>
    </form>
  </div>
</div>

<!-- AI Quick Create (Chapter & Module by names) -->
<div id="aiQuickCreateModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
  <div class="card glass" style="width:min(560px, 90vw); position:relative;">
    <button class="btn" onclick="document.getElementById('aiQuickCreateModal').style.display='none'" style="position:absolute; top:12px; right:12px;">✕</button>
    <h3 style="margin:0 0 12px 0;">Created by AI</h3>
    <form method="post">
      <input type="hidden" name="action" value="ai_create_from_generator">
      <div class="field">
        <label>Chapter Name</label>
        <input type="text" name="ai_chapter_title" required>
      </div>
      <div class="field">
        <label>Module Name</label>
        <input type="text" name="ai_module_title" required>
      </div>
      <p style="color:var(--muted);">We will create the chapter and module. You can add YouTube later from the Manage Chapter modal.</p>
      <button class="btn" type="submit" style="background:var(--gradient-primary);">Create</button>
    </form>
  </div>
</div>

<script>
// Link chapter -> module select in AI FAQs modal
(function(){
  var map = <?php echo json_encode($modulesByChapter ?: []); ?>;
  var chapSel = document.getElementById('gaif_chapter_id');
  var modSel = document.getElementById('gaif_module_id');
  if (chapSel && modSel) {
    chapSel.addEventListener('change', function(){
      var cid = this.value;
      var mods = map[String(cid)] || [];
      modSel.innerHTML = '<option value="">-- Chapter Wide --</option>' + mods.map(function(m){ return '<option value="' + Number(m.id) + '\">' + (m.title || 'Untitled') + '</option>'; }).join('');
    });
  }
})();
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>


