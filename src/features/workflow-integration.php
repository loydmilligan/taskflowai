<?php
/**
 * AI-Guided Daily Workflow Integration for TaskFlow AI
 * Extends existing GeminiAI class with workflow-aware capabilities
 */

// Ensure GeminiAI class is loaded before extending
if (!class_exists('GeminiAI')) {
    // Skip workflow integration if base class not available
    return;
}

class WorkflowAwareGeminiAI extends GeminiAI {
    private $workflowSystem;
    
    public function __construct() {
        parent::__construct();
        $this->workflowSystem = new AIWorkflowSystem(Database::getInstance());
    }
    
    /**
     * Enhanced chat method with workflow context awareness
     */
    public function chat($message, $context = []) {
        // Check for workflow trigger words
        $workflowContext = $this->analyzeWorkflowIntent($message);
        
        if ($workflowContext['should_trigger']) {
            return $this->handleWorkflowTrigger($workflowContext['type'], $message, $context);
        }
        
        // Add workflow status to context
        $context = array_merge($context, $this->getWorkflowContext());
        
        // Call parent chat method with enhanced context
        $response = parent::chat($message, $context);
        
        // Parse for workflow actions
        if ($response['success']) {
            $workflowActions = $this->parseWorkflowActions($response['response']);
            if (!empty($workflowActions)) {
                $response['workflow_actions'] = $workflowActions;
            }
        }
        
        return $response;
    }
    
    /**
     * Analyze message for workflow intent
     */
    private function analyzeWorkflowIntent($message) {
        $message = strtolower($message);
        
        // Morning workflow triggers
        $morningTriggers = [
            'good morning', 'start day', 'morning ritual', 'daily planning',
            'what should i focus', 'task selection', 'energy level', 'daily goals'
        ];
        
        // Evening workflow triggers
        $eveningTriggers = [
            'good evening', 'end day', 'evening reflection', 'what did i accomplish',
            'review today', 'daily review', 'productivity', 'tomorrow planning'
        ];
        
        foreach ($morningTriggers as $trigger) {
            if (strpos($message, $trigger) !== false) {
                return ['should_trigger' => true, 'type' => 'morning'];
            }
        }
        
        foreach ($eveningTriggers as $trigger) {
            if (strpos($message, $trigger) !== false) {
                return ['should_trigger' => true, 'type' => 'evening'];
            }
        }
        
        return ['should_trigger' => false];
    }
    
