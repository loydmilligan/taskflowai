<?php
/**
 * TaskFlow AI Integration Layer
 * Coordinates all new features with existing single-file architecture
 * 
 * Integrates: AI Workflow, Advanced Chat, Mobile PWA, Project Filtering
 * Maintains: Single-file philosophy, API-first design, mobile-first UX
 */

require_once __DIR__ . '/features/ai-workflow.php';
require_once __DIR__ . '/features/advanced-chat.php';
require_once __DIR__ . '/features/mobile-pwa.php';
require_once __DIR__ . '/features/project-filtering.php';
require_once __DIR__ . '/features/workflow-ui.php';
require_once __DIR__ . '/features/proactive-workflows.php';
require_once __DIR__ . '/features/enhanced-ai-conversation.php';
require_once __DIR__ . '/features/workflow-settings-ui.php';
require_once __DIR__ . '/features/workflow-scheduler.php';

// Load workflow integration only if GeminiAI class exists
if (class_exists('GeminiAI')) {
    require_once __DIR__ . '/features/workflow-integration.php';
}

class TaskFlowFeatureIntegrator {
    private $db;
    private $aiWorkflow;
    private $advancedChat;
    private $mobilePWA;
    private $projectFiltering;
    
    // Route handlers
    private $workflowRoutes;
    private $chatRoutes;
    private $pwaRoutes;
    private $filteringRoutes;
    private $settingsRoutes;
    private $scheduler;
    
    // Workflow-aware components
    private $workflowGemini;
    
    public function __construct($database, $geminiAI = null, $ntfyManager = null) {
        $this->db = $database;
        
        // Initialize feature systems
        $this->aiWorkflow = new AIWorkflowSystem($database);
        $this->advancedChat = new AdvancedChatInterface($database);
        $this->mobilePWA = new MobilePWAManager($database);
        $this->projectFiltering = new ProjectFilteringSystem($database);
        
        // Initialize enhanced features
        $this->proactiveWorkflows = new ProactiveWorkflowManager($database, $ntfyManager, $this->aiWorkflow);
        $this->enhancedChat = new EnhancedChatRoutes($database, $geminiAI);
        $this->settingsUI = new WorkflowSettingsUI($database, $this->proactiveWorkflows);
        $this->scheduler = new WorkflowScheduler($database, $this->proactiveWorkflows);
        
        // Initialize route handlers
        $this->workflowRoutes = new WorkflowRoutes($this->aiWorkflow);
        $this->chatRoutes = new AdvancedChatRoutes($this->advancedChat);
        $this->pwaRoutes = new PWARoutes($this->mobilePWA);
        $this->filteringRoutes = new ProjectFilteringRoutes($this->projectFiltering);
        $this->proactiveRoutes = new ProactiveWorkflowRoutes($this->proactiveWorkflows);
        $this->settingsRoutes = new WorkflowSettingsRoutes($this->settingsUI);
    }
    
    /**
     * Central request router for all new features
     */
    public function handleRequest($method, $uri, $data = []) {
        // Extract endpoint from URI
        $parsedUri = parse_url($uri);
        $endpoint = $parsedUri['path'];
        
        // Route to appropriate handler
        if (strpos($endpoint, '/api/workflow') === 0) {
            return $this->workflowRoutes->handleRequest($method, $endpoint, $data);
        }
        
        if (strpos($endpoint, '/api/chat') === 0) {
            return $this->chatRoutes->handleRequest($method, $endpoint, $data);
        }
        
        if (strpos($endpoint, '/api/pwa') === 0 || 
            in_array($endpoint, ['/manifest.json', '/sw.js', '/offline.html'])) {
            return $this->pwaRoutes->handleRequest($method, $endpoint, $data);
        }
        
        if (strpos($endpoint, '/api/projects') === 0 || 
            strpos($endpoint, '/api/unassigned') === 0 || 
            strpos($endpoint, '/api/filter-options') === 0 ||
            strpos($endpoint, '/api/bulk-assign') === 0) {
            return $this->filteringRoutes->handleRequest($method, $endpoint, $data);
        }
        
        // Proactive workflow endpoints
        if (strpos($endpoint, '/api/proactive') === 0 || 
            strpos($endpoint, '/api/workflow-action') === 0) {
            return $this->proactiveRoutes->handleRequest($method, $endpoint, $data);
        }
        
        // Enhanced chat endpoints
        if (strpos($endpoint, '/api/enhanced-chat') === 0 || 
            strpos($endpoint, '/api/conversation') === 0 ||
            strpos($endpoint, '/api/todays-plan') === 0) {
            return $this->enhancedChat->handleRequest($method, $endpoint, $data);
        }
        
        // Workflow settings endpoints
        if (strpos($endpoint, '/api/workflow-settings') === 0) {
            return $this->settingsRoutes->handleRequest($method, $endpoint, $data);
        }
        
        // Feature integration endpoints
        if (strpos($endpoint, '/api/integration') === 0) {
            return $this->handleIntegrationRequest($method, $endpoint, $data);
        }
        
        return null; // Not handled by new features
    }
    
