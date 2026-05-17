<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mic Diagnostic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; max-width: 600px; margin: 0 auto; }
        .log { background: #1e1e1e; color: #0f0; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 13px; min-height: 200px; margin-top: 20px; white-space: pre-wrap; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; }
        .btn-blue { background: #0d6efd; color: white; }
        .btn-green { background: #198754; color: white; }
        .btn-red { background: #dc3545; color: white; }
        .status { padding: 8px 15px; border-radius: 20px; display: inline-block; font-size: 13px; font-weight: 600; margin: 5px; }
        .granted { background: #d1e7dd; color: #0f5132; }
        .denied { background: #f8d7da; color: #842029; }
        .prompt { background: #fff3cd; color: #664d03; }
    </style>
</head>
<body>
    <h3>🎤 Microphone Diagnostic Tool</h3>
    <p>Is page se check hoga ki mic ka exact issue kya hai.</p>
    
    <div id="status-bar"></div>
    
    <div>
        <button class="btn-blue" onclick="checkPermission()"><i class="fas fa-search"></i> 1. Check Permission State</button>
        <button class="btn-green" onclick="testGetUserMedia()"><i class="fas fa-microphone"></i> 2. Test getUserMedia</button>
        <button class="btn-red" onclick="testSpeechRecognition()"><i class="fas fa-comment"></i> 3. Test SpeechRecognition</button>
    </div>
    
    <div class="log" id="log">Waiting for test...\n</div>

<script>
const log = (msg, color='#0f0') => {
    const el = document.getElementById('log');
    el.innerHTML += `<span style="color:${color}">[${new Date().toLocaleTimeString()}] ${msg}</span>\n`;
};

async function checkPermission() {
    log('--- Checking Permission State ---', '#fff');
    
    // Protocol check
    log('Protocol: ' + window.location.protocol);
    log('isSecureContext: ' + window.isSecureContext);
    log('Hostname: ' + window.location.hostname);
    
    // Navigator.permissions check
    if (navigator.permissions && navigator.permissions.query) {
        try {
            const ps = await navigator.permissions.query({ name: 'microphone' });
            const state = ps.state;
            log('Permission API State: ' + state, state === 'granted' ? '#0f0' : state === 'denied' ? '#f00' : '#ff0');
            
            document.getElementById('status-bar').innerHTML = 
                `<span class="status ${state}">${state.toUpperCase()}</span>`;
            
            ps.onchange = () => log('Permission changed to: ' + ps.state, '#ff0');
        } catch(e) {
            log('Permission API Error: ' + e.message, '#f00');
        }
    } else {
        log('navigator.permissions NOT supported', '#f00');
    }
}

async function testGetUserMedia() {
    log('--- Testing getUserMedia ---', '#fff');
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        log('✅ getUserMedia SUCCESS! Mic access granted.', '#0f0');
        log('Tracks: ' + stream.getAudioTracks().map(t => t.label).join(', '));
        stream.getTracks().forEach(t => t.stop());
        log('Stream stopped.');
    } catch(e) {
        log('❌ getUserMedia FAILED!', '#f00');
        log('Error Name: ' + e.name, '#f00');
        log('Error Message: ' + e.message, '#f00');
    }
}

function testSpeechRecognition() {
    log('--- Testing SpeechRecognition ---', '#fff');
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
        log('❌ SpeechRecognition NOT supported in this browser!', '#f00');
        return;
    }
    log('✅ SpeechRecognition is supported.');
    const r = new SR();
    r.lang = 'en-IN';
    r.onstart = () => log('✅ Recognition STARTED. Speak something...', '#0f0');
    r.onresult = (e) => log('✅ Result: ' + e.results[0][0].transcript, '#0ff');
    r.onerror = (e) => log('❌ Recognition Error: ' + e.error + ' | ' + (e.message||''), '#f00');
    r.onend = () => log('Recognition ended.', '#aaa');
    try {
        r.start();
        log('start() called...', '#ff0');
    } catch(e) {
        log('start() threw: ' + e.message, '#f00');
    }
}

// Auto-run permission check on load
checkPermission();
</script>
</body>
</html>
