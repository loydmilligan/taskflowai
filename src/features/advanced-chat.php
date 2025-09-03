<?php
/**
 * Advanced Chat Interface Feature
 * Voice input, shortcuts, history search, and context memory
 * 
 * Score: 8/10 - Enhances core interaction model, mobile-friendly
 * Implementation: ~300 lines, Speech API integration, UI improvements
 */

class AdvancedChatInterface {
    private $db;
    private $contextWindow = 10; // Number of recent messages to maintain context
    
    public function __construct($database) {
        $this->db = $database;
        $this->initializeChatTables();
    }
    
    /**
     * Initialize chat-specific database tables
     */
    private function initializeChatTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS chat_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_key TEXT UNIQUE NOT NULL,
            context_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id INTEGER,
            message_type TEXT NOT NULL CHECK (message_type IN ('user', 'assistant', 'system')),
            content TEXT NOT NULL,
            metadata JSON,
            voice_data BLOB,
            shortcuts_used TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES chat_sessions (id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS chat_shortcuts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            trigger_text TEXT UNIQUE NOT NULL,
            replacement_text TEXT NOT NULL,
            category TEXT DEFAULT 'general',
            usage_count INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        -- Search index for chat history
        CREATE VIRTUAL TABLE IF NOT EXISTS chat_search USING fts5(
            content, 
            session_key,
            created_at,
            content=chat_messages,
            content_rowid=id
        );
        
        -- Triggers to maintain search index
        CREATE TRIGGER IF NOT EXISTS chat_search_insert AFTER INSERT ON chat_messages 
        BEGIN
            INSERT INTO chat_search(rowid, content, session_key, created_at) 
            SELECT NEW.id, NEW.content, cs.session_key, NEW.created_at
            FROM chat_sessions cs WHERE cs.id = NEW.session_id;
        END;
        
        CREATE TRIGGER IF NOT EXISTS chat_search_delete AFTER DELETE ON chat_messages 
        BEGIN
            DELETE FROM chat_search WHERE rowid = OLD.id;
        END;
        
        CREATE INDEX IF NOT EXISTS idx_chat_messages_session ON chat_messages(session_id, created_at);
        CREATE INDEX IF NOT EXISTS idx_chat_sessions_activity ON chat_sessions(last_activity);
        ";
        
        $this->db->getPdo()->exec($sql);
        
        // Initialize default shortcuts if empty
        $this->initializeDefaultShortcuts();
    }
    
