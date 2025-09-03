/**
 * Advanced Chat Interface - Frontend Implementation
 * Voice input, shortcuts, history search, and mobile optimization
 */

class AdvancedChatInterface {
    constructor() {
        this.sessionId = null;
        this.isRecording = false;
        this.recognition = null;
        this.shortcuts = new Map();
        this.chatHistory = [];
        this.currentSearchIndex = -1;
        this.searchResults = [];
        
        this.init();
    }

    async init() {
        await this.initializeSession();
        this.setupVoiceRecognition();
        this.setupShortcuts();
        this.setupEventListeners();
        this.loadChatHistory();
        this.initializeSearchIndex();
    }

    async initializeSession() {
        try {
            const response = await fetch('/api/chat/session', {
                method: 'GET'
            });
            const session = await response.json();
            this.sessionId = session.id;
        } catch (error) {
            console.error('Failed to initialize chat session:', error);
        }
    }

    setupVoiceRecognition() {
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            console.log('Speech recognition not supported');
            return;
        }

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        this.recognition = new SpeechRecognition();
        
        this.recognition.continuous = false;
        this.recognition.interimResults = true;
        this.recognition.lang = 'en-US';
        
        this.recognition.onstart = () => {
            this.isRecording = true;
            this.updateVoiceButton(true);
            this.showVoiceIndicator();
        };
        
        this.recognition.onresult = (event) => {
            let transcript = '';
            let isFinal = false;
            
            for (let i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    isFinal = true;
                }
            }
            
