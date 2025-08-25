# TaskFlow AI - Phase 2 Architecture Design Specifications

## 1. PHP Single-File Routing Architecture

### Routing Structure for index.php

The application uses URL path-based routing with a simple dispatcher pattern:

```php
<?php
// Router configuration
$routes = [
    'GET' => [
        '/' => 'showDashboard',
        '/projects' => 'showProjects', 
        '/tasks' => 'showTasks',
        '/notes' => 'showNotes',
        '/plan' => 'showPlan',
        '/scraps' => 'showScraps',
        '/settings' => 'showSettings',
        '/api/docs' => 'showApiDocs'
    ],
    'POST' => [
        '/api/chat' => 'handleChat',
        '/api/projects' => 'createProject',
        '/api/tasks' => 'createTask',
        '/api/notes' => 'createNote',
        '/api/scraps' => 'createScrap'
    ],
    'PUT' => [
        '/api/projects/{id}' => 'updateProject',
        '/api/tasks/{id}' => 'updateTask',
        '/api/notes/{id}' => 'updateNote'
    ],
    'DELETE' => [
        '/api/projects/{id}' => 'deleteProject',
        '/api/tasks/{id}' => 'deleteTask',
        '/api/notes/{id}' => 'deleteNote'
    ]
];

// Main routing logic
function route($method, $path) {
    global $routes;
    
    // Extract path parameters
    if (preg_match('/\/api\/\w+\/(\d+)/', $path, $matches)) {
        $_REQUEST['id'] = $matches[1];
        $path = preg_replace('/\/\d+/', '/{id}', $path);
    }
    
    if (isset($routes[$method][$path])) {
        return call_user_func($routes[$method][$path]);
    }
    
    return show404();
}
```

### Application Structure

```php
<?php
// TaskFlow AI - Single File Application
// =================================

// Configuration and Constants
define('DB_FILE', 'taskflow.db');
define('API_VERSION', 'v1');

// Database Connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        initDatabase($pdo);
    }
    return $pdo;
}

// Authentication Middleware
function authenticate() {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? '';
    
    if (!preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
        return false;
    }
    
    $token = $matches[1];
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM api_keys WHERE key_hash = ? AND active = 1");
    $stmt->execute([hash('sha256', $token)]);
    
    return $stmt->fetch() !== false;
}

// Response Helpers
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Main Application Entry Point
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Handle API routes
if (strpos($path, '/api/') === 0 && $method !== 'GET') {
    if (!authenticate()) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
}

// Route the request
route($method, $path);
```

## 2. Mobile-First Chat Interface Design

### CSS Grid/Flexbox Layout Structure

```css
/* Mobile-First Chat Interface Layout */

/* App Container */
.app-container {
    display: grid;
    grid-template-rows: 60px 1fr 80px;
    height: 100vh;
    max-width: 100vw;
    overflow: hidden;
}

/* Header with Navigation */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 16px;
    background: var(--primary-color);
    color: white;
    position: sticky;
    top: 0;
    z-index: 100;
}

.nav-tabs {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.nav-tabs::-webkit-scrollbar {
    display: none;
}

.nav-tab {
    padding: 8px 16px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    text-decoration: none;
    white-space: nowrap;
    min-height: 44px; /* Touch target */
    display: flex;
    align-items: center;
}

/* Main Content Area */
.main-content {
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    padding: 16px;
    position: relative;
}

/* Chat Interface */
.chat-container {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: white;
    border-top: 1px solid #e0e0e0;
    padding: 12px;
    display: grid;
    grid-template-columns: 1fr 60px;
    gap: 12px;
    align-items: end;
}

.chat-input {
    min-height: 44px;
    max-height: 120px;
    border: 1px solid #ddd;
    border-radius: 24px;
    padding: 12px 16px;
    resize: none;
    font-size: 16px; /* Prevent zoom on iOS */
    line-height: 1.4;
    overflow-y: auto;
}

.chat-send-btn {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: none;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    cursor: pointer;
}

/* Chat History Overlay */
.chat-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 2000;
    display: none;
}

.chat-history {
    position: absolute;
    bottom: 92px; /* Above chat input */
    left: 12px;
    right: 12px;
    max-height: 60vh;
    background: white;
    border-radius: 12px;
    padding: 16px;
    overflow-y: auto;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
}

.chat-message {
    margin-bottom: 16px;
    padding: 12px;
    border-radius: 12px;
    max-width: 85%;
}

.chat-message.user {
    background: var(--primary-color);
    color: white;
    margin-left: auto;
    text-align: right;
}

.chat-message.ai {
    background: #f5f5f5;
    color: #333;
}

/* Entity Lists */
.entity-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.entity-card {
    background: white;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border-left: 4px solid var(--accent-color);
}

/* Touch Optimizations */
.touch-target {
    min-height: 44px;
    min-width: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Responsive Breakpoints */
@media (min-width: 768px) {
    .app-container {
        max-width: 1200px;
        margin: 0 auto;
        grid-template-columns: 300px 1fr;
        grid-template-rows: 60px 1fr 80px;
        grid-template-areas: 
            "sidebar header"
            "sidebar main"
            "sidebar chat";
    }
    
    .sidebar {
        grid-area: sidebar;
        background: #f8f9fa;
        border-right: 1px solid #e0e0e0;
    }
    
    .header {
        grid-area: header;
    }
    
    .main-content {
        grid-area: main;
    }
    
    .chat-container {
        grid-area: chat;
        position: relative;
        border-top: 1px solid #e0e0e0;
    }
}
```

