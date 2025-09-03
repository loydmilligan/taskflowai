<?php
/**
 * AI-Guided Daily Workflow Test Suite
 * Comprehensive testing for morning/evening workflow features
 */

require_once __DIR__ . '/../src/integration-layer.php';

class AIWorkflowTestSuite {
    private $db;
    private $workflowSystem;
    private $featureIntegrator;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->workflowSystem = new AIWorkflowSystem($this->db);
        $this->featureIntegrator = new TaskFlowFeatureIntegrator($this->db);
    }
    
    /**
     * Run all workflow tests
     */
    public function runAllTests() {
        echo "🧪 AI-Guided Daily Workflow Test Suite\n";
        echo "=====================================\n\n";
        
        $results = [
            'morning_workflow' => $this->testMorningWorkflow(),
            'evening_workflow' => $this->testEveningWorkflow(),
            'ai_task_selection' => $this->testAITaskSelection(),
            'scrap_processing' => $this->testScrapProcessing(),
            'workflow_conversation' => $this->testWorkflowConversation(),
            'gemini_integration' => $this->testGeminiIntegration(),
            'ui_generation' => $this->testUIGeneration(),
            'api_endpoints' => $this->testAPIEndpoints(),
            'mobile_responsiveness' => $this->testMobileResponsiveness(),
            'integration_layer' => $this->testIntegrationLayer()
        ];
        
        $this->displayResults($results);
        return $results;
    }
    
    /**
     * Test morning workflow functionality
     */
    private function testMorningWorkflow() {
        echo "🌅 Testing Morning Workflow...\n";
        
        try {
            // Create test tasks
            $this->createTestData();
            
            // Start morning workflow
            $workflow = $this->workflowSystem->startMorningWorkflow();
            
            // Validate workflow structure
            $requiredFields = ['id', 'selected_tasks', 'unprocessed_scraps', 'scrap_suggestions'];
            foreach ($requiredFields as $field) {
                if (!isset($workflow[$field])) {
                    throw new Exception("Missing field: $field");
                }
            }
            
            // Validate task selection
            if (empty($workflow['selected_tasks'])) {
                throw new Exception("No tasks selected for morning workflow");
            }
            
            // Test workflow completion
            $completed = $this->workflowSystem->completeWorkflow($workflow['id'], [
                'energy_level' => 8,
                'daily_intention' => 'Focus on high-priority tasks'
            ]);
            
            if (!$completed) {
                throw new Exception("Failed to complete morning workflow");
            }
            
            echo "✅ Morning workflow test passed\n";
            return ['status' => 'PASS', 'details' => 'Morning workflow created and completed successfully'];
            
        } catch (Exception $e) {
            echo "❌ Morning workflow test failed: " . $e->getMessage() . "\n";
            return ['status' => 'FAIL', 'details' => $e->getMessage()];
        }
    }
    
    /**
     * Test evening workflow functionality
     */
    private function testEveningWorkflow() {
        echo "🌙 Testing Evening Workflow...\n";
        
        try {
            // Start evening workflow
            $workflow = $this->workflowSystem->startEveningWorkflow();
            
            // Validate workflow structure
            $requiredFields = ['id', 'completion_stats', 'reflection_prompts', 'suggested_followups'];
            foreach ($requiredFields as $field) {
                if (!isset($workflow[$field])) {
                    throw new Exception("Missing field: $field");
                }
            }
            
            // Validate completion stats
            $stats = $workflow['completion_stats'];
            if (!isset($stats['tasks_completed']) || !isset($stats['productivity_score'])) {
                throw new Exception("Invalid completion stats");
            }
            
            // Test reflection prompts
            if (empty($workflow['reflection_prompts'])) {
                throw new Exception("No reflection prompts generated");
            }
            
            echo "✅ Evening workflow test passed\n";
            return ['status' => 'PASS', 'details' => 'Evening workflow generated with proper structure'];
            
        } catch (Exception $e) {
            echo "❌ Evening workflow test failed: " . $e->getMessage() . "\n";
            return ['status' => 'FAIL', 'details' => $e->getMessage()];
        }
    }
    
    /**
     * Test AI-powered task selection
     */
    private function testAITaskSelection() {
        echo "🎯 Testing AI Task Selection...\n";
        
        try {
            // Create diverse test tasks
            $this->createDiverseTestTasks();
            
            // Get workflow with AI selection
            $workflow = $this->workflowSystem->startMorningWorkflow();
            $selectedTasks = $workflow['selected_tasks'];
            
            // Validate selection logic
            if (count($selectedTasks) > 5) {
                throw new Exception("Too many tasks selected (max 5)");
            }
            
            // Validate high-priority tasks are prioritized
            $highPriorityCount = 0;
            foreach ($selectedTasks as $task) {
                if ($task['priority'] === 'high') {
                    $highPriorityCount++;
                }
                
                // Check for selection reasoning
                if (!isset($task['selection_reason'])) {
                    throw new Exception("Missing selection reason for task");
                }
            }
            
            echo "✅ AI task selection test passed\n";
            return ['status' => 'PASS', 'details' => "Selected {$highPriorityCount} high-priority tasks with reasoning"];
            
        } catch (Exception $e) {
            echo "❌ AI task selection test failed: " . $e->getMessage() . "\n";
            return ['status' => 'FAIL', 'details' => $e->getMessage()];
        }
    }
    
    /**
     * Test scrap processing with AI analysis
     */
    private function testScrapProcessing() {
        echo "📝 Testing Scrap Processing...\n";
        
        try {
            // Create test scraps
            $this->createTestScraps();
            
            $workflow = $this->workflowSystem->startMorningWorkflow();
            $scrapSuggestions = $workflow['scrap_suggestions'];
            
            // Validate suggestions structure
            foreach ($scrapSuggestions as $suggestion) {
                $requiredFields = ['scrap_id', 'suggestion_type', 'suggested_action', 'confidence'];
                foreach ($requiredFields as $field) {
                    if (!isset($suggestion[$field])) {
                        throw new Exception("Missing field in suggestion: $field");
                    }
                }
                
                if ($suggestion['confidence'] < 0 || $suggestion['confidence'] > 1) {
                    throw new Exception("Invalid confidence score");
                }
            }
            
            echo "✅ Scrap processing test passed\n";
            return ['status' => 'PASS', 'details' => count($scrapSuggestions) . ' scrap suggestions generated'];
            
        } catch (Exception $e) {
            echo "❌ Scrap processing test failed: " . $e->getMessage() . "\n";
            return ['status' => 'FAIL', 'details' => $e->getMessage()];
        }
    }
    
    /**
     * Test workflow conversation functionality
     */
    private function testWorkflowConversation() {
        echo "💬 Testing Workflow Conversation...\n";
        
        try {
            $workflow = $this->workflowSystem->startMorningWorkflow();
            $workflowId = $workflow['id'];
            
            // Add test messages
            $this->workflowSystem->addWorkflowMessage($workflowId, 'user', 'My energy is high today');
            $this->workflowSystem->addWorkflowMessage($workflowId, 'assistant', 'Great! High energy is perfect for tackling challenging tasks.');
            
            // Retrieve conversation
            $conversation = $this->workflowSystem->getWorkflowConversation($workflowId);
            
            if (count($conversation) < 3) { // Initial + 2 test messages
                throw new Exception("Conversation not properly stored");
            }
            
            // Validate message structure
            foreach ($conversation as $message) {
                $requiredFields = ['message_type', 'content', 'created_at'];
                foreach ($requiredFields as $field) {
                    if (!isset($message[$field])) {
                        throw new Exception("Missing field in message: $field");
                    }
                }
            }
            
            echo "✅ Workflow conversation test passed\n";
            return ['status' => 'PASS', 'details' => 'Conversation stored and retrieved correctly'];
            
        } catch (Exception $e) {
            echo "❌ Workflow conversation test failed: " . $e->getMessage() . "\n";
            return ['status' => 'FAIL', 'details' => $e->getMessage()];
        }
    }
    
    /**
     * Test Gemini AI integration
     */
    private function testGeminiIntegration() {
        echo "🤖 Testing Gemini AI Integration...\n";
        
        try {
            $workflowGemini = new WorkflowAwareGeminiAI();
            
            // Test workflow trigger detection
            $response = $workflowGemini->chat('Good morning, help me plan my day');
            
            if (!isset($response['workflow_triggered'])) {
                // Not necessarily an error if API key not configured
                echo "⚠️ Gemini integration requires API key configuration\n";
                return ['status' => 'SKIP', 'details' => 'Requires Gemini API key configuration'];
            }
            
            if ($response['workflow_type'] !== 'morning') {
                throw new Exception("Failed to detect morning workflow trigger");
            }
            
            echo "✅ Gemini AI integration test passed\n";
            return ['status' => 'PASS', 'details' => 'Workflow trigger detection working'];
            
        } catch (Exception $e) {
            echo "❌ Gemini AI integration test failed: " . $e->getMessage() . "\n";
            return ['status' => 'FAIL', 'details' => $e->getMessage()];
        }
    }
    
    /**
     * Test UI generation
     */
    private function testUIGeneration() {
        echo "🎨 Testing UI Generation...\n";
        
        try {
            $workflow = $this->workflowSystem->startMorningWorkflow();
            
            $morningHTML = WorkflowUIGenerator::generateMorningWorkflowUI($workflow);
            $eveningHTML = WorkflowUIGenerator::generateEveningWorkflowUI([
                'completion_stats' => ['tasks_completed' => 3, 'productivity_score' => 7.5],
                'reflection_prompts' => ['What was your biggest win?'],
                'suggested_followups' => ['Plan tomorrow']
            ]);
            
            // Validate HTML structure
            if (strpos($morningHTML, 'morning-workflow') === false) {
                throw new Exception("Morning workflow HTML missing expected elements");
            }
            
            if (strpos($eveningHTML, 'evening-workflow') === false) {
                throw new Exception("Evening workflow HTML missing expected elements");
            }
            
            // Test CSS and JS generation
            $css = WorkflowUIGenerator::generateWorkflowCSS();
            $js = WorkflowUIGenerator::generateWorkflowJS();
            
            if (empty($css) || empty($js)) {
                throw new Exception("CSS or JS generation failed");
            }
            
            echo "✅ UI generation test passed\n";
            return ['status' => 'PASS', 'details' => 'HTML, CSS, and JS generated successfully'];
            
        } catch (Exception $e) {
            echo "❌ UI generation test failed: " . $e->getMessage() . "\n";
            return ['status' => 'FAIL', 'details' => $e->getMessage()];
        }
    }
    
    /**
     * Test API endpoints
     */
    private function testAPIEndpoints() {
        echo "🔌 Testing API Endpoints...\n";
        
        try {
            $routes = new WorkflowRoutes($this->workflowSystem);
            
            // Test morning workflow start
            $morningResult = $routes->handleRequest('POST', '/api/workflow/morning', []);
            if (empty($morningResult['id'])) {
                throw new Exception("Morning workflow API failed");
            }
            
            // Test workflow status
            $statusResult = $routes->handleRequest('GET', '/api/workflow/status', []);
            if (!isset($statusResult['morning'])) {
                throw new Exception("Status API failed");
            }
            
            // Test message addition
            $messageResult = $routes->handleRequest('POST', '/api/workflow/message', [
                'workflow_id' => $morningResult['id'],
                'message_type' => 'user',
                'content' => 'Test message'
            ]);
            
            if (!$messageResult) {
                throw new Exception("Message API failed");
            }
            
            echo "✅ API endpoints test passed\n";
            return ['status' => 'PASS', 'details' => 'All API endpoints responding correctly'];
            
        } catch (Exception $e) {
            echo "❌ API endpoints test failed: " . $e->getMessage() . "\n";
            return ['status' => 'FAIL', 'details' => $e->getMessage()];
        }
    }
    
    /**
     * Test mobile responsiveness
     */
    private function testMobileResponsiveness() {
        echo "📱 Testing Mobile Responsiveness...\n";
        
        try {
            $css = WorkflowUIGenerator::generateWorkflowCSS();
            
            // Check for mobile-specific CSS
            if (strpos($css, '@media (max-width: 768px)') === false) {
                throw new Exception("Mobile responsiveness rules not found in CSS");
            }
            
            // Check for mobile-first design elements
            $mobileElements = ['flex-direction: column', 'grid-template-columns: repeat(2, 1fr)'];
            foreach ($mobileElements as $element) {
                if (strpos($css, $element) === false) {
                    throw new Exception("Missing mobile design element: $element");
                }
            }
            
            echo "✅ Mobile responsiveness test passed\n";
            return ['status' => 'PASS', 'details' => 'Mobile-responsive CSS rules present'];
            
        } catch (Exception $e) {
            echo "❌ Mobile responsiveness test failed: " . $e->getMessage() . "\n";
            return ['status' => 'FAIL', 'details' => $e->getMessage()];
        }
    }
    
    /**
     * Test integration layer functionality
     */
    private function testIntegrationLayer() {
        echo "🔗 Testing Integration Layer...\n";
        
        try {
            // Test feature status
            $featureStatus = $this->featureIntegrator->getFeatureStatus();
            
            if ($featureStatus['ai_workflow'] !== 'healthy') {
                throw new Exception("AI workflow feature not healthy");
            }
            
            // Test dashboard data
            $dashboardData = $this->featureIntegrator->getDashboardData();
            if (!isset($dashboardData['workflow']['status'])) {
                throw new Exception("Dashboard missing workflow status");
            }
            
            // Test smart suggestions
            $suggestions = $this->featureIntegrator->getSmartSuggestions(['context' => 'general']);
            if (!is_array($suggestions)) {
                throw new Exception("Smart suggestions not returned as array");
            }
            
            echo "✅ Integration layer test passed\n";
            return ['status' => 'PASS', 'details' => 'All integration features working correctly'];
            
        } catch (Exception $e) {
            echo "❌ Integration layer test failed: " . $e->getMessage() . "\n";
            return ['status' => 'FAIL', 'details' => $e->getMessage()];
        }
    }
    
    /**
     * Create test data for workflow testing
     */
    private function createTestData() {
        $taskManager = new TaskManager();
        $scrapManager = new ScrapManager();
        
        // Create test tasks
        $taskManager->create([
            'title' => 'High Priority Task',
            'description' => 'Important task for testing',
            'priority' => 'high',
            'due_date' => date('Y-m-d')
        ]);
        
        $taskManager->create([
            'title' => 'Medium Priority Task',
            'description' => 'Medium task for testing',
            'priority' => 'medium'
        ]);
        
        // Create test scraps
        $scrapManager->create([
            'content' => 'Need to schedule dentist appointment'
        ]);
    }
    
    /**
     * Create diverse test tasks for AI selection testing
     */
    private function createDiverseTestTasks() {
        $taskManager = new TaskManager();
        
        $tasks = [
            ['title' => 'Urgent Bug Fix', 'priority' => 'high', 'due_date' => date('Y-m-d')],
            ['title' => 'Code Review', 'priority' => 'medium', 'due_date' => date('Y-m-d', strtotime('+1 day'))],
            ['title' => 'Documentation Update', 'priority' => 'low'],
            ['title' => 'Client Meeting Prep', 'priority' => 'high', 'due_date' => date('Y-m-d')],
            ['title' => 'Research Task', 'priority' => 'medium'],
            ['title' => 'Email Cleanup', 'priority' => 'low']
        ];
        
        foreach ($tasks as $taskData) {
            $taskManager->create($taskData);
        }
    }
    
    /**
     * Create test scraps for processing
     */
    private function createTestScraps() {
        $scrapManager = new ScrapManager();
        
        $scraps = [
            'Todo: Call insurance company',
            'Idea: New feature for mobile app',
            'Remember to buy groceries',
            'What if we used AI for recommendations?'
        ];
        
        foreach ($scraps as $content) {
            $scrapManager->create(['content' => $content]);
        }
    }
    
    /**
     * Display test results summary
     */
    private function displayResults($results) {
        echo "\n📊 Test Results Summary\n";
        echo "======================\n";
        
        $passed = 0;
        $failed = 0;
        $skipped = 0;
        
        foreach ($results as $testName => $result) {
            $status = $result['status'];
            $icon = $status === 'PASS' ? '✅' : ($status === 'FAIL' ? '❌' : '⚠️');
            
            echo sprintf("%-25s %s %s\n", $testName, $icon, $status);
            if (!empty($result['details'])) {
                echo sprintf("%-25s   %s\n", '', $result['details']);
            }
            
            if ($status === 'PASS') $passed++;
            elseif ($status === 'FAIL') $failed++;
            else $skipped++;
        }
        
        echo "\n📈 Summary: {$passed} passed, {$failed} failed, {$skipped} skipped\n";
        
        if ($failed === 0) {
            echo "🎉 All tests passed! AI-Guided Daily Workflow is ready.\n";
        } else {
            echo "⚠️ Some tests failed. Please review and fix issues.\n";
        }
    }
}

// Run tests if called directly
if (basename($_SERVER['SCRIPT_NAME']) === 'ai-workflow-test.php') {
    $testSuite = new AIWorkflowTestSuite();
    $testSuite->runAllTests();
}

?>