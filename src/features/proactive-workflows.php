<?php
/**
 * Proactive Workflow Notification System for TaskFlow AI
 * 
 * Features:
 * - Scheduled morning/evening workflow notifications
 * - ntfy integration with interactive action buttons
 * - Configurable times and topics
 * - Snooze functionality (15/30/60 minutes)
 * - Workflow state management
 * - Deep linking to workflow interfaces
 * - Admin metrics and testing
 */

class ProactiveWorkflowManager {
    private $db;
    private $ntfyManager;
    private $aiWorkflow;
    
    public function __construct($database, $ntfyManager = null, $aiWorkflow = null) {
        $this->db = $database;
        $this->ntfyManager = $ntfyManager;
        $this->aiWorkflow = $aiWorkflow;
        $this->initializeTables();
    }
    
    /**
     * Initialize database tables for proactive workflows
     */
    private function initializeTables() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS workflow_schedules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL CHECK(type IN ('morning', 'evening')),
                enabled INTEGER DEFAULT 1,
                scheduled_time TEXT NOT NULL DEFAULT '09:00',
                timezone TEXT DEFAULT 'UTC',
                ntfy_topic TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS workflow_states (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workflow_type TEXT NOT NULL CHECK(workflow_type IN ('morning', 'evening')),
                workflow_date DATE NOT NULL,
                state TEXT NOT NULL CHECK(state IN ('pending', 'notified', 'snoozed', 'started', 'completed', 'cancelled')),
                snooze_until TIMESTAMP NULL,
                snooze_count INTEGER DEFAULT 0,
                notification_sent_at TIMESTAMP NULL,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                cancelled_at TIMESTAMP NULL,
                metadata TEXT, -- JSON for storing additional data
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(workflow_type, workflow_date)
            )
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS workflow_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workflow_type TEXT NOT NULL,
                workflow_date DATE NOT NULL,
                action TEXT NOT NULL,
                status TEXT NOT NULL,
                message TEXT,
                error_details TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create default schedules if they don't exist
        $this->createDefaultSchedules();
    }
    
    /**
     * Create default workflow schedules
     */
    private function createDefaultSchedules() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM workflow_schedules");
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            // Create default morning schedule
            $this->db->exec("
                INSERT INTO workflow_schedules (type, enabled, scheduled_time, ntfy_topic) 
                VALUES ('morning', 1, '09:00', 'taskflow-morning-workflow')
            ");
            
            // Create default evening schedule  
            $this->db->exec("
                INSERT INTO workflow_schedules (type, enabled, scheduled_time, ntfy_topic)
                VALUES ('evening', 1, '18:00', 'taskflow-evening-workflow')
            ");
        }
    }
    
    /**
     * Get workflow schedule configuration
     */
    public function getScheduleConfig($type = null) {
        if ($type) {
            $stmt = $this->db->prepare("SELECT * FROM workflow_schedules WHERE type = ?");
            $stmt->execute([$type]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $stmt = $this->db->prepare("SELECT * FROM workflow_schedules ORDER BY type");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update workflow schedule configuration
     */
    public function updateScheduleConfig($type, $config) {
        $stmt = $this->db->prepare("
            UPDATE workflow_schedules 
            SET enabled = ?, scheduled_time = ?, timezone = ?, ntfy_topic = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE type = ?
        ");
        
        return $stmt->execute([
            $config['enabled'] ?? 1,
            $config['scheduled_time'] ?? '09:00',
            $config['timezone'] ?? 'UTC', 
            $config['ntfy_topic'] ?? "taskflow-{$type}-workflow",
            $type
        ]);
    }
    
    /**
     * Check for due workflows and send notifications
     */
    public function checkDueWorkflows() {
        $this->logAction('system', date('Y-m-d'), 'check_due_workflows', 'started', 'Checking for due workflows');
        
        $schedules = $this->getScheduleConfig();
        $today = date('Y-m-d');
        $now = new DateTime();
        
        foreach ($schedules as $schedule) {
            if (!$schedule['enabled']) continue;
            
            $workflowTime = DateTime::createFromFormat('Y-m-d H:i', $today . ' ' . $schedule['scheduled_time']);
            
            // Check if it's time for this workflow (within 5 minutes)
            $timeDiff = $now->getTimestamp() - $workflowTime->getTimestamp();
            if ($timeDiff >= 0 && $timeDiff <= 300) { // 5 minutes window
                $this->processWorkflowTrigger($schedule['type'], $today, $schedule);
            }
        }
        
        // Also check for snoozed workflows that are due
        $this->processSnoozedWorkflows();
    }
    
    /**
     * Process workflow trigger
     */
    private function processWorkflowTrigger($type, $date, $schedule) {
        // Check if workflow already processed today
        $state = $this->getWorkflowState($type, $date);
        
        if ($state && in_array($state['state'], ['notified', 'started', 'completed', 'cancelled'])) {
            return; // Already processed
        }
        
        // Create or update workflow state
        $this->updateWorkflowState($type, $date, 'notified');
        
        // Send notifications
        $this->sendWorkflowNotification($type, $date, $schedule);
    }
    
    /**
     * Process snoozed workflows that are now due
     */
    private function processSnoozedWorkflows() {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            SELECT ws.*, sch.ntfy_topic 
            FROM workflow_states ws
            JOIN workflow_schedules sch ON ws.workflow_type = sch.type
            WHERE ws.state = 'snoozed' 
            AND ws.snooze_until <= ?
            AND sch.enabled = 1
        ");
        $stmt->execute([$now]);
        $snoozedWorkflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($snoozedWorkflows as $workflow) {
            // Update state back to notified
            $this->updateWorkflowState($workflow['workflow_type'], $workflow['workflow_date'], 'notified');
            
            // Resend notification
            $schedule = $this->getScheduleConfig($workflow['workflow_type']);
            $this->sendWorkflowNotification($workflow['workflow_type'], $workflow['workflow_date'], $schedule);
            
            $this->logAction($workflow['workflow_type'], $workflow['workflow_date'], 
                           'snooze_expired', 'success', 'Snooze period expired, resending notification');
        }
    }
    
    /**
     * Send workflow notification via ntfy and in-app
     */
    private function sendWorkflowNotification($type, $date, $schedule) {
        $title = $type === 'morning' ? 
            "🌅 Good Morning! Ready for your daily workflow?" : 
            "🌙 Evening Reflection Time - How was your day?";
            
        $message = $type === 'morning' ? 
            "Let's start your day with AI-guided task selection and daily planning." :
            "Time to reflect on your progress and prepare for tomorrow.";
        
        // Create action buttons for ntfy
        $actions = [
            [
                'action' => 'http',
                'label' => 'Start Workflow',
                'url' => "https://taskflow.mattmariani.com/workflow/{$type}?trigger=notification&date={$date}",
                'method' => 'GET'
            ],
            [
                'action' => 'http', 
                'label' => 'Snooze 15min',
                'url' => "https://taskflow.mattmariani.com/api/workflow/snooze",
                'method' => 'POST',
                'body' => json_encode(['type' => $type, 'date' => $date, 'minutes' => 15])
            ],
            [
                'action' => 'http',
                'label' => 'Snooze 30min', 
                'url' => "https://taskflow.mattmariani.com/api/workflow/snooze",
                'method' => 'POST',
                'body' => json_encode(['type' => $type, 'date' => $date, 'minutes' => 30])
            ],
            [
                'action' => 'http',
                'label' => 'Cancel Today',
                'url' => "https://taskflow.mattmariani.com/api/workflow/cancel",
                'method' => 'POST', 
                'body' => json_encode(['type' => $type, 'date' => $date])
            ]
        ];
        
        try {
            // Send ntfy notification
            if ($this->ntfyManager && !empty($schedule['ntfy_topic'])) {
                $result = $this->ntfyManager->sendNotification(
                    $title,
                    $message,
                    ["workflow", $type],
                    4, // High priority
                    $actions
                );
                
                if ($result['success']) {
                    $this->logAction($type, $date, 'notification_sent', 'success', 'ntfy notification sent successfully');
                } else {
                    $this->logAction($type, $date, 'notification_failed', 'error', 'ntfy notification failed: ' . $result['error']);
                }
            }
            
            // Send in-app chat message (if AI workflow system available)
            if ($this->aiWorkflow) {
                $this->sendInAppWorkflowMessage($type, $date, $title, $message);
            }
            
            // Update notification sent timestamp
            $stmt = $this->db->prepare("
                UPDATE workflow_states 
                SET notification_sent_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP 
                WHERE workflow_type = ? AND workflow_date = ?
            ");
            $stmt->execute([$type, $date]);
            
        } catch (Exception $e) {
            $this->logAction($type, $date, 'notification_error', 'error', 'Exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Send in-app workflow message
     */
    private function sendInAppWorkflowMessage($type, $date, $title, $message) {
        // This would integrate with the chat system to show workflow prompts
        $workflowMessage = [
            'type' => 'workflow_notification',
            'workflow_type' => $type,
            'date' => $date,
            'title' => $title,
            'message' => $message,
            'actions' => [
                ['label' => 'Start Now', 'action' => 'start_workflow'],
                ['label' => 'Snooze 15min', 'action' => 'snooze', 'minutes' => 15],
                ['label' => 'Skip Today', 'action' => 'cancel']
            ]
        ];
        
        // Store in database for chat system to pick up
        $stmt = $this->db->prepare("
            INSERT INTO chat_messages (type, content, metadata, created_at) 
            VALUES ('workflow_notification', ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$title . "\n\n" . $message, json_encode($workflowMessage)]);
    }
    
    /**
     * Handle workflow action (snooze, cancel, start)
     */
    public function handleWorkflowAction($type, $date, $action, $data = []) {
        $currentState = $this->getWorkflowState($type, $date);
        
        switch ($action) {
            case 'snooze':
                $minutes = $data['minutes'] ?? 15;
                $snoozeUntil = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
                
                $this->updateWorkflowState($type, $date, 'snoozed', [
                    'snooze_until' => $snoozeUntil,
                    'snooze_count' => ($currentState['snooze_count'] ?? 0) + 1
                ]);
                
                $this->logAction($type, $date, 'snoozed', 'success', "Snoozed for {$minutes} minutes");
                return ['success' => true, 'message' => "Workflow snoozed for {$minutes} minutes"];
                
            case 'cancel':
                $this->updateWorkflowState($type, $date, 'cancelled', [
                    'cancelled_at' => date('Y-m-d H:i:s')
                ]);
                
                $this->logAction($type, $date, 'cancelled', 'success', 'Workflow cancelled by user');
                return ['success' => true, 'message' => 'Today\'s workflow cancelled'];
                
            case 'start':
                $this->updateWorkflowState($type, $date, 'started', [
                    'started_at' => date('Y-m-d H:i:s')
                ]);
                
                $this->logAction($type, $date, 'started', 'success', 'Workflow started by user');
                return ['success' => true, 'message' => 'Workflow started', 'redirect' => "/workflow/{$type}"];
                
            case 'complete':
                $this->updateWorkflowState($type, $date, 'completed', [
                    'completed_at' => date('Y-m-d H:i:s')
                ]);
                
                $this->logAction($type, $date, 'completed', 'success', 'Workflow completed');
                return ['success' => true, 'message' => 'Workflow completed'];
                
            default:
                return ['success' => false, 'error' => 'Invalid action'];
        }
    }
    
    /**
     * Get workflow state for specific date and type
     */
    public function getWorkflowState($type, $date) {
        $stmt = $this->db->prepare("SELECT * FROM workflow_states WHERE workflow_type = ? AND workflow_date = ?");
        $stmt->execute([$type, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update workflow state
     */
    private function updateWorkflowState($type, $date, $state, $metadata = []) {
        // Check if state exists
        $currentState = $this->getWorkflowState($type, $date);
        
        if ($currentState) {
            // Update existing state
            $sql = "UPDATE workflow_states SET state = ?, updated_at = CURRENT_TIMESTAMP";
            $params = [$state];
            
            if (isset($metadata['snooze_until'])) {
                $sql .= ", snooze_until = ?";
                $params[] = $metadata['snooze_until'];
            }
            if (isset($metadata['snooze_count'])) {
                $sql .= ", snooze_count = ?"; 
                $params[] = $metadata['snooze_count'];
            }
            if (isset($metadata['started_at'])) {
                $sql .= ", started_at = ?";
                $params[] = $metadata['started_at'];
            }
            if (isset($metadata['completed_at'])) {
                $sql .= ", completed_at = ?";
                $params[] = $metadata['completed_at'];
            }
            if (isset($metadata['cancelled_at'])) {
                $sql .= ", cancelled_at = ?";
                $params[] = $metadata['cancelled_at'];
            }
            
            $sql .= " WHERE workflow_type = ? AND workflow_date = ?";
            $params[] = $type;
            $params[] = $date;
            
        } else {
            // Create new state
            $sql = "INSERT INTO workflow_states (workflow_type, workflow_date, state";
            $values = "VALUES (?, ?, ?";
            $params = [$type, $date, $state];
            
            if (isset($metadata['snooze_until'])) {
                $sql .= ", snooze_until";
                $values .= ", ?";
                $params[] = $metadata['snooze_until'];
            }
            if (isset($metadata['snooze_count'])) {
                $sql .= ", snooze_count";
                $values .= ", ?";
                $params[] = $metadata['snooze_count'];
            }
            
            $sql .= ") " . $values . ")";
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Log workflow action
     */
    private function logAction($type, $date, $action, $status, $message = null, $errorDetails = null) {
        $stmt = $this->db->prepare("
            INSERT INTO workflow_logs (workflow_type, workflow_date, action, status, message, error_details) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$type, $date, $action, $status, $message, $errorDetails]);
    }
    
    /**
     * Get workflow analytics and metrics
     */
    public function getWorkflowMetrics($days = 30) {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        // Overall metrics
        $stmt = $this->db->prepare("
            SELECT 
                workflow_type,
                COUNT(*) as total_workflows,
                SUM(CASE WHEN state = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN state = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN state = 'started' THEN 1 ELSE 0 END) as started,
                AVG(snooze_count) as avg_snoozes,
                COUNT(CASE WHEN state = 'snoozed' THEN 1 END) as currently_snoozed
            FROM workflow_states 
            WHERE workflow_date >= ?
            GROUP BY workflow_type
        ");
        $stmt->execute([$startDate]);
        $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent activity
        $stmt = $this->db->prepare("
            SELECT workflow_type, workflow_date, action, status, message, created_at
            FROM workflow_logs 
            WHERE workflow_date >= ?
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([$startDate]);
        $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'metrics' => $metrics,
            'recent_activity' => $recentActivity,
            'period_days' => $days
        ];
    }
    
    /**
     * Test notification system
     */
    public function testNotification($type) {
        $schedule = $this->getScheduleConfig($type);
        if (!$schedule) {
            return ['success' => false, 'error' => 'Schedule not found'];
        }
        
        $testDate = date('Y-m-d');
        $this->sendWorkflowNotification($type, $testDate, $schedule);
        
        return ['success' => true, 'message' => 'Test notification sent'];
    }
}

/**
 * API Routes for Proactive Workflows
 */
class ProactiveWorkflowRoutes {
    private $manager;
    
    public function __construct($manager) {
        $this->manager = $manager;
    }
    
    public function handleRequest($method, $uri, $data = []) {
        // Workflow action endpoints (called by ntfy buttons)
        if (preg_match('#^/api/workflow/snooze/?$#', $uri) && $method === 'POST') {
            return $this->manager->handleWorkflowAction(
                $data['type'], 
                $data['date'], 
                'snooze', 
                ['minutes' => $data['minutes']]
            );
        }
        
        if (preg_match('#^/api/workflow/cancel/?$#', $uri) && $method === 'POST') {
            return $this->manager->handleWorkflowAction($data['type'], $data['date'], 'cancel');
        }
        
        if (preg_match('#^/api/workflow/start/?$#', $uri) && $method === 'POST') {
            return $this->manager->handleWorkflowAction($data['type'], $data['date'], 'start');
        }
        
        if (preg_match('#^/api/workflow/complete/?$#', $uri) && $method === 'POST') {
            return $this->manager->handleWorkflowAction($data['type'], $data['date'], 'complete');
        }
        
        // Schedule configuration endpoints
        if (preg_match('#^/api/workflow/schedules/?$#', $uri)) {
            if ($method === 'GET') {
                return $this->manager->getScheduleConfig();
            }
            if ($method === 'POST') {
                $result = $this->manager->updateScheduleConfig($data['type'], $data);
                return ['success' => $result];
            }
        }
        
        // Metrics and admin endpoints
        if (preg_match('#^/api/workflow/metrics/?$#', $uri) && $method === 'GET') {
            $days = $_GET['days'] ?? 30;
            return $this->manager->getWorkflowMetrics($days);
        }
        
        if (preg_match('#^/api/workflow/test/([^/]+)/?$#', $uri, $matches) && $method === 'POST') {
            return $this->manager->testNotification($matches[1]);
        }
        
        // Workflow state endpoint
        if (preg_match('#^/api/workflow/state/([^/]+)/([^/]+)/?$#', $uri, $matches) && $method === 'GET') {
            return $this->manager->getWorkflowState($matches[1], $matches[2]);
        }
        
        return null; // Not handled by this router
    }
}

// Initialization hook for workflow system
if (getenv('CLAUDE_FLOW_HOOKS')) {
    exec("npx claude-flow@alpha hooks post-edit --file 'proactive-workflows.php' --memory-key 'swarm/proactive-workflows/implementation'");
}
?>