    /**
     * Handle integration-specific requests
     */
    private function handleIntegrationRequest($method, $endpoint, $data = []) {
        switch ($endpoint) {
            case '/api/integration/dashboard':
                if ($method === 'GET') {
                    return $this->getDashboardData();
                }
                break;
                
            case '/api/integration/smart-suggestions':
                if ($method === 'GET') {
                    return $this->getSmartSuggestions($_GET);
                }
                break;
                
            case '/api/integration/unified-search':
                if ($method === 'GET') {
                    return $this->unifiedSearch($_GET);
                }
                break;
                
            case '/api/integration/ai-context':
                if ($method === 'GET') {
                    return $this->getAIContext($_GET);
                }
                break;
                
            case '/api/integration/feature-status':
                if ($method === 'GET') {
                    return $this->getFeatureStatus();
                }
                break;
        }
        
        return null;
    }
    
    /**
     * Get unified dashboard data combining all features
     */
    public function getDashboardData() {
        // Today's workflow status
        $workflowStatus = $this->aiWorkflow->getTodaysWorkflowStatus();
        
        // Project summaries with filtering
        $projectSummaries = $this->projectFiltering->getProjectSummaries(['limit' => 10]);
        
        // Unassigned items summary
        $unassignedSummary = $this->projectFiltering->getUnassignedSummary();
        
        // Recent chat activity
        $defaultSession = $this->advancedChat->getSession();
        $recentMessages = $this->advancedChat->getRecentMessages($defaultSession['id'], 5);
        
        // PWA status and analytics
        $pwaAnalytics = $this->mobilePWA->getAnalytics(7);
        
        return [
            'workflow' => [
                'status' => $workflowStatus,
                'morning_completed' => !empty($workflowStatus['morning']) && $workflowStatus['morning']['status'] === 'completed',
                'evening_pending' => empty($workflowStatus['evening']) || $workflowStatus['evening']['status'] !== 'completed'
            ],
            'projects' => [
                'summaries' => $projectSummaries,
                'total_projects' => count($projectSummaries),
                'active_projects' => count(array_filter($projectSummaries, fn($p) => $p['task_count'] > 0))
            ],
            'unassigned' => $unassignedSummary,
            'chat' => [
                'session_id' => $defaultSession['id'],
                'recent_messages' => count($recentMessages),
                'last_activity' => !empty($recentMessages) ? end($recentMessages)['created_at'] : null
            ],
            'pwa' => [
                'install_rate' => $this->calculateInstallRate($pwaAnalytics),
                'active_sessions' => $pwaAnalytics['active_sessions'],
                'offline_ready' => true
            ],
            'generated_at' => date('c')
        ];
    }
    
