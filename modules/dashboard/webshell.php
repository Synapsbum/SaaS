<?php
$title = 'WebShell';
require 'templates/header.php';
?>

<style>
/* Terminal Container */
.terminal-container {
    background: #0d1117;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    animation: terminal-appear 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

@keyframes terminal-appear {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Terminal Header */
.terminal-header {
    background: linear-gradient(135deg, #1a1a2e, #16213e);
    padding: 15px 20px;
    border-bottom: 2px solid rgba(99, 102, 241, 0.3);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.terminal-buttons {
    display: flex;
    gap: 8px;
}

.terminal-btn {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s;
}

.terminal-btn.close { background: #ff5f56; }
.terminal-btn.minimize { background: #ffbd2e; }
.terminal-btn.maximize { background: #27c93f; }

.terminal-btn:hover {
    transform: scale(1.2);
    box-shadow: 0 0 10px currentColor;
}

.terminal-title {
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.terminal-status {
    display: flex;
    align-items: center;
    gap: 12px;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-muted);
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    animation: pulse-dot 2s ease-in-out infinite;
}

.status-dot.online {
    background: #27c93f;
    box-shadow: 0 0 8px #27c93f;
}

.status-dot.offline {
    background: #6b7280;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(0.95); }
}

/* Terminal Body */
.terminal-body {
    background: #0d1117;
    padding: 24px;
    min-height: 500px;
    font-family: 'Courier New', 'Consolas', monospace;
    color: #58a6ff;
    font-size: 14px;
    line-height: 1.8;
    position: relative;
}

.terminal-output {
    margin-bottom: 20px;
}

.terminal-line {
    margin-bottom: 8px;
    display: flex;
    align-items: flex-start;
    animation: line-appear 0.3s ease-out;
}

@keyframes line-appear {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.terminal-prompt {
    color: #39d353;
    margin-right: 8px;
    font-weight: bold;
}

.terminal-command {
    color: #79c0ff;
}

.terminal-result {
    color: #c9d1d9;
    padding-left: 20px;
    white-space: pre-wrap;
}

.terminal-error {
    color: #ff7b72;
}

.terminal-success {
    color: #3fb950;
}

.terminal-warning {
    color: #d29922;
}

/* Input Area */
.terminal-input-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 20px;
    position: relative;
}

.terminal-input-wrapper::before {
    content: '';
    position: absolute;
    left: -10px;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 20px;
    background: linear-gradient(180deg, transparent, #58a6ff, transparent);
    animation: input-glow 2s ease-in-out infinite;
}

@keyframes input-glow {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
}

.terminal-input {
    flex: 1;
    background: rgba(139, 148, 158, 0.1);
    border: 1px solid rgba(88, 166, 255, 0.3);
    border-radius: 8px;
    padding: 12px 16px;
    color: #c9d1d9;
    font-family: 'Courier New', 'Consolas', monospace;
    font-size: 14px;
    transition: all 0.3s;
}

.terminal-input:focus {
    outline: none;
    border-color: #58a6ff;
    background: rgba(139, 148, 158, 0.15);
    box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.1);
}

.terminal-send-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, #238636, #2ea043);
    border: none;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.terminal-send-btn:hover {
    background: linear-gradient(135deg, #2ea043, #3fb950);
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(46, 160, 67, 0.3);
}

.terminal-send-btn:active {
    transform: translateY(0);
}

/* Suggestions */
.terminal-suggestions {
    position: absolute;
    bottom: 100%;
    left: 0;
    right: 0;
    background: #161b22;
    border: 1px solid rgba(88, 166, 255, 0.3);
    border-radius: 8px;
    margin-bottom: 8px;
    padding: 8px;
    display: none;
    z-index: 1000;
    box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.5);
}

.terminal-suggestions.active {
    display: block;
    animation: suggestions-appear 0.2s ease-out;
}

@keyframes suggestions-appear {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.suggestion-item {
    padding: 8px 12px;
    color: #8b949e;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s;
    font-size: 13px;
}

.suggestion-item:hover {
    background: rgba(88, 166, 255, 0.1);
    color: #58a6ff;
}

/* Quick Commands */
.quick-commands {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid rgba(88, 166, 255, 0.2);
}

.quick-commands h6 {
    color: #8b949e;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
}

.quick-command-btn {
    display: inline-block;
    background: rgba(88, 166, 255, 0.1);
    border: 1px solid rgba(88, 166, 255, 0.3);
    border-radius: 6px;
    padding: 6px 12px;
    color: #58a6ff;
    font-size: 12px;
    margin: 4px;
    cursor: pointer;
    transition: all 0.3s;
    font-family: 'Courier New', 'Consolas', monospace;
}

.quick-command-btn:hover {
    background: rgba(88, 166, 255, 0.2);
    border-color: #58a6ff;
    transform: translateY(-2px);
}

/* Loading Animation */
.terminal-loading {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #58a6ff;
}

.loading-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #58a6ff;
    animation: loading-bounce 1.4s infinite ease-in-out both;
}

.loading-dot:nth-child(1) { animation-delay: -0.32s; }
.loading-dot:nth-child(2) { animation-delay: -0.16s; }

@keyframes loading-bounce {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}

/* Coming Soon Overlay */
.coming-soon-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(13, 17, 23, 0.95);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
}

.coming-soon-content {
    text-align: center;
    padding: 40px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border: 2px solid rgba(99, 102, 241, 0.3);
    border-radius: 20px;
    max-width: 500px;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.coming-soon-icon {
    font-size: 80px;
    margin-bottom: 20px;
    animation: rotate-icon 4s linear infinite;
}

@keyframes rotate-icon {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.coming-soon-title {
    font-size: 32px;
    font-weight: 800;
    background: linear-gradient(135deg, #58a6ff, #bc8cff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 15px;
}

.coming-soon-text {
    color: var(--text-secondary);
    font-size: 16px;
    margin-bottom: 20px;
}

.feature-list {
    text-align: left;
    margin-top: 30px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(88, 166, 255, 0.05);
    border-radius: 8px;
    margin-bottom: 8px;
    color: var(--text-primary);
    font-size: 14px;
}

.feature-icon {
    color: #58a6ff;
    font-size: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .terminal-body {
        padding: 16px;
        min-height: 400px;
        font-size: 13px;
    }
    
    .terminal-header {
        padding: 12px 16px;
    }
    
    .coming-soon-content {
        padding: 30px 20px;
    }
    
    .coming-soon-icon {
        font-size: 60px;
    }
    
    .coming-soon-title {
        font-size: 24px;
    }
}
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="terminal-container">
            <!-- Terminal Header -->
            <div class="terminal-header">
                <div class="terminal-buttons">
                    <div class="terminal-btn close"></div>
                    <div class="terminal-btn minimize"></div>
                    <div class="terminal-btn maximize"></div>
                </div>
                <div class="terminal-title">
                    <i class="bi bi-terminal-fill"></i>
                    WebShell Terminal
                </div>
                <div class="terminal-status">
                    <div class="status-indicator">
                        <span class="status-dot offline"></span>
                        <span>Çok Yakında</span>
                    </div>
                </div>
            </div>
            
            <!-- Terminal Body -->
            <div class="terminal-body">
                <!-- Coming Soon Overlay -->
                <div class="coming-soon-overlay">
                    <div class="coming-soon-content">
                        <div class="coming-soon-icon">⚡</div>
                        <h2 class="coming-soon-title">Çok Yakında!</h2>
                        <p class="coming-soon-text">
                            WebShell yönetim paneli şu anda geliştirme aşamasında.<br>
                            <strong>Gelecek Hafta</strong> sizlerle!
                        </p>
                        
                        <div class="feature-list">
                            <div class="feature-item">
                                <i class="bi bi-check-circle-fill feature-icon"></i>
                                <span>En ucuz, en kaliteli webshell satışı</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Demo Terminal Output (hidden behind overlay) -->
                <div class="terminal-output" id="terminalOutput">
                    <div class="terminal-line">
                        <span class="terminal-prompt">root@webshell:~$</span>
                        <span class="terminal-command">Welcome to WebShell</span>
                    </div>
                    <div class="terminal-line">
                        <span class="terminal-result terminal-success">System initialized successfully</span>
                    </div>
                    <div class="terminal-line">
                        <span class="terminal-result">Type 'help' for available commands</span>
                    </div>
                </div>
                
                <!-- Input Area -->
                <div class="terminal-input-wrapper">
                    <div class="terminal-suggestions" id="suggestions"></div>
                    <span class="terminal-prompt">root@webshell:~$</span>
                    <input 
                        type="text" 
                        class="terminal-input" 
                        id="terminalInput"
                        placeholder="Komut girin..."
                        disabled
                    >
                    <button class="terminal-send-btn" disabled>
                        <i class="bi bi-send-fill"></i>
                        Run
                    </button>
                </div>
                
                <!-- Quick Commands -->
                <div class="quick-commands">
                    <h6><i class="bi bi-lightning-charge-fill me-2"></i>Hızlı Komutlar</h6>
                    <div class="quick-command-btn">ls -la</div>
                    <div class="quick-command-btn">pwd</div>
                    <div class="quick-command-btn">whoami</div>
                    <div class="quick-command-btn">uname -a</div>
                    <div class="quick-command-btn">df -h</div>
                    <div class="quick-command-btn">top</div>
                    <div class="quick-command-btn">ps aux</div>
                    <div class="quick-command-btn">netstat -tulpn</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Demo terminal functionality (will be implemented later)
const terminalInput = document.getElementById('terminalInput');
const terminalOutput = document.getElementById('terminalOutput');

// Command history
let commandHistory = [];
let historyIndex = -1;

// Available commands for demo
const availableCommands = [
    'help', 'ls', 'pwd', 'cd', 'cat', 'mkdir', 'rm',
    'cp', 'mv', 'chmod', 'chown', 'ps', 'top', 'df',
    'du', 'whoami', 'uname', 'clear', 'exit'
];

// Quick command buttons
document.querySelectorAll('.quick-command-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        // This will be functional when WebShell is activated
        console.log('Command clicked:', this.textContent);
    });
});

// Typing animation for demo
function typeText(element, text, delay = 50) {
    let i = 0;
    element.textContent = '';
    const interval = setInterval(() => {
        if (i < text.length) {
            element.textContent += text.charAt(i);
            i++;
        } else {
            clearInterval(interval);
        }
    }, delay);
}

// Add some demo animations
setTimeout(() => {
    const lines = document.querySelectorAll('.terminal-line');
    lines.forEach((line, index) => {
        line.style.animationDelay = `${index * 0.2}s`;
    });
}, 100);
</script>

<?php require 'templates/footer.php'; ?>