### Mobile Interaction Patterns

```javascript
// Chat Interface JavaScript
class ChatInterface {
    constructor() {
        this.chatInput = document.querySelector('.chat-input');
        this.sendBtn = document.querySelector('.chat-send-btn');
        this.chatHistory = document.querySelector('.chat-history');
        this.overlay = document.querySelector('.chat-overlay');
        
        this.initEventListeners();
        this.setupAutoResize();
    }
    
    initEventListeners() {
        // Send message on button click
        this.sendBtn.addEventListener('click', () => this.sendMessage());
        
        // Send on Enter, new line on Shift+Enter
        this.chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Show chat history on input focus
        this.chatInput.addEventListener('focus', () => this.showChatHistory());
        
        // Hide chat history when clicking outside
        this.overlay.addEventListener('click', () => this.hideChatHistory());
        
        // Handle swipe gestures
        this.setupSwipeNavigation();
    }
    
    setupAutoResize() {
        this.chatInput.addEventListener('input', () => {
            this.chatInput.style.height = 'auto';
            this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 120) + 'px';
        });
    }
    
    async sendMessage() {
        const message = this.chatInput.value.trim();
        if (!message) return;
        
        // Clear input and show loading
        this.chatInput.value = '';
        this.showLoading();
        
        try {
            const response = await fetch('/api/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.getApiKey()}`
                },
                body: JSON.stringify({ message })
            });
            
            const data = await response.json();
            this.displayMessage(message, 'user');
            this.displayMessage(data.response, 'ai');
            
            // Handle any actions from AI response
            if (data.actions) {
                this.handleActions(data.actions);
            }
            
        } catch (error) {
            this.displayError('Failed to send message. Please try again.');
        } finally {
            this.hideLoading();
        }
    }
    
    setupSwipeNavigation() {
        let startX = 0;
        let startY = 0;
        
        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        });
        
        document.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            
            const deltaX = endX - startX;
            const deltaY = endY - startY;
            
            // Horizontal swipe for navigation
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
                if (deltaX > 0) {
                    this.navigateBack();
                } else {
                    this.navigateForward();
                }
            }
        });
    }
}
```

## 3. SQLite Database Schema

### Complete Database Schema

```sql
-- TaskFlow AI Database Schema
-- ===========================

-- Core entity tables
CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'completed', 'archived')),
    tags TEXT, -- JSON array of tags
    area TEXT, -- Life area (work, personal, etc)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER,
    title TEXT NOT NULL,
    description TEXT,
    due_date DATE,
    priority TEXT DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'completed', 'cancelled')),
    tags TEXT, -- JSON array of tags
    area TEXT,
    estimated_minutes INTEGER,
    actual_minutes INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    date_assigned DATE, -- For daily planning
    date_range_end DATE, -- For multi-day planning
    tags TEXT, -- JSON array of tags
    area TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS scraps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    content TEXT NOT NULL,
    date_assigned DATE, -- Optional date context
    date_range_end DATE, -- For date ranges
    processed BOOLEAN DEFAULT FALSE, -- Converted to task/note
    converted_to_type TEXT, -- 'task' or 'note'
    converted_to_id INTEGER, -- ID of created task/note
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME
);

