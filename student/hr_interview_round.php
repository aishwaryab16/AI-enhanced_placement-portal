<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$interview_id = isset($_GET['interview_id']) ? (int)$_GET['interview_id'] : 0;
$company = $_GET['company'] ?? '';
$role = $_GET['role'] ?? '';
$round = $_GET['round'] ?? 'HR Round';

$defaultBg = '../assets/images/ChatGPT Image Nov 12, 2025, 07_57_03 PM.png';
$backgroundUpload = __DIR__ . '/../uploads/hr_stage.jpg';
$backgroundUrl = file_exists($backgroundUpload)
    ? '../uploads/hr_stage.jpg'
    : $defaultBg;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Interview Simulation - <?php echo htmlspecialchars($company ?: 'Company'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter','Segoe UI',Tahoma,Arial,sans-serif;
            min-height: 100vh;
            color: #0f172a;
            background: url('<?php echo htmlspecialchars($backgroundUrl); ?>') center/cover no-repeat fixed;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(12, 16, 28, 0.7);
            backdrop-filter: blur(3px);
            z-index: 0;
        }
        .layout {
            position: relative;
            z-index: 1;
            max-width: 1280px;
            margin: 0;
            padding: 20px 0 20px 20px;
        }
        .header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            background: rgba(255,255,255,0.95);
            border: 1px solid rgba(226, 232, 240, 0.7);
            border-radius: 26px;
            padding: 26px 30px;
            box-shadow: 0 30px 80px rgba(15, 15, 17, 0.38);
        }
        .header h1 {
            margin: 6px 0 10px;
            font-size: clamp(28px, 3vw, 36px);
            letter-spacing: -0.01em;
            color: #1f2937;
        }
        .header p {
            margin: 0;
            color: #475569;
            max-width: 600px;
            line-height: 1.7;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: linear-gradient(120deg, #2563eb, #1d4ed8);
            color: #f8fafc;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.35);
        }
        .meta {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 12px;
            min-width: 220px;
        }
        .meta-card {
            background: rgba(248,250,252,0.94);
            border: 1px solid rgba(203, 213, 225, 0.8);
            border-radius: 16px;
            padding: 14px 18px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.18);
        }
        .meta-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #64748b;
        }
        .meta-value {
            font-weight: 600;
            font-size: 1rem;
            color: #1e293b;
        }
        .stage {
            margin-top: 60px;
            display: flex;
            justify-content: flex-start;
        }
        .chat-stack {
            display: flex;
            flex-direction: column;
            gap: 18px;
            max-width: 400px;
            width: 100%;
        }
        .chat-panel {
            display: flex;
            flex-direction: column;
            gap: 0;
            min-height: 55vh;
            max-height: 70vh;
            background: rgba(26, 26, 26, 0.75);
            border-radius: 0;
            padding: 0;
            border: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        .chat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            background: rgba(26, 26, 26, 0.6);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .chat-header h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        .chat-header .chevron {
            font-size: 0.9rem;
            color: #ffffff;
            opacity: 0.7;
            cursor: pointer;
        }
        .voice-toggle {
            border: none;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .voice-toggle.active {
            background: rgba(37, 99, 235, 0.3);
            color: #ffffff;
            border-color: rgba(37, 99, 235, 0.5);
        }
        .voice-toggle:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .chat-scroll {
            flex: 1;
            min-height: 200px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 16px 18px;
            background: rgba(26, 26, 26, 0.3);
        }
        .chat-scroll::-webkit-scrollbar {
            width: 8px;
        }
        .chat-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }
        .chat-scroll::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        .message {
            max-width: min(80%, 320px);
            padding: 10px 14px;
            border-radius: 8px;
            line-height: 1.5;
            display: inline-flex;
            flex-direction: column;
            gap: 4px;
            position: relative;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 300px;
            overflow-y: auto;
        }
        .message::-webkit-scrollbar {
            width: 4px;
        }
        .message::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
        }
        .message label {
            display: none;
        }
        .message.ai {
            align-self: flex-start;
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            padding: 12px 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .message.student {
            align-self: flex-end;
            background: #2563eb;
            color: #ffffff;
            border: none;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .typing-indicator {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 8px;
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            align-self: flex-start;
            font-size: 0.92rem;
            letter-spacing: 0.01em;
            color: #f1f5f9;
        }
        .typing-indicator.active {
            display: inline-flex;
        }
        .typing-indicator .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #bfdbfe;
            animation: pulse 1.2s infinite;
        }
        .typing-indicator .dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator .dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes pulse {
            0% { opacity: 0.2; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.1); }
            100% { opacity: 0.2; transform: scale(0.8); }
        }
        @keyframes murmur {
            0% { transform: translateY(0) scale(1); box-shadow: 0 20px 45px rgba(15, 23, 42, 0.35); }
            30% { transform: translateY(-1px) scale(0.996); box-shadow: 0 22px 48px rgba(15, 23, 42, 0.38); }
            70% { transform: translateY(1px) scale(1); box-shadow: 0 18px 40px rgba(15, 23, 42, 0.3); }
            100% { transform: translateY(0) scale(1); }
        }
        .message.ai.murmuring { animation: murmur 1.6s ease-in-out 2; }
        .composer {
            display: flex;
            flex-direction: row;
            gap: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 14px 18px;
            background: rgba(26, 26, 26, 0.6);
            align-items: center;
        }
        .composer textarea {
            flex: 1;
            min-height: 40px;
            max-height: 100px;
            padding: 10px 14px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(42, 42, 42, 0.8);
            color: #ffffff;
            resize: none;
            font-size: 0.9rem;
            line-height: 1.5;
            font-family: inherit;
            transition: all 0.2s ease;
        }
        .composer textarea::placeholder {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
        }
        .composer textarea:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.4);
            background: #2f2f2f;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn {
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            padding: 0;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            transition: all 0.2s ease;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn i {
            margin: 0;
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            box-shadow: none;
        }
        .btn-primary {
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
        }
        .btn-primary:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.5);
            background: #1d4ed8;
        }
        .btn-accent {
            display: none;
        }
        .btn-ghost {
            background: #10b981;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
        }
        .btn-ghost:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.5);
            background: #059669;
        }
        .btn-speaking {
            background: #ef4444 !important;
            color: #ffffff !important;
            animation: pulse-red 1.5s ease-in-out infinite;
        }
        @keyframes pulse-red {
            0%, 100% { box-shadow: 0 4px 16px rgba(239, 68, 68, 0.4); }
            50% { box-shadow: 0 4px 24px rgba(239, 68, 68, 0.7); }
        }
        .note-pad {
            background: rgba(255,255,255,0.95);
            border-radius: 18px;
            border: 1px solid rgba(228, 232, 240, 0.75);
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: #1f2937;
            box-shadow: 0 30px 70px rgba(15, 15, 15, 0.32);
        }
        .note-pad label {
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
        }
        .note-pad textarea {
            min-height: 96px;
            border-radius: 16px;
            border: 1px solid rgba(203,213,225,0.75);
            background: #f8fafc;
            color: #0f172a;
            padding: 12px 14px;
            resize: vertical;
            font-family: inherit;
            line-height: 1.5;
        }
        .note-pad textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.18);
        }
        .tips {
            font-size: 0.9rem;
            color: #475569;
            line-height: 1.6;
        }
        .tips strong {
            color: #2563eb;
        }
        .bottom-controls {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            align-items: center;
            z-index: 1000;
        }
        .bottom-controls .btn {
            width: 56px;
            height: 56px;
            font-size: 1.2rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }
        .bottom-controls .btn-finish {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #ffffff;
        }
        .bottom-controls .btn-finish:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
            background: linear-gradient(135deg, #b91c1c, #991b1b);
        }
        @media (max-width: 860px) {
            .chat-panel {
                padding: 22px;
            }
            .message {
                max-width: 100%;
            }
        }
        @media (max-width: 720px) {
            .layout {
                padding: 32px 18px 120px;
            }
            .meta {
                width: 100%;
            }
            .stage {
                justify-content: center;
            }
            .chat-panel {
                border-radius: 0;
            }
            .bottom-controls {
                bottom: 20px;
                gap: 12px;
            }
            .bottom-controls .btn {
                width: 50px;
                height: 50px;
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <div class="stage">
            <div class="chat-stack">
                <div class="chat-panel">
                    <div class="chat-header">
                        <h2>AI Assistant <i class="fas fa-chevron-down chevron"></i></h2>
                        <button id="btnVoice" class="voice-toggle active" type="button">
                            <i class="fas fa-volume-up"></i> Voice On
                        </button>
                    </div>

                    <div id="chatScroll" class="chat-scroll">
                        <!-- Messages inserted here -->
                        <div id="typingIndicator" class="typing-indicator">
                            <div class="dot"></div>
                            <div class="dot"></div>
                            <div class="dot"></div>
                            <span>HR interviewer is preparing...</span>
                        </div>
                    </div>

                    <div class="composer">
                        <textarea id="userInput" placeholder="Type your message...." autocomplete="off"></textarea>
                        <div class="action-buttons">
                            <button id="btnSend" class="btn btn-primary" type="button" title="Send your message">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="note-pad" style="display: none;">
                    <label for="notePad"><i class="fas fa-pencil-alt"></i> Personal notes (optional)</label>
                    <textarea id="notePad" placeholder="Capture key takeaways, feedback points, or questions you want to revisit. These notes are just for you and will be summarised when you finish."></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-controls">
        <button id="btnSpeak" class="btn btn-ghost" type="button" title="Use voice input">
            <i class="fas fa-microphone"></i>
        </button>
        <button id="btnFinish" class="btn btn-finish" type="button" disabled title="Complete the interview round">
            <i class="fas fa-flag-checkered"></i>
        </button>
    </div>

    <script>
    const interviewId = <?php echo (int)$interview_id; ?>;
    const company = <?php echo json_encode($company); ?>;
    const role = <?php echo json_encode($role); ?>;
    const roundTitle = <?php echo json_encode($round); ?>;

    const chatScroll = document.getElementById('chatScroll');
    const typingIndicator = document.getElementById('typingIndicator');
    const userInput = document.getElementById('userInput');
    const btnSend = document.getElementById('btnSend');
    const btnSpeak = document.getElementById('btnSpeak');
    const btnVoice = document.getElementById('btnVoice');
    const btnFinish = document.getElementById('btnFinish');
    const notePad = document.getElementById('notePad');

    const conversation = [];
    let isWaiting = false;
    let recognition = null;
    let isListening = false;
    const recognitionAvailable = ('SpeechRecognition' in window) || ('webkitSpeechRecognition' in window);
    const ttsAvailable = ('speechSynthesis' in window) && ('SpeechSynthesisUtterance' in window);
    let voiceEnabled = ttsAvailable;
    let narratorVoice = null;
    let userInteracted = false;
    let pendingSpeech = null;

    function initVoices() {
        if (!ttsAvailable) return;
        const pickVoice = () => {
            const voices = window.speechSynthesis.getVoices();
            if (!voices.length) {
                // If no voices yet, try again after a short delay
                setTimeout(pickVoice, 100);
                return;
            }
            const englishVoices = voices.filter(v => v.lang && v.lang.toLowerCase().startsWith('en'));
            narratorVoice = englishVoices.find(v => /Female|Woman/i.test(v.name)) || englishVoices[0] || voices[0];
            console.log('Voice initialized:', narratorVoice ? narratorVoice.name : 'No voice found');
        };
        pickVoice();
        if (window.speechSynthesis.onvoiceschanged !== undefined) {
            window.speechSynthesis.onvoiceschanged = pickVoice;
        }
    }

    function speakAI(text) {
        if (!voiceEnabled || !ttsAvailable) {
            console.log('Speech disabled or not available');
            return;
        }
        if (!text || !text.trim()) {
            console.log('No text to speak');
            return;
        }
        
        // Clean the text - remove "Interviewer:", "HR INTERVIEWER:", etc. prefixes
        let cleanText = text.trim();
        cleanText = cleanText.replace(/^(Interviewer|HR INTERVIEWER|HR Interviewer):\s*/i, '');
        cleanText = cleanText.trim();
        
        if (!cleanText) {
            console.log('No text to speak after cleaning');
            return;
        }
        
        // If user hasn't interacted yet, queue the speech
        if (!userInteracted) {
            pendingSpeech = cleanText;
            console.log('Speech queued, waiting for user interaction...');
            return;
        }
        
        // Use cleaned text for speech
        text = cleanText;
        
        // Try to get voices if not already loaded
        const voices = window.speechSynthesis.getVoices();
        if (voices.length > 0 && !narratorVoice) {
            const englishVoices = voices.filter(v => v.lang && v.lang.toLowerCase().startsWith('en'));
            narratorVoice = englishVoices.find(v => /Female|Woman/i.test(v.name)) || englishVoices[0] || voices[0];
        }
        
        const doSpeak = (useVoice = true) => {
            try {
                // Cancel any ongoing speech
                window.speechSynthesis.cancel();
                
                // Create new utterance
                const utterance = new SpeechSynthesisUtterance(text.trim());
                
                // Set voice if available and requested
                if (useVoice && narratorVoice) {
                    utterance.voice = narratorVoice;
                }
                
                utterance.rate = 0.95;
                utterance.pitch = 1.0;
                utterance.volume = 1.0;
                utterance.lang = 'en-US';
                
                // Add event listeners for debugging
                utterance.onstart = () => {
                    console.log('Speech started successfully');
                };
                utterance.onend = () => {
                    console.log('Speech ended');
                };
                utterance.onerror = (event) => {
                    console.error('Speech error:', event.error);
                    // Try again without voice selection if there's an error
                    if ((event.error === 'not-allowed' || event.error === 'synthesis-failed') && useVoice) {
                        console.log('Retrying speech without voice selection...');
                        setTimeout(() => doSpeak(false), 100);
                    }
                };
                
                // Minimal delay to ensure cancellation is processed
                setTimeout(() => {
                    window.speechSynthesis.speak(utterance);
                    console.log('Speaking:', text.substring(0, 50) + '...');
                }, 50);
            } catch (error) {
                console.error('Error in speakAI:', error);
                // Fallback: try without voice selection
                if (useVoice) {
                    setTimeout(() => doSpeak(false), 100);
                }
            }
        };
        
        doSpeak(true);
    }
    
    // Mark user interaction and play pending speech
    function markUserInteraction() {
        if (!userInteracted) {
            userInteracted = true;
            console.log('User interaction detected, speech enabled');
            // Ensure voices are loaded
            if (!narratorVoice) {
                initVoices();
            }
            if (pendingSpeech) {
                const speechText = pendingSpeech;
                pendingSpeech = null;
                // Play immediately after interaction, minimal delay
                setTimeout(() => {
                    console.log('Playing queued speech after user interaction');
                    speakAI(speechText);
                }, 100);
            }
        }
    }

    function createMessageElement(sender, text) {
        const wrapper = document.createElement('div');
        wrapper.className = 'message ' + sender;

        const label = document.createElement('label');
        label.textContent = sender === 'ai' ? 'HR INTERVIEWER' : 'YOU';
        wrapper.appendChild(label);

        const content = document.createElement('div');
        setMessageText(content, text);
        wrapper.appendChild(content);

        return wrapper;
    }

    function setMessageText(node, text) {
        const lines = (text || '').split(/\r?\n/);
        lines.forEach(function(line, idx) {
            node.appendChild(document.createTextNode(line));
            if (idx < lines.length - 1) {
                node.appendChild(document.createElement('br'));
            }
        });
    }

    function refreshVoiceButton() {
        if (!btnVoice) return;
        btnVoice.classList.toggle('active', !!voiceEnabled);
        btnVoice.innerHTML = voiceEnabled
            ? '<i class="fas fa-volume-up"></i> Voice On'
            : '<i class="fas fa-volume-mute"></i> Voice Off';
    }

    function addMessage(sender, text) {
        const element = createMessageElement(sender, text);
        conversation.push({ sender, text: text.trim(), timestamp: new Date().toISOString() });
        chatScroll.insertBefore(element, typingIndicator);
        chatScroll.scrollTop = chatScroll.scrollHeight;
        if (sender === 'ai') {
            // Speak immediately - text will be cleaned in speakAI function
            // If user hasn't interacted, it will be queued
            speakAI(text);
            requestAnimationFrame(() => {
                element.classList.add('murmuring');
                setTimeout(() => element.classList.remove('murmuring'), 2400);
            });
        }
        updateFinishAvailability();
    }

    function setTyping(active) {
        typingIndicator.classList.toggle('active', active);
        if (active) {
            chatScroll.scrollTop = chatScroll.scrollHeight;
        }
    }

    function updateFinishAvailability() {
        const studentTurns = conversation.filter(msg => msg.sender === 'student').length;
        btnFinish.disabled = studentTurns === 0;
    }

    async function requestAI(latestCandidateMessage = '', { initial = false } = {}) {
        if (isWaiting) return;
        isWaiting = true;
        setTyping(true);
        btnSend.disabled = true;
        btnSpeak.disabled = !recognitionAvailable;

        const history = conversation.map(entry => {
            const speaker = entry.sender === 'ai' ? 'Interviewer' : 'Candidate';
            return speaker + ': ' + entry.text;
        }).join('\\n');

        const baseInstruction = `You are acting as a senior HR interviewer for ${company || 'the organisation'}, evaluating a candidate for the ${role || 'open'} role.

CRITICAL INSTRUCTIONS:
1. Ask ONLY domain-specific and company-specific questions related to ${role || 'the role'} and ${company || 'the company'}.
2. Focus on technical domain knowledge, skills, and experience relevant to ${role || 'the role'}.
3. Ask about the candidate's understanding of ${company || 'the company'}'s business, products, services, and industry position.
4. Inquire about how their domain expertise aligns with ${company || 'the company'}'s needs and projects.
5. Be encouraging and supportive - if a candidate doesn't know something, guide them or ask a simpler related question instead of being dismissive.
6. Keep responses concise (2-3 sentences), professional, warm, and focused on domain expertise and company fit.
7. Always end with a clear, specific follow-up question related to the domain or company unless wrapping up.
8. If the candidate seems unsure or says they don't know, acknowledge it positively and either:
   - Break down the question into simpler parts
   - Ask about related experience they might have
   - Guide them to think about how they would approach learning about it
9. Make questions progressive - start with foundational concepts and build up to more advanced topics.
10. Reference specific ${company || 'the company'} products, services, or initiatives when relevant to make questions more concrete.`;

        const directive = initial
            ? `The conversation has not begun. Greet the candidate warmly, briefly introduce yourself as part of the HR team at ${company || 'the company'}, mention that you're evaluating them for the ${role || 'open'} role, and ask the first domain-specific or company-specific question. Make it welcoming and start with a foundational question that's not too difficult.`
            : `The candidate just responded with: "${latestCandidateMessage}". 

Frame your next message as the HR interviewer:
- Acknowledge their response briefly and positively
- If they seem unsure or said they don't know something, be encouraging and either:
  * Break the question into simpler parts
  * Ask about related experience or how they would approach learning it
  * Guide them with a more foundational question
- Ask a follow-up question that is SPECIFICALLY related to:
  * Domain knowledge and technical skills for ${role || 'the role'}
  * Understanding of ${company || 'the company'}'s business, products, or services
  * How their expertise applies to ${company || 'the company'}'s needs
- Keep your response concise (2-3 sentences) and always end with a clear question
- Do NOT ask generic behavioral or personal questions
- Be supportive and help the candidate demonstrate their knowledge`;

        const prompt = `${baseInstruction}

IMPORTANT: Do NOT include "Interviewer:", "HR Interviewer:", or any prefix in your response. Just provide your message directly.

Conversation transcript so far:
${history || 'No prior messages.'}

${directive}

HR interviewer reply (respond directly without any prefix):`;

        try {
            const res = await fetch('../api/ai_proxy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    prompt,
                    target_role: role || 'HR Round Candidate',
                    target_company: company || 'the organisation'
                })
            });
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.error || 'AI response failed');
            }
            addMessage('ai', data.response.trim());
        } catch (error) {
            console.error('AI HR error:', error);
            addMessage('ai', 'Apologies, I am having trouble fetching the next question right now. Please check your connection or try again in a moment.');
        } finally {
            setTyping(false);
            isWaiting = false;
            btnSend.disabled = false;
            btnSpeak.disabled = !recognitionAvailable;
            userInput.focus();
        }
    }

    function sendMessage() {
        const text = (userInput.value || '').trim();
        if (!text) {
            return;
        }
        addMessage('student', text);
        userInput.value = '';
        requestAI(text);
    }

    function buildSummary() {
        const highlights = conversation.slice(-6).map(entry => {
            const speaker = entry.sender === 'ai' ? 'HR' : 'You';
            return `${speaker}: ${entry.text}`;
        }).join(' | ');
        let summary = `[HR Round] ${company || 'Company'} — Highlights: ${highlights || 'Conversation captured.'}`;
        const notes = (notePad.value || '').trim();
        if (notes) {
            summary += ` | Personal notes: ${notes}`;
        }
        const MAX_NOTE = 450;
        if (summary.length > MAX_NOTE) {
            summary = summary.slice(0, MAX_NOTE - 3) + '...';
        }
        return summary;
    }

    async function finishRound() {
        const studentTurns = conversation.filter(msg => msg.sender === 'student').length;
        if (studentTurns === 0) {
            alert('Please complete at least one response before finishing the round.');
            return;
        }
        
        // Show loading indicator
        btnFinish.disabled = true;
        btnFinish.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
        
        try {
            // Get AI-analyzed score
            const scoreResponse = await fetch('../api/analyze_interview_score.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    conversation: conversation,
                    company: company,
                    job_role: role,
                    round_name: roundTitle
                })
            });
            
            const scoreData = await scoreResponse.json();
            
            if (scoreData.success && scoreData.score !== undefined) {
                const aiScore = scoreData.score;
                const summary = buildSummary();
                
                // Add AI feedback to summary if available
                let enhancedSummary = summary;
                if (scoreData.feedback) {
                    enhancedSummary += '\n\nAI Evaluation: ' + scoreData.feedback;
                }
                if (scoreData.strengths && scoreData.strengths.length > 0) {
                    enhancedSummary += '\nStrengths: ' + scoreData.strengths.join(', ');
                }
                if (scoreData.weaknesses && scoreData.weaknesses.length > 0) {
                    enhancedSummary += '\nAreas for Improvement: ' + scoreData.weaknesses.join(', ');
                }
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `interview_rounds.php?interview_id=${interviewId}`;

                const noteField = document.createElement('input');
                noteField.type = 'hidden';
                noteField.name = 'result_note';
                noteField.value = enhancedSummary;
                form.appendChild(noteField);

                const completeField = document.createElement('input');
                completeField.type = 'hidden';
                completeField.name = 'complete_round';
                completeField.value = '1';
                form.appendChild(completeField);
                
                // Add AI-analyzed score to form
                const scoreField = document.createElement('input');
                scoreField.type = 'hidden';
                scoreField.name = 'interview_score';
                scoreField.value = aiScore;
                form.appendChild(scoreField);

                document.body.appendChild(form);
                form.submit();
            } else {
                // Fallback to basic calculation if AI analysis fails
                console.error('AI scoring failed:', scoreData.error);
                alert('Could not analyze interview. Using basic scoring. Error: ' + (scoreData.error || 'Unknown error'));
                
                // Basic fallback score
                let baseScore = 50;
                const studentMessages = conversation.filter(msg => msg.sender === 'student').length;
                const avgMessageLength = studentMessages > 0 
                    ? conversation.filter(msg => msg.sender === 'student')
                        .reduce((sum, msg) => sum + msg.text.length, 0) / studentMessages
                    : 0;
                
                if (studentMessages >= 3) baseScore += 20;
                if (studentMessages >= 5) baseScore += 10;
                if (avgMessageLength > 50) baseScore += 10;
                if (avgMessageLength > 100) baseScore += 10;
                
                const fallbackScore = Math.min(100, baseScore);
                
                const summary = buildSummary();
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `interview_rounds.php?interview_id=${interviewId}`;

                const noteField = document.createElement('input');
                noteField.type = 'hidden';
                noteField.name = 'result_note';
                noteField.value = summary;
                form.appendChild(noteField);

                const completeField = document.createElement('input');
                completeField.type = 'hidden';
                completeField.name = 'complete_round';
                completeField.value = '1';
                form.appendChild(completeField);
                
                const scoreField = document.createElement('input');
                scoreField.type = 'hidden';
                scoreField.name = 'interview_score';
                scoreField.value = fallbackScore;
                form.appendChild(scoreField);

                document.body.appendChild(form);
                form.submit();
            }
        } catch (error) {
            console.error('Error analyzing interview:', error);
            alert('Error analyzing interview. Please try again.');
            btnFinish.disabled = false;
            btnFinish.innerHTML = '<i class="fas fa-flag-checkered"></i>';
        }
    }

    function initSpeech() {
        if (!recognitionAvailable) {
            btnSpeak.disabled = true;
            btnSpeak.title = 'Speech recognition is not supported in this browser.';
            return;
        }
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        recognition = new SpeechRecognition();
        recognition.lang = 'en-US';
        recognition.continuous = false;
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;

        recognition.addEventListener('result', (event) => {
            let finalTranscript = '';
            
            for (let i = event.resultIndex; i < event.results.length; i++) {
                if (event.results[i].isFinal) {
                    finalTranscript += event.results[i][0].transcript + ' ';
                }
            }
            
            if (finalTranscript) {
                const transcript = finalTranscript.trim();
                if (userInput.value) {
                    userInput.value = userInput.value.trim() + ' ' + transcript;
                } else {
                    userInput.value = transcript;
                }
            }
        });

        recognition.addEventListener('end', () => {
            isListening = false;
            btnSpeak.classList.remove('btn-speaking');
            btnSpeak.innerHTML = '<i class="fas fa-microphone"></i>';
            btnSpeak.disabled = false;
        });

        recognition.addEventListener('error', (event) => {
            console.error('Speech recognition error:', event.error);
            isListening = false;
            btnSpeak.classList.remove('btn-speaking');
            btnSpeak.innerHTML = '<i class="fas fa-microphone"></i>';
            btnSpeak.disabled = false;
            if (event.error !== 'no-speech') {
                alert('Sorry, I could not capture that. Please try speaking again or type your response.');
            }
        });
    }

    function toggleSpeech() {
        if (!recognition) return;
        if (isListening) {
            recognition.stop();
            return;
        }
        try {
            recognition.start();
            isListening = true;
            btnSpeak.classList.add('btn-speaking');
            btnSpeak.innerHTML = '<i class="fas fa-microphone"></i>';
            btnSpeak.disabled = false;
        } catch (error) {
            console.error('Speech recognition start error:', error);
            isListening = false;
            btnSpeak.classList.remove('btn-speaking');
            btnSpeak.innerHTML = '<i class="fas fa-microphone"></i>';
            alert('Microphone access was blocked. Please allow microphone permissions and try again.');
        }
    }

    // Mark user interaction on any user action
    document.addEventListener('click', markUserInteraction, { once: true });
    document.addEventListener('keydown', markUserInteraction, { once: true });
    document.addEventListener('touchstart', markUserInteraction, { once: true });
    
    btnSend.addEventListener('click', (e) => {
        markUserInteraction();
        sendMessage();
    });
    userInput.addEventListener('keydown', (event) => {
        markUserInteraction();
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });
    btnFinish.addEventListener('click', (e) => {
        markUserInteraction();
        finishRound();
    });
    btnSpeak.addEventListener('click', (e) => {
        markUserInteraction();
        toggleSpeech();
    });

    document.addEventListener('DOMContentLoaded', () => {
        initSpeech();
        
        // Initialize voices immediately and wait for them to load
        if (ttsAvailable) {
            initVoices();
            // Force voice loading on Chrome/Edge
            if (window.speechSynthesis.getVoices().length === 0) {
                window.speechSynthesis.onvoiceschanged = () => {
                    initVoices();
                };
            }
        }
        
        if (btnVoice) {
            if (!ttsAvailable) {
                voiceEnabled = false;
                btnVoice.disabled = true;
                btnVoice.classList.remove('active');
                btnVoice.innerHTML = '<i class="fas fa-volume-mute"></i> Voice Unavailable';
                btnVoice.title = 'Voice playback is not supported in this browser.';
            } else {
                refreshVoiceButton();
                btnVoice.addEventListener('click', () => {
                    voiceEnabled = !voiceEnabled;
                    if (!voiceEnabled) {
                        window.speechSynthesis.cancel();
                    } else {
                        // Re-initialize voices if needed
                        if (!narratorVoice) {
                            initVoices();
                        }
                    }
                    refreshVoiceButton();
                });
            }
        }
        
        // Start the interview after a short delay to ensure voices are loaded
        // Note: Speech will be queued until user interacts
        setTimeout(() => {
            requestAI('', { initial: true });
        }, 500);
        
        // Add a visual indicator if speech is queued
        const checkPendingSpeech = setInterval(() => {
            if (pendingSpeech && !userInteracted) {
                // Show a subtle hint that user needs to interact
                if (!document.querySelector('.speech-hint')) {
                    const hint = document.createElement('div');
                    hint.className = 'speech-hint';
                    hint.style.cssText = 'position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 10px 20px; border-radius: 20px; font-size: 0.9rem; z-index: 999; pointer-events: none;';
                    hint.textContent = 'Click anywhere to enable voice';
                    document.body.appendChild(hint);
                    setTimeout(() => hint.remove(), 3000);
                }
            } else {
                clearInterval(checkPendingSpeech);
            }
        }, 1000);
        
        userInput.focus();
    });
    </script>
</body>
</html>

