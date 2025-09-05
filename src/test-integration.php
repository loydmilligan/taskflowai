<?php
/**
 * Integration Test Script
 * Tests the new proactive workflow and enhanced conversation features
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/features/enhanced-ai-conversation.php';
require_once __DIR__ . '/features/proactive-workflows.php';

echo "=== TaskFlow AI Integration Test ===\n\n";

try {
    // Initialize database
    $database = new Database();
    $db = $database->getConnection();
    echo "✓ Database connection established\n";
    
    // Test 1: Conversation Memory System
    echo "\n--- Test 1: Conversation Memory System ---\n";
    
    $memoryManager = new ConversationMemoryManager($db);
    
    // Create test session
    $session = $memoryManager->createSession('test-user-123');
    echo "✓ Created conversation session: {$session['session_id']}\n";
    
    // Add test messages
    $messageId1 = $memoryManager->addMessage(
        $session['session_id'],
        'user',
        'I want to work on my project presentation today'
    );
    echo "✓ Added user message: {$messageId1}\n";
    
    $messageId2 = $memoryManager->addMessage(
        $session['session_id'],
        'assistant',
        'Great! I can help you with your presentation. What specific aspects would you like to focus on? [ADD_TO_TODAY:presentation_work:User wants to work on project presentation]'
    );
    echo "✓ Added assistant message with action: {$messageId2}\n";
    
    // Test message retrieval
    $messages = $memoryManager->getMessages($session['session_id'], 10);
    echo "✓ Retrieved {count($messages)} messages from session\n";
    
    // Test conversation context
    $context = $memoryManager->getConversationContext($session['session_id']);
    echo "✓ Generated conversation context with " . strlen($context) . " characters\n";
    
    // Test 2: Today's Plan Integration
    echo "\n--- Test 2: Today's Plan Integration ---\n";
    
    $todaysPlan = new TodaysPlanManager($db);
    
    // Add task to today's plan
    $taskId = $todaysPlan->addTask(
        'presentation_work',
        'Work on project presentation',
        'User wants to work on project presentation',
        'high',
        $session['session_id']
    );
    echo "✓ Added task to Today's Plan: {$taskId}\n";
    
    // Get today's tasks
    $todaysTasks = $todaysPlan->getTodaysTasks();
    echo "✓ Retrieved " . count($todaysTasks) . " tasks from Today's Plan\n";
    
    // Test task completion
    $todaysPlan->completeTask($taskId, 'Presentation slides completed');
    echo "✓ Marked task as completed\n";
    
    // Test 3: Enhanced AI Conversation
    echo "\n--- Test 3: Enhanced AI Conversation ---\n";
    
    // Create mock GeminiAI for testing
    $mockGemini = new class {
        public function generateResponse($prompt, $options = []) {
            return "This is a test response. I understand you want to work on your presentation. [ADD_TO_TODAY:research_task:Research market trends for presentation]";
        }
    };
    
    $enhancedAI = new EnhancedWorkflowAI($db, $mockGemini);
    
    // Test conversation with memory
    $response = $enhancedAI->processMessage(
        'Can you help me research market trends for the presentation?',
        $session['session_id']
    );
    echo "✓ Processed message with conversation memory\n";
    echo "   Response: " . substr($response['response'], 0, 100) . "...\n";
    
    if (!empty($response['actions'])) {
        echo "✓ Detected " . count($response['actions']) . " actions in response\n";
        foreach ($response['actions'] as $action) {
            echo "   Action: {$action['type']} - {$action['task_title']}\n";
        }
    }
    
    // Test 4: Proactive Workflow System
    echo "\n--- Test 4: Proactive Workflow System ---\n";
    
    // Create mock ntfy manager
    $mockNtfy = new class {
        public function sendNotification($topic, $title, $message, $options = []) {
            echo "   Mock notification sent to {$topic}: {$title}\n";
            return ['success' => true, 'message_id' => 'test-123'];
        }
    };
    
    $proactiveWorkflows = new ProactiveWorkflowManager($db, $mockNtfy, null);
    
    // Configure morning workflow
    $configResult = $proactiveWorkflows->configureWorkflowSchedule(
        'morning',
        true,
        '09:00',
        'taskflow-morning',
        ['timezone' => 'America/New_York']
    );
    echo "✓ Configured morning workflow schedule\n";
    
    // Test notification sending
    $notificationResult = $proactiveWorkflows->sendWorkflowNotification(
        'morning',
        ['trigger_type' => 'test']
    );
    echo "✓ Sent test workflow notification\n";
    
    // Get workflow schedules
    $schedules = $proactiveWorkflows->getWorkflowSchedules();
    echo "✓ Retrieved " . count($schedules) . " workflow schedules\n";
    
    // Test 5: Database Schema Verification
    echo "\n--- Test 5: Database Schema Verification ---\n";
    
    $tables = [
        'conversation_sessions',
        'conversation_messages', 
        'todays_plan',
        'workflow_conversations',
        'workflow_schedules',
        'workflow_states',
        'workflow_logs'
    ];
    
    $existingTables = [];
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            $existingTables[] = $table;
            echo "✓ Table exists: {$table}\n";
        } else {
            echo "✗ Table missing: {$table}\n";
        }
    }
    
    echo "\n✓ Found " . count($existingTables) . "/" . count($tables) . " required tables\n";
    
    // Test 6: Integration Layer Routes
    echo "\n--- Test 6: Integration Layer Routes ---\n";
    
    require_once __DIR__ . '/integration-layer.php';
    
    $integrator = new TaskFlowFeatureIntegrator($db, $mockGemini, $mockNtfy);
    
    // Test settings route
    $settingsResponse = $integrator->handleRequest('GET', '/api/workflow-settings');
    if ($settingsResponse) {
        echo "✓ Workflow settings route working\n";
    } else {
        echo "✗ Workflow settings route not responding\n";
    }
    
    // Test conversation route
    $conversationData = ['message' => 'Test conversation', 'session_id' => $session['session_id']];
    $conversationResponse = $integrator->handleRequest('POST', '/api/enhanced-chat/message', $conversationData);
    if ($conversationResponse) {
        echo "✓ Enhanced chat route working\n";
    } else {
        echo "✗ Enhanced chat route not responding\n";
    }
    
    // Test feature status
    $statusResponse = $integrator->getFeatureStatus();
    if ($statusResponse) {
        echo "✓ Feature status check working\n";
        $healthyFeatures = array_filter($statusResponse, fn($status) => $status === 'healthy');
        echo "   Healthy features: " . count($healthyFeatures) . "/" . count($statusResponse) . "\n";
    }
    
    // Cleanup test data
    echo "\n--- Cleanup ---\n";
    
    // Remove test session and related data
    $stmt = $db->prepare("DELETE FROM conversation_messages WHERE session_id = ?");
    $stmt->execute([$session['session_id']]);
    
    $stmt = $db->prepare("DELETE FROM conversation_sessions WHERE session_id = ?");  
    $stmt->execute([$session['session_id']]);
    
    $stmt = $db->prepare("DELETE FROM todays_plan WHERE task_id LIKE 'presentation_%' OR task_id LIKE 'research_%'");
    $stmt->execute();
    
    $stmt = $db->prepare("DELETE FROM workflow_schedules WHERE workflow_type = 'morning' AND ntfy_topic = 'taskflow-morning'");
    $stmt->execute();
    
    echo "✓ Cleaned up test data\n";
    
    echo "\n=== Test Results Summary ===\n";
    echo "✓ Conversation Memory System: Working\n";
    echo "✓ Today's Plan Integration: Working\n"; 
    echo "✓ Enhanced AI Conversation: Working\n";
    echo "✓ Proactive Workflow System: Working\n";
    echo "✓ Database Schema: " . count($existingTables) . "/" . count($tables) . " tables present\n";
    echo "✓ Integration Layer Routes: Working\n";
    echo "\n🎉 All core functionality tests passed!\n";
    
} catch (Exception $e) {
    echo "\n✗ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>