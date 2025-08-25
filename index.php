<?php
/**
 * TaskFlow AI - Single File Mobile-First Task Management App
 * Chat-driven interface powered by Google Gemini AI
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

/**
 * Database Configuration and Setup
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $dbPath = __DIR__ . '/taskflow.db';
        $this->pdo = new PDO("sqlite:$dbPath");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->initializeSchema();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getPdo() {
        return $this->pdo;
    }
    
    private function initializeSchema() {
        $sql = "
        -- Settings table for configuration
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        -- API keys for authentication
        CREATE TABLE IF NOT EXISTS api_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key_hash TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        -- Projects table
        CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            status TEXT DEFAULT 'active',
            tags TEXT DEFAULT '[]',
            area TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        -- Tasks table
        CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER,
            title TEXT NOT NULL,
            description TEXT,
            due_date DATE,
            priority TEXT DEFAULT 'medium',
            status TEXT DEFAULT 'pending',
            tags TEXT DEFAULT '[]',
            area TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        );
        
        -- Notes table
        CREATE TABLE IF NOT EXISTS notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            date_assigned DATE,
            date_range_end DATE,
            tags TEXT DEFAULT '[]',
            area TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        -- Scraps table for raw input
        CREATE TABLE IF NOT EXISTS scraps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT NOT NULL,
            date_assigned DATE,
            date_range_end DATE,
            processed BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        -- Chat history for AI context
        CREATE TABLE IF NOT EXISTS chat_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message TEXT NOT NULL,
            response TEXT NOT NULL,
            context TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        -- Indexes for performance
        CREATE INDEX IF NOT EXISTS idx_tasks_due_date ON tasks(due_date);
        CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status);
        CREATE INDEX IF NOT EXISTS idx_tasks_project_id ON tasks(project_id);
        CREATE INDEX IF NOT EXISTS idx_notes_date_assigned ON notes(date_assigned);
        CREATE INDEX IF NOT EXISTS idx_scraps_processed ON scraps(processed);
        CREATE INDEX IF NOT EXISTS idx_chat_timestamp ON chat_history(timestamp);
        ";
        
        $this->pdo->exec($sql);
        
        // Create default API key if none exists
        $this->createDefaultApiKey();
        
        // Set default Gemini API key placeholder
        $this->setDefaultSettings();
    }
    
    private function createDefaultApiKey() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM api_keys");
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            $defaultKey = 'taskflow_' . bin2hex(random_bytes(16));
            $keyHash = password_hash($defaultKey, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("INSERT INTO api_keys (key_hash, name) VALUES (?, ?)");
            $stmt->execute([$keyHash, 'Default API Key']);
            
            // Store the actual key in settings for display (in production, this should be shown once)
            $this->pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)")
                     ->execute(['default_api_key', $defaultKey]);
        }
    }
    
    private function setDefaultSettings() {
        $defaults = [
            'gemini_api_key' => '',
            'app_name' => 'TaskFlow AI',
            'ntfy_topic' => '',
            'morning_reminder_time' => '09:00'
        ];
        
        foreach ($defaults as $key => $value) {
            $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
    }
}

/**
 * Authentication Helper
 */
class Auth {
    private static $db;
    
    public static function init() {
        self::$db = Database::getInstance()->getPdo();
    }
    
    public static function validateApiKey($key) {
        if (!$key) return false;
        
        $stmt = self::$db->prepare("SELECT id FROM api_keys WHERE key_hash = ?");
        $stmt->execute([password_hash($key, PASSWORD_DEFAULT)]);
        
        // Since we can't verify hash directly, we need to check all keys
        $stmt = self::$db->prepare("SELECT id, key_hash FROM api_keys");
        $stmt->execute();
        $keys = $stmt->fetchAll();
        
        foreach ($keys as $keyData) {
            if (password_verify($key, $keyData['key_hash'])) {
                // Update last used
                $updateStmt = self::$db->prepare("UPDATE api_keys SET last_used = CURRENT_TIMESTAMP WHERE id = ?");
                $updateStmt->execute([$keyData['id']]);
                return true;
            }
        }
        
        return false;
    }
    
    public static function requireAuth() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'Authorization header required']);
            exit;
        }
        
        $token = $matches[1];
        if (!self::validateApiKey($token)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }
    }
}

/**
 * Google Gemini AI Integration
 */