    /**
     * Handle workflow trigger
     */
    private function handleWorkflowTrigger($type, $message, $context) {
        try {
            if ($type === 'morning') {
                $workflowData = $this->workflowSystem->startMorningWorkflow();
                $response = $this->generateMorningResponse($workflowData, $message);
            } else {
                $workflowData = $this->workflowSystem->startEveningWorkflow();
                $response = $this->generateEveningResponse($workflowData, $message);
            }
            
            return [
                'success' => true,
                'response' => $response,
                'workflow_triggered' => true,
                'workflow_type' => $type,
                'workflow_data' => $workflowData,
                'actions' => [
                    [
                        'type' => 'SHOW_WORKFLOW_UI',
                        'data' => ['workflow_type' => $type, 'workflow_id' => $workflowData['id']]
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response' => 'Sorry, I encountered an error starting your ' . $type . ' workflow. Please try again.'
            ];
        }
    }
    
    /**
     * Generate morning workflow response
     */
    private function generateMorningResponse($workflowData, $originalMessage) {
        $taskCount = count($workflowData['selected_tasks']);
        $scrapCount = count($workflowData['unprocessed_scraps']);
        
        $response = "Good morning! 🌅 I've prepared your morning ritual.\n\n";
        $response .= "**Today's Focus:** I've analyzed your tasks and selected {$taskCount} key items for maximum impact.\n\n";
        
        if ($scrapCount > 0) {
            $response .= "**Scrap Processing:** You have {$scrapCount} unprocessed scraps with actionable insights.\n\n";
        }
        
        // Highlight top selected task
        if (!empty($workflowData['selected_tasks'])) {
            $topTask = $workflowData['selected_tasks'][0];
            $response .= "**Priority Focus:** \"{$topTask['title']}\" - {$topTask['selection_reason']}\n\n";
        }
        
        $response .= "Let's start with a quick energy check-in and set your daily intention. The workflow interface is ready for you!\n\n";
        $response .= "*💡 Tip: Your energy level helps me adjust task recommendations throughout the day.*";
        
        return $response;
    }
    
    /**
     * Generate evening workflow response
     */
    private function generateEveningResponse($workflowData, $originalMessage) {
        $stats = $workflowData['completion_stats'];
        $response = "Good evening! 🌙 Time for reflection and tomorrow's preparation.\n\n";
        
        if ($stats['tasks_completed'] > 0) {
            $response .= "**Great Progress:** You completed {$stats['tasks_completed']} tasks today! ";
            $response .= "Your productivity score: {$stats['productivity_score']}/10\n\n";
        } else {
            $response .= "**Every Day is Different:** Sometimes progress isn't just about completed tasks. Let's reflect on today's experiences.\n\n";
        }
        
        $response .= "**Reflection Time:** I've prepared thoughtful prompts to capture insights and lessons from today.\n\n";
        $response .= "**Tomorrow's Setup:** Based on today's patterns, I'll help you plan for tomorrow's success.\n\n";
        $response .= "Ready for your evening reflection? The interface is prepared with your personalized questions.";
        
        return $response;
    }
    
    /**
     * Get workflow context for enhanced AI responses
     */
    private function getWorkflowContext() {
        $workflowStatus = $this->workflowSystem->getTodaysWorkflowStatus();
        
        return [
            'workflow_status' => $workflowStatus,
            'morning_completed' => !empty($workflowStatus['morning']) && $workflowStatus['morning']['status'] === 'completed',
            'evening_completed' => !empty($workflowStatus['evening']) && $workflowStatus['evening']['status'] === 'completed',
            'has_workflows' => !empty($workflowStatus['morning']) || !empty($workflowStatus['evening'])
        ];
    }
    
    /**
     * Parse workflow-specific actions from AI response
     */
    private function parseWorkflowActions($response) {
        $actions = [];
        
        // Match workflow action patterns
        if (preg_match_all('/\[ACTION:([A-Z_]+)\]\s*(\{(?:[^{}]|{[^}]*})*\})/i', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $actionType = $match[1];
                $actionData = json_decode($match[2], true);
                
                if ($actionData !== null && in_array($actionType, [
                    'START_MORNING_WORKFLOW', 
                    'START_EVENING_WORKFLOW',
                    'ADD_WORKFLOW_MESSAGE'
                ])) {
                    $actions[] = [
                        'type' => $actionType,
                        'data' => $actionData
                    ];
                }
            }
        }
        
        return $actions;
    }
    
    /**
     * Process workflow conversation message
     */
    public function processWorkflowMessage($workflowId, $message, $messageType = 'user') {
        // Add user message to workflow
        $this->workflowSystem->addWorkflowMessage($workflowId, $messageType, $message);
        
        // Get workflow context
        $workflow = $this->workflowSystem->getWorkflowConversation($workflowId);
        $workflowType = $this->determineWorkflowType($workflowId);
        
        // Generate contextual response
        $context = [
            'workflow_id' => $workflowId,
            'workflow_type' => $workflowType,
            'conversation_history' => array_slice($workflow, -5) // Last 5 messages
        ];
        
        $response = $this->generateWorkflowResponse($message, $context);
        
        // Add AI response to workflow
        if ($response['success']) {
            $this->workflowSystem->addWorkflowMessage($workflowId, 'assistant', $response['response']);
        }
        
        return $response;
    }
    
    /**
     * Generate workflow-specific response
     */
    private function generateWorkflowResponse($message, $context) {
        $workflowType = $context['workflow_type'];
        
        if ($workflowType === 'morning') {
            $systemPrompt = "You are helping with a morning workflow. Focus on energy assessment, task prioritization, and daily intention setting. Be encouraging and practical.";
        } else {
            $systemPrompt = "You are helping with an evening reflection workflow. Focus on completion review, insights capture, and tomorrow's preparation. Be thoughtful and supportive.";
        }
        
        $fullPrompt = $systemPrompt . "\n\nConversation context: " . json_encode($context) . "\n\nUser: " . $message;
        
        return parent::chat($fullPrompt, $context);
    }
    
    /**
     * Determine workflow type from ID
     */
    private function determineWorkflowType($workflowId) {
        $stmt = Database::getInstance()->getPdo()->prepare(
            "SELECT type FROM daily_workflows WHERE id = ?"
        );
        $stmt->execute([$workflowId]);
        $result = $stmt->fetch();
        
        return $result ? $result['type'] : 'unknown';
    }
    
    /**
     * Get enhanced system prompt with workflow awareness
     */
    protected function buildSystemPrompt($context) {
        $basePrompt = parent::buildSystemPrompt($context);
        
        $workflowAddition = "\n\nWORKFLOW AWARENESS:\n";
        $workflowAddition .= "- Detect when users want to start morning (planning) or evening (reflection) workflows\n";
        $workflowAddition .= "- Suggest workflows when appropriate (morning planning, daily review, etc.)\n";
        $workflowAddition .= "- Maintain conversation context within workflow sessions\n";
        $workflowAddition .= "- Provide workflow-specific guidance and responses\n";
        
        if (isset($context['workflow_status'])) {
            $status = $context['workflow_status'];
            $workflowAddition .= "\nCurrent workflow status:\n";
            $workflowAddition .= "- Morning: " . ($status['morning']['status'] ?? 'not started') . "\n";
            $workflowAddition .= "- Evening: " . ($status['evening']['status'] ?? 'not started') . "\n";
        }
        
        return $basePrompt . $workflowAddition;
    }
}

// Enhanced API Routes for Workflow Integration
class WorkflowIntegratedAPIHandler extends APIHandler {
    private $workflowGemini;
    
    public function __construct($projects, $tasks, $notes, $scraps, $geminiAI) {
        parent::__construct($projects, $tasks, $notes, $scraps, $geminiAI);
        $this->workflowGemini = new WorkflowAwareGeminiAI();
    }
    
    /**
     * Handle chat requests with workflow awareness
     */
    protected function handleChat($data) {
        $message = $data['message'] ?? '';
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId) {
            // This is a workflow conversation
            return $this->workflowGemini->processWorkflowMessage($workflowId, $message);
        } else {
            // Regular chat with workflow awareness
            $context = $this->buildChatContext($data);
            return $this->workflowGemini->chat($message, $context);
        }
    }
    
    /**
     * Enhanced context building with workflow data
     */
    private function buildChatContext($data) {
        $context = [];
        
        // Get recent tasks
        $tasks = $this->tasks->getAll(['limit' => 5]);
        $context['recent_tasks'] = $tasks;
        
        // Get projects
        $projects = $this->projects->getAll(['limit' => 10]);
        $context['projects'] = $projects;
        
        // Get unprocessed scraps
        $scraps = $this->scraps->getAll(['processed' => false, 'limit' => 5]);
        $context['unprocessed_scraps'] = $scraps;
        
        return $context;
    }
}

// Initialization hook for workflow integration
if (getenv('CLAUDE_FLOW_HOOKS')) {
    exec("npx claude-flow@alpha hooks post-edit --file 'workflow-integration.php' --memory-key 'swarm/ai-workflow/gemini-integration'");
}

?>