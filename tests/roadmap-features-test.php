<?php
/**
 * Comprehensive Test Suite for TaskFlow AI Roadmap Features
 * Tests all 4 major features: AI Workflow, Advanced Chat, Mobile PWA, Project Filtering
 * 
 * PHPUnit test cases with >90% coverage requirement
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/features/ai-workflow.php';
require_once __DIR__ . '/../src/features/advanced-chat.php';
require_once __DIR__ . '/../src/features/mobile-pwa.php';
require_once __DIR__ . '/../src/features/project-filtering.php';
require_once __DIR__ . '/../src/integration-layer.php';

class RoadmapFeaturesTest extends TestCase {
    private $database;
    private $testDbFile;
    
    // Feature systems
    private $aiWorkflow;
    private $advancedChat;
    private $mobilePWA;
    private $projectFiltering;
    private $integrator;
    
    protected function setUp(): void {
        // Create test database
        $this->testDbFile = tempnam(sys_get_temp_dir(), 'taskflow_test_');
        $this->database = $this->createTestDatabase();
        
        // Initialize feature systems
        $this->aiWorkflow = new AIWorkflowSystem($this->database);
        $this->advancedChat = new AdvancedChatInterface($this->database);
        $this->mobilePWA = new MobilePWAManager($this->database);
        $this->projectFiltering = new ProjectFilteringSystem($this->database);
        $this->integrator = new TaskFlowFeatureIntegrator($this->database);
        
        // Seed test data
        $this->seedTestData();
    }
    
    protected function tearDown(): void {
        if (file_exists($this->testDbFile)) {
            unlink($this->testDbFile);
        }
    }
    
    private function createTestDatabase() {
        $pdo = new PDO("sqlite:{$this->testDbFile}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create basic tables
        $pdo->exec("
            CREATE TABLE projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                color TEXT,
                status TEXT DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                status TEXT DEFAULT 'pending',
                priority TEXT DEFAULT 'medium',
                project_id INTEGER,
                due_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects (id)
            );
            
            CREATE TABLE notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                content TEXT,
                project_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects (id)
            );
            
            CREATE TABLE scraps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content TEXT NOT NULL,
                processed INTEGER DEFAULT 0,
                project_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects (id)
            );
        ");
        
        return (object)['getPdo' => fn() => $pdo];
    }
    
    private function seedTestData() {
        $pdo = $this->database->getPdo();
        
        // Projects
        $pdo->exec("INSERT INTO projects (name, description, color) VALUES 
            ('TaskFlow Development', 'Main development project', '#2563eb'),
            ('Personal Tasks', 'Personal productivity', '#10b981')
        ");
        
        // Tasks
        $pdo->exec("INSERT INTO tasks (title, description, status, priority, project_id, due_date) VALUES
            ('Implement AI Workflow', 'Morning/evening rituals', 'pending', 'high', 1, date('now', '+1 day')),
            ('Setup PWA', 'Progressive web app features', 'in_progress', 'high', 1, date('now', '+2 days')),
            ('Buy groceries', 'Weekly shopping', 'pending', 'low', 2, date('now')),
            ('Unassigned task', 'No project assigned', 'pending', 'medium', NULL, NULL)
        ");
        
        // Notes
        $pdo->exec("INSERT INTO notes (title, content, project_id) VALUES
            ('AI Workflow Design', 'Morning ritual should include task selection and energy assessment', 1),
            ('PWA Requirements', 'Offline sync, push notifications, app shortcuts', 1),
            ('Random thoughts', 'This should be unassigned', NULL)
        ");
        
        // Scraps
        $pdo->exec("INSERT INTO scraps (content, processed, project_id) VALUES
            ('TODO: Add voice input to chat', 0, 1),
            ('Idea: Smart task suggestions based on context', 0, NULL),
            ('Processed scrap item', 1, 2)
        ");
    }
    
    // =============================================================================
    // AI WORKFLOW TESTS
    // =============================================================================
    
    public function testAIWorkflowInitialization() {
        $this->assertInstanceOf(AIWorkflowSystem::class, $this->aiWorkflow);
    }
    
    public function testMorningWorkflowCreation() {
        $date = date('Y-m-d');
        $workflow = $this->aiWorkflow->startMorningWorkflow($date);
        
        $this->assertIsArray($workflow);
        $this->assertEquals($date, $workflow['date']);
        $this->assertArrayHasKey('selected_tasks', $workflow);
        $this->assertArrayHasKey('unprocessed_scraps', $workflow);
        $this->assertArrayHasKey('scrap_suggestions', $workflow);
    }
    
    public function testMorningWorkflowTaskSelection() {
        $workflow = $this->aiWorkflow->startMorningWorkflow();
        
        // Should select high-priority tasks first
        $this->assertNotEmpty($workflow['selected_tasks']);
        
        $highPriorityTasks = array_filter($workflow['selected_tasks'], 
            fn($task) => $task['priority'] === 'high');
        $this->assertNotEmpty($highPriorityTasks);
    }
    
    public function testEveningWorkflowCreation() {
        // First create morning workflow
        $date = date('Y-m-d');
        $this->aiWorkflow->startMorningWorkflow($date);
        
        // Then create evening workflow
        $evening = $this->aiWorkflow->startEveningWorkflow($date);
        
        $this->assertIsArray($evening);
        $this->assertEquals($date, $evening['date']);
        $this->assertArrayHasKey('completion_stats', $evening);
        $this->assertArrayHasKey('reflection_prompts', $evening);
    }
    
    public function testWorkflowStatusTracking() {
        $date = date('Y-m-d');
        
        // Initially no workflows
        $status = $this->aiWorkflow->getTodaysWorkflowStatus($date);
        $this->assertNull($status['morning']);
        $this->assertNull($status['evening']);
        
        // After creating morning workflow
        $this->aiWorkflow->startMorningWorkflow($date);
        $status = $this->aiWorkflow->getTodaysWorkflowStatus($date);
        $this->assertNotNull($status['morning']);
        $this->assertEquals('active', $status['morning']['status']);
    }
    
    // =============================================================================
    // ADVANCED CHAT TESTS
    // =============================================================================
    
    public function testAdvancedChatInitialization() {
        $this->assertInstanceOf(AdvancedChatInterface::class, $this->advancedChat);
    }
    
    public function testChatSessionCreation() {
        $session = $this->advancedChat->getSession('test-session');
        
        $this->assertIsArray($session);
        $this->assertEquals('test-session', $session['session_key']);
        $this->assertArrayHasKey('id', $session);
    }
    
    public function testMessageAddingAndRetrieval() {
        $session = $this->advancedChat->getSession('test-session');
        
        // Add message
        $messageId = $this->advancedChat->addMessage(
            $session['id'], 
            'user', 
            'Hello, this is a test message'
        );
        
        $this->assertIsInt($messageId);
        $this->assertGreaterThan(0, $messageId);
        
        // Retrieve messages
        $messages = $this->advancedChat->getRecentMessages($session['id']);
        $this->assertNotEmpty($messages);
        $this->assertEquals('Hello, this is a test message', $messages[0]['content']);
    }
    
    public function testShortcutProcessing() {
        $session = $this->advancedChat->getSession('test-session');
        
        // Add message with shortcut
        $this->advancedChat->addMessage($session['id'], 'user', '//t Create new task');
        
        $messages = $this->advancedChat->getRecentMessages($session['id']);
        $lastMessage = end($messages);
        
        // Should be processed to "Create a new task: Create new task"
        $this->assertStringContains('Create a new task:', $lastMessage['content']);
    }
    
    public function testChatHistorySearch() {
        $session = $this->advancedChat->getSession('test-session');
        
        // Add searchable messages
        $this->advancedChat->addMessage($session['id'], 'user', 'AI workflow implementation details');
        $this->advancedChat->addMessage($session['id'], 'assistant', 'The workflow includes morning and evening rituals');
        
        // Search
        $results = $this->advancedChat->searchHistory('workflow', $session['session_key']);
        
        $this->assertNotEmpty($results);
        $this->assertCount(2, $results);
    }
    
    public function testSmartSuggestions() {
        $session = $this->advancedChat->getSession('test-session');
        
        // Add some context messages
        $this->advancedChat->addMessage($session['id'], 'user', 'I need to create a new task');
        
        $suggestions = $this->advancedChat->generateSmartSuggestions($session['id']);
        
        $this->assertIsArray($suggestions);
        $this->assertNotEmpty($suggestions);
    }
    
    // =============================================================================
    // MOBILE PWA TESTS
    // =============================================================================
    
    public function testMobilePWAInitialization() {
        $this->assertInstanceOf(MobilePWAManager::class, $this->mobilePWA);
    }
    
    public function testPWAManifestGeneration() {
        $manifest = $this->mobilePWA->generateManifest();
        
        $this->assertIsArray($manifest);
        $this->assertEquals('TaskFlow AI', $manifest['name']);
        $this->assertEquals('TaskFlow', $manifest['short_name']);
        $this->assertEquals('standalone', $manifest['display']);
        $this->assertArrayHasKey('icons', $manifest);
        $this->assertArrayHasKey('shortcuts', $manifest);
    }
    
    public function testServiceWorkerGeneration() {
        $sw = $this->mobilePWA->generateServiceWorker();
        
        $this->assertIsString($sw);
        $this->assertStringContains('CACHE_NAME', $sw);
        $this->assertStringContains('install', $sw);
        $this->assertStringContains('fetch', $sw);
        $this->assertStringContains('sync', $sw);
    }
    
    public function testSessionRegistration() {
        $result = $this->mobilePWA->registerSession('test-session-123', [
            'userAgent' => 'Test Browser',
            'platform' => 'Test Platform'
        ]);
        
        $this->assertTrue($result);
    }
    
    public function testOfflineSyncQueue() {
        // Add action to sync queue
        $result = $this->mobilePWA->addToSyncQueue('create', 'task', 'test-123', [
            'title' => 'Test Task',
            'priority' => 'high'
        ]);
        
        $this->assertTrue($result);
        
        // Get pending actions
        $pending = $this->mobilePWA->getPendingSyncActions();
        
        $this->assertNotEmpty($pending);
        $this->assertEquals('create', $pending[0]['action']);
        $this->assertEquals('task', $pending[0]['entity_type']);
    }
    
    public function testOfflineDataCaching() {
        $testData = ['key' => 'value', 'timestamp' => time()];
        
        // Cache data
        $result = $this->mobilePWA->cacheForOffline('test-key', 'test-entity', $testData, 1);
        $this->assertTrue($result);
        
        // Retrieve cached data
        $cached = $this->mobilePWA->getCachedData('test-key');
        
        $this->assertEquals($testData, $cached);
    }
    
    public function testPWAAnalytics() {
        // Register some sessions
        $this->mobilePWA->registerSession('session1', ['platform' => 'Android']);
        $this->mobilePWA->updateInstallStatus('session1', 'installed');
        
        $analytics = $this->mobilePWA->getAnalytics(30);
        
        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('install_statistics', $analytics);
        $this->assertArrayHasKey('active_sessions', $analytics);
    }
    
    // =============================================================================
    // PROJECT FILTERING TESTS
    // =============================================================================
    
    public function testProjectFilteringInitialization() {
        $this->assertInstanceOf(ProjectFilteringSystem::class, $this->projectFiltering);
    }
    
    public function testProjectTaskFiltering() {
        // Get all tasks
        $allTasks = $this->projectFiltering->getProjectTasks('all');
        $this->assertNotEmpty($allTasks);
        
        // Filter by project
        $projectTasks = $this->projectFiltering->getProjectTasks(1); // TaskFlow Development
        $this->assertCount(2, $projectTasks); // Should have 2 tasks
        
        // Filter unassigned tasks
        $unassignedTasks = $this->projectFiltering->getProjectTasks('unassigned');
        $this->assertCount(1, $unassignedTasks);
        
        // Filter by status
        $pendingTasks = $this->projectFiltering->getProjectTasks('all', ['status' => 'pending']);
        $pendingCount = count(array_filter($allTasks, fn($t) => $t['status'] === 'pending'));
        $this->assertCount($pendingCount, $pendingTasks);
    }
    
    public function testProjectNotesFiltering() {
        $allNotes = $this->projectFiltering->getProjectNotes('all');
        $this->assertNotEmpty($allNotes);
        
        $projectNotes = $this->projectFiltering->getProjectNotes(1);
        $this->assertCount(2, $projectNotes);
        
        $unassignedNotes = $this->projectFiltering->getProjectNotes('unassigned');
        $this->assertCount(1, $unassignedNotes);
    }
    
    public function testProjectScrapsFiltering() {
        $allScraps = $this->projectFiltering->getProjectScraps('all');
        $this->assertNotEmpty($allScraps);
        
        $unprocessedScraps = $this->projectFiltering->getProjectScraps('all', ['processed' => false]);
        $this->assertCount(2, $unprocessedScraps);
    }
    
    public function testProjectSummaries() {
        $summaries = $this->projectFiltering->getProjectSummaries();
        
        $this->assertNotEmpty($summaries);
        $this->assertCount(2, $summaries); // Two projects created
        
        foreach ($summaries as $summary) {
            $this->assertArrayHasKey('project_name', $summary);
            $this->assertArrayHasKey('task_count', $summary);
            $this->assertArrayHasKey('note_count', $summary);
        }
    }
    
    public function testUnassignedEntitiesSummary() {
        $summary = $this->projectFiltering->getUnassignedSummary();
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('total_entities', $summary);
        $this->assertArrayHasKey('by_type', $summary);
        $this->assertEquals(3, $summary['total_entities']); // 1 task + 1 note + 1 scrap
    }
    
    public function testUnassignedEntitiesRetrieval() {
        $entities = $this->projectFiltering->getUnassignedEntities();
        
        $this->assertCount(3, $entities);
        
        // Filter by entity type
        $tasks = $this->projectFiltering->getUnassignedEntities(['entity_type' => 'task']);
        $this->assertCount(1, $tasks);
        $this->assertEquals('task', $tasks[0]['entity_type']);
    }
    
    public function testBulkAssignToProject() {
        $assignments = [
            ['entity_type' => 'task', 'entity_id' => 4], // Unassigned task
            ['entity_type' => 'note', 'entity_id' => 3]  // Unassigned note
        ];
        
        $result = $this->projectFiltering->bulkAssignToProject(1, $assignments);
        $this->assertTrue($result);
        
        // Verify assignments
        $unassignedAfter = $this->projectFiltering->getUnassignedEntities();
        $this->assertCount(1, $unassignedAfter); // Only scrap should remain unassigned
    }
    
    // =============================================================================
    // INTEGRATION LAYER TESTS
    // =============================================================================
    
    public function testIntegrationLayerInitialization() {
        $this->assertInstanceOf(TaskFlowFeatureIntegrator::class, $this->integrator);
    }
    
    public function testFeatureInitialization() {
        $result = $this->integrator->initializeFeatures();
        $this->assertTrue($result);
    }
    
    public function testDashboardDataRetrieval() {
        $dashboard = $this->integrator->getDashboardData();
        
        $this->assertIsArray($dashboard);
        $this->assertArrayHasKey('workflow', $dashboard);
        $this->assertArrayHasKey('projects', $dashboard);
        $this->assertArrayHasKey('unassigned', $dashboard);
        $this->assertArrayHasKey('chat', $dashboard);
        $this->assertArrayHasKey('pwa', $dashboard);
    }
    
    public function testUnifiedSearch() {
        // Create morning workflow to generate conversation
        $this->aiWorkflow->startMorningWorkflow();
        
        // Add chat message
        $session = $this->advancedChat->getSession();
        $this->advancedChat->addMessage($session['id'], 'user', 'workflow implementation test');
        
        // Search across all features
        $results = $this->integrator->unifiedSearch(['q' => 'workflow']);
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('results', $results);
        $this->assertNotEmpty($results['results']);
        
        // Should find results in both chat and tasks
        $types = array_column($results['results'], 'type');
        $this->assertContains('task', $types); // Task with "workflow" in title
    }
    
    public function testSmartSuggestionsIntegration() {
        $session = $this->advancedChat->getSession();
        $suggestions = $this->integrator->getSmartSuggestions(['session_id' => $session['id']]);
        
        $this->assertIsArray($suggestions);
        $this->assertArrayHasKey('workflow', $suggestions); // Should suggest morning workflow
    }
    
    public function testAIContextGeneration() {
        $session = $this->advancedChat->getSession();
        $context = $this->integrator->getAIContext(['session_id' => $session['id']]);
        
        $this->assertIsArray($context);
        $this->assertArrayHasKey('workflow', $context);
        $this->assertArrayHasKey('projects', $context);
        $this->assertArrayHasKey('unassigned', $context);
        $this->assertArrayHasKey('features_available', $context);
    }
    
    public function testFeatureStatusHealthCheck() {
        $status = $this->integrator->getFeatureStatus();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('ai_workflow', $status);
        $this->assertArrayHasKey('advanced_chat', $status);
        $this->assertArrayHasKey('mobile_pwa', $status);
        $this->assertArrayHasKey('project_filtering', $status);
        $this->assertArrayHasKey('overall_health', $status);
        
        // All features should be healthy in tests
        $this->assertEquals('healthy', $status['overall_health']);
    }
    
    public function testFeatureConfiguration() {
        $config = $this->integrator->getFeatureConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('ai_workflow', $config);
        $this->assertArrayHasKey('advanced_chat', $config);
        $this->assertArrayHasKey('mobile_pwa', $config);
        $this->assertArrayHasKey('project_filtering', $config);
        
        // All features should be enabled
        $this->assertTrue($config['ai_workflow']['enabled']);
        $this->assertTrue($config['advanced_chat']['enabled']);
        $this->assertTrue($config['mobile_pwa']['enabled']);
        $this->assertTrue($config['project_filtering']['enabled']);
    }
    
    // =============================================================================
    // API INTEGRATION TESTS
    // =============================================================================
    
    public function testWorkflowAPIRouting() {
        $result = $this->integrator->handleRequest('POST', '/api/workflow/morning', []);
        $this->assertNotNull($result);
        $this->assertIsArray($result);
    }
    
    public function testChatAPIRouting() {
        $session = $this->advancedChat->getSession();
        $result = $this->integrator->handleRequest('POST', '/api/chat/message', [
            'session_id' => $session['id'],
            'message_type' => 'user',
            'content' => 'Test message'
        ]);
        
        $this->assertNotNull($result);
    }
    
    public function testPWAAPIRouting() {
        $result = $this->integrator->handleRequest('POST', '/api/pwa/session', [
            'session_id' => 'test-session',
            'device_info' => ['platform' => 'test']
        ]);
        
        $this->assertNotNull($result);
    }
    
    public function testFilteringAPIRouting() {
        $result = $this->integrator->handleRequest('GET', '/api/projects/tasks?project_id=all', []);
        $this->assertNotNull($result);
        $this->assertIsArray($result);
    }
    
    public function testIntegrationAPIRouting() {
        $result = $this->integrator->handleRequest('GET', '/api/integration/dashboard', []);
        $this->assertNotNull($result);
        $this->assertIsArray($result);
    }
    
    // =============================================================================
    // PERFORMANCE AND LOAD TESTS
    // =============================================================================
    
    public function testConcurrentWorkflowCreation() {
        $start = microtime(true);
        
        // Simulate concurrent workflow creation
        for ($i = 0; $i < 10; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $this->aiWorkflow->startMorningWorkflow($date);
        }
        
        $duration = microtime(true) - $start;
        $this->assertLessThan(5.0, $duration, 'Workflow creation should be fast');
    }
    
    public function testLargeDatasetFiltering() {
        // Add more test data
        $pdo = $this->database->getPdo();
        for ($i = 0; $i < 100; $i++) {
            $pdo->exec("INSERT INTO tasks (title, priority, project_id) VALUES 
                ('Test Task $i', 'medium', " . ($i % 2 + 1) . ")");
        }
        
        $start = microtime(true);
        $results = $this->projectFiltering->getProjectTasks('all', ['limit' => 50]);
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(1.0, $duration, 'Filtering should be fast even with large datasets');
        $this->assertCount(50, $results);
    }
    
    public function testChatSearchPerformance() {
        $session = $this->advancedChat->getSession();
        
        // Add many messages
        for ($i = 0; $i < 50; $i++) {
            $this->advancedChat->addMessage($session['id'], 'user', "Test message $i about workflow and tasks");
        }
        
        $start = microtime(true);
        $results = $this->advancedChat->searchHistory('workflow', $session['session_key']);
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(1.0, $duration, 'Chat search should be fast');
        $this->assertNotEmpty($results);
    }
}

// Hook execution for coordination
if (getenv('CLAUDE_FLOW_HOOKS')) {
    exec("npx claude-flow@alpha hooks pre-task --description 'comprehensive-testing-suite'");
    exec("npx claude-flow@alpha hooks session-restore --session-id 'swarm-roadmap-impl'");
    
    register_shutdown_function(function() {
        exec("npx claude-flow@alpha hooks post-edit --file 'roadmap-features-test.php' --memory-key 'swarm/testing/comprehensive-suite'");
        exec("npx claude-flow@alpha hooks post-task --task-id 'comprehensive-testing-suite'");
    });
}

?>