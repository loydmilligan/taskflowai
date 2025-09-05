<?php
/**
 * Enhanced AI Conversation System with Memory and Today's Plan Integration
 * 
 * Features:
 * - Persistent conversation memory across messages
 * - Context-aware multi-turn conversations
 * - Workflow integration with Today's Plan
 * - Session management for ongoing conversations
 * - Smart task selection and daily planning
 */

class ConversationMemoryManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->initializeTables();
    }
    
    /**
     * Initialize conversation memory tables
     */
    private function initializeTables() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS conversation_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT UNIQUE NOT NULL,
                type TEXT DEFAULT 'general', -- 'general', 'morning_workflow', 'evening_workflow'
                context_summary TEXT,
                current_state TEXT, -- JSON for current conversation state
                workflow_data TEXT, -- JSON for workflow-specific data
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP,
                is_active INTEGER DEFAULT 1
            )
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS conversation_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT NOT NULL,
                message_type TEXT CHECK(message_type IN ('user', 'assistant', 'system')),
                content TEXT NOT NULL,
                metadata TEXT, -- JSON for additional message data
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (session_id) REFERENCES conversation_sessions(session_id)
            )
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS todays_plan (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                selected_date DATE NOT NULL,
                selection_reason TEXT,
                priority_order INTEGER,
                workflow_session_id TEXT,
                ai_selected INTEGER DEFAULT 0,
                user_confirmed INTEGER DEFAULT 0,
                completed INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (task_id) REFERENCES tasks(id),
                UNIQUE(task_id, selected_date)
            )
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS workflow_conversations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workflow_type TEXT NOT NULL,
                workflow_date DATE NOT NULL,
                session_id TEXT NOT NULL,
                phase TEXT, -- 'task_selection', 'energy_check', 'intention_setting', 'reflection', etc.
                phase_data TEXT, -- JSON for phase-specific data
                completed INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (session_id) REFERENCES conversation_sessions(session_id)
            )
        ");
    }
    
    /**
     * Start or resume a conversation session
     */
    public function getOrCreateSession($sessionId = null, $type = 'general', $workflowData = null) {
        if (!$sessionId) {
            $sessionId = 'session_' . uniqid() . '_' . time();
        }
        
        // Check if session exists and is active
        $stmt = $this->db->prepare("SELECT * FROM conversation_sessions WHERE session_id = ? AND is_active = 1");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            // Update last activity
            $this->db->prepare("UPDATE conversation_sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_id = ?")
                     ->execute([$sessionId]);
            return $session;
        }
        
        // Create new session
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours')); // Sessions expire after 24 hours
        
        $stmt = $this->db->prepare("
            INSERT INTO conversation_sessions (session_id, type, workflow_data, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$sessionId, $type, json_encode($workflowData), $expiresAt]);
        
        return [
            'session_id' => $sessionId,
            'type' => $type,
            'workflow_data' => json_encode($workflowData),
            'current_state' => null,
            'context_summary' => null,
            'started_at' => date('Y-m-d H:i:s'),
            'is_active' => 1
        ];
    }
    
    /**
     * Add message to conversation
     */
    public function addMessage($sessionId, $messageType, $content, $metadata = []) {
        $stmt = $this->db->prepare("
            INSERT INTO conversation_messages (session_id, message_type, content, metadata)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$sessionId, $messageType, $content, json_encode($metadata)]);
        
        // Update session activity
        $this->db->prepare("UPDATE conversation_sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_id = ?")
                 ->execute([$sessionId]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get conversation history
     */
    public function getConversationHistory($sessionId, $limit = 20) {
        $stmt = $this->db->prepare("
            SELECT message_type, content, metadata, created_at
            FROM conversation_messages 
            WHERE session_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$sessionId, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC)); // Chronological order
    }
    
    /**
     * Update conversation state
     */
    public function updateConversationState($sessionId, $state, $contextSummary = null) {
        $stmt = $this->db->prepare("
            UPDATE conversation_sessions 
            SET current_state = ?, context_summary = ?, last_activity = CURRENT_TIMESTAMP
            WHERE session_id = ?
        ");
        $stmt->execute([json_encode($state), $contextSummary, $sessionId]);
    }
    
    /**
     * Get conversation context for AI
     */
    public function getContextForAI($sessionId) {
        $session = $this->getOrCreateSession($sessionId);
        $history = $this->getConversationHistory($sessionId);
        
        $context = [
            'session_id' => $sessionId,
            'session_type' => $session['type'],
            'current_state' => json_decode($session['current_state'], true),
            'context_summary' => $session['context_summary'],
            'workflow_data' => json_decode($session['workflow_data'], true),
            'conversation_history' => $history,
            'session_duration' => time() - strtotime($session['started_at'])
        ];
        
        return $context;
    }
}

class TodaysPlanManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Add task to today's plan
     */
    public function addTaskToToday($taskId, $date = null, $reason = null, $sessionId = null, $aiSelected = true) {
        if (!$date) $date = date('Y-m-d');
        
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO todays_plan 
            (task_id, selected_date, selection_reason, workflow_session_id, ai_selected, priority_order)
            VALUES (?, ?, ?, ?, ?, 
                (SELECT COALESCE(MAX(priority_order), 0) + 1 FROM todays_plan WHERE selected_date = ?)
            )
        ");
        
        return $stmt->execute([$taskId, $date, $reason, $sessionId, $aiSelected ? 1 : 0, $date]);
    }
    
    /**
     * Get today's plan with task details
     */
    public function getTodaysPlan($date = null) {
        if (!$date) $date = date('Y-m-d');
        
        $stmt = $this->db->prepare("
            SELECT tp.*, t.title, t.description, t.priority, t.status, t.due_date, 
                   p.name as project_name, tp.selection_reason
            FROM todays_plan tp
            JOIN tasks t ON tp.task_id = t.id  
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE tp.selected_date = ?
            ORDER BY tp.priority_order ASC
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Remove task from today's plan
     */
    public function removeFromToday($taskId, $date = null) {
        if (!$date) $date = date('Y-m-d');
        
        $stmt = $this->db->prepare("DELETE FROM todays_plan WHERE task_id = ? AND selected_date = ?");
        return $stmt->execute([$taskId, $date]);
    }
    
    /**
     * Mark task as completed in today's plan
     */
    public function markCompleted($taskId, $date = null) {
        if (!$date) $date = date('Y-m-d');
        
        $stmt = $this->db->prepare("
            UPDATE todays_plan 
            SET completed = 1, updated_at = CURRENT_TIMESTAMP 
            WHERE task_id = ? AND selected_date = ?
        ");
        return $stmt->execute([$taskId, $date]);
    }
    
    /**
     * Reorder today's plan
     */
    public function reorderTasks($taskIds, $date = null) {
        if (!$date) $date = date('Y-m-d');
        
        $this->db->beginTransaction();
        try {
            foreach ($taskIds as $order => $taskId) {
                $stmt = $this->db->prepare("
                    UPDATE todays_plan 
                    SET priority_order = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE task_id = ? AND selected_date = ?
                ");
                $stmt->execute([$order + 1, $taskId, $date]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
}

class EnhancedWorkflowAI {
    private $db;
    private $geminiAI;
    private $conversationManager;
    private $todaysManager;
    
    public function __construct($database, $geminiAI) {
        $this->db = $database;
        $this->geminiAI = $geminiAI;
        $this->conversationManager = new ConversationMemoryManager($database);
        $this->todaysManager = new TodaysPlanManager($database);
    }
    
    /**
     * Enhanced chat with conversation memory
     */
    public function chat($message, $sessionId = null, $context = []) {
        // Get or create session
        $session = $this->conversationManager->getOrCreateSession($sessionId, $context['type'] ?? 'general');
        $sessionId = $session['session_id'];
        
        // Add user message to conversation
        $this->conversationManager->addMessage($sessionId, 'user', $message);
        
        // Get full conversation context
        $conversationContext = $this->conversationManager->getContextForAI($sessionId);
        
        // Build enhanced prompt with memory
        $systemPrompt = $this->buildEnhancedSystemPrompt($conversationContext);
        
        // Get AI response
        $response = $this->geminiAI->chat($message, array_merge($context, [
            'system_prompt' => $systemPrompt,
            'conversation_context' => $conversationContext
        ]));
        
        if ($response['success']) {
            // Add AI response to conversation
            $this->conversationManager->addMessage($sessionId, 'assistant', $response['response']);
            
            // Parse for workflow actions
            $workflowActions = $this->parseWorkflowActions($response['response'], $sessionId);
            if (!empty($workflowActions)) {
                $response['workflow_actions'] = $workflowActions;
                $this->executeWorkflowActions($workflowActions, $sessionId);
            }
            
            // Update conversation state
            $this->updateConversationState($sessionId, $response, $conversationContext);
            
            $response['session_id'] = $sessionId;
        }
        
        return $response;
    }
    
    /**
     * Build enhanced system prompt with conversation memory
     */
    private function buildEnhancedSystemPrompt($context) {
        $basePrompt = "You are TaskFlow AI, an intelligent task management assistant. You help users manage their projects, tasks, and daily workflows through natural conversation.";
        
        $memoryPrompt = "\n\nCONVERSATION MEMORY:\n";
        if ($context['context_summary']) {
            $memoryPrompt .= "Context Summary: " . $context['context_summary'] . "\n";
        }
        
        if ($context['current_state']) {
            $memoryPrompt .= "Current State: " . json_encode($context['current_state']) . "\n";
        }
        
        if ($context['session_type'] !== 'general') {
            $memoryPrompt .= "Session Type: " . $context['session_type'] . "\n";
        }
        
        if (!empty($context['conversation_history'])) {
            $memoryPrompt .= "Recent Conversation:\n";
            $recent = array_slice($context['conversation_history'], -5); // Last 5 messages
            foreach ($recent as $msg) {
                $memoryPrompt .= "- {$msg['message_type']}: " . substr($msg['content'], 0, 100) . "...\n";
            }
        }
        
        $workflowPrompt = "\n\nWORKFLOW CAPABILITIES:\n";
        $workflowPrompt .= "- You can add tasks to Today's Plan using [ADD_TO_TODAY:task_id:reason]\n";
        $workflowPrompt .= "- You can start workflows using [START_WORKFLOW:type] (morning/evening)\n";
        $workflowPrompt .= "- You maintain conversation context across multiple messages\n";
        $workflowPrompt .= "- When users ask follow-up questions, refer to previous context\n";
        $workflowPrompt .= "- For workflows, guide users through multiple phases: task selection, energy check, intention setting\n";
        
        $todaysPlanPrompt = "\n\nTODAY'S PLAN INTEGRATION:\n";
        $todaysPlanPrompt .= "- When selecting tasks for workflows, automatically add them to Today's Plan\n";
        $todaysPlanPrompt .= "- Provide clear reasons for task selection\n";
        $todaysPlanPrompt .= "- Consider task priority, due dates, and user energy levels\n";
        $todaysPlanPrompt .= "- Ask follow-up questions to refine task selection if needed\n";
        
        return $basePrompt . $memoryPrompt . $workflowPrompt . $todaysPlanPrompt;
    }
    
    /**
     * Parse workflow actions from AI response
     */
    private function parseWorkflowActions($response, $sessionId) {
        $actions = [];
        
        // Parse ADD_TO_TODAY actions
        if (preg_match_all('/\[ADD_TO_TODAY:(\d+):([^\]]+)\]/', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $actions[] = [
                    'type' => 'ADD_TO_TODAY',
                    'task_id' => intval($match[1]),
                    'reason' => $match[2],
                    'session_id' => $sessionId
                ];
            }
        }
        
        // Parse START_WORKFLOW actions
        if (preg_match_all('/\[START_WORKFLOW:([^\]]+)\]/', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $actions[] = [
                    'type' => 'START_WORKFLOW',
                    'workflow_type' => $match[1],
                    'session_id' => $sessionId
                ];
            }
        }
        
        // Parse other workflow actions
        if (preg_match_all('/\[([A-Z_]+):([^\]]*)\]/', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!in_array($match[1], ['ADD_TO_TODAY', 'START_WORKFLOW'])) {
                    $actions[] = [
                        'type' => $match[1],
                        'data' => $match[2],
                        'session_id' => $sessionId
                    ];
                }
            }
        }
        
        return $actions;
    }
    
    /**
     * Execute workflow actions
     */
    private function executeWorkflowActions($actions, $sessionId) {
        foreach ($actions as $action) {
            switch ($action['type']) {
                case 'ADD_TO_TODAY':
                    $this->todaysManager->addTaskToToday(
                        $action['task_id'],
                        date('Y-m-d'),
                        $action['reason'],
                        $sessionId,
                        true // AI selected
                    );
                    break;
                    
                case 'START_WORKFLOW':
                    // Create workflow conversation entry
                    $stmt = $this->db->prepare("
                        INSERT INTO workflow_conversations (workflow_type, workflow_date, session_id, phase)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$action['workflow_type'], date('Y-m-d'), $sessionId, 'initiated']);
                    break;
            }
        }
    }
    
    /**
     * Update conversation state based on AI response
     */
    private function updateConversationState($sessionId, $response, $context) {
        // Determine conversation state
        $state = $context['current_state'] ?: [];
        
        // Update based on response content and actions
        if (isset($response['workflow_actions'])) {
            $state['has_pending_actions'] = true;
            $state['last_actions'] = $response['workflow_actions'];
        }
        
        // Generate context summary for long conversations
        if (count($context['conversation_history']) > 10) {
            $state['needs_summary'] = true;
        }
        
        $this->conversationManager->updateConversationState($sessionId, $state);
    }
    
    /**
     * Start morning workflow with conversation memory
     */
    public function startMorningWorkflow($sessionId = null) {
        $session = $this->conversationManager->getOrCreateSession($sessionId, 'morning_workflow', [
            'workflow_type' => 'morning',
            'date' => date('Y-m-d'),
            'phase' => 'task_selection'
        ]);
        
        // Get available tasks
        $availableTasks = $this->getAvailableTasksForSelection();
        
        // AI analyzes and selects tasks
        $selectedTasks = $this->aiSelectDailyTasks($availableTasks, $session['session_id']);
        
        // Add selected tasks to Today's Plan
        foreach ($selectedTasks as $task) {
            $this->todaysManager->addTaskToToday(
                $task['id'],
                date('Y-m-d'),
                $task['selection_reason'],
                $session['session_id'],
                true
            );
        }
        
        return [
            'session_id' => $session['session_id'],
            'workflow_type' => 'morning',
            'selected_tasks' => $selectedTasks,
            'next_phase' => 'energy_check'
        ];
    }
    
    /**
     * AI task selection for daily workflow
     */
    private function aiSelectDailyTasks($availableTasks, $sessionId) {
        if (empty($availableTasks)) return [];
        
        $taskPrompt = "Analyze these tasks and select 3-5 key items for today's focus:\n\n";
        foreach ($availableTasks as $task) {
            $taskPrompt .= "- {$task['title']} (Priority: {$task['priority']}, Due: {$task['due_date']}, Project: {$task['project_name']})\n";
        }
        
        $taskPrompt .= "\nFor each selected task, use this format: [ADD_TO_TODAY:task_id:reason]\n";
        $taskPrompt .= "Consider: urgency, importance, energy required, dependencies, and impact.";
        
        $response = $this->geminiAI->chat($taskPrompt);
        
        if ($response['success']) {
            $actions = $this->parseWorkflowActions($response['response'], $sessionId);
            $selectedTasks = [];
            
            foreach ($actions as $action) {
                if ($action['type'] === 'ADD_TO_TODAY') {
                    // Find the task details
                    $task = array_filter($availableTasks, function($t) use ($action) {
                        return $t['id'] == $action['task_id'];
                    });
                    
                    if ($task) {
                        $taskData = array_values($task)[0];
                        $taskData['selection_reason'] = $action['reason'];
                        $selectedTasks[] = $taskData;
                    }
                }
            }
            
            return $selectedTasks;
        }
        
        return [];
    }
    
    /**
     * Get available tasks for AI selection
     */
    private function getAvailableTasksForSelection() {
        $stmt = $this->db->prepare("
            SELECT t.*, p.name as project_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE t.status IN ('pending', 'in-progress')
            AND t.id NOT IN (
                SELECT task_id FROM todays_plan WHERE selected_date = ?
            )
            ORDER BY 
                CASE t.priority 
                    WHEN 'high' THEN 3
                    WHEN 'medium' THEN 2 
                    ELSE 1 
                END DESC,
                t.due_date ASC
            LIMIT 20
        ");
        $stmt->execute([date('Y-m-d')]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Integration with existing system
class EnhancedChatRoutes {
    private $workflowAI;
    private $todaysManager;
    
    public function __construct($database, $geminiAI) {
        $this->workflowAI = new EnhancedWorkflowAI($database, $geminiAI);
        $this->todaysManager = new TodaysPlanManager($database);
    }
    
    public function handleRequest($method, $uri, $data = []) {
        // Enhanced chat endpoint with memory
        if ($uri === '/api/chat' && $method === 'POST') {
            $message = $data['message'] ?? '';
            $sessionId = $data['session_id'] ?? null;
            $context = $data['context'] ?? [];
            
            return $this->workflowAI->chat($message, $sessionId, $context);
        }
        
        // Today's Plan endpoints
        if ($uri === '/api/plan/today' && $method === 'GET') {
            $date = $_GET['date'] ?? date('Y-m-d');
            return $this->todaysManager->getTodaysPlan($date);
        }
        
        if (preg_match('#^/api/plan/today/add/(\d+)/?$#', $uri, $matches) && $method === 'POST') {
            $taskId = $matches[1];
            $reason = $data['reason'] ?? 'Added manually';
            $result = $this->todaysManager->addTaskToToday($taskId, date('Y-m-d'), $reason, null, false);
            return ['success' => $result];
        }
        
        if (preg_match('#^/api/plan/today/remove/(\d+)/?$#', $uri, $matches) && $method === 'DELETE') {
            $result = $this->todaysManager->removeFromToday($matches[1]);
            return ['success' => $result];
        }
        
        if (preg_match('#^/api/plan/today/complete/(\d+)/?$#', $uri, $matches) && $method === 'POST') {
            $result = $this->todaysManager->markCompleted($matches[1]);
            return ['success' => $result];
        }
        
        // Morning workflow with memory
        if ($uri === '/api/workflow/morning/start' && $method === 'POST') {
            $sessionId = $data['session_id'] ?? null;
            return $this->workflowAI->startMorningWorkflow($sessionId);
        }
        
        return null;
    }
}

// Hook for integration
if (getenv('CLAUDE_FLOW_HOOKS')) {
    exec("npx claude-flow@alpha hooks post-edit --file 'enhanced-ai-conversation.php' --memory-key 'swarm/enhanced-ai/conversation-memory'");
}
?>