    /**
     * Get smart suggestions based on user context and AI analysis
     */
    public function getSmartSuggestions($params) {
        $sessionId = $params['session_id'] ?? null;
        $context = $params['context'] ?? 'general';
        
        $suggestions = [];
        
        // Chat-based suggestions
        if ($sessionId) {
            $chatSuggestions = $this->advancedChat->generateSmartSuggestions($sessionId);
            $suggestions['chat'] = $chatSuggestions;
        }
        
        // Workflow-based suggestions
        $workflowStatus = $this->aiWorkflow->getTodaysWorkflowStatus();
        if (empty($workflowStatus['morning'])) {
            $suggestions['workflow'][] = [
                'text' => 'Start your morning workflow',
                'action' => 'start_morning_workflow',
                'priority' => 'high'
            ];
        }
        if (!empty($workflowStatus['morning']) && $workflowStatus['morning']['status'] === 'completed' && 
            empty($workflowStatus['evening'])) {
            $suggestions['workflow'][] = [
                'text' => 'Begin evening reflection',
                'action' => 'start_evening_workflow', 
                'priority' => 'medium'
            ];
        }
        
        // Project-based suggestions
        $unassignedCount = $this->projectFiltering->getUnassignedSummary()['total_entities'];
        if ($unassignedCount > 0) {
            $suggestions['projects'][] = [
                'text' => "Organize {$unassignedCount} unassigned items",
                'action' => 'organize_unassigned',
                'priority' => 'medium'
            ];
        }
        
        // PWA suggestions
        if ($context === 'mobile' || $context === 'installation') {
            $suggestions['pwa'][] = [
                'text' => 'Install as mobile app for better experience',
                'action' => 'install_pwa',
                'priority' => 'low'
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Unified search across all entities and features
     */
    public function unifiedSearch($params) {
        $query = $params['q'] ?? '';
        $limit = (int)($params['limit'] ?? 20);
        
        if (empty($query)) {
            return ['results' => [], 'total' => 0];
        }
        
        $results = [];
        
        // Search in chat history
        try {
            $chatResults = $this->advancedChat->searchHistory($query, null, $limit);
            foreach ($chatResults as $result) {
                $results[] = [
                    'type' => 'chat',
                    'title' => 'Chat: ' . substr($result['content'], 0, 50) . '...',
                    'content' => $result['snippet'],
                    'created_at' => $result['created_at'],
                    'relevance' => 'high'
                ];
            }
        } catch (Exception $e) {
            error_log("Chat search error: " . $e->getMessage());
        }
        
        // Search in tasks (using project filtering system)
        try {
            $taskResults = $this->projectFiltering->getProjectTasks('all', [
                'search' => $query,
                'limit' => $limit
            ]);
            foreach ($taskResults as $task) {
                $results[] = [
                    'type' => 'task',
                    'id' => $task['id'],
                    'title' => $task['title'],
                    'content' => $task['description'] ?? '',
                    'project' => $task['project_name'] ?? 'Unassigned',
                    'priority' => $task['priority'],
                    'status' => $task['status'],
                    'created_at' => $task['created_at'],
                    'relevance' => 'high'
                ];
            }
        } catch (Exception $e) {
            error_log("Task search error: " . $e->getMessage());
        }
        
        // Search in notes
        try {
            $noteResults = $this->projectFiltering->getProjectNotes('all', [
                'search' => $query,
                'limit' => $limit
            ]);
            foreach ($noteResults as $note) {
                $results[] = [
                    'type' => 'note',
                    'id' => $note['id'],
                    'title' => $note['title'],
                    'content' => substr($note['content'], 0, 200) . '...',
                    'project' => $note['project_name'] ?? 'Unassigned',
                    'created_at' => $note['created_at'],
                    'relevance' => 'medium'
                ];
            }
        } catch (Exception $e) {
            error_log("Note search error: " . $e->getMessage());
        }
        
        // Sort by relevance and date
        usort($results, function($a, $b) {
            $relevanceOrder = ['high' => 1, 'medium' => 2, 'low' => 3];
            $aScore = $relevanceOrder[$a['relevance']] ?? 3;
            $bScore = $relevanceOrder[$b['relevance']] ?? 3;
            
            if ($aScore === $bScore) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            }
            
            return $aScore - $bScore;
        });
        
        // Limit final results
        $results = array_slice($results, 0, $limit);
        
        return [
            'results' => $results,
            'total' => count($results),
            'query' => $query,
            'searched_at' => date('c')
        ];
    }
    
    /**
     * Get AI context for enhanced responses
     */
    public function getAIContext($params) {
        $sessionId = $params['session_id'] ?? null;
        
        $context = [
            'timestamp' => date('c'),
            'features_available' => ['workflow', 'chat', 'pwa', 'filtering']
        ];
        
        // Chat context
        if ($sessionId) {
            $context['chat'] = $this->advancedChat->getConversationContext($sessionId);
        }
        
        // Workflow context
        $workflowStatus = $this->aiWorkflow->getTodaysWorkflowStatus();
        $context['workflow'] = [
            'morning_status' => $workflowStatus['morning']['status'] ?? 'pending',
            'evening_status' => $workflowStatus['evening']['status'] ?? 'pending',
            'today_date' => date('Y-m-d')
        ];
        
        // Project context
        $projectSummaries = $this->projectFiltering->getProjectSummaries(['limit' => 5]);
        $context['projects'] = [
            'active_count' => count($projectSummaries),
            'top_projects' => array_map(fn($p) => [
                'name' => $p['project_name'],
                'task_count' => $p['task_count'],
                'completion_rate' => $p['task_count'] > 0 ? $p['completed_tasks'] / $p['task_count'] : 0
            ], $projectSummaries)
        ];
        
        // Unassigned items context
        $unassignedSummary = $this->projectFiltering->getUnassignedSummary();
        $context['unassigned'] = $unassignedSummary;
        
        return $context;
    }
    
    /**
     * Get feature status and health check
     */
    public function getFeatureStatus() {
        $status = [
            'ai_workflow' => $this->testFeature('workflow'),
            'advanced_chat' => $this->testFeature('chat'),
            'mobile_pwa' => $this->testFeature('pwa'),
            'project_filtering' => $this->testFeature('filtering'),
            'proactive_workflows' => $this->testFeature('proactive'),
            'enhanced_chat' => $this->testFeature('enhanced_chat'),
            'integration_layer' => 'healthy',
            'overall_health' => 'healthy'
        ];
        
        // Determine overall health
        $healthyCount = count(array_filter($status, fn($s) => $s === 'healthy'));
        if ($healthyCount < 5) {
            $status['overall_health'] = 'degraded';
        }
        if ($healthyCount < 3) {
            $status['overall_health'] = 'unhealthy';
        }
        
        $status['tested_at'] = date('c');
        return $status;
    }
    
    /**
     * Test individual feature health
     */
    private function testFeature($feature) {
        try {
            switch ($feature) {
                case 'workflow':
                    $this->aiWorkflow->getTodaysWorkflowStatus();
                    return 'healthy';
                    
                case 'chat':
                    $this->advancedChat->getSession();
                    return 'healthy';
                    
                case 'pwa':
                    $this->mobilePWA->getAnalytics(1);
                    return 'healthy';
                    
                case 'filtering':
                    $this->projectFiltering->getProjectSummaries(['limit' => 1]);
                    return 'healthy';
                    
                case 'proactive':
                    // Test proactive workflow system
                    $this->proactiveWorkflows->getWorkflowSchedules();
                    return 'healthy';
                    
                case 'enhanced_chat':
                    // Test enhanced chat system
                    $this->enhancedChat->getConversationMemory()->getActiveSession();
                    return 'healthy';
            }
        } catch (Exception $e) {
            error_log("Feature test failed for $feature: " . $e->getMessage());
            return 'unhealthy';
        }
        
        return 'unknown';
    }
    
    /**
     * Calculate PWA install rate from analytics
     */
    private function calculateInstallRate($analytics) {
        $installStats = $analytics['install_statistics'];
        $installed = 0;
        $total = 0;
        
        foreach ($installStats as $stat) {
            $total += $stat['count'];
            if ($stat['install_status'] === 'installed' || $stat['install_status'] === 'standalone') {
                $installed += $stat['count'];
            }
        }
        
        return $total > 0 ? round(($installed / $total) * 100, 1) : 0;
    }
    
    /**
     * Initialize all features (called during app startup)
     */
    public function initializeFeatures() {
        try {
            // Clean expired PWA cache
            $this->mobilePWA->cleanExpiredCache();
            
            // Initialize default chat session
            $this->advancedChat->getSession();
            
            return true;
        } catch (Exception $e) {
            error_log("Feature initialization error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get feature configuration for frontend
     */
    public function getFeatureConfig() {
        return [
            'ai_workflow' => [
                'enabled' => true,
                'morning_workflow' => true,
                'evening_workflow' => true
            ],
            'advanced_chat' => [
                'enabled' => true,
                'voice_input' => true,
                'shortcuts' => true,
                'history_search' => true
            ],
            'mobile_pwa' => [
                'enabled' => true,
                'offline_sync' => true,
                'push_notifications' => true,
                'install_prompt' => true
            ],
            'project_filtering' => [
                'enabled' => true,
                'cross_entity' => true,
                'unassigned_view' => true,
                'bulk_operations' => true
            ],
            'proactive_workflows' => [
                'enabled' => true,
                'morning_notifications' => true,
                'evening_notifications' => true,
                'snooze_options' => [15, 30, 60],
                'ntfy_integration' => true,
                'interactive_buttons' => true
            ],
            'enhanced_chat' => [
                'enabled' => true,
                'conversation_memory' => true,
                'multi_turn_sessions' => true,
                'todays_plan_integration' => true,
                'action_parsing' => true,
                'context_preservation' => true
            ],
            'integration' => [
                'unified_search' => true,
                'smart_suggestions' => true,
                'ai_context' => true
            ]
        ];
    }
}

// Hook execution for coordination
if (getenv('CLAUDE_FLOW_HOOKS')) {
    exec("npx claude-flow@alpha hooks pre-task --description 'feature-integration-layer'");
    exec("npx claude-flow@alpha hooks session-restore --session-id 'swarm-roadmap-impl'");
    
    register_shutdown_function(function() {
        exec("npx claude-flow@alpha hooks post-edit --file 'integration-layer.php' --memory-key 'swarm/integration/implementation'");
        exec("npx claude-flow@alpha hooks post-task --task-id 'feature-integration-layer'");
    });
}

?>