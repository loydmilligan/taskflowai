<?php
/**
 * Workflow Scheduler
 * Handles scheduled execution of proactive workflow notifications
 * 
 * Features:
 * - Cron-based workflow checking
 * - Due workflow detection
 * - Notification sending
 * - Error handling and logging
 * - Manual trigger support
 */

class WorkflowScheduler {
    private $db;
    private $proactiveWorkflows;
    private $lockFile;
    private $maxExecutionTime;
    
    public function __construct($database, $proactiveWorkflows) {
        $this->db = $database;
        $this->proactiveWorkflows = $proactiveWorkflows;
        $this->lockFile = sys_get_temp_dir() . '/taskflow_scheduler.lock';
        $this->maxExecutionTime = 300; // 5 minutes
    }
    
    /**
     * Main scheduler entry point - checks for due workflows
     */
    public function run() {
        // Prevent concurrent execution
        if (!$this->acquireLock()) {
            $this->log('Scheduler already running, skipping execution');
            return false;
        }
        
        try {
            set_time_limit($this->maxExecutionTime);
            $this->log('Workflow scheduler started');
            
            $startTime = microtime(true);
            $processedCount = 0;
            
            // Check for due workflows
            $dueWorkflows = $this->proactiveWorkflows->checkDueWorkflows();
            
            foreach ($dueWorkflows as $workflow) {
                try {
                    $this->processWorkflow($workflow);
                    $processedCount++;
                } catch (Exception $e) {
                    $this->log("Error processing workflow {$workflow['id']}: " . $e->getMessage(), 'error');
                }
            }
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->log("Scheduler completed: processed {$processedCount} workflows in {$executionTime}ms");
            
            // Update scheduler status
            $this->updateSchedulerStatus([
                'last_run' => date('Y-m-d H:i:s'),
                'execution_time_ms' => $executionTime,
                'processed_workflows' => $processedCount,
                'status' => 'success'
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->log('Scheduler error: ' . $e->getMessage(), 'error');
            
            $this->updateSchedulerStatus([
                'last_run' => date('Y-m-d H:i:s'),
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);
            
            return false;
            
        } finally {
            $this->releaseLock();
        }
    }
    
    /**
     * Process individual workflow notification
     */
    private function processWorkflow($workflow) {
        $this->log("Processing workflow: {$workflow['workflow_type']} for user {$workflow['user_id'] ?? 'default'}");
        
        // Check if workflow is already processed today
        if ($this->isWorkflowProcessedToday($workflow)) {
            $this->log("Workflow {$workflow['id']} already processed today, skipping");
            return;
        }
        
        // Check workflow state (if snoozed, cancelled, etc.)
        $workflowState = $this->getWorkflowState($workflow);
        if ($workflowState && !$this->shouldProcessWorkflow($workflowState)) {
            $this->log("Workflow {$workflow['id']} state prevents processing: {$workflowState['state']}");
            return;
        }
        
        // Send notification
        $notificationResult = $this->proactiveWorkflows->sendWorkflowNotification(
            $workflow['workflow_type'],
            [
                'scheduled_time' => $workflow['scheduled_time'],
                'user_id' => $workflow['user_id'] ?? null,
                'trigger_type' => 'scheduled'
            ]
        );
        
        if ($notificationResult) {
            $this->log("Successfully sent {$workflow['workflow_type']} workflow notification");
            
            // Mark as processed
            $this->markWorkflowProcessed($workflow);
            
        } else {
            $this->log("Failed to send {$workflow['workflow_type']} workflow notification", 'error');
            
            // Update failure count
            $this->incrementWorkflowFailures($workflow);
        }
    }
    
    /**
     * Check if workflow was already processed today
     */
    private function isWorkflowProcessedToday($workflow) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM workflow_logs 
                WHERE workflow_type = ? 
                AND action_type = 'notification_sent'
                AND DATE(created_at) = CURDATE()
            ");
            
            $stmt->execute([$workflow['workflow_type']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
            
        } catch (Exception $e) {
            $this->log("Error checking workflow processed status: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Get current workflow state
     */
    private function getWorkflowState($workflow) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM workflow_states 
                WHERE workflow_type = ? 
                AND state_date = CURDATE()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            
            $stmt->execute([$workflow['workflow_type']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->log("Error getting workflow state: " . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Check if workflow should be processed based on state
     */
    private function shouldProcessWorkflow($workflowState) {
        if (!$workflowState) {
            return true;
        }
        
        switch ($workflowState['state']) {
            case 'cancelled':
                return false;
                
            case 'completed':
                return false;
                
            case 'snoozed':
                // Check if snooze time has passed
                $snoozeUntil = new DateTime($workflowState['snooze_until']);
                $now = new DateTime();
                return $now >= $snoozeUntil;
                
            case 'pending':
            default:
                return true;
        }
    }
    
    /**
     * Mark workflow as processed
     */
    private function markWorkflowProcessed($workflow) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO workflow_logs (workflow_type, action_type, details, created_at)
                VALUES (?, 'notification_sent', ?, NOW())
            ");
            
            $details = json_encode([
                'scheduled_time' => $workflow['scheduled_time'],
                'processed_at' => date('Y-m-d H:i:s'),
                'trigger_type' => 'scheduled'
            ]);
            
            $stmt->execute([$workflow['workflow_type'], $details]);
            
        } catch (Exception $e) {
            $this->log("Error marking workflow as processed: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Increment failure count for workflow
     */
    private function incrementWorkflowFailures($workflow) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO workflow_logs (workflow_type, action_type, details, created_at)
                VALUES (?, 'notification_failed', ?, NOW())
            ");
            
            $details = json_encode([
                'scheduled_time' => $workflow['scheduled_time'],
                'failed_at' => date('Y-m-d H:i:s'),
                'trigger_type' => 'scheduled'
            ]);
            
            $stmt->execute([$workflow['workflow_type'], $details]);
            
        } catch (Exception $e) {
            $this->log("Error logging workflow failure: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Acquire execution lock to prevent concurrent runs
     */
    private function acquireLock() {
        if (file_exists($this->lockFile)) {
            $lockTime = filemtime($this->lockFile);
            
            // Remove stale locks (older than max execution time)
            if (time() - $lockTime > $this->maxExecutionTime) {
                unlink($this->lockFile);
                $this->log('Removed stale scheduler lock');
            } else {
                return false;
            }
        }
        
        return file_put_contents($this->lockFile, getmypid()) !== false;
    }
    
    /**
     * Release execution lock
     */
    private function releaseLock() {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }
    
    /**
     * Update scheduler status in database
     */
    private function updateSchedulerStatus($status) {
        try {
            // Create scheduler_status table if it doesn't exist
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS scheduler_status (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    last_run DATETIME,
                    execution_time_ms DECIMAL(10,2),
                    processed_workflows INT DEFAULT 0,
                    status VARCHAR(50),
                    error_message TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
            // Insert or update status
            $stmt = $this->db->prepare("
                INSERT INTO scheduler_status 
                (last_run, execution_time_ms, processed_workflows, status, error_message)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                last_run = VALUES(last_run),
                execution_time_ms = VALUES(execution_time_ms),
                processed_workflows = VALUES(processed_workflows),
                status = VALUES(status),
                error_message = VALUES(error_message),
                updated_at = NOW()
            ");
            
            $stmt->execute([
                $status['last_run'] ?? null,
                $status['execution_time_ms'] ?? null,
                $status['processed_workflows'] ?? 0,
                $status['status'] ?? 'unknown',
                $status['error_message'] ?? null
            ]);
            
        } catch (Exception $e) {
            $this->log("Error updating scheduler status: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Get scheduler status
     */
    public function getSchedulerStatus() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM scheduler_status 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            
            $stmt->execute();
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Add runtime information
            $runtimeStatus = [
                'is_running' => file_exists($this->lockFile),
                'lock_age_seconds' => file_exists($this->lockFile) ? time() - filemtime($this->lockFile) : 0,
                'next_check_estimate' => $this->estimateNextCheck()
            ];
            
            return array_merge($status ?: [], $runtimeStatus);
            
        } catch (Exception $e) {
            $this->log("Error getting scheduler status: " . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Estimate next check time based on current schedules
     */
    private function estimateNextCheck() {
        try {
            $schedules = $this->proactiveWorkflows->getWorkflowSchedules();
            $nextTimes = [];
            
            foreach ($schedules as $schedule) {
                if (!$schedule['enabled']) {
                    continue;
                }
                
                // Calculate next occurrence
                $scheduleTime = $schedule['scheduled_time'];
                $timezone = new DateTimeZone($schedule['timezone'] ?? date_default_timezone_get());
                
                $nextOccurrence = new DateTime("today {$scheduleTime}", $timezone);
                
                // If today's time has passed, use tomorrow
                if ($nextOccurrence < new DateTime('now', $timezone)) {
                    $nextOccurrence = new DateTime("tomorrow {$scheduleTime}", $timezone);
                }
                
                $nextTimes[] = $nextOccurrence->format('Y-m-d H:i:s T');
            }
            
            return empty($nextTimes) ? null : min($nextTimes);
            
        } catch (Exception $e) {
            $this->log("Error estimating next check: " . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Manual trigger for testing
     */
    public function manualTrigger($workflowType = null) {
        try {
            $this->log("Manual trigger initiated" . ($workflowType ? " for {$workflowType}" : ""));
            
            if ($workflowType) {
                // Trigger specific workflow type
                $result = $this->proactiveWorkflows->sendWorkflowNotification(
                    $workflowType,
                    [
                        'trigger_type' => 'manual',
                        'triggered_at' => date('Y-m-d H:i:s')
                    ]
                );
                
                return [
                    'success' => $result !== false,
                    'workflow_type' => $workflowType,
                    'message' => $result ? 'Manual trigger successful' : 'Manual trigger failed'
                ];
                
            } else {
                // Run full scheduler check
                $result = $this->run();
                
                return [
                    'success' => $result,
                    'message' => $result ? 'Scheduler run completed' : 'Scheduler run failed'
                ];
            }
            
        } catch (Exception $e) {
            $this->log("Manual trigger error: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up old logs and states
     */
    public function cleanup($daysToKeep = 90) {
        try {
            $this->log("Starting cleanup of logs older than {$daysToKeep} days");
            
            // Clean workflow logs
            $stmt = $this->db->prepare("
                DELETE FROM workflow_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$daysToKeep]);
            $logsDeleted = $stmt->rowCount();
            
            // Clean workflow states
            $stmt = $this->db->prepare("
                DELETE FROM workflow_states 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$daysToKeep]);
            $statesDeleted = $stmt->rowCount();
            
            // Clean scheduler status (keep last 30 days)
            $stmt = $this->db->prepare("
                DELETE FROM scheduler_status 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $statusDeleted = $stmt->rowCount();
            
            $this->log("Cleanup completed: {$logsDeleted} logs, {$statesDeleted} states, {$statusDeleted} status records deleted");
            
            return [
                'logs_deleted' => $logsDeleted,
                'states_deleted' => $statesDeleted,
                'status_deleted' => $statusDeleted
            ];
            
        } catch (Exception $e) {
            $this->log("Cleanup error: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Log scheduler activities
     */
    private function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] Scheduler: {$message}";
        
        error_log($logMessage);
        
        // Also log to database for better tracking
        try {
            // Create scheduler_logs table if it doesn't exist
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS scheduler_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    level VARCHAR(20),
                    message TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            $stmt = $this->db->prepare("
                INSERT INTO scheduler_logs (level, message) VALUES (?, ?)
            ");
            $stmt->execute([$level, $message]);
            
        } catch (Exception $e) {
            // Fallback to error_log only
            error_log("Failed to log to database: " . $e->getMessage());
        }
    }
    
    /**
     * Get recent scheduler logs
     */
    public function getRecentLogs($limit = 100) {
        try {
            $stmt = $this->db->prepare("
                SELECT level, message, created_at
                FROM scheduler_logs 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting scheduler logs: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * CLI interface for scheduler
 */
if (php_sapi_name() === 'cli' && isset($argv) && basename($argv[0]) === 'workflow-scheduler.php') {
    
    // Simple CLI interface for cron jobs
    require_once __DIR__ . '/../database.php';
    require_once __DIR__ . '/proactive-workflows.php';
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Create dummy ntfy manager for CLI
        $ntfyManager = new class {
            public function sendNotification($topic, $title, $message, $options = []) {
                echo "Would send ntfy notification to {$topic}: {$title} - {$message}\n";
                return true;
            }
        };
        
        $proactiveWorkflows = new ProactiveWorkflowManager($db, $ntfyManager, null);
        $scheduler = new WorkflowScheduler($db, $proactiveWorkflows);
        
        $command = $argv[1] ?? 'run';
        
        switch ($command) {
            case 'run':
                echo "Running workflow scheduler...\n";
                $result = $scheduler->run();
                echo $result ? "Scheduler completed successfully\n" : "Scheduler failed\n";
                exit($result ? 0 : 1);
                
            case 'status':
                echo "Getting scheduler status...\n";
                $status = $scheduler->getSchedulerStatus();
                echo json_encode($status, JSON_PRETTY_PRINT) . "\n";
                break;
                
            case 'trigger':
                $workflowType = $argv[2] ?? null;
                echo "Manual trigger" . ($workflowType ? " for {$workflowType}" : "") . "...\n";
                $result = $scheduler->manualTrigger($workflowType);
                echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
                break;
                
            case 'cleanup':
                $days = (int)($argv[2] ?? 90);
                echo "Cleaning up logs older than {$days} days...\n";
                $result = $scheduler->cleanup($days);
                echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
                break;
                
            case 'logs':
                $limit = (int)($argv[2] ?? 20);
                echo "Recent scheduler logs (last {$limit}):\n";
                $logs = $scheduler->getRecentLogs($limit);
                foreach ($logs as $log) {
                    echo "[{$log['created_at']}] [{$log['level']}] {$log['message']}\n";
                }
                break;
                
            default:
                echo "Usage: php workflow-scheduler.php [command]\n";
                echo "Commands:\n";
                echo "  run        - Run the scheduler (check for due workflows)\n";
                echo "  status     - Show scheduler status\n";
                echo "  trigger    - Manual trigger [workflow_type]\n";
                echo "  cleanup    - Clean up old logs [days_to_keep]\n";
                echo "  logs       - Show recent logs [limit]\n";
        }
        
    } catch (Exception $e) {
        echo "Scheduler error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

?>