            if (isFinal) {
                this.insertTextAtCursor(transcript);
                this.processVoiceCommand(transcript);
            } else {
                this.showVoicePreview(transcript);
            }
        };
        
        this.recognition.onend = () => {
            this.isRecording = false;
            this.updateVoiceButton(false);
            this.hideVoiceIndicator();
        };
        
        this.recognition.onerror = (event) => {
            console.error('Speech recognition error:', event.error);
            this.isRecording = false;
            this.updateVoiceButton(false);
            this.hideVoiceIndicator();
        };
    }

    async setupShortcuts() {
        try {
            const response = await fetch('/api/chat/shortcuts');
            const shortcuts = await response.json();
            
            shortcuts.forEach(shortcut => {
                this.shortcuts.set(shortcut.trigger_text, {
                    replacement: shortcut.replacement_text,
                    category: shortcut.category,
                    usage: shortcut.usage_count
                });
            });
            
            this.createShortcutPanel();
        } catch (error) {
            console.error('Failed to load shortcuts:', error);
        }
    }

    setupEventListeners() {
        const chatInput = document.getElementById('chat-input');
        const voiceButton = document.getElementById('voice-button');
        const searchButton = document.getElementById('search-button');
        const shortcutButton = document.getElementById('shortcut-button');
        
        // Enhanced input handling
        chatInput.addEventListener('input', (e) => {
            this.handleInputChange(e);
            this.processMentions(e.target.value);
        });
        
        chatInput.addEventListener('keydown', (e) => {
            this.handleKeydown(e);
        });
        
        // Voice button
        if (voiceButton) {
            voiceButton.addEventListener('click', () => {
                this.toggleVoiceRecording();
            });
            
            // Long press for continuous recording on mobile
            let longPressTimer;
            voiceButton.addEventListener('touchstart', (e) => {
                e.preventDefault();
                longPressTimer = setTimeout(() => {
                    this.startContinuousRecording();
                }, 500);
            });
            
            voiceButton.addEventListener('touchend', (e) => {
                e.preventDefault();
                clearTimeout(longPressTimer);
                if (this.isRecording) {
                    this.stopVoiceRecording();
                }
            });
        }
        
        // Search functionality
        if (searchButton) {
            searchButton.addEventListener('click', () => {
                this.toggleSearchMode();
            });
        }
        
        // Shortcut panel
        if (shortcutButton) {
            shortcutButton.addEventListener('click', () => {
                this.toggleShortcutPanel();
            });
        }
        
        // Swipe gestures for mobile
        this.setupSwipeGestures();
    }

    handleInputChange(event) {
        const input = event.target;
        const value = input.value;
        
        // Auto-resize
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
        
        // Process shortcuts in real-time
        this.processShortcuts(value);
        
        // Show smart suggestions
        this.showSmartSuggestions(value);
        
        // Update send button state
        this.updateSendButton(value.trim().length > 0);
    }

    handleKeydown(event) {
        const input = event.target;
        
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.sendMessage();
        } else if (event.key === 'ArrowUp' && input.value === '') {
            event.preventDefault();
            this.navigateHistory('up');
        } else if (event.key === 'ArrowDown' && input.value === '') {
            event.preventDefault();
            this.navigateHistory('down');
        } else if (event.key === 'Tab') {
            event.preventDefault();
            this.handleTabCompletion();
        } else if (event.ctrlKey || event.metaKey) {
            this.handleShortcuts(event);
        }
    }

    processShortcuts(text) {
        const words = text.split(' ');
        const lastWord = words[words.length - 1];
        
        // Check if last word is a trigger
        if (this.shortcuts.has(lastWord)) {
            const shortcut = this.shortcuts.get(lastWord);
            this.showShortcutPreview(shortcut.replacement);
        } else {
            this.hideShortcutPreview();
        }
    }

    processMentions(text) {
        // Process @mentions for entities
        const mentions = text.match(/@\w+/g);
        if (mentions) {
            mentions.forEach(mention => {
                this.highlightMention(mention);
            });
        }
    }

    toggleVoiceRecording() {
        if (this.isRecording) {
            this.stopVoiceRecording();
        } else {
            this.startVoiceRecording();
        }
    }

    startVoiceRecording() {
        if (this.recognition && !this.isRecording) {
            try {
                this.recognition.start();
            } catch (error) {
                console.error('Failed to start voice recording:', error);
            }
        }
    }

    stopVoiceRecording() {
        if (this.recognition && this.isRecording) {
            this.recognition.stop();
        }
    }

    startContinuousRecording() {
        this.recognition.continuous = true;
        this.startVoiceRecording();
    }

    processVoiceCommand(transcript) {
        const lowerTranscript = transcript.toLowerCase().trim();
        
        // Check for voice commands
        const commands = {
            'send message': () => this.sendMessage(),
            'clear input': () => this.clearInput(),
            'show shortcuts': () => this.toggleShortcutPanel(),
            'search history': () => this.toggleSearchMode(),
            'new task': () => this.insertShortcut('//t'),
            'new note': () => this.insertShortcut('//n'),
            'new project': () => this.insertShortcut('//p')
        };
        
        for (const [command, action] of Object.entries(commands)) {
            if (lowerTranscript.includes(command)) {
                action();
                return;
            }
        }
    }

    insertTextAtCursor(text) {
        const input = document.getElementById('chat-input');
        const start = input.selectionStart;
        const end = input.selectionEnd;
        const currentValue = input.value;
        
        input.value = currentValue.slice(0, start) + text + currentValue.slice(end);
        input.selectionStart = input.selectionEnd = start + text.length;
        
        this.handleInputChange({ target: input });
    }

    insertShortcut(trigger) {
        const shortcut = this.shortcuts.get(trigger);
        if (shortcut) {
            this.insertTextAtCursor(shortcut.replacement);
        }
    }

    async sendMessage() {
        const input = document.getElementById('chat-input');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Clear input and disable send button
        input.value = '';
        input.style.height = 'auto';
        this.updateSendButton(false);
        
        // Add to history
        this.addToHistory(message);
        
        // Add user message to chat
        this.addMessageToChat('user', message);
        
        // Show loading state
        const loadingMessage = this.addMessageToChat('ai', this.createLoadingIndicator());
        
        try {
            // Send to backend
            const response = await fetch('/api/chat/message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    session_id: this.sessionId,
                    message_type: 'user',
                    content: message,
                    metadata: {
                        timestamp: Date.now(),
                        voice_input: false,
                        shortcuts_detected: this.detectShortcuts(message)
                    }
                })
            });
            
            const data = await response.json();
            
            // Remove loading message
            loadingMessage.remove();
            
            if (data.success) {
                // Process AI response
                await this.handleAIResponse(data.response || data.content);
                
                // Handle any actions
                if (data.actions) {
                    this.handleAIActions(data.actions);
                }
            } else {
                this.addMessageToChat('ai', 'Sorry, I encountered an error. Please try again.');
            }
            
        } catch (error) {
            console.error('Chat error:', error);
            loadingMessage.innerHTML = '<p>Sorry, I encountered an error. Please try again.</p>';
        }
        
        // Update search index
        this.updateSearchIndex();
    }

    async handleAIResponse(response) {
        // Add AI response to chat
        this.addMessageToChat('ai', response);
        
        // Add to backend
        await fetch('/api/chat/message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                session_id: this.sessionId,
                message_type: 'assistant',
                content: response,
                metadata: {
                    timestamp: Date.now()
                }
            })
        });
    }

    detectShortcuts(message) {
        const detected = [];
        for (const [trigger, shortcut] of this.shortcuts) {
            if (message.includes(trigger)) {
                detected.push(trigger);
            }
        }
        return detected;
    }

    addMessageToChat(type, content) {
        const messagesContainer = document.getElementById('chat-messages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        
        if (typeof content === 'string') {
            messageDiv.innerHTML = `<p>${this.formatMessage(content)}</p>`;
        } else {
            messageDiv.appendChild(content);
        }
        
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        // Add animation
        messageDiv.style.opacity = '0';
        messageDiv.style.transform = 'translateY(20px)';
        requestAnimationFrame(() => {
            messageDiv.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            messageDiv.style.opacity = '1';
            messageDiv.style.transform = 'translateY(0)';
        });
        
        return messageDiv;
    }

    formatMessage(content) {
        // Format mentions
        content = content.replace(/@(\w+)/g, '<span class="mention">@$1</span>');
        
        // Format shortcuts
        for (const [trigger] of this.shortcuts) {
            const regex = new RegExp(`\\${trigger}`, 'g');
            content = content.replace(regex, `<span class="shortcut">${trigger}</span>`);
        }
        
        return content;
    }

    createLoadingIndicator() {
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'loading';
        loadingDiv.innerHTML = `
            <div class="typing-indicator">
                <span></span><span></span><span></span>
            </div>
            <span>Thinking...</span>
        `;
        return loadingDiv;
    }

    // Search functionality
    toggleSearchMode() {
        const searchPanel = document.getElementById('search-panel');
        const searchInput = document.getElementById('search-input');
        
        if (searchPanel.classList.contains('active')) {
            searchPanel.classList.remove('active');
        } else {
            searchPanel.classList.add('active');
            searchInput.focus();
        }
    }

    async searchHistory(query) {
        try {
            const response = await fetch(`/api/chat/search?q=${encodeURIComponent(query)}&session_id=${this.sessionId}`);
            const results = await response.json();
            
            this.displaySearchResults(results);
            this.searchResults = results;
            this.currentSearchIndex = -1;
            
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    displaySearchResults(results) {
        const resultsContainer = document.getElementById('search-results');
        
        if (results.length === 0) {
            resultsContainer.innerHTML = '<div class="no-results">No results found</div>';
            return;
        }
        
        resultsContainer.innerHTML = results.map((result, index) => `
            <div class="search-result" data-index="${index}" onclick="advancedChat.selectSearchResult(${index})">
                <div class="result-content">${result.snippet || result.content}</div>
                <div class="result-meta">${this.formatDate(result.created_at)} • ${result.message_type}</div>
            </div>
        `).join('');
    }

    selectSearchResult(index) {
        const result = this.searchResults[index];
        const input = document.getElementById('chat-input');
        input.value = result.content;
        this.toggleSearchMode();
        input.focus();
    }

    // Shortcut panel
    createShortcutPanel() {
        const panel = document.getElementById('shortcut-panel');
        const shortcuts = Array.from(this.shortcuts.entries())
            .sort((a, b) => b[1].usage - a[1].usage);
        
        const shortcutsByCategory = shortcuts.reduce((acc, [trigger, data]) => {
            if (!acc[data.category]) acc[data.category] = [];
            acc[data.category].push({ trigger, ...data });
            return acc;
        }, {});
        
        panel.innerHTML = Object.entries(shortcutsByCategory).map(([category, shortcuts]) => `
            <div class="shortcut-category">
                <h4>${category}</h4>
                <div class="shortcut-grid">
                    ${shortcuts.map(shortcut => `
                        <div class="shortcut-item" onclick="advancedChat.insertShortcut('${shortcut.trigger}')">
                            <span class="shortcut-trigger">${shortcut.trigger}</span>
                            <span class="shortcut-desc">${shortcut.replacement.substring(0, 30)}...</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
    }

    toggleShortcutPanel() {
        const panel = document.getElementById('shortcut-panel');
        panel.classList.toggle('active');
    }

    // History navigation
    addToHistory(message) {
        this.chatHistory.unshift(message);
        if (this.chatHistory.length > 50) {
            this.chatHistory.pop();
        }
        this.currentHistoryIndex = -1;
    }

    navigateHistory(direction) {
        const input = document.getElementById('chat-input');
        
        if (direction === 'up') {
            if (this.currentHistoryIndex < this.chatHistory.length - 1) {
                this.currentHistoryIndex++;
                input.value = this.chatHistory[this.currentHistoryIndex];
            }
        } else if (direction === 'down') {
            if (this.currentHistoryIndex > 0) {
                this.currentHistoryIndex--;
                input.value = this.chatHistory[this.currentHistoryIndex];
            } else if (this.currentHistoryIndex === 0) {
                this.currentHistoryIndex = -1;
                input.value = '';
            }
        }
        
        this.handleInputChange({ target: input });
    }

    // Smart suggestions
    async showSmartSuggestions(input) {
        if (input.length < 2) {
            this.hideSuggestions();
            return;
        }
        
        try {
            const response = await fetch(`/api/chat/suggestions?session_id=${this.sessionId}`);
            const suggestions = await response.json();
            
            this.displaySuggestions(suggestions.filter(s => 
                s.text.toLowerCase().includes(input.toLowerCase())
            ));
        } catch (error) {
            console.error('Failed to load suggestions:', error);
        }
    }

    displaySuggestions(suggestions) {
        const container = document.getElementById('suggestions-container');
        
        if (suggestions.length === 0) {
            this.hideSuggestions();
            return;
        }
        
        container.innerHTML = suggestions.map(suggestion => `
            <div class="suggestion-item" onclick="advancedChat.applySuggestion('${suggestion.text}')">
                <span class="suggestion-text">${suggestion.text}</span>
                <span class="suggestion-type">${suggestion.type}</span>
            </div>
        `).join('');
        
        container.style.display = 'block';
    }

    applySuggestion(text) {
        const input = document.getElementById('chat-input');
        input.value = text;
        this.hideSuggestions();
        this.handleInputChange({ target: input });
    }

    hideSuggestions() {
        const container = document.getElementById('suggestions-container');
        container.style.display = 'none';
    }

    // Voice UI updates
    updateVoiceButton(recording) {
        const button = document.getElementById('voice-button');
        if (recording) {
            button.classList.add('recording');
            button.innerHTML = '🔴'; // Recording indicator
        } else {
            button.classList.remove('recording');
            button.innerHTML = '🎤'; // Microphone
        }
    }

    showVoiceIndicator() {
        const indicator = document.getElementById('voice-indicator');
        indicator.style.display = 'block';
    }

    hideVoiceIndicator() {
        const indicator = document.getElementById('voice-indicator');
        indicator.style.display = 'none';
    }

    showVoicePreview(text) {
        const preview = document.getElementById('voice-preview');
        preview.textContent = text;
        preview.style.display = 'block';
    }

    hideVoicePreview() {
        const preview = document.getElementById('voice-preview');
        preview.style.display = 'none';
    }

    // Utility methods
    updateSendButton(enabled) {
        const button = document.getElementById('chat-send');
        button.disabled = !enabled;
    }

    clearInput() {
        const input = document.getElementById('chat-input');
        input.value = '';
        input.style.height = 'auto';
        this.updateSendButton(false);
    }

    setupSwipeGestures() {
        const chatContainer = document.getElementById('chat-container');
        let startX, startY;
        
        chatContainer.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });
        
        chatContainer.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            const diffX = startX - endX;
            const diffY = startY - endY;
            
            // Horizontal swipe to close chat
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                if (diffX > 0) {
                    this.closeChat();
                }
            }
        }, { passive: true });
    }

    closeChat() {
        const chatContainer = document.getElementById('chat');
        chatContainer.classList.remove('active');
    }

    async loadChatHistory() {
        try {
            const response = await fetch(`/api/chat/messages?session_id=${this.sessionId}&limit=20`);
            const messages = await response.json();
            
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.innerHTML = '';
            
            messages.forEach(message => {
                this.addMessageToChat(message.message_type, message.content);
            });
            
        } catch (error) {
            console.error('Failed to load chat history:', error);
        }
    }

    async initializeSearchIndex() {
        // Initialize client-side search index for faster searches
        try {
            const response = await fetch(`/api/chat/messages?session_id=${this.sessionId}&limit=100`);
            const messages = await response.json();
            
            // Simple client-side indexing
            this.searchIndex = messages.map(msg => ({
                id: msg.id,
                content: msg.content.toLowerCase(),
                type: msg.message_type,
                timestamp: msg.created_at
            }));
            
        } catch (error) {
            console.error('Failed to initialize search index:', error);
        }
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
        if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
        return date.toLocaleDateString();
    }
}

// Initialize when DOM is ready
let advancedChat;
document.addEventListener('DOMContentLoaded', () => {
    advancedChat = new AdvancedChatInterface();
});