-- System and configuration tables
CREATE TABLE IF NOT EXISTS chat_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message TEXT NOT NULL,
    response TEXT NOT NULL,
    context TEXT, -- JSON context for conversation continuity
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    tokens_used INTEGER,
    response_time_ms INTEGER
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    key_hash TEXT NOT NULL UNIQUE, -- SHA-256 hash of the key
    permissions TEXT DEFAULT 'read,write', -- JSON array or comma-separated
    active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME,
    usage_count INTEGER DEFAULT 0
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_tasks_due_date ON tasks(due_date);
CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status);
CREATE INDEX IF NOT EXISTS idx_tasks_project_id ON tasks(project_id);
CREATE INDEX IF NOT EXISTS idx_notes_date_assigned ON notes(date_assigned);
CREATE INDEX IF NOT EXISTS idx_scraps_processed ON scraps(processed);
CREATE INDEX IF NOT EXISTS idx_scraps_date_assigned ON scraps(date_assigned);
CREATE INDEX IF NOT EXISTS idx_chat_timestamp ON chat_history(timestamp);
CREATE INDEX IF NOT EXISTS idx_api_keys_hash ON api_keys(key_hash);

-- Full-text search indexes
CREATE VIRTUAL TABLE IF NOT EXISTS tasks_fts USING fts5(
    title, 
    description, 
    content='tasks', 
    content_rowid='id'
);

CREATE VIRTUAL TABLE IF NOT EXISTS notes_fts USING fts5(
    title, 
    content, 
    content='notes', 
    content_rowid='id'
);

-- Default settings
INSERT OR IGNORE INTO settings (key, value, description) VALUES
('gemini_api_key', '', 'Google Gemini API key for chat functionality'),
('morning_time', '09:00', 'Time to send morning scrap processing reminder'),
('ntfy_topic', '', 'ntfy.sh topic for notifications'),
('timezone', 'UTC', 'User timezone for date calculations'),
('theme', 'light', 'UI theme preference'),
('daily_goal_tasks', '5', 'Target number of tasks to complete daily'),
('week_start_day', 'monday', 'First day of week for planning views');

-- Triggers for maintaining updated_at timestamps
CREATE TRIGGER IF NOT EXISTS projects_updated_at 
    AFTER UPDATE ON projects
    BEGIN
        UPDATE projects SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

CREATE TRIGGER IF NOT EXISTS tasks_updated_at 
    AFTER UPDATE ON tasks
    BEGIN
        UPDATE tasks SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

CREATE TRIGGER IF NOT EXISTS notes_updated_at 
    AFTER UPDATE ON notes
    BEGIN
        UPDATE notes SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

-- Trigger to maintain FTS indexes
CREATE TRIGGER IF NOT EXISTS tasks_fts_insert AFTER INSERT ON tasks BEGIN
    INSERT INTO tasks_fts(rowid, title, description) VALUES (NEW.id, NEW.title, NEW.description);
END;

CREATE TRIGGER IF NOT EXISTS tasks_fts_update AFTER UPDATE ON tasks BEGIN
    UPDATE tasks_fts SET title = NEW.title, description = NEW.description WHERE rowid = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS tasks_fts_delete AFTER DELETE ON tasks BEGIN
    DELETE FROM tasks_fts WHERE rowid = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS notes_fts_insert AFTER INSERT ON notes BEGIN
    INSERT INTO notes_fts(rowid, title, content) VALUES (NEW.id, NEW.title, NEW.content);
END;

CREATE TRIGGER IF NOT EXISTS notes_fts_update AFTER UPDATE ON notes BEGIN
    UPDATE notes_fts SET title = NEW.title, content = NEW.content WHERE rowid = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS notes_fts_delete AFTER DELETE ON notes BEGIN
    DELETE FROM notes_fts WHERE rowid = OLD.id;
END;
```

### Entity Relationships

```
projects (1) ---> (many) tasks
                     |
                     v
scraps (many) ---> (1) tasks/notes (converted_to_type/id)
                     ^
                     |
notes (many) --------|

chat_history (independent)
settings (independent) 
api_keys (independent)
```

## 4. REST API Endpoint Structure

### Authentication Flow

```php
// API Authentication
function generateApiKey($name) {
    $key = bin2hex(random_bytes(32));
    $hash = hash('sha256', $key);
    
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO api_keys (name, key_hash) VALUES (?, ?)");
    $stmt->execute([$name, $hash]);
    
    return $key; // Return once, never stored in plain text
}

function validateApiKey($key) {
    $hash = hash('sha256', $key);
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE api_keys 
        SET last_used_at = CURRENT_TIMESTAMP, usage_count = usage_count + 1 
        WHERE key_hash = ? AND active = 1
    ");
    $stmt->execute([$hash]);
    
    return $stmt->rowCount() > 0;
}
```

### Complete API Endpoint Map

```php
// REST API Endpoints
// ==================

