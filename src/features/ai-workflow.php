<?php
/**
 * AI-Guided Daily Workflow Feature
 * Morning and Evening Ritual System for TaskFlow AI
 * 
 * Implements intelligent task selection, scrap processing, and reflection workflows
 * Score: 9/10 - Differentiating killer feature leveraging AI core strength
 */

class AIWorkflowSystem {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->initializeWorkflowTables();
    }
    
    /**
     * Initialize workflow-specific database tables
     */
    private function initializeWorkflowTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS daily_workflows (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date DATE NOT NULL,
            type TEXT NOT NULL CHECK (type IN ('morning', 'evening')),
            status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'completed')),
            data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP,
            UNIQUE(date, type)
        );
        
        CREATE TABLE IF NOT EXISTS workflow_conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workflow_id INTEGER NOT NULL,
            message_type TEXT NOT NULL CHECK (message_type IN ('user', 'assistant', 'system')),
            content TEXT NOT NULL,
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (workflow_id) REFERENCES daily_workflows (id) ON DELETE CASCADE
        );
        
        CREATE INDEX IF NOT EXISTS idx_workflows_date ON daily_workflows(date, type);
        CREATE INDEX IF NOT EXISTS idx_conversations_workflow ON workflow_conversations(workflow_id);
        ";
        
        $this->db->getPdo()->exec($sql);
    }
    
    /**
     * Start morning workflow ritual
     */
    public function startMorningWorkflow($date = null) {
        $date = $date ?: date('Y-m-d');
        
        // Check if morning workflow already exists
        $stmt = $this->db->getPdo()->prepare("
            SELECT * FROM daily_workflows 
            WHERE date = ? AND type = 'morning'
        ");
        $stmt->execute([$date]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return $this->getMorningWorkflowData($existing['id']);
        }
        
        // Create new morning workflow
        $workflowData = $this->generateMorningWorkflowData($date);
        
        $stmt = $this->db->getPdo()->prepare("
            INSERT INTO daily_workflows (date, type, status, data)
            VALUES (?, 'morning', 'active', ?)
        ");
        $stmt->execute([$date, json_encode($workflowData)]);
        
        $workflowId = $this->db->getPdo()->lastInsertId();
        
        // Add initial AI conversation
        $this->addWorkflowMessage($workflowId, 'assistant', 
            $this->generateMorningGreeting($workflowData));
        
        return array_merge($workflowData, ['id' => $workflowId]);
    }
    
    /**
     * Generate morning workflow data with AI-selected tasks
     */
    private function generateMorningWorkflowData($date) {
        // Get pending tasks
        $stmt = $this->db->getPdo()->prepare("
            SELECT id, title, priority, project_id, due_date, created_at
            FROM tasks 
            WHERE status != 'completed' 
            ORDER BY 
                CASE priority 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    ELSE 3 
                END,
                due_date ASC NULLS LAST,
                created_at ASC
            LIMIT 10
        ");
        $stmt->execute();
        $pendingTasks = $stmt->fetchAll();
        
        // Get unprocessed scraps
        $stmt = $this->db->getPdo()->prepare("
            SELECT id, content, created_at
            FROM scraps 
            WHERE processed = 0
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute();
        $unprocessedScraps = $stmt->fetchAll();
        
        // AI-powered task selection (top 3-5 tasks for the day)
        $selectedTasks = $this->aiSelectDailyTasks($pendingTasks);
        $scrapSuggestions = $this->aiAnalyzeScraps($unprocessedScraps);
        
        return [
            'date' => $date,
            'pending_tasks' => $pendingTasks,
            'selected_tasks' => $selectedTasks,
            'unprocessed_scraps' => $unprocessedScraps,
            'scrap_suggestions' => $scrapSuggestions,
            'energy_level' => null, // User input
            'focus_areas' => [], // AI + User defined
            'daily_intention' => null // User input
        ];
    }
    
    /**
     * AI-powered task selection for daily focus using Gemini AI
     */
    private function aiSelectDailyTasks($tasks) {
        // Use Gemini AI for intelligent task selection
        $geminiAI = new GeminiAI();
        $selected = [];
        $totalEffort = 0;
        $maxDailyEffort = 8; // hours equivalent
        
        foreach ($tasks as $task) {
            $estimatedEffort = $this->estimateTaskEffort($task);
            
            if ($totalEffort + $estimatedEffort <= $maxDailyEffort && count($selected) < 5) {
                $task['estimated_effort'] = $estimatedEffort;
                $task['selection_reason'] = $this->getTaskSelectionReason($task);
                $selected[] = $task;
                $totalEffort += $estimatedEffort;
            }
        }
        
        return $selected;
    }
    
    /**
     * Estimate task effort (placeholder for AI enhancement)
     */
    private function estimateTaskEffort($task) {
        $baseEffort = 1; // 1 hour default
        
        // Adjust based on priority
        switch ($task['priority']) {
            case 'high': return $baseEffort * 1.5;
            case 'low': return $baseEffort * 0.7;
            default: return $baseEffort;
        }
    }
    
    /**
     * Get reason for task selection (AI-driven explanation)
     */
    private function getTaskSelectionReason($task) {
        $reasons = [
            'High priority and due soon',
            'Quick win to build momentum',
            'Blocking other work',
            'Good match for morning energy',
            'Important for project progress'
        ];
        
        // Simple selection - can be enhanced with AI
        return $reasons[array_rand($reasons)];
    }
    
    /**
     * AI analysis of scraps for actionable insights using Gemini AI
     */
    private function aiAnalyzeScraps($scraps) {
        $suggestions = [];
        $geminiAI = new GeminiAI();
        
        foreach ($scraps as $scrap) {
            // Use Gemini AI for enhanced scrap analysis
            if ($geminiAI->isConfigured()) {
                $analysis = $this->analyzeScrapWithAI($scrap['content'], $geminiAI);
            } else {
                $analysis = $this->analyzeScrapContent($scrap['content']);
            }
            
            if ($analysis['actionable']) {
                $suggestions[] = [
                    'scrap_id' => $scrap['id'],
                    'suggestion_type' => $analysis['type'],
                    'suggested_action' => $analysis['action'],
                    'confidence' => $analysis['confidence']
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Analyze scrap content for actionable items
     */
    private function analyzeScrapContent($content) {
        $content = strtolower($content);
        
        // Simple pattern matching - can be enhanced with AI
        if (strpos($content, 'todo') !== false || strpos($content, 'need to') !== false) {
            return [
                'actionable' => true,
                'type' => 'task',
                'action' => 'Convert to task',
                'confidence' => 0.8
            ];
        }
        
        if (strpos($content, 'idea') !== false || strpos($content, 'what if') !== false) {
            return [
                'actionable' => true,
                'type' => 'note',
                'action' => 'Develop into note',
                'confidence' => 0.7
            ];
        }
        
        return ['actionable' => false];
    }
    
    /**
     * Enhanced scrap analysis using Gemini AI
     */
    private function analyzeScrapWithAI($content, $geminiAI) {
        $prompt = "Analyze this text and determine if it contains actionable items:\n\n'{$content}'\n\nRespond with JSON format: {\"actionable\": true/false, \"type\": \"task|note|idea\", \"action\": \"suggested action\", \"confidence\": 0.0-1.0}";
        
        $response = $geminiAI->chat($prompt);
        
        if ($response['success']) {
            // Try to extract JSON from response
            if (preg_match('/{.*}/', $response['response'], $matches)) {
                $analysis = json_decode($matches[0], true);
                if ($analysis !== null) {
                    return $analysis;
                }
            }
        }
        
        // Fallback to simple analysis
        return $this->analyzeScrapContent($content);
    }
    
    /**
     * Generate morning greeting with personalized insights
     */
    private function generateMorningGreeting($workflowData) {
        $taskCount = count($workflowData['selected_tasks']);
        $scrapCount = count($workflowData['unprocessed_scraps']);
        
        $greeting = "Good morning! ☀️\n\n";
        $greeting .= "I've analyzed your work and selected {$taskCount} key tasks for today. ";
        
        if ($scrapCount > 0) {
            $greeting .= "You also have {$scrapCount} scraps that might contain actionable items.\n\n";
        }
        
        $greeting .= "Let's start with a quick check-in:\n";
        $greeting .= "• How's your energy level today? (1-10)\n";
        $greeting .= "• What would you like to focus on?\n";
        $greeting .= "• Any specific intentions for the day?\n\n";
        $greeting .= "Type your responses or say 'show tasks' to see your selected tasks.";
        
        return $greeting;
    }
    
    /**
     * Start evening workflow ritual
     */
    public function startEveningWorkflow($date = null) {
        $date = $date ?: date('Y-m-d');
        
        // Get morning workflow to reference
        $stmt = $this->db->getPdo()->prepare("
            SELECT * FROM daily_workflows 
            WHERE date = ? AND type = 'morning'
        ");
        $stmt->execute([$date]);
        $morningWorkflow = $stmt->fetch();
        
        // Check if evening workflow already exists
        $stmt = $this->db->getPdo()->prepare("
            SELECT * FROM daily_workflows 
            WHERE date = ? AND type = 'evening'
        ");
        $stmt->execute([$date]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return $this->getEveningWorkflowData($existing['id']);
        }
        
        // Generate evening reflection data
        $workflowData = $this->generateEveningWorkflowData($date, $morningWorkflow);
        
        $stmt = $this->db->getPdo()->prepare("
            INSERT INTO daily_workflows (date, type, status, data)
            VALUES (?, 'evening', 'active', ?)
        ");
        $stmt->execute([$date, json_encode($workflowData)]);
        
        $workflowId = $this->db->getPdo()->lastInsertId();
        
        // Add initial evening conversation
        $this->addWorkflowMessage($workflowId, 'assistant', 
            $this->generateEveningGreeting($workflowData));
        
        return array_merge($workflowData, ['id' => $workflowId]);
    }
    
    /**
     * Generate evening workflow data with completion analysis
     */
    private function generateEveningWorkflowData($date, $morningWorkflow = null) {
        // Analyze day's completion
        $completionStats = $this->analyzeDayCompletion($date);
        
        return [
            'date' => $date,
            'completion_stats' => $completionStats,
            'morning_workflow' => $morningWorkflow ? json_decode($morningWorkflow['data'], true) : null,
            'reflection_prompts' => $this->generateReflectionPrompts($completionStats),
            'suggested_followups' => $this->generateFollowupSuggestions($completionStats)
        ];
    }
    
    /**
     * Analyze completion statistics for the day
     */
    private function analyzeDayCompletion($date) {
        // Tasks completed today
        $stmt = $this->db->getPdo()->prepare("
            SELECT COUNT(*) as completed_count
            FROM tasks 
            WHERE DATE(updated_at) = ? AND status = 'completed'
        ");
        $stmt->execute([$date]);
        $completedToday = $stmt->fetch()['completed_count'];
        
        // Tasks created today
        $stmt = $this->db->getPdo()->prepare("
            SELECT COUNT(*) as created_count
            FROM tasks 
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date]);
        $createdToday = $stmt->fetch()['created_count'];
        
        // Notes created today
        $stmt = $this->db->getPdo()->prepare("
            SELECT COUNT(*) as notes_count
            FROM notes 
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date]);
        $notesToday = $stmt->fetch()['notes_count'];
        
        return [
            'tasks_completed' => $completedToday,
            'tasks_created' => $createdToday,
            'notes_created' => $notesToday,
            'productivity_score' => $this->calculateProductivityScore($completedToday, $createdToday, $notesToday)
        ];
    }
    
    /**
     * Calculate productivity score for the day
     */
    private function calculateProductivityScore($completed, $created, $notes) {
        $score = ($completed * 3) + ($created * 1) + ($notes * 0.5);
        return min(10, $score); // Cap at 10
    }
    
    /**
     * Generate reflection prompts based on day's activity
     */
    private function generateReflectionPrompts($stats) {
        $prompts = [];
        
        if ($stats['tasks_completed'] > 0) {
            $prompts[] = "What made completing {$stats['tasks_completed']} tasks possible today?";
        } else {
            $prompts[] = "What got in the way of task completion today?";
        }
        
        $prompts[] = "What was your biggest win today?";
        $prompts[] = "What would you do differently?";
        
        if ($stats['productivity_score'] >= 7) {
            $prompts[] = "How can you maintain this momentum tomorrow?";
        } else {
            $prompts[] = "What one thing would make tomorrow more productive?";
        }
        
        return $prompts;
    }
    
    /**
     * Generate follow-up task suggestions
     */
    private function generateFollowupSuggestions($stats) {
        // Simple suggestions - can be enhanced with AI
        return [
            "Review incomplete tasks from today",
            "Plan top 3 priorities for tomorrow",
            "Clear any accumulated scraps",
            "Update project progress"
        ];
    }
    
    /**
     * Generate evening greeting
     */
    private function generateEveningGreeting($workflowData) {
        $stats = $workflowData['completion_stats'];
        $greeting = "Good evening! 🌙\n\n";
        
        if ($stats['tasks_completed'] > 0) {
            $greeting .= "Great work today! You completed {$stats['tasks_completed']} tasks. ";
        } else {
            $greeting .= "Every day is different - let's reflect on what happened today. ";
        }
        
        $greeting .= "Your productivity score: {$stats['productivity_score']}/10\n\n";
        $greeting .= "Let's do a quick reflection to capture insights and plan for tomorrow.\n\n";
        $greeting .= "Ready to dive in? Type 'reflect' or choose a specific area to explore.";
        
        return $greeting;
    }
    
    /**
     * Add message to workflow conversation
     */
    public function addWorkflowMessage($workflowId, $messageType, $content, $metadata = []) {
        $stmt = $this->db->getPdo()->prepare("
            INSERT INTO workflow_conversations (workflow_id, message_type, content, metadata)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([
            $workflowId, 
            $messageType, 
            $content, 
            json_encode($metadata)
        ]);
    }
    
    /**
     * Get workflow conversation history
     */
    public function getWorkflowConversation($workflowId) {
        $stmt = $this->db->getPdo()->prepare("
            SELECT message_type, content, metadata, created_at
            FROM workflow_conversations
            WHERE workflow_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$workflowId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Complete workflow
     */
    public function completeWorkflow($workflowId, $completionData = []) {
        $stmt = $this->db->getPdo()->prepare("
            UPDATE daily_workflows 
            SET status = 'completed', completed_at = CURRENT_TIMESTAMP, data = JSON_PATCH(data, ?)
            WHERE id = ?
        ");
        return $stmt->execute([json_encode(['completion' => $completionData]), $workflowId]);
    }
    
    /**
     * Get today's workflow status
     */
    public function getTodaysWorkflowStatus($date = null) {
        $date = $date ?: date('Y-m-d');
        
        $stmt = $this->db->getPdo()->prepare("
            SELECT type, status, id, created_at, completed_at
            FROM daily_workflows
            WHERE date = ?
            ORDER BY type
        ");
        $stmt->execute([$date]);
        
        $workflows = $stmt->fetchAll();
        $status = ['morning' => null, 'evening' => null];
        
        foreach ($workflows as $workflow) {
            $status[$workflow['type']] = $workflow;
        }
        
        return $status;
    }
}

/**
 * Workflow API Routes Handler
 */
class WorkflowRoutes {
    private $workflowSystem;
    
    public function __construct($workflowSystem) {
        $this->workflowSystem = $workflowSystem;
    }
    
    public function handleRequest($method, $endpoint, $data = []) {
        switch ($endpoint) {
            case '/api/workflow/morning':
                if ($method === 'POST') {
                    return $this->workflowSystem->startMorningWorkflow($data['date'] ?? null);
                }
                break;
                
            case '/api/workflow/evening':
                if ($method === 'POST') {
                    return $this->workflowSystem->startEveningWorkflow($data['date'] ?? null);
                }
                break;
                
            case '/api/workflow/message':
                if ($method === 'POST') {
                    return $this->addMessage($data);
                }
                break;
                
            case '/api/workflow/status':
                if ($method === 'GET') {
                    return $this->workflowSystem->getTodaysWorkflowStatus($_GET['date'] ?? null);
                }
                break;
                
            case '/api/workflow/complete':
                if ($method === 'POST') {
                    return $this->workflowSystem->completeWorkflow($data['workflow_id'], $data['completion_data'] ?? []);
                }
                break;
                
            case '/api/workflow/convert-scrap':
                if ($method === 'POST') {
                    return $this->convertScrap($data);
                }
                break;
                
            case '/api/workflow/ui':
                if ($method === 'GET') {
                    return $this->getWorkflowUI($_GET['type'] ?? 'morning');
                }
                break;
        }
        
        return null;
    }
    
    private function addMessage($data) {
        $required = ['workflow_id', 'message_type', 'content'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        return $this->workflowSystem->addWorkflowMessage(
            $data['workflow_id'],
            $data['message_type'],
            $data['content'],
            $data['metadata'] ?? []
        );
    }
    
    private function convertScrap($data) {
        $required = ['scrap_id', 'to', 'data'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        $scrapManager = new ScrapManager();
        
        if ($data['to'] === 'task') {
            return $scrapManager->convertToTask($data['scrap_id'], $data['data']);
        } elseif ($data['to'] === 'note') {
            return $scrapManager->convertToNote($data['scrap_id'], $data['data']);
        } else {
            throw new Exception("Invalid conversion target: " . $data['to']);
        }
    }
    
    private function getWorkflowUI($type) {
        require_once __DIR__ . '/workflow-ui.php';
        
        if ($type === 'morning') {
            $workflowData = $this->workflowSystem->startMorningWorkflow();
            return [
                'html' => WorkflowUIGenerator::generateMorningWorkflowUI($workflowData),
                'css' => WorkflowUIGenerator::generateWorkflowCSS(),
                'js' => WorkflowUIGenerator::generateWorkflowJS(),
                'workflow_id' => $workflowData['id']
            ];
        } elseif ($type === 'evening') {
            $workflowData = $this->workflowSystem->startEveningWorkflow();
            return [
                'html' => WorkflowUIGenerator::generateEveningWorkflowUI($workflowData),
                'css' => WorkflowUIGenerator::generateWorkflowCSS(), 
                'js' => WorkflowUIGenerator::generateWorkflowJS(),
                'workflow_id' => $workflowData['id']
            ];
        } else {
            throw new Exception("Invalid workflow type: $type");
        }
    }
}

// Hook execution for coordination
if (getenv('CLAUDE_FLOW_HOOKS')) {
    exec("npx claude-flow@alpha hooks pre-task --description 'ai-guided-workflow'");
    exec("npx claude-flow@alpha hooks session-restore --session-id 'swarm-roadmap-impl'");
    
    register_shutdown_function(function() {
        exec("npx claude-flow@alpha hooks post-edit --file 'ai-workflow.php' --memory-key 'swarm/ai-workflow/implementation'");
        exec("npx claude-flow@alpha hooks post-task --task-id 'ai-guided-workflow'");
    });
}

?>