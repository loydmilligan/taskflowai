<?php
/**
 * Workflow Settings UI
 * Provides user interface for configuring proactive workflow notifications
 * 
 * Features:
 * - Morning/Evening workflow toggles
 * - Time configuration with timezone handling
 * - ntfy topic configuration
 * - Notification preferences
 * - Test notification functionality
 */

class WorkflowSettingsUI {
    private $db;
    private $proactiveWorkflows;
    
    public function __construct($database, $proactiveWorkflows) {
        $this->db = $database;
        $this->proactiveWorkflows = $proactiveWorkflows;
    }
    
    /**
     * Get current workflow settings for display
     */
    public function getWorkflowSettings() {
        try {
            $settings = $this->proactiveWorkflows->getWorkflowSchedules();
            
            // Get default settings if none exist
            if (empty($settings)) {
                return [
                    'morning_enabled' => false,
                    'morning_time' => '09:00',
                    'evening_enabled' => false,
                    'evening_time' => '18:00',
                    'timezone' => date_default_timezone_get(),
                    'ntfy_morning_topic' => '',
                    'ntfy_evening_topic' => '',
                    'snooze_options' => [15, 30, 60],
                    'notification_style' => 'full', // full, minimal, silent
                    'weekend_enabled' => true,
                    'holiday_skip' => false
                ];
            }
            
            // Transform database settings to UI format
            $uiSettings = [
                'morning_enabled' => false,
                'morning_time' => '09:00',
                'evening_enabled' => false,
                'evening_time' => '18:00',
                'timezone' => date_default_timezone_get(),
                'ntfy_morning_topic' => '',
                'ntfy_evening_topic' => '',
                'snooze_options' => [15, 30, 60],
                'notification_style' => 'full',
                'weekend_enabled' => true,
                'holiday_skip' => false
            ];
            
            foreach ($settings as $setting) {
                if ($setting['workflow_type'] === 'morning') {
                    $uiSettings['morning_enabled'] = $setting['enabled'];
                    $uiSettings['morning_time'] = $setting['scheduled_time'];
                    $uiSettings['ntfy_morning_topic'] = $setting['ntfy_topic'];
                } elseif ($setting['workflow_type'] === 'evening') {
                    $uiSettings['evening_enabled'] = $setting['enabled'];
                    $uiSettings['evening_time'] = $setting['scheduled_time'];
                    $uiSettings['ntfy_evening_topic'] = $setting['ntfy_topic'];
                }
                
                // Common settings from any record
                $uiSettings['timezone'] = $setting['timezone'] ?? date_default_timezone_get();
                $uiSettings['weekend_enabled'] = $setting['weekend_enabled'] ?? true;
            }
            
            return $uiSettings;
            
        } catch (Exception $e) {
            error_log("Error getting workflow settings: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update workflow settings from UI
     */
    public function updateWorkflowSettings($settingsData) {
        try {
            $result = [];
            
            // Update morning workflow settings
            if (isset($settingsData['morning_enabled'])) {
                $morningResult = $this->proactiveWorkflows->configureWorkflowSchedule(
                    'morning',
                    $settingsData['morning_enabled'],
                    $settingsData['morning_time'] ?? '09:00',
                    $settingsData['ntfy_morning_topic'] ?? '',
                    [
                        'timezone' => $settingsData['timezone'] ?? date_default_timezone_get(),
                        'weekend_enabled' => $settingsData['weekend_enabled'] ?? true,
                        'holiday_skip' => $settingsData['holiday_skip'] ?? false,
                        'notification_style' => $settingsData['notification_style'] ?? 'full',
                        'snooze_options' => $settingsData['snooze_options'] ?? [15, 30, 60]
                    ]
                );
                $result['morning'] = $morningResult;
            }
            
            // Update evening workflow settings
            if (isset($settingsData['evening_enabled'])) {
                $eveningResult = $this->proactiveWorkflows->configureWorkflowSchedule(
                    'evening',
                    $settingsData['evening_enabled'],
                    $settingsData['evening_time'] ?? '18:00',
                    $settingsData['ntfy_evening_topic'] ?? '',
                    [
                        'timezone' => $settingsData['timezone'] ?? date_default_timezone_get(),
                        'weekend_enabled' => $settingsData['weekend_enabled'] ?? true,
                        'holiday_skip' => $settingsData['holiday_skip'] ?? false,
                        'notification_style' => $settingsData['notification_style'] ?? 'full',
                        'snooze_options' => $settingsData['snooze_options'] ?? [15, 30, 60]
                    ]
                );
                $result['evening'] = $eveningResult;
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error updating workflow settings: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test workflow notification
     */
    public function testNotification($workflowType = 'morning') {
        try {
            return $this->proactiveWorkflows->testNotification($workflowType);
        } catch (Exception $e) {
            error_log("Error testing notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get workflow engagement metrics for admin view
     */
    public function getEngagementMetrics($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    workflow_type,
                    action_type,
                    COUNT(*) as action_count,
                    DATE(created_at) as action_date
                FROM workflow_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY workflow_type, action_type, DATE(created_at)
                ORDER BY action_date DESC
            ");
            
            $stmt->execute([$days]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process metrics
            $metrics = [
                'summary' => [
                    'total_notifications' => 0,
                    'total_starts' => 0,
                    'total_snoozes' => 0,
                    'total_cancels' => 0,
                    'engagement_rate' => 0
                ],
                'by_type' => [
                    'morning' => ['notifications' => 0, 'starts' => 0, 'snoozes' => 0, 'cancels' => 0],
                    'evening' => ['notifications' => 0, 'starts' => 0, 'snoozes' => 0, 'cancels' => 0]
                ],
                'daily_breakdown' => []
            ];
            
            foreach ($logs as $log) {
                $type = $log['workflow_type'];
                $action = $log['action_type'];
                $count = (int)$log['action_count'];
                $date = $log['action_date'];
                
                // Update summary
                if ($action === 'notification_sent') {
                    $metrics['summary']['total_notifications'] += $count;
                    $metrics['by_type'][$type]['notifications'] += $count;
                } elseif ($action === 'workflow_started') {
                    $metrics['summary']['total_starts'] += $count;
                    $metrics['by_type'][$type]['starts'] += $count;
                } elseif ($action === 'workflow_snoozed') {
                    $metrics['summary']['total_snoozes'] += $count;
                    $metrics['by_type'][$type]['snoozes'] += $count;
                } elseif ($action === 'workflow_cancelled') {
                    $metrics['summary']['total_cancels'] += $count;
                    $metrics['by_type'][$type]['cancels'] += $count;
                }
                
                // Daily breakdown
                if (!isset($metrics['daily_breakdown'][$date])) {
                    $metrics['daily_breakdown'][$date] = [
                        'morning' => ['notifications' => 0, 'starts' => 0, 'snoozes' => 0, 'cancels' => 0],
                        'evening' => ['notifications' => 0, 'starts' => 0, 'snoozes' => 0, 'cancels' => 0]
                    ];
                }
                
                if ($action === 'notification_sent') {
                    $metrics['daily_breakdown'][$date][$type]['notifications'] += $count;
                } elseif ($action === 'workflow_started') {
                    $metrics['daily_breakdown'][$date][$type]['starts'] += $count;
                } elseif ($action === 'workflow_snoozed') {
                    $metrics['daily_breakdown'][$date][$type]['snoozes'] += $count;
                } elseif ($action === 'workflow_cancelled') {
                    $metrics['daily_breakdown'][$date][$type]['cancels'] += $count;
                }
            }
            
            // Calculate engagement rate
            if ($metrics['summary']['total_notifications'] > 0) {
                $totalEngagements = $metrics['summary']['total_starts'] + 
                                  $metrics['summary']['total_snoozes'] + 
                                  $metrics['summary']['total_cancels'];
                $metrics['summary']['engagement_rate'] = 
                    round(($totalEngagements / $metrics['summary']['total_notifications']) * 100, 1);
            }
            
            return $metrics;
            
        } catch (Exception $e) {
            error_log("Error getting engagement metrics: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get available timezones for settings
     */
    public function getAvailableTimezones() {
        $timezones = [];
        $regions = [
            'America' => DateTimeZone::AMERICA,
            'Europe' => DateTimeZone::EUROPE,
            'Asia' => DateTimeZone::ASIA,
            'Australia' => DateTimeZone::AUSTRALIA,
            'Pacific' => DateTimeZone::PACIFIC,
            'Atlantic' => DateTimeZone::ATLANTIC,
            'Indian' => DateTimeZone::INDIAN
        ];
        
        foreach ($regions as $regionName => $region) {
            $regionTimezones = DateTimeZone::listIdentifiers($region);
            foreach ($regionTimezones as $timezone) {
                $dt = new DateTime('now', new DateTimeZone($timezone));
                $offset = $dt->format('P');
                $timezones[] = [
                    'value' => $timezone,
                    'label' => str_replace('_', ' ', $timezone) . ' (' . $offset . ')',
                    'region' => $regionName,
                    'offset' => $offset
                ];
            }
        }
        
        // Sort by offset, then by name
        usort($timezones, function($a, $b) {
            $offsetCompare = strcmp($a['offset'], $b['offset']);
            if ($offsetCompare === 0) {
                return strcmp($a['label'], $b['label']);
            }
            return $offsetCompare;
        });
        
        return $timezones;
    }
    
    /**
     * Validate workflow settings data
     */
    public function validateSettings($settingsData) {
        $errors = [];
        
        // Validate time formats
        if (isset($settingsData['morning_time'])) {
            if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $settingsData['morning_time'])) {
                $errors[] = 'Invalid morning time format. Use HH:MM format.';
            }
        }
        
        if (isset($settingsData['evening_time'])) {
            if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $settingsData['evening_time'])) {
                $errors[] = 'Invalid evening time format. Use HH:MM format.';
            }
        }
        
        // Validate timezone
        if (isset($settingsData['timezone'])) {
            if (!in_array($settingsData['timezone'], timezone_identifiers_list())) {
                $errors[] = 'Invalid timezone specified.';
            }
        }
        
        // Validate ntfy topics
        if (isset($settingsData['ntfy_morning_topic'])) {
            if (!empty($settingsData['ntfy_morning_topic']) && 
                !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $settingsData['ntfy_morning_topic'])) {
                $errors[] = 'Invalid morning ntfy topic. Use alphanumeric characters, hyphens, and underscores only.';
            }
        }
        
        if (isset($settingsData['ntfy_evening_topic'])) {
            if (!empty($settingsData['ntfy_evening_topic']) && 
                !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $settingsData['ntfy_evening_topic'])) {
                $errors[] = 'Invalid evening ntfy topic. Use alphanumeric characters, hyphens, and underscores only.';
            }
        }
        
        // Validate snooze options
        if (isset($settingsData['snooze_options'])) {
            if (!is_array($settingsData['snooze_options'])) {
                $errors[] = 'Snooze options must be an array.';
            } else {
                foreach ($settingsData['snooze_options'] as $option) {
                    if (!is_int($option) || $option < 1 || $option > 480) {
                        $errors[] = 'Snooze options must be integers between 1 and 480 minutes.';
                        break;
                    }
                }
            }
        }
        
        return $errors;
    }
}

/**
 * Workflow Settings Routes
 * Handle API requests for workflow settings management
 */
class WorkflowSettingsRoutes {
    private $settingsUI;
    
    public function __construct($settingsUI) {
        $this->settingsUI = $settingsUI;
    }
    
    public function handleRequest($method, $endpoint, $data = []) {
        switch ($endpoint) {
            case '/api/workflow-settings':
                if ($method === 'GET') {
                    return $this->getSettings();
                } elseif ($method === 'POST') {
                    return $this->updateSettings($data);
                }
                break;
                
            case '/api/workflow-settings/test':
                if ($method === 'POST') {
                    return $this->testNotification($data);
                }
                break;
                
            case '/api/workflow-settings/metrics':
                if ($method === 'GET') {
                    return $this->getMetrics($_GET);
                }
                break;
                
            case '/api/workflow-settings/timezones':
                if ($method === 'GET') {
                    return $this->getTimezones();
                }
                break;
        }
        
        return null;
    }
    
    private function getSettings() {
        try {
            $settings = $this->settingsUI->getWorkflowSettings();
            
            if ($settings === null) {
                return [
                    'success' => false,
                    'error' => 'Failed to retrieve workflow settings'
                ];
            }
            
            return [
                'success' => true,
                'settings' => $settings
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error retrieving settings: ' . $e->getMessage()
            ];
        }
    }
    
    private function updateSettings($data) {
        try {
            // Validate settings
            $validationErrors = $this->settingsUI->validateSettings($data);
            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'validation_errors' => $validationErrors
                ];
            }
            
            $result = $this->settingsUI->updateWorkflowSettings($data);
            
            if ($result === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to update workflow settings'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Workflow settings updated successfully',
                'updated_settings' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error updating settings: ' . $e->getMessage()
            ];
        }
    }
    
    private function testNotification($data) {
        try {
            $workflowType = $data['workflow_type'] ?? 'morning';
            $result = $this->settingsUI->testNotification($workflowType);
            
            return [
                'success' => $result !== false,
                'message' => $result !== false 
                    ? 'Test notification sent successfully' 
                    : 'Failed to send test notification',
                'test_result' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error sending test notification: ' . $e->getMessage()
            ];
        }
    }
    
    private function getMetrics($params) {
        try {
            $days = (int)($params['days'] ?? 30);
            $days = max(1, min(365, $days)); // Limit between 1 and 365 days
            
            $metrics = $this->settingsUI->getEngagementMetrics($days);
            
            if ($metrics === null) {
                return [
                    'success' => false,
                    'error' => 'Failed to retrieve engagement metrics'
                ];
            }
            
            return [
                'success' => true,
                'metrics' => $metrics,
                'period_days' => $days
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error retrieving metrics: ' . $e->getMessage()
            ];
        }
    }
    
    private function getTimezones() {
        try {
            $timezones = $this->settingsUI->getAvailableTimezones();
            
            return [
                'success' => true,
                'timezones' => $timezones,
                'current_timezone' => date_default_timezone_get()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error retrieving timezones: ' . $e->getMessage()
            ];
        }
    }
}

?>