// Projects API
$routes['GET']['/api/projects'] = function() {
    $db = getDB();
    $stmt = $db->query("
        SELECT p.*, 
               COUNT(t.id) as task_count,
               COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks
        FROM projects p
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE p.status != 'archived'
        GROUP BY p.id
        ORDER BY p.updated_at DESC
    ");
    
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
};

$routes['POST']['/api/projects'] = function() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO projects (name, description, tags, area) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['name'],
        $data['description'] ?? '',
        json_encode($data['tags'] ?? []),
        $data['area'] ?? ''
    ]);
    
    $projectId = $db->lastInsertId();
    $project = $db->query("SELECT * FROM projects WHERE id = $projectId")->fetch(PDO::FETCH_ASSOC);
    
    jsonResponse($project, 201);
};

$routes['GET']['/api/projects/{id}'] = function() {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*,
               COUNT(t.id) as task_count,
               COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks
        FROM projects p
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE p.id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$_REQUEST['id']]);
    
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        jsonResponse(['error' => 'Project not found'], 404);
    }
    
    // Include tasks
    $stmt = $db->prepare("SELECT * FROM tasks WHERE project_id = ? ORDER BY due_date ASC, priority DESC");
    $stmt->execute([$_REQUEST['id']]);
    $project['tasks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($project);
};

// Tasks API
$routes['GET']['/api/tasks'] = function() {
    $db = getDB();
    $where = ['1=1'];
    $params = [];
    
    // Filtering
    if (isset($_GET['status'])) {
        $where[] = 'status = ?';
        $params[] = $_GET['status'];
    }
    
    if (isset($_GET['due_date'])) {
        $where[] = 'due_date = ?';
        $params[] = $_GET['due_date'];
    }
    
    if (isset($_GET['project_id'])) {
        $where[] = 'project_id = ?';
        $params[] = $_GET['project_id'];
    }
    
    if (isset($_GET['overdue'])) {
        $where[] = 'due_date < CURRENT_DATE AND status != "completed"';
    }
    
    // Search
    if (isset($_GET['search'])) {
        $where[] = 'id IN (SELECT rowid FROM tasks_fts WHERE tasks_fts MATCH ?)';
        $params[] = $_GET['search'];
    }
    
    $sql = "
        SELECT t.*, p.name as project_name 
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY 
            CASE WHEN due_date IS NULL THEN 1 ELSE 0 END,
            due_date ASC,
            CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END
        LIMIT " . (int)($_GET['limit'] ?? 50) . "
        OFFSET " . (int)($_GET['offset'] ?? 0);
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
};

// Chat API
$routes['POST']['/api/chat'] = function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $message = $data['message'] ?? '';
    
    if (empty($message)) {
        jsonResponse(['error' => 'Message is required'], 400);
    }
    
    $response = processAIChat($message);
    
    // Store in chat history
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO chat_history (message, response, context) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $message,
        $response['response'],
        json_encode($response['context'] ?? [])
    ]);
    
    jsonResponse($response);
};

// Plan API (Daily/Weekly Planning)
$routes['GET']['/api/plan'] = function() {
    $date = $_GET['date'] ?? date('Y-m-d');
    $db = getDB();
    
    // Get tasks due on this date
    $stmt = $db->prepare("
        SELECT t.*, p.name as project_name 
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE due_date = ? AND status != 'completed'
        ORDER BY priority DESC
    ");
    $stmt->execute([$date]);
    $dueTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get notes assigned to this date
    $stmt = $db->prepare("
        SELECT * FROM notes 
        WHERE (date_assigned = ? OR (date_assigned <= ? AND date_range_end >= ?))
        ORDER BY created_at ASC
    ");
    $stmt->execute([$date, $date, $date]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get scraps assigned to this date
    $stmt = $db->prepare("
        SELECT * FROM scraps 
        WHERE processed = FALSE AND (date_assigned = ? OR date_assigned IS NULL)
        ORDER BY created_at ASC
    ");
    $stmt->execute([$date]);
    $scraps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get overdue tasks
    $stmt = $db->prepare("
        SELECT t.*, p.name as project_name 
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE due_date < ? AND status != 'completed'
        ORDER BY due_date ASC
    ");
    $stmt->execute([$date]);
    $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'date' => $date,
        'due_tasks' => $dueTasks,
        'notes' => $notes,
        'scraps' => $scraps,
        'overdue_tasks' => $overdue,
        'summary' => [
            'due_count' => count($dueTasks),
            'overdue_count' => count($overdue),
            'notes_count' => count($notes),
            'scraps_count' => count($scraps)
        ]
    ]);
};

// Scrap Conversion API
$routes['POST']['/api/scraps/{id}/convert'] = function() {
    $data = json_decode(file_get_contents('php://input'), true);
    $scrapId = $_REQUEST['id'];
    $type = $data['type']; // 'task' or 'note'
    
    $db = getDB();
    
    // Get the scrap
    $stmt = $db->prepare("SELECT * FROM scraps WHERE id = ?");
    $stmt->execute([$scrapId]);
    $scrap = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scrap) {
        jsonResponse(['error' => 'Scrap not found'], 404);
    }
    
    $convertedId = null;
    
    if ($type === 'task') {
        $stmt = $db->prepare("
            INSERT INTO tasks (title, description, due_date, priority, tags, area)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['title'] ?? substr($scrap['content'], 0, 100),
            $scrap['content'],
            $data['due_date'] ?? null,
            $data['priority'] ?? 'medium',
            json_encode($data['tags'] ?? []),
            $data['area'] ?? ''
        ]);
        $convertedId = $db->lastInsertId();
        
    } else if ($type === 'note') {
        $stmt = $db->prepare("
            INSERT INTO notes (title, content, date_assigned, tags, area)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['title'] ?? substr($scrap['content'], 0, 100),
            $scrap['content'],
            $data['date_assigned'] ?? $scrap['date_assigned'],
            json_encode($data['tags'] ?? []),
            $data['area'] ?? ''
        ]);
        $convertedId = $db->lastInsertId();
    }
    
    // Mark scrap as processed
    $stmt = $db->prepare("
        UPDATE scraps 
        SET processed = TRUE, converted_to_type = ?, converted_to_id = ?, processed_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$type, $convertedId, $scrapId]);
    
    jsonResponse(['converted_id' => $convertedId, 'type' => $type]);
};