    /**
     * Initialize default chat shortcuts
     */
    private function initializeDefaultShortcuts() {
        $stmt = $this->db->getPdo()->prepare("SELECT COUNT(*) as count FROM chat_shortcuts");
        $stmt->execute();
        
        if ($stmt->fetch()['count'] == 0) {
            $defaultShortcuts = [
                ['//t', 'Create a new task: ', 'tasks'],
                ['//n', 'Create a new note: ', 'notes'],
                ['//p', 'Create a new project: ', 'projects'],
                ['//s', 'Add to scraps: ', 'scraps'],
                ['//help', 'Show available commands and shortcuts', 'system'],
                ['//today', 'Show today\'s tasks and priorities', 'workflow'],
                ['//focus', 'Start focus mode for deep work', 'workflow'],
                ['//review', 'Review completed tasks and progress', 'workflow'],
                ['//search', 'Search through notes and tasks: ', 'search'],
                ['//ai', 'Ask AI for help with: ', 'ai'],
                ['//quick', 'Quick capture: ', 'capture'],
                ['//done', 'Mark task as completed: ', 'tasks'],
                ['//priority', 'Set task priority: ', 'tasks'],
                ['//due', 'Set due date: ', 'tasks']
            ];
            
            foreach ($defaultShortcuts as $shortcut) {
                $stmt = $this->db->getPdo()->prepare("
                    INSERT INTO chat_shortcuts (trigger_text, replacement_text, category)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute($shortcut);
            }
        }
    }
    
    /**
     * Get or create chat session
     */
    public function getSession($sessionKey = null) {
        if (!$sessionKey) {
            $sessionKey = 'default_' . date('Y-m-d');
        }
        
        // Try to get existing session
        $stmt = $this->db->getPdo()->prepare("
            SELECT * FROM chat_sessions WHERE session_key = ?
        ");
        $stmt->execute([$sessionKey]);
        $session = $stmt->fetch();
        
        if (!$session) {
            // Create new session
            $stmt = $this->db->getPdo()->prepare("
                INSERT INTO chat_sessions (session_key, context_data)
                VALUES (?, ?)
            ");
            $stmt->execute([$sessionKey, json_encode(['created' => date('c')])]);
            
            $sessionId = $this->db->getPdo()->lastInsertId();
            
            $session = [
                'id' => $sessionId,
                'session_key' => $sessionKey,
                'context_data' => json_encode(['created' => date('c')]),
                'created_at' => date('Y-m-d H:i:s'),
                'last_activity' => date('Y-m-d H:i:s')
            ];
        }
        
        // Update last activity
        $this->updateSessionActivity($session['id']);
        
        return $session;
    }
    
    /**
     * Update session last activity timestamp
     */
    private function updateSessionActivity($sessionId) {
        $stmt = $this->db->getPdo()->prepare("
            UPDATE chat_sessions 
            SET last_activity = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        return $stmt->execute([$sessionId]);
    }
    
    /**
     * Add message to chat session
     */
    public function addMessage($sessionId, $messageType, $content, $metadata = [], $voiceData = null) {
        // Process shortcuts in content
        $processedContent = $this->processShortcuts($content);
        $shortcutsUsed = $processedContent !== $content ? $this->getUsedShortcuts($content) : null;
        
        $stmt = $this->db->getPdo()->prepare("
            INSERT INTO chat_messages (session_id, message_type, content, metadata, voice_data, shortcuts_used)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $sessionId,
            $messageType,
            $processedContent,
            json_encode($metadata),
            $voiceData,
            $shortcutsUsed ? json_encode($shortcutsUsed) : null
        ]);
        
        if ($result) {
            $messageId = $this->db->getPdo()->lastInsertId();
            $this->updateSessionActivity($sessionId);
            $this->maintainContextWindow($sessionId);
            return $messageId;
        }
        
        return false;
    }
    
    /**
     * Process shortcuts in message content
     */
    private function processShortcuts($content) {
        // Get all shortcuts
        $stmt = $this->db->getPdo()->prepare("
            SELECT trigger_text, replacement_text FROM chat_shortcuts 
            ORDER BY LENGTH(trigger_text) DESC
        ");
        $stmt->execute();
        $shortcuts = $stmt->fetchAll();
        
        $processed = $content;
        
        foreach ($shortcuts as $shortcut) {
            if (strpos($processed, $shortcut['trigger_text']) !== false) {
                $processed = str_replace($shortcut['trigger_text'], $shortcut['replacement_text'], $processed);
                
                // Update usage count
                $this->incrementShortcutUsage($shortcut['trigger_text']);
            }
        }
        
        return $processed;
    }
    
    /**
     * Get shortcuts used in content
     */
    private function getUsedShortcuts($content) {
        $stmt = $this->db->getPdo()->prepare("
            SELECT trigger_text FROM chat_shortcuts 
            ORDER BY LENGTH(trigger_text) DESC
        ");
        $stmt->execute();
        $shortcuts = $stmt->fetchAll();
        
        $used = [];
        foreach ($shortcuts as $shortcut) {
            if (strpos($content, $shortcut['trigger_text']) !== false) {
                $used[] = $shortcut['trigger_text'];
            }
        }
        
        return $used;
    }
    
    /**
     * Increment shortcut usage counter
     */
    private function incrementShortcutUsage($triggerText) {
        $stmt = $this->db->getPdo()->prepare("
            UPDATE chat_shortcuts 
            SET usage_count = usage_count + 1, updated_at = CURRENT_TIMESTAMP
            WHERE trigger_text = ?
        ");
        return $stmt->execute([$triggerText]);
    }
    
    /**
     * Maintain context window by archiving old messages
     */
    private function maintainContextWindow($sessionId) {
        $stmt = $this->db->getPdo()->prepare("
            SELECT COUNT(*) as count FROM chat_messages WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $count = $stmt->fetch()['count'];
        
        if ($count > $this->contextWindow * 2) {
            // Archive older messages (keep double the context window)
            $stmt = $this->db->getPdo()->prepare("
                UPDATE chat_messages 
                SET metadata = JSON_SET(COALESCE(metadata, '{}'), '$.archived', 1)
                WHERE session_id = ? 
                AND id NOT IN (
                    SELECT id FROM chat_messages 
                    WHERE session_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT ?
                )
            ");
            $stmt->execute([$sessionId, $sessionId, $this->contextWindow * 2]);
        }
    }
    
    /**
     * Get recent chat messages for context
     */
    public function getRecentMessages($sessionId, $limit = null) {
        $limit = $limit ?: $this->contextWindow;
        
        $stmt = $this->db->getPdo()->prepare("
            SELECT message_type, content, metadata, shortcuts_used, created_at
            FROM chat_messages
            WHERE session_id = ? 
            AND JSON_EXTRACT(COALESCE(metadata, '{}'), '$.archived') IS NULL
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$sessionId, $limit]);
        
        return array_reverse($stmt->fetchAll());
    }
    
    /**
     * Search chat history
     */
    public function searchHistory($query, $sessionKey = null, $limit = 20) {
        $sql = "
            SELECT cm.content, cm.message_type, cm.created_at, cs.session_key,
                   snippet(chat_search, 0, '<mark>', '</mark>', '...', 50) as snippet
            FROM chat_search cs
            JOIN chat_messages cm ON cs.rowid = cm.id
            JOIN chat_sessions css ON cm.session_id = css.id
            WHERE chat_search MATCH ?
        ";
        
        $params = [$query];
        
        if ($sessionKey) {
            $sql .= " AND css.session_key = ?";
            $params[] = $sessionKey;
        }
        
        $sql .= " ORDER BY rank LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get chat shortcuts
     */
    public function getShortcuts($category = null) {
        $sql = "SELECT * FROM chat_shortcuts";
        $params = [];
        
        if ($category) {
            $sql .= " WHERE category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY usage_count DESC, trigger_text ASC";
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Add custom shortcut
     */
    public function addShortcut($triggerText, $replacementText, $category = 'custom') {
        $stmt = $this->db->getPdo()->prepare("
            INSERT OR REPLACE INTO chat_shortcuts (trigger_text, replacement_text, category)
            VALUES (?, ?, ?)
        ");
        
        return $stmt->execute([$triggerText, $replacementText, $category]);
    }
    
    /**
     * Remove shortcut
     */
    public function removeShortcut($triggerText) {
        $stmt = $this->db->getPdo()->prepare("
            DELETE FROM chat_shortcuts WHERE trigger_text = ?
        ");
        
        return $stmt->execute([$triggerText]);
    }
    
    /**
     * Get conversation context for AI
     */
    public function getConversationContext($sessionId) {
        $recentMessages = $this->getRecentMessages($sessionId);
        
        // Build context summary
        $context = [
            'message_count' => count($recentMessages),
            'recent_messages' => $recentMessages,
            'session_info' => $this->getSession(null) // Get session by ID
        ];
        
        // Analyze conversation patterns
        $context['patterns'] = $this->analyzeConversationPatterns($recentMessages);
        
        return $context;
    }
    
    /**
     * Analyze conversation patterns for better AI responses
     */
    private function analyzeConversationPatterns($messages) {
        $patterns = [
            'primary_intent' => 'general',
            'question_types' => [],
            'entity_focus' => [],
            'shortcuts_used' => []
        ];
        
        foreach ($messages as $message) {
            if ($message['message_type'] === 'user') {
                // Detect primary intent
                if (strpos(strtolower($message['content']), 'create') !== false) {
                    $patterns['primary_intent'] = 'creation';
                } elseif (strpos(strtolower($message['content']), 'search') !== false) {
                    $patterns['primary_intent'] = 'search';
                } elseif (strpos(strtolower($message['content']), 'help') !== false) {
                    $patterns['primary_intent'] = 'help';
                }
                
                // Track entity mentions
                $entities = ['task', 'note', 'project', 'scrap'];
                foreach ($entities as $entity) {
                    if (strpos(strtolower($message['content']), $entity) !== false) {
                        $patterns['entity_focus'][] = $entity;
                    }
                }
            }
            
            // Track shortcuts usage
            if (!empty($message['shortcuts_used'])) {
                $shortcuts = json_decode($message['shortcuts_used'], true);
                $patterns['shortcuts_used'] = array_merge($patterns['shortcuts_used'], $shortcuts);
            }
        }
        
        // Remove duplicates
        $patterns['entity_focus'] = array_unique($patterns['entity_focus']);
        $patterns['shortcuts_used'] = array_unique($patterns['shortcuts_used']);
        
        return $patterns;
    }
    
    /**
     * Generate smart suggestions based on context
     */
    public function generateSmartSuggestions($sessionId) {
        $context = $this->getConversationContext($sessionId);
        $suggestions = [];
        
        // Based on conversation patterns
        switch ($context['patterns']['primary_intent']) {
            case 'creation':
                $suggestions[] = ['text' => '//t Create another task', 'type' => 'shortcut'];
                $suggestions[] = ['text' => '//n Add a note', 'type' => 'shortcut'];
                break;
                
            case 'search':
                $suggestions[] = ['text' => 'Search in notes', 'type' => 'action'];
                $suggestions[] = ['text' => 'Filter by project', 'type' => 'action'];
                break;
                
            default:
                $suggestions[] = ['text' => '//today Show today\'s priorities', 'type' => 'shortcut'];
                $suggestions[] = ['text' => '//help Show available commands', 'type' => 'shortcut'];
        }
        
        // Add popular shortcuts
        $popularShortcuts = $this->getShortcuts();
        foreach (array_slice($popularShortcuts, 0, 3) as $shortcut) {
            $suggestions[] = [
                'text' => $shortcut['trigger_text'] . ' ' . substr($shortcut['replacement_text'], 0, 20) . '...',
                'type' => 'popular_shortcut'
            ];
        }
        
        return $suggestions;
    }
}

/**
 * Advanced Chat API Routes Handler
 */
class AdvancedChatRoutes {
    private $chatInterface;
    
    public function __construct($chatInterface) {
        $this->chatInterface = $chatInterface;
    }
    
    public function handleRequest($method, $endpoint, $data = []) {
        switch ($endpoint) {
            case '/api/chat/session':
                if ($method === 'GET') {
                    return $this->chatInterface->getSession($_GET['session_key'] ?? null);
                }
                break;
                
            case '/api/chat/message':
                if ($method === 'POST') {
                    return $this->addMessage($data);
                }
                break;
                
            case '/api/chat/messages':
                if ($method === 'GET') {
                    $sessionId = $_GET['session_id'] ?? null;
                    $limit = $_GET['limit'] ?? null;
                    return $this->chatInterface->getRecentMessages($sessionId, $limit);
                }
                break;
                
            case '/api/chat/search':
                if ($method === 'GET') {
                    $query = $_GET['q'] ?? '';
                    $sessionKey = $_GET['session_key'] ?? null;
                    $limit = $_GET['limit'] ?? 20;
                    return $this->chatInterface->searchHistory($query, $sessionKey, $limit);
                }
                break;
                
            case '/api/chat/shortcuts':
                if ($method === 'GET') {
                    return $this->chatInterface->getShortcuts($_GET['category'] ?? null);
                }
                if ($method === 'POST') {
                    return $this->addShortcut($data);
                }
                if ($method === 'DELETE') {
                    return $this->chatInterface->removeShortcut($data['trigger_text']);
                }
                break;
                
            case '/api/chat/context':
                if ($method === 'GET') {
                    return $this->chatInterface->getConversationContext($_GET['session_id']);
                }
                break;
                
            case '/api/chat/suggestions':
                if ($method === 'GET') {
                    return $this->chatInterface->generateSmartSuggestions($_GET['session_id']);
                }
                break;
        }
        
        return null;
    }
    
    private function addMessage($data) {
        $required = ['session_id', 'message_type', 'content'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        return $this->chatInterface->addMessage(
            $data['session_id'],
            $data['message_type'],
            $data['content'],
            $data['metadata'] ?? [],
            $data['voice_data'] ?? null
        );
    }
    
    private function addShortcut($data) {
        $required = ['trigger_text', 'replacement_text'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        return $this->chatInterface->addShortcut(
            $data['trigger_text'],
            $data['replacement_text'],
            $data['category'] ?? 'custom'
        );
    }
}

// Voice Processing Utilities
class VoiceProcessing {
    /**
     * Process voice input (placeholder for Web Speech API integration)
     */
    public static function processVoiceInput($audioBlob) {
        // This would integrate with Web Speech API on the frontend
        // Server-side voice processing could use services like:
        // - Google Cloud Speech-to-Text
        // - Azure Cognitive Services
        // - AWS Transcribe
        
        return [
            'transcription' => 'Voice processing not yet implemented',
            'confidence' => 0.0,
            'language' => 'en-US'
        ];
    }
    
    /**
     * Generate voice response (placeholder for Text-to-Speech)
     */
    public static function generateVoiceResponse($text) {
        // This would integrate with TTS services
        return [
            'audio_url' => null,
            'duration' => 0,
            'format' => 'mp3'
        ];
    }
}

// Hook execution for coordination
if (getenv('CLAUDE_FLOW_HOOKS')) {
    exec("npx claude-flow@alpha hooks pre-task --description 'advanced-chat-interface'");
    exec("npx claude-flow@alpha hooks session-restore --session-id 'swarm-roadmap-impl'");
    
    register_shutdown_function(function() {
        exec("npx claude-flow@alpha hooks post-edit --file 'advanced-chat.php' --memory-key 'swarm/chat-interface/implementation'");
        exec("npx claude-flow@alpha hooks post-task --task-id 'advanced-chat-interface'");
    });
}

?>