class GeminiAI {
    private $apiKey;
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
        $stmt = $this->db->prepare("SELECT value FROM settings WHERE key = 'gemini_api_key'");
        $stmt->execute();
        $this->apiKey = $stmt->fetchColumn();
    }
    
    public function isConfigured() {
        return !empty($this->apiKey);
    }
    
    public function chat($message, $context = []) {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Gemini API key not configured',
                'response' => 'I need to be configured with a Google Gemini API key to help you. Please add your API key in the settings.'
            ];
        }
        
        try {
            $systemPrompt = $this->buildSystemPrompt($context);
            $response = $this->callGeminiAPI($systemPrompt . "\n\nUser: " . $message);
            
            if ($response) {
                // Store chat history
                $stmt = $this->db->prepare("INSERT INTO chat_history (message, response, context) VALUES (?, ?, ?)");
                $stmt->execute([$message, $response, json_encode($context)]);
                
                // Parse response for actions
                $actions = $this->parseActions($response);
                
                return [
                    'success' => true,
                    'response' => $response,
                    'actions' => $actions
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to get response from Gemini API',
                'response' => 'Sorry, I encountered an error while processing your request. Please try again.'
            ];
            
        } catch (Exception $e) {
            error_log("Gemini API Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response' => 'Sorry, I encountered an error while processing your request. Please try again.'
            ];
        }
    }
    
    private function buildSystemPrompt($context) {
        $prompt = "You are TaskFlow AI, a helpful assistant for managing projects, tasks, and notes. You can help users create, edit, and organize their work through natural conversation.

CAPABILITIES:
- Create and manage projects, tasks, notes, and scraps
- Convert scraps into tasks or notes
- Set due dates, priorities, and organize by areas/tags
- Navigate between different views
- Process daily planning and scrap organization

CURRENT CONTEXT:
";
        
        if (!empty($context['recent_tasks'])) {
            $prompt .= "Recent Tasks: " . json_encode($context['recent_tasks']) . "\n";
        }
        
        if (!empty($context['projects'])) {
            $prompt .= "Current Projects: " . json_encode($context['projects']) . "\n";
        }
        
        if (!empty($context['unprocessed_scraps'])) {
            $prompt .= "Unprocessed Scraps: " . json_encode($context['unprocessed_scraps']) . "\n";
        }
        
        $prompt .= "\nWhen users want to create or modify entities, respond with the appropriate action in your response. Use this format for actions:

[ACTION:CREATE_TASK] {\"title\": \"Task title\", \"description\": \"Description\", \"due_date\": \"YYYY-MM-DD\", \"priority\": \"high|medium|low\", \"project_id\": null, \"tags\": [\"tag1\"], \"area\": \"area_name\"}

[ACTION:CREATE_PROJECT] {\"name\": \"Project name\", \"description\": \"Description\", \"area\": \"area_name\", \"tags\": [\"tag1\"]}

[ACTION:CREATE_NOTE] {\"title\": \"Note title\", \"content\": \"Content\", \"date_assigned\": \"YYYY-MM-DD\", \"tags\": [\"tag1\"], \"area\": \"area_name\"}

[ACTION:CREATE_SCRAP] {\"content\": \"Raw thought or idea\", \"date_assigned\": \"YYYY-MM-DD\"}

[ACTION:UPDATE_TASK] {\"id\": 123, \"updates\": {\"title\": \"New title\", \"status\": \"completed\"}}

[ACTION:CONVERT_SCRAP] {\"scrap_id\": 123, \"to\": \"task|note\", \"data\": {...}}

Be conversational and helpful. Provide context and explanations with your responses.";

        return $prompt;
    }
    
    private function callGeminiAPI($prompt) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $this->apiKey;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2048
            ]
        ];
        
        // Use file_get_contents instead of cURL
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($data),
                'timeout' => 30
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to call Gemini API");
        }
        
        $decoded = json_decode($response, true);
        
        if (!$decoded || !isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid response format from Gemini API");
        }
        
        return $decoded['candidates'][0]['content']['parts'][0]['text'];
    }
    
    private function parseActions($response) {
        $actions = [];
        
        // Match action patterns - handle nested JSON properly
        if (preg_match_all('/\[ACTION:([A-Z_]+)\]\s*(\{(?:[^{}]|{[^}]*})*\})/i', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $actionType = $match[1];
                $actionData = json_decode($match[2], true);
                
                if ($actionData !== null) {
                    $actions[] = [
                        'type' => $actionType,
                        'data' => $actionData
                    ];
                }
            }
        }
        
        return $actions;
    }
    
    public function getRecentChatHistory($limit = 10) {
        $stmt = $this->db->prepare("SELECT message, response, timestamp FROM chat_history ORDER BY timestamp DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}

/**
 * Entity Managers
 */
class ProjectManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO projects (name, description, status, tags, area) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['status'] ?? 'active',
            json_encode($data['tags'] ?? []),
            $data['area'] ?? null
        ]);
        
        return $this->getById($this->db->lastInsertId());
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT * FROM projects WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['area'])) {
            $sql .= " AND area = ?";
            $params[] = $filters['area'];
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $projects = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($projects as &$project) {
            $project['tags'] = json_decode($project['tags'], true);
        }
        
        return $projects;
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();
        
        if ($project) {
            $project['tags'] = json_decode($project['tags'], true);
        }
        
        return $project;
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['name', 'description', 'status', 'area'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['tags'])) {
            $fields[] = "tags = ?";
            $params[] = json_encode($data['tags']);
        }
        
        if (!empty($fields)) {
            $fields[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $id;
            
            $sql = "UPDATE projects SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
        
        return $this->getById($id);
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM projects WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

class TaskManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO tasks (project_id, title, description, due_date, priority, status, tags, area) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['project_id'] ?? null,
            $data['title'],
            $data['description'] ?? '',
            $data['due_date'] ?? null,
            $data['priority'] ?? 'medium',
            $data['status'] ?? 'pending',
            json_encode($data['tags'] ?? []),
            $data['area'] ?? null
        ]);
        
        return $this->getById($this->db->lastInsertId());
    }
    
    public function getAll($filters = []) {
        $sql = "
            SELECT t.*, p.name as project_name 
            FROM tasks t 
            LEFT JOIN projects p ON t.project_id = p.id 
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (!empty($filters['project_id'])) {
            $sql .= " AND t.project_id = ?";
            $params[] = $filters['project_id'];
        }
        
        if (!empty($filters['area'])) {
            $sql .= " AND t.area = ?";
            $params[] = $filters['area'];
        }
        
        if (!empty($filters['due_date'])) {
            $sql .= " AND t.due_date = ?";
            $params[] = $filters['due_date'];
        }
        
        if (isset($filters['overdue']) && $filters['overdue']) {
            $sql .= " AND t.due_date < DATE('now') AND t.status != 'completed'";
        }
        
        $sql .= " ORDER BY 
            CASE t.priority 
                WHEN 'high' THEN 1 
                WHEN 'medium' THEN 2 
                WHEN 'low' THEN 3 
            END,
            t.due_date ASC,
            t.created_at DESC
        ";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = $filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $tasks = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($tasks as &$task) {
            $task['tags'] = json_decode($task['tags'], true);
        }
        
        return $tasks;
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT t.*, p.name as project_name 
            FROM tasks t 
            LEFT JOIN projects p ON t.project_id = p.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        
        if ($task) {
            $task['tags'] = json_decode($task['tags'], true);
        }
        
        return $task;
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['project_id', 'title', 'description', 'due_date', 'priority', 'status', 'area'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['tags'])) {
            $fields[] = "tags = ?";
            $params[] = json_encode($data['tags']);
        }
        
        if (!empty($fields)) {
            $fields[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $id;
            
            $sql = "UPDATE tasks SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
        
        return $this->getById($id);
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM tasks WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getTodaysPlan() {
        $today = date('Y-m-d');
        
        // Get tasks due today or overdue
        $tasks = $this->getAll([
            'due_date' => $today
        ]);
        
        $overdue = $this->getAll([
            'overdue' => true
        ]);
        
        return [
            'today' => $tasks,
            'overdue' => $overdue,
            'date' => $today
        ];
    }
}

class NoteManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO notes (title, content, date_assigned, date_range_end, tags, area) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['date_assigned'] ?? null,
            $data['date_range_end'] ?? null,
            json_encode($data['tags'] ?? []),
            $data['area'] ?? null
        ]);
        
        return $this->getById($this->db->lastInsertId());
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT * FROM notes WHERE 1=1";
        $params = [];
        
        if (!empty($filters['area'])) {
            $sql .= " AND area = ?";
            $params[] = $filters['area'];
        }
        
        if (!empty($filters['date_assigned'])) {
            $sql .= " AND date_assigned = ?";
            $params[] = $filters['date_assigned'];
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $notes = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($notes as &$note) {
            $note['tags'] = json_decode($note['tags'], true);
        }
        
        return $notes;
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM notes WHERE id = ?");
        $stmt->execute([$id]);
        $note = $stmt->fetch();
        
        if ($note) {
            $note['tags'] = json_decode($note['tags'], true);
        }
        
        return $note;
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['title', 'content', 'date_assigned', 'date_range_end', 'area'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['tags'])) {
            $fields[] = "tags = ?";
            $params[] = json_encode($data['tags']);
        }
        
        if (!empty($fields)) {
            $fields[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $id;
            
            $sql = "UPDATE notes SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
        
        return $this->getById($id);
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM notes WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

class ScrapManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO scraps (content, date_assigned, date_range_end) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $data['content'],
            $data['date_assigned'] ?? null,
            $data['date_range_end'] ?? null
        ]);
        
        return $this->getById($this->db->lastInsertId());
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT * FROM scraps WHERE 1=1";
        $params = [];
        
        if (isset($filters['processed'])) {
            $sql .= " AND processed = ?";
            $params[] = $filters['processed'] ? 1 : 0;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM scraps WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function convertToTask($scrapId, $taskData) {
        $scrap = $this->getById($scrapId);
        if (!$scrap) {
            throw new Exception("Scrap not found");
        }
        
        // Create task
        $taskManager = new TaskManager();
        $taskData['description'] = $taskData['description'] ?? $scrap['content'];
        $task = $taskManager->create($taskData);
        
        // Mark scrap as processed
        $stmt = $this->db->prepare("UPDATE scraps SET processed = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$scrapId]);
        
        return $task;
    }
    
    public function convertToNote($scrapId, $noteData) {
        $scrap = $this->getById($scrapId);
        if (!$scrap) {
            throw new Exception("Scrap not found");
        }
        
        // Create note
        $noteManager = new NoteManager();
        $noteData['content'] = $noteData['content'] ?? $scrap['content'];
        $note = $noteManager->create($noteData);
        
        // Mark scrap as processed
        $stmt = $this->db->prepare("UPDATE scraps SET processed = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$scrapId]);
        
        return $note;
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM scraps WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getUnprocessed() {
        return $this->getAll(['processed' => false]);
    }
}

/**
 * Settings Manager
 */
class SettingsManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }
    
    public function get($key) {
        $stmt = $this->db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn();
    }
    
    public function set($key, $value) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
        return $stmt->execute([$key, $value]);
    }
    
    public function getAll() {
        $stmt = $this->db->prepare("SELECT key, value FROM settings");
        $stmt->execute();
        $settings = [];
        
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }
        
        return $settings;
    }
}