// Search API
$routes['GET']['/api/search'] = function() {
    $query = $_GET['q'] ?? '';
    if (empty($query)) {
        jsonResponse(['error' => 'Query parameter required'], 400);
    }
    
    $db = getDB();
    $results = ['tasks' => [], 'notes' => [], 'projects' => []];
    
    // Search tasks
    $stmt = $db->prepare("
        SELECT t.*, p.name as project_name 
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.id IN (SELECT rowid FROM tasks_fts WHERE tasks_fts MATCH ?)
        LIMIT 10
    ");
    $stmt->execute([$query]);
    $results['tasks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search notes
    $stmt = $db->prepare("
        SELECT * FROM notes 
        WHERE id IN (SELECT rowid FROM notes_fts WHERE notes_fts MATCH ?)
        LIMIT 10
    ");
    $stmt->execute([$query]);
    $results['notes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search projects (simple LIKE search)
    $stmt = $db->prepare("
        SELECT * FROM projects 
        WHERE name LIKE ? OR description LIKE ?
        LIMIT 10
    ");
    $stmt->execute(["%$query%", "%$query%"]);
    $results['projects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($results);
};
```

### Error Handling and Response Standards

```php
// Standardized API Response Format
function apiResponse($data = null, $error = null, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    
    $response = [
        'success' => $status < 400,
        'timestamp' => date('c'),
        'status' => $status
    ];
    
    if ($error) {
        $response['error'] = $error;
    }
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

// Global error handler for API routes
function handleApiError($exception) {
    $status = 500;
    $error = 'Internal server error';
    
    if ($exception instanceof InvalidArgumentException) {
        $status = 400;
        $error = $exception->getMessage();
    } else if ($exception instanceof UnauthorizedAccessException) {
        $status = 401;
        $error = 'Unauthorized access';
    }
    
    apiResponse(null, $error, $status);
}

set_exception_handler('handleApiError');
```

## 5. Implementation Priorities

### Phase 2A - Core Infrastructure (Week 1)
1. **Database Setup**: Implement complete SQLite schema with triggers
2. **Basic Routing**: Create URL routing system with API authentication
3. **Mobile Layout**: Build responsive CSS grid layout for mobile-first design
4. **Chat Interface**: Implement basic chat input/output UI components

### Phase 2B - API Foundation (Week 2)
1. **CRUD Operations**: Complete all entity CRUD endpoints
2. **Search & Filtering**: Implement full-text search and filtering
3. **Plan API**: Build daily planning and task aggregation endpoints
4. **Error Handling**: Robust error handling and response formatting

### Phase 2C - Mobile Optimization (Week 3)
1. **Touch Interactions**: Swipe navigation and touch-optimized controls
2. **Chat History**: Persistent chat overlay with conversation history
3. **Performance**: Optimize database queries and mobile rendering
4. **PWA Setup**: Service worker and app manifest for mobile installation

This architecture provides a solid foundation for the TaskFlow AI mobile-first chat-driven task management application, with clear separation between the single-file PHP backend, mobile-optimized frontend, and comprehensive REST API.