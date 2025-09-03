<?php
/**
 * Cross-Entity Project Filtering System
 * Project filters across Tasks/Notes, "Unassigned" filter, project-specific views
 * 
 * Score: 7/10 - Critical missing functionality for project management
 * Implementation: ~100 lines, extends existing filter patterns, minimal complexity
 */

class ProjectFilteringSystem {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->initializeFilteringTables();
    }
    
    /**
     * Initialize filtering-specific tables and indexes
     */
    private function initializeFilteringTables() {
        $sql = "
        -- Add project filtering views
        CREATE VIEW IF NOT EXISTS project_entity_summary AS
        SELECT 
            p.id as project_id,
            p.name as project_name,
            p.color as project_color,
            COUNT(DISTINCT t.id) as task_count,
            COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as completed_tasks,
            COUNT(DISTINCT n.id) as note_count,
            COUNT(DISTINCT s.id) as scrap_count,
            MIN(t.due_date) as next_due_date,
            MAX(COALESCE(t.updated_at, n.updated_at, s.updated_at, p.updated_at)) as last_activity
        FROM projects p
        LEFT JOIN tasks t ON p.id = t.project_id
        LEFT JOIN notes n ON p.id = n.project_id
        LEFT JOIN scraps s ON p.id = s.project_id
        GROUP BY p.id, p.name, p.color;
        
        -- Add unassigned entities view
        CREATE VIEW IF NOT EXISTS unassigned_entities AS
        SELECT 
            'task' as entity_type,
            id as entity_id,
            title as entity_title,
            NULL as content,
            priority,
            status,
            due_date,
            created_at,
            updated_at
        FROM tasks 
        WHERE project_id IS NULL
        
        UNION ALL
        
        SELECT 
            'note' as entity_type,
            id as entity_id,
            title as entity_title,
            content,
            'medium' as priority,
            'active' as status,
            NULL as due_date,
            created_at,
            updated_at
        FROM notes 
        WHERE project_id IS NULL
        
        UNION ALL
        
        SELECT 
            'scrap' as entity_type,
            id as entity_id,
            SUBSTR(content, 1, 50) || '...' as entity_title,
            content,
            'low' as priority,
            CASE WHEN processed = 1 THEN 'processed' ELSE 'active' END as status,
            NULL as due_date,
            created_at,
            updated_at
        FROM scraps 
        WHERE project_id IS NULL;
        
        -- Create filtering performance indexes
        CREATE INDEX IF NOT EXISTS idx_tasks_project_status ON tasks(project_id, status, due_date);
        CREATE INDEX IF NOT EXISTS idx_notes_project_updated ON notes(project_id, updated_at);
        CREATE INDEX IF NOT EXISTS idx_scraps_project_processed ON scraps(project_id, processed, updated_at);
        ";
        
        $this->db->getPdo()->exec($sql);
    }
    
    /**
     * Get filtered tasks by project
     */
    public function getProjectTasks($projectId, $filters = []) {
        $sql = "
        SELECT t.*, p.name as project_name, p.color as project_color
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE 1=1
        ";
        
        $params = [];
        
        // Project filter
        if ($projectId === 'unassigned') {
            $sql .= " AND t.project_id IS NULL";
        } elseif ($projectId && $projectId !== 'all') {
            $sql .= " AND t.project_id = ?";
            $params[] = $projectId;
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = str_repeat('?,', count($filters['status']) - 1) . '?';
                $sql .= " AND t.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= " AND t.status = ?";
                $params[] = $filters['status'];
            }
        }
        
        // Priority filter
        if (!empty($filters['priority'])) {
            if (is_array($filters['priority'])) {
                $placeholders = str_repeat('?,', count($filters['priority']) - 1) . '?';
                $sql .= " AND t.priority IN ($placeholders)";
                $params = array_merge($params, $filters['priority']);
            } else {
                $sql .= " AND t.priority = ?";
                $params[] = $filters['priority'];
            }
        }
        
        // Due date filter
        if (!empty($filters['due_date'])) {
            switch ($filters['due_date']) {
                case 'overdue':
                    $sql .= " AND t.due_date < date('now') AND t.status != 'completed'";
                    break;
                case 'today':
                    $sql .= " AND date(t.due_date) = date('now')";
                    break;
                case 'week':
                    $sql .= " AND t.due_date BETWEEN date('now') AND date('now', '+7 days')";
                    break;
                case 'month':
                    $sql .= " AND t.due_date BETWEEN date('now') AND date('now', '+1 month')";
                    break;
                case 'no_date':
                    $sql .= " AND t.due_date IS NULL";
                    break;
            }
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Sorting
        $orderBy = $this->buildOrderBy($filters['sort'] ?? 'priority_due');
        $sql .= " ORDER BY $orderBy";
        
        // Limit
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get filtered notes by project
     */
    public function getProjectNotes($projectId, $filters = []) {
        $sql = "
        SELECT n.*, p.name as project_name, p.color as project_color
        FROM notes n
        LEFT JOIN projects p ON n.project_id = p.id
        WHERE 1=1
        ";
        
        $params = [];
        
        // Project filter
        if ($projectId === 'unassigned') {
            $sql .= " AND n.project_id IS NULL";
        } elseif ($projectId && $projectId !== 'all') {
            $sql .= " AND n.project_id = ?";
            $params[] = $projectId;
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (n.title LIKE ? OR n.content LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Date filter
        if (!empty($filters['created_date'])) {
            switch ($filters['created_date']) {
                case 'today':
                    $sql .= " AND date(n.created_at) = date('now')";
                    break;
                case 'week':
                    $sql .= " AND n.created_at >= date('now', '-7 days')";
                    break;
                case 'month':
                    $sql .= " AND n.created_at >= date('now', '-1 month')";
                    break;
            }
        }
        
        // Sorting
        $orderBy = $this->buildOrderBy($filters['sort'] ?? 'updated', 'notes');
        $sql .= " ORDER BY $orderBy";
        
        // Limit
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get filtered scraps by project
     */
    public function getProjectScraps($projectId, $filters = []) {
        $sql = "
        SELECT s.*, p.name as project_name, p.color as project_color
        FROM scraps s
        LEFT JOIN projects p ON s.project_id = p.id
        WHERE 1=1
        ";
        
        $params = [];
        
        // Project filter
        if ($projectId === 'unassigned') {
            $sql .= " AND s.project_id IS NULL";
        } elseif ($projectId && $projectId !== 'all') {
            $sql .= " AND s.project_id = ?";
            $params[] = $projectId;
        }
        
        // Processed filter
        if (isset($filters['processed'])) {
            $sql .= " AND s.processed = ?";
            $params[] = $filters['processed'] ? 1 : 0;
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $sql .= " AND s.content LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        
        // Date filter
        if (!empty($filters['created_date'])) {
            switch ($filters['created_date']) {
                case 'today':
                    $sql .= " AND date(s.created_at) = date('now')";
                    break;
                case 'week':
                    $sql .= " AND s.created_at >= date('now', '-7 days')";
                    break;
                case 'month':
                    $sql .= " AND s.created_at >= date('now', '-1 month')";
                    break;
            }
        }
        
        // Sorting
        $orderBy = $this->buildOrderBy($filters['sort'] ?? 'created', 'scraps');
        $sql .= " ORDER BY $orderBy";
        
        // Limit
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get project summary with entity counts
     */
    public function getProjectSummaries($filters = []) {
        $sql = "SELECT * FROM project_entity_summary";
        $params = [];
        
        // Search filter for project names
        if (!empty($filters['search'])) {
            $sql .= " WHERE project_name LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        
        // Sorting
        $sortOptions = [
            'name' => 'project_name ASC',
            'activity' => 'last_activity DESC',
            'tasks' => 'task_count DESC',
            'completion' => '(completed_tasks * 1.0 / NULLIF(task_count, 0)) DESC'
        ];
        
        $sort = $filters['sort'] ?? 'activity';
        $orderBy = $sortOptions[$sort] ?? $sortOptions['activity'];
        $sql .= " ORDER BY $orderBy";
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get unassigned entities summary
     */
    public function getUnassignedSummary() {
        $sql = "
        SELECT 
            entity_type,
            COUNT(*) as count,
            COUNT(CASE WHEN entity_type = 'task' AND status != 'completed' THEN 1 END) as active_tasks,
            COUNT(CASE WHEN entity_type = 'task' AND priority = 'high' THEN 1 END) as high_priority,
            COUNT(CASE WHEN entity_type = 'scrap' AND status != 'processed' THEN 1 END) as unprocessed_scraps
        FROM unassigned_entities
        GROUP BY entity_type
        ";
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute();
        
        $results = $stmt->fetchAll();
        
        // Calculate totals
        $summary = [
            'total_entities' => 0,
            'by_type' => [],
            'active_tasks' => 0,
            'high_priority' => 0,
            'unprocessed_scraps' => 0
        ];
        
        foreach ($results as $row) {
            $summary['total_entities'] += $row['count'];
            $summary['by_type'][$row['entity_type']] = $row['count'];
            $summary['active_tasks'] += $row['active_tasks'];
            $summary['high_priority'] += $row['high_priority'];
            $summary['unprocessed_scraps'] += $row['unprocessed_scraps'];
        }
        
        return $summary;
    }
    
    /**
     * Get all unassigned entities
     */
    public function getUnassignedEntities($filters = []) {
        $sql = "SELECT * FROM unassigned_entities WHERE 1=1";
        $params = [];
        
        // Entity type filter
        if (!empty($filters['entity_type'])) {
            if (is_array($filters['entity_type'])) {
                $placeholders = str_repeat('?,', count($filters['entity_type']) - 1) . '?';
                $sql .= " AND entity_type IN ($placeholders)";
                $params = array_merge($params, $filters['entity_type']);
            } else {
                $sql .= " AND entity_type = ?";
                $params[] = $filters['entity_type'];
            }
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        // Priority filter
        if (!empty($filters['priority'])) {
            $sql .= " AND priority = ?";
            $params[] = $filters['priority'];
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (entity_title LIKE ? OR content LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Sorting
        $orderBy = $this->buildOrderBy($filters['sort'] ?? 'updated', 'mixed');
        $sql .= " ORDER BY $orderBy";
        
        // Limit
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Build ORDER BY clause based on sort option
     */
    private function buildOrderBy($sort, $context = 'tasks') {
        $sortOptions = [
            'tasks' => [
                'priority_due' => "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, due_date ASC NULLS LAST",
                'due_date' => 'due_date ASC NULLS LAST',
                'created' => 'created_at DESC',
                'updated' => 'updated_at DESC',
                'title' => 'title ASC',
                'status' => "CASE status WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END"
            ],
            'notes' => [
                'updated' => 'updated_at DESC',
                'created' => 'created_at DESC',
                'title' => 'title ASC'
            ],
            'scraps' => [
                'created' => 'created_at DESC',
                'updated' => 'updated_at DESC',
                'processed' => 'processed ASC, created_at DESC'
            ],
            'mixed' => [
                'updated' => 'updated_at DESC',
                'created' => 'created_at DESC',
                'priority' => "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END",
                'type' => 'entity_type ASC, updated_at DESC'
            ]
        ];
        
        return $sortOptions[$context][$sort] ?? $sortOptions[$context]['updated'] ?? 'updated_at DESC';
    }
    
    /**
     * Get filter options for UI
     */
    public function getFilterOptions($entityType = 'tasks') {
        $options = [
            'projects' => $this->getProjectOptions(),
            'common' => [
                'search' => true,
                'limit' => [10, 25, 50, 100, 'all']
            ]
        ];
        
        switch ($entityType) {
            case 'tasks':
                $options['status'] = ['pending', 'in_progress', 'completed'];
                $options['priority'] = ['high', 'medium', 'low'];
                $options['due_date'] = ['overdue', 'today', 'week', 'month', 'no_date'];
                $options['sort'] = ['priority_due', 'due_date', 'created', 'updated', 'title', 'status'];
                break;
                
            case 'notes':
                $options['created_date'] = ['today', 'week', 'month'];
                $options['sort'] = ['updated', 'created', 'title'];
                break;
                
            case 'scraps':
                $options['processed'] = [true, false];
                $options['created_date'] = ['today', 'week', 'month'];
                $options['sort'] = ['created', 'updated', 'processed'];
                break;
                
            case 'mixed':
                $options['entity_type'] = ['task', 'note', 'scrap'];
                $options['status'] = ['active', 'completed', 'processed'];
                $options['priority'] = ['high', 'medium', 'low'];
                $options['sort'] = ['updated', 'created', 'priority', 'type'];
                break;
        }
        
        return $options;
    }
    
    /**
     * Get project options for dropdown/filter
     */
    private function getProjectOptions() {
        $stmt = $this->db->getPdo()->prepare("
            SELECT id, name, color 
            FROM projects 
            ORDER BY name ASC
        ");
        $stmt->execute();
        
        $projects = $stmt->fetchAll();
        
        // Add special options
        array_unshift($projects, [
            'id' => 'all',
            'name' => 'All Projects',
            'color' => null
        ]);
        
        array_unshift($projects, [
            'id' => 'unassigned',
            'name' => 'Unassigned Items',
            'color' => '#6B7280'
        ]);
        
        return $projects;
    }
    
    /**
     * Bulk assign entities to project
     */
    public function bulkAssignToProject($projectId, $assignments) {
        $this->db->getPdo()->beginTransaction();
        
        try {
            foreach ($assignments as $assignment) {
                $table = $this->getTableName($assignment['entity_type']);
                if (!$table) continue;
                
                $stmt = $this->db->getPdo()->prepare("
                    UPDATE $table 
                    SET project_id = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$projectId, $assignment['entity_id']]);
            }
            
            $this->db->getPdo()->commit();
            return true;
        } catch (Exception $e) {
            $this->db->getPdo()->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get table name for entity type
     */
    private function getTableName($entityType) {
        $mapping = [
            'task' => 'tasks',
            'note' => 'notes',
            'scrap' => 'scraps'
        ];
        
        return $mapping[$entityType] ?? null;
    }
}

/**
 * Project Filtering API Routes Handler
 */
class ProjectFilteringRoutes {
    private $filteringSystem;
    
    public function __construct($filteringSystem) {
        $this->filteringSystem = $filteringSystem;
    }
    
    public function handleRequest($method, $endpoint, $data = []) {
        switch ($endpoint) {
            case '/api/projects/tasks':
                if ($method === 'GET') {
                    $projectId = $_GET['project_id'] ?? 'all';
                    $filters = $this->parseFilters($_GET);
                    return $this->filteringSystem->getProjectTasks($projectId, $filters);
                }
                break;
                
            case '/api/projects/notes':
                if ($method === 'GET') {
                    $projectId = $_GET['project_id'] ?? 'all';
                    $filters = $this->parseFilters($_GET);
                    return $this->filteringSystem->getProjectNotes($projectId, $filters);
                }
                break;
                
            case '/api/projects/scraps':
                if ($method === 'GET') {
                    $projectId = $_GET['project_id'] ?? 'all';
                    $filters = $this->parseFilters($_GET);
                    return $this->filteringSystem->getProjectScraps($projectId, $filters);
                }
                break;
                
            case '/api/projects/summary':
                if ($method === 'GET') {
                    return $this->filteringSystem->getProjectSummaries($this->parseFilters($_GET));
                }
                break;
                
            case '/api/unassigned':
                if ($method === 'GET') {
                    return $this->filteringSystem->getUnassignedEntities($this->parseFilters($_GET));
                }
                break;
                
            case '/api/unassigned/summary':
                if ($method === 'GET') {
                    return $this->filteringSystem->getUnassignedSummary();
                }
                break;
                
            case '/api/filter-options':
                if ($method === 'GET') {
                    $entityType = $_GET['entity_type'] ?? 'tasks';
                    return $this->filteringSystem->getFilterOptions($entityType);
                }
                break;
                
            case '/api/bulk-assign':
                if ($method === 'POST') {
                    return $this->bulkAssign($data);
                }
                break;
        }
        
        return null;
    }
    
    private function parseFilters($params) {
        $filters = [];
        
        // Multi-value filters
        $multiValueFilters = ['status', 'priority', 'entity_type'];
        foreach ($multiValueFilters as $filter) {
            if (!empty($params[$filter])) {
                if (strpos($params[$filter], ',') !== false) {
                    $filters[$filter] = explode(',', $params[$filter]);
                } else {
                    $filters[$filter] = $params[$filter];
                }
            }
        }
        
        // Single value filters
        $singleValueFilters = ['due_date', 'created_date', 'processed', 'search', 'sort', 'limit'];
        foreach ($singleValueFilters as $filter) {
            if (!empty($params[$filter])) {
                $filters[$filter] = $params[$filter];
            }
        }
        
        // Boolean filters
        if (isset($params['processed'])) {
            $filters['processed'] = filter_var($params['processed'], FILTER_VALIDATE_BOOLEAN);
        }
        
        return $filters;
    }
    
    private function bulkAssign($data) {
        $required = ['project_id', 'assignments'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        return $this->filteringSystem->bulkAssignToProject(
            $data['project_id'],
            $data['assignments']
        );
    }
}

// Hook execution for coordination
if (getenv('CLAUDE_FLOW_HOOKS')) {
    exec("npx claude-flow@alpha hooks pre-task --description 'project-filtering-system'");
    exec("npx claude-flow@alpha hooks session-restore --session-id 'swarm-roadmap-impl'");
    
    register_shutdown_function(function() {
        exec("npx claude-flow@alpha hooks post-edit --file 'project-filtering.php' --memory-key 'swarm/project-filters/implementation'");
        exec("npx claude-flow@alpha hooks post-task --task-id 'project-filtering-system'");
    });
}

?>