/**
 * Router and API Handler
 */
class Router {
    private $projects;
    private $tasks;
    private $notes;
    private $scraps;
    private $settings;
    private $gemini;
    
    public function __construct() {
        Auth::init();
        
        $this->projects = new ProjectManager();
        $this->tasks = new TaskManager();
        $this->notes = new NoteManager();
        $this->scraps = new ScrapManager();
        $this->settings = new SettingsManager();
        $this->gemini = new GeminiAI();
    }
    
    public function handleRequest() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Remove base path if needed
        $uri = preg_replace('#^/index\.php#', '', $uri);
        
        // API routes
        if (strpos($uri, '/api/') === 0) {
            Auth::requireAuth();
            $this->handleApiRequest($uri, $method);
            return;
        }
        
        // Web interface
        $this->handleWebRequest($uri);
    }
    
    private function handleApiRequest($uri, $method) {
        header('Content-Type: application/json');
        
        try {
            // Projects endpoints
            if (preg_match('#^/api/projects/?$#', $uri)) {
                if ($method === 'GET') {
                    $filters = $_GET;
                    echo json_encode($this->projects->getAll($filters));
                } elseif ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($this->projects->create($data));
                }
            } elseif (preg_match('#^/api/projects/(\d+)/?$#', $uri, $matches)) {
                $id = $matches[1];
                if ($method === 'GET') {
                    $project = $this->projects->getById($id);
                    echo json_encode($project ?: ['error' => 'Project not found']);
                } elseif ($method === 'PUT') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($this->projects->update($id, $data));
                } elseif ($method === 'DELETE') {
                    $result = $this->projects->delete($id);
                    echo json_encode(['success' => $result]);
                }
            }
            
            // Tasks endpoints
            elseif (preg_match('#^/api/tasks/?$#', $uri)) {
                if ($method === 'GET') {
                    $filters = $_GET;
                    echo json_encode($this->tasks->getAll($filters));
                } elseif ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($this->tasks->create($data));
                }
            } elseif (preg_match('#^/api/tasks/(\d+)/?$#', $uri, $matches)) {
                $id = $matches[1];
                if ($method === 'GET') {
                    $task = $this->tasks->getById($id);
                    echo json_encode($task ?: ['error' => 'Task not found']);
                } elseif ($method === 'PUT') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($this->tasks->update($id, $data));
                } elseif ($method === 'DELETE') {
                    $result = $this->tasks->delete($id);
                    echo json_encode(['success' => $result]);
                }
            }
            
            // Notes endpoints
            elseif (preg_match('#^/api/notes/?$#', $uri)) {
                if ($method === 'GET') {
                    $filters = $_GET;
                    echo json_encode($this->notes->getAll($filters));
                } elseif ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($this->notes->create($data));
                }
            } elseif (preg_match('#^/api/notes/(\d+)/?$#', $uri, $matches)) {
                $id = $matches[1];
                if ($method === 'GET') {
                    $note = $this->notes->getById($id);
                    echo json_encode($note ?: ['error' => 'Note not found']);
                } elseif ($method === 'PUT') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($this->notes->update($id, $data));
                } elseif ($method === 'DELETE') {
                    $result = $this->notes->delete($id);
                    echo json_encode(['success' => $result]);
                }
            }
            
            // Scraps endpoints
            elseif (preg_match('#^/api/scraps/?$#', $uri)) {
                if ($method === 'GET') {
                    $filters = $_GET;
                    echo json_encode($this->scraps->getAll($filters));
                } elseif ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo json_encode($this->scraps->create($data));
                }
            } elseif (preg_match('#^/api/scraps/(\d+)/convert/?$#', $uri, $matches)) {
                if ($method === 'POST') {
                    $scrapId = $matches[1];
                    $data = json_decode(file_get_contents('php://input'), true);
                    
                    if ($data['to'] === 'task') {
                        $result = $this->scraps->convertToTask($scrapId, $data['data']);
                    } elseif ($data['to'] === 'note') {
                        $result = $this->scraps->convertToNote($scrapId, $data['data']);
                    } else {
                        throw new Exception('Invalid conversion type');
                    }
                    
                    echo json_encode($result);
                }
            }
            
            // Chat endpoint
            elseif ($uri === '/api/chat' && $method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $message = $data['message'] ?? '';
                
                // Build context for AI
                $context = [
                    'recent_tasks' => $this->tasks->getAll(['limit' => 5]),
                    'projects' => $this->projects->getAll(['status' => 'active']),
                    'unprocessed_scraps' => $this->scraps->getUnprocessed()
                ];
                
                $response = $this->gemini->chat($message, $context);
                
                // Process any actions returned by AI
                if ($response['success'] && !empty($response['actions'])) {
                    $actionResults = [];
                    foreach ($response['actions'] as $action) {
                        $actionResults[] = $this->processAction($action);
                    }
                    $response['action_results'] = $actionResults;
                }
                
                echo json_encode($response);
            }
            
            // Chat history endpoint
            elseif ($uri === '/api/chat/history' && $method === 'GET') {
                $limit = $_GET['limit'] ?? 10;
                echo json_encode($this->gemini->getRecentChatHistory($limit));
            }
            
            // Plan endpoints
            elseif ($uri === '/api/plan' && $method === 'GET') {
                echo json_encode($this->tasks->getTodaysPlan());
            } elseif (preg_match('#^/api/plan/(\d{4}-\d{2}-\d{2})/?$#', $uri, $matches)) {
                $date = $matches[1];
                $tasks = $this->tasks->getAll(['due_date' => $date]);
                echo json_encode(['date' => $date, 'tasks' => $tasks]);
            }
            
            // Settings endpoints
            elseif ($uri === '/api/settings' && $method === 'GET') {
                echo json_encode($this->settings->getAll());
            } elseif ($uri === '/api/settings' && $method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                foreach ($data as $key => $value) {
                    $this->settings->set($key, $value);
                }
                echo json_encode(['success' => true]);
            }
            
            else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function processAction($action) {
        try {
            switch ($action['type']) {
                case 'CREATE_TASK':
                    return $this->tasks->create($action['data']);
                    
                case 'CREATE_PROJECT':
                    return $this->projects->create($action['data']);
                    
                case 'CREATE_NOTE':
                    return $this->notes->create($action['data']);
                    
                case 'CREATE_SCRAP':
                    return $this->scraps->create($action['data']);
                    
                case 'UPDATE_TASK':
                    return $this->tasks->update($action['data']['id'], $action['data']['updates']);
                    
                case 'CONVERT_SCRAP':
                    $scrapId = $action['data']['scrap_id'];
                    $to = $action['data']['to'];
                    $data = $action['data']['data'];
                    
                    if ($to === 'task') {
                        return $this->scraps->convertToTask($scrapId, $data);
                    } elseif ($to === 'note') {
                        return $this->scraps->convertToNote($scrapId, $data);
                    }
                    break;
                    
                default:
                    throw new Exception("Unknown action type: " . $action['type']);
            }
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function handleWebRequest($uri) {
        // Serve the mobile-first web interface
        if ($uri === '/' || $uri === '') {
            $this->renderWebApp();
        } else {
            http_response_code(404);
            echo "Not Found";
        }
    }
    
    private function renderWebApp() {
        $settings = $this->settings->getAll();
        $geminiConfigured = !empty($settings['gemini_api_key']);
        $appName = $settings['app_name'] ?? 'TaskFlow AI';
        
        // Get API key for frontend
        $defaultApiKey = $settings['default_api_key'] ?? '';
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($appName) . '</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8fafc;
            color: #1f2937;
            min-height: 100vh;
            padding-bottom: 120px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .header h1 {
            color: #3b82f6;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #6b7280;
            font-size: 14px;
        }
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            color: #6b7280;
        }
        
        .tab.active {
            background: #3b82f6;
            color: white;
        }
        
        .view {
            display: none;
        }
        
        .view.active {
            display: block;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .task-item, .project-item, .note-item, .scrap-item {
            padding: 16px;
            background: white;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .task-item.high {
            border-left-color: #ef4444;
        }
        
        .task-item.medium {
            border-left-color: #f59e0b;
        }
        
        .task-item.low {
            border-left-color: #10b981;
        }
        
        .task-item.completed {
            opacity: 0.6;
            text-decoration: line-through;
        }
        
        .scrap-item {
            border-left: 4px solid #8b5cf6;
        }
        
        .scrap-item.completed {
            opacity: 0.6;
            border-left-color: #10b981;
        }
        
        .convert-btn {
            background: #8b5cf6;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            margin-left: 8px;
        }
        
        .convert-btn:hover {
            background: #7c3aed;
        }
        
        .item-title {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .item-meta {
            font-size: 12px;
            color: #6b7280;
        }
        
        .chat-fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: #1a73e8;
            border-radius: 50%;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(26, 115, 232, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .chat-fab:hover {
            background: #1557b0;
            transform: scale(1.1);
        }
        
        .chat-sidebar {
            position: fixed;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .chat-sidebar.open {
            opacity: 1;
            visibility: visible;
        }
        
        .chat-panel {
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: white;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .chat-sidebar.open .chat-panel {
            transform: translateX(0);
        }
        
        .chat-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
        }
        
        .chat-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .chat-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chat-close:hover {
            background: #e5e7eb;
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .chat-input-container {
            padding: 20px;
            border-top: 1px solid #e5e7eb;
            background: white;
            display: flex;
            gap: 12px;
        }
        
        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 24px;
            font-size: 16px;
            resize: none;
            min-height: 44px;
            max-height: 120px;
        }
        
        .chat-input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        .send-btn {
            padding: 12px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 24px;
            font-weight: 600;
            cursor: pointer;
            min-height: 44px;
            transition: background 0.2s;
        }
        
        .send-btn:hover {
            background: #2563eb;
        }
        
        .send-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        
        .chat-history {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
            padding: 0 20px;
        }
        
        .chat-message {
            margin-bottom: 16px;
        }
        
        .chat-message.user {
            text-align: right;
        }
        
        .chat-message.ai {
            text-align: left;
        }
        
        .message-bubble {
            display: inline-block;
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        
        .user .message-bubble {
            background: #3b82f6;
            color: white;
        }
        
        .ai .message-bubble {
            background: #f3f4f6;
            color: #1f2937;
        }
        
        .empty-state {
            text-align: center;
            color: #6b7280;
            padding: 40px 20px;
        }
        
        .settings-form {
            display: grid;
            gap: 16px;
        }
        
        .form-group {
            display: grid;
            gap: 4px;
        }
        
        .form-label {
            font-weight: 600;
            color: #374151;
        }
        
        .form-input {
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        .btn-primary {
            padding: 12px 24px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        /* Desktop responsive design */
        @media (min-width: 768px) {
            .chat-panel {
                width: 400px;
            }
            
            .container {
                padding-bottom: 20px;
            }
        }
        
        @media (max-width: 640px) {
            .container {
                padding: 10px;
                padding-bottom: 100px;
            }
            
            .tabs {
                font-size: 14px;
            }
            
            .chat-fab {
                bottom: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($appName) . '</h1>
            <p>AI-powered task management through natural conversation</p>
        </div>
        
        <div class="tabs">
            <div class="tab active" data-view="today">Today</div>
            <div class="tab" data-view="scraps">Scraps</div>
            <div class="tab" data-view="tasks">Tasks</div>
            <div class="tab" data-view="projects">Projects</div>
            <div class="tab" data-view="notes">Notes</div>
            <div class="tab" data-view="settings">Settings</div>
        </div>
        
        <div id="today-view" class="view active">
            <div class="card">
                <h2>Today\'s Plan</h2>
                <div id="today-content">
                    <div class="empty-state">Loading today\'s tasks...</div>
                </div>
            </div>
        </div>
        
        <div id="scraps-view" class="view">
            <div class="card">
                <h2>Scraps</h2>
                <p style="color: #6b7280; font-size: 14px; margin-bottom: 20px;">Quick thoughts and ideas - convert to tasks or notes later</p>
                <div id="scraps-content">
                    <div class="empty-state">Loading scraps...</div>
                </div>
            </div>
        </div>
        
        <div id="tasks-view" class="view">
            <div class="card">
                <h2>All Tasks</h2>
                <div id="tasks-content">
                    <div class="empty-state">Loading tasks...</div>
                </div>
            </div>
        </div>
        
        <div id="projects-view" class="view">
            <div class="card">
                <h2>Projects</h2>
                <div id="projects-content">
                    <div class="empty-state">Loading projects...</div>
                </div>
            </div>
        </div>
        
        <div id="notes-view" class="view">
            <div class="card">
                <h2>Notes</h2>
                <div id="notes-content">
                    <div class="empty-state">Loading notes...</div>
                </div>
            </div>
        </div>
        
        <div id="settings-view" class="view">
            <div class="card">
                <h2>Settings</h2>
                <form id="settings-form" class="settings-form">
                    <div class="form-group">
                        <label class="form-label">Google Gemini API Key</label>
                        <input type="password" id="gemini-api-key" class="form-input" placeholder="Enter your Gemini API key">
                        <small style="color: #6b7280;">Required for AI chat functionality</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">App Name</label>
                        <input type="text" id="app-name" class="form-input" value="' . htmlspecialchars($appName) . '">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">API Key for External Access</label>
                        <input type="text" class="form-input" value="' . htmlspecialchars($defaultApiKey) . '" readonly>
                        <small style="color: #6b7280;">Use this key for API access</small>
                    </div>
                    
                    <button type="submit" class="btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
        
    </div>
    
    <!-- Floating Action Button -->
    <button class="chat-fab" id="chat-fab">💬</button>
    
    <!-- Chat Sidebar -->
    <div class="chat-sidebar" id="chat-sidebar">
        <div class="chat-panel">
            <div class="chat-header">
                <div class="chat-title">AI Assistant</div>
                <button class="chat-close" id="chat-close">×</button>
            </div>
            <div class="chat-messages" id="chat-history">
                <div style="text-align: center; color: #6b7280; padding: 20px;">
                    👋 Hi! I am your AI assistant. I can help you create tasks, notes, and manage your projects through natural conversation.
                </div>
            </div>
            <div class="chat-input-container">
                <textarea id="chat-input" class="chat-input" placeholder="Ask me anything about your tasks, projects, or notes..." rows="1"></textarea>
                <button id="send-btn" class="send-btn">Send</button>
            </div>
        </div>
    </div>
    
    <script>
        const API_KEY = "' . htmlspecialchars($defaultApiKey) . '";
        const API_BASE = "/api";
        
        let currentView = "today";
        let chatHistory = [];
        
        // API helper
        async function apiCall(endpoint, options = {}) {
            const config = {
                headers: {
                    "Authorization": `Bearer ${API_KEY}`,
                    "Content-Type": "application/json",
                    ...options.headers
                },
                ...options
            };
            
            const response = await fetch(`${API_BASE}${endpoint}`, config);
            
            if (!response.ok) {
                throw new Error(`API Error: ${response.status}`);
            }
            
            return response.json();
        }
        
        // Tab switching
        document.querySelectorAll(".tab").forEach(tab => {
            tab.addEventListener("click", () => {
                const view = tab.dataset.view;
                switchView(view);
            });
        });
        
        function switchView(view) {
            // Update tabs
            document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
            document.querySelector(`[data-view="${view}"]`).classList.add("active");
            
            // Update views
            document.querySelectorAll(".view").forEach(v => v.classList.remove("active"));
            document.getElementById(`${view}-view`).classList.add("active");
            
            currentView = view;
            loadViewData(view);
        }
        
        // Load data for specific view
        async function loadViewData(view) {
            try {
                switch (view) {
                    case "today":
                        const plan = await apiCall("/plan");
                        renderTodaysPlan(plan);
                        break;
                    case "scraps":
                        const scraps = await apiCall("/scraps");
                        renderScraps(scraps);
                        break;
                    case "tasks":
                        const tasks = await apiCall("/tasks");
                        renderTasks(tasks);
                        break;
                    case "projects":
                        const projects = await apiCall("/projects");
                        renderProjects(projects);
                        break;
                    case "notes":
                        const notes = await apiCall("/notes");
                        renderNotes(notes);
                        break;
                    case "settings":
                        const settings = await apiCall("/settings");
                        loadSettings(settings);
                        break;
                }
            } catch (error) {
                console.error("Error loading view data:", error);
                document.getElementById(`${view}-content`).innerHTML = `
                    <div class="empty-state">Error loading data: ${error.message}</div>
                `;
            }
        }
        
        // Render functions
        function renderTodaysPlan(plan) {
            const container = document.getElementById("today-content");
            let html = "";
            
            if (plan.overdue && plan.overdue.length > 0) {
                html += "<h3 style=\"color: #ef4444; margin: 16px 0 8px 0;\">Overdue</h3>";
                plan.overdue.forEach(task => {
                    html += renderTaskItem(task);
                });
            }
            
            if (plan.today && plan.today.length > 0) {
                html += "<h3 style=\"margin: 16px 0 8px 0;\">Due Today</h3>";
                plan.today.forEach(task => {
                    html += renderTaskItem(task);
                });
            }
            
            if (!html) {
                html = `<div class="empty-state">No tasks due today. Great job!</div>`;
            }
            
            container.innerHTML = html;
        }
        
        function renderTasks(tasks) {
            const container = document.getElementById("tasks-content");
            
            if (tasks.length === 0) {
                container.innerHTML = `<div class="empty-state">No tasks yet. Create some through chat!</div>`;
                return;
            }
            
            let html = "";
            tasks.forEach(task => {
                html += renderTaskItem(task);
            });
            
            container.innerHTML = html;
        }
        
        function renderTaskItem(task) {
            const priorityClass = task.priority || "medium";
            const statusClass = task.status === "completed" ? "completed" : "";
            const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : "";
            const project = task.project_name ? `📁 ${task.project_name}` : "";
            
            return `
                <div class="task-item ${priorityClass} ${statusClass}">
                    <div class="item-title">${task.title}</div>
                    ${task.description ? `<div style="margin: 4px 0; color: #6b7280; font-size: 14px;">${task.description}</div>` : ""}
                    <div class="item-meta">
                        <span class="status-badge status-${task.status}">${task.status}</span>
                        ${dueDate ? `📅 ${dueDate}` : ""}
                        ${project}
                        ${task.priority ? `🔥 ${task.priority}` : ""}
                    </div>
                </div>
            `;
        }
        
        function renderProjects(projects) {
            const container = document.getElementById("projects-content");
            
            if (projects.length === 0) {
                container.innerHTML = `<div class="empty-state">No projects yet. Create some through chat!</div>`;
                return;
            }
            
            let html = "";
            projects.forEach(project => {
                html += `
                    <div class="project-item">
                        <div class="item-title">${project.name}</div>
                        ${project.description ? `<div style="margin: 4px 0; color: #6b7280; font-size: 14px;">${project.description}</div>` : ""}
                        <div class="item-meta">
                            <span class="status-badge status-${project.status}">${project.status}</span>
                            ${project.area ? `🏷️ ${project.area}` : ""}
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function renderNotes(notes) {
            const container = document.getElementById("notes-content");
            
            if (notes.length === 0) {
                container.innerHTML = `<div class="empty-state">No notes yet. Create some through chat!</div>`;
                return;
            }
            
            let html = "";
            notes.forEach(note => {
                const dateAssigned = note.date_assigned ? new Date(note.date_assigned).toLocaleDateString() : "";
                html += `
                    <div class="note-item">
                        <div class="item-title">${note.title}</div>
                        <div style="margin: 8px 0; color: #374151; font-size: 14px; line-height: 1.4;">${note.content.substring(0, 200)}${note.content.length > 200 ? "..." : ""}</div>
                        <div class="item-meta">
                            ${dateAssigned ? `📅 ${dateAssigned}` : ""}
                            ${note.area ? `🏷️ ${note.area}` : ""}
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function renderScraps(scraps) {
            const container = document.getElementById("scraps-content");
            
            if (scraps.length === 0) {
                container.innerHTML = `<div class="empty-state">No scraps yet. Add quick thoughts through chat!</div>`;
                return;
            }
            
            let html = "";
            scraps.forEach(scrap => {
                const dateAssigned = scrap.date_assigned ? new Date(scrap.date_assigned).toLocaleDateString() : "";
                const processedClass = scrap.processed ? "completed" : "";
                const processedText = scrap.processed ? "✓ Processed" : "📝 Raw";
                
                html += `
                    <div class="scrap-item ${processedClass}">
                        <div style="margin-bottom: 8px; color: #374151; font-size: 14px; line-height: 1.4;">${scrap.content}</div>
                        <div class="item-meta">
                            <span class="status ${processedClass}">${processedText}</span>
                            ${dateAssigned ? `📅 ${dateAssigned}` : ""}
                            <button onclick="convertScrap(${scrap.id})" class="convert-btn" ${scrap.processed ? "style=\"display:none\"" : ""}>Convert</button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        async function convertScrap(scrapId) {
            const message = `Convert scrap ${scrapId} to a task or note`;
            chatInput.value = message;
            sendMessage();
        }
        
        function loadSettings(settings) {
            document.getElementById("gemini-api-key").value = settings.gemini_api_key || "";
            document.getElementById("app-name").value = settings.app_name || "";
        }
        
        // Chat functionality
        const chatInput = document.getElementById("chat-input");
        const sendBtn = document.getElementById("send-btn");
        const chatHistoryEl = document.getElementById("chat-history");
        
        // Chat sidebar controls
        const chatFab = document.getElementById("chat-fab");
        const chatSidebar = document.getElementById("chat-sidebar");
        const chatClose = document.getElementById("chat-close");
        
        // Open chat sidebar
        chatFab.addEventListener("click", () => {
            chatSidebar.classList.add("open");
            setTimeout(() => chatInput.focus(), 300);
        });
        
        // Close chat sidebar
        chatClose.addEventListener("click", closeChatSidebar);
        chatSidebar.addEventListener("click", (e) => {
            if (e.target === chatSidebar) {
                closeChatSidebar();
            }
        });
        
        function closeChatSidebar() {
            chatSidebar.classList.remove("open");
        }
        
        // ESC key to close sidebar
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && chatSidebar.classList.contains("open")) {
                closeChatSidebar();
            }
        });
        
        chatInput.addEventListener("input", () => {
            chatInput.style.height = "auto";
            chatInput.style.height = chatInput.scrollHeight + "px";
        });
        
        chatInput.addEventListener("keydown", (e) => {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        sendBtn.addEventListener("click", sendMessage);
        
        async function sendMessage() {
            const message = chatInput.value.trim();
            if (!message) return;
            
            chatInput.value = "";
            chatInput.style.height = "auto";
            
            sendBtn.disabled = true;
            sendBtn.textContent = "Sending...";
            
            // Add user message to chat
            addChatMessage(message, "user");
            
            try {
                const response = await apiCall("/chat", {
                    method: "POST",
                    body: JSON.stringify({ message })
                });
                
                // Clean up the response by removing action syntax
                let cleanResponse = response.response;
                cleanResponse = cleanResponse.replace(/\[ACTION:[A-Z_]+\][^]*/g, "").trim();
                
                addChatMessage(cleanResponse, "ai");
                
                // Refresh current view if actions were performed
                if (response.action_results && response.action_results.length > 0) {
                    setTimeout(() => loadViewData(currentView), 500);
                }
                
            } catch (error) {
                addChatMessage(`Sorry, I encountered an error: ${error.message}`, "ai");
            }
            
            sendBtn.disabled = false;
            sendBtn.textContent = "Send";
        }
        
        function addChatMessage(message, sender) {
            const messageEl = document.createElement("div");
            messageEl.className = `chat-message ${sender}`;
            messageEl.innerHTML = `
                <div class="message-bubble">${message}</div>
            `;
            
            chatHistoryEl.appendChild(messageEl);
            chatHistoryEl.scrollTop = chatHistoryEl.scrollHeight;
        }
        
        // Settings form
        document.getElementById("settings-form").addEventListener("submit", async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const settings = {
                gemini_api_key: document.getElementById("gemini-api-key").value,
                app_name: document.getElementById("app-name").value
            };
            
            try {
                await apiCall("/settings", {
                    method: "POST",
                    body: JSON.stringify(settings)
                });
                
                alert("Settings saved successfully!");
                
            } catch (error) {
                alert(`Error saving settings: ${error.message}`);
            }
        });
        
        // Initialize app
        loadViewData("today");
        
        // Focus chat input for mobile
        setTimeout(() => chatInput.focus(), 100);
    </script>
</body>
</html>';
    }
}

// Initialize and run the application
try {
    $router = new Router();
    $router->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        echo "Application Error: " . $e->getMessage();
    }
}
?>