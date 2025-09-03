<?php
/**
 * AI-Guided Daily Workflow UI Components
 * Mobile-first responsive interface for workflow interactions
 */

class WorkflowUIGenerator {
    
    /**
     * Generate morning workflow interface
     */
    public static function generateMorningWorkflowUI($workflowData) {
        $selectedTasks = $workflowData['selected_tasks'];
        $unprocessedScraps = $workflowData['unprocessed_scraps'];
        $scrapSuggestions = $workflowData['scrap_suggestions'];
        
        return '
        <div class="workflow-container morning-workflow" id="morning-workflow">
            <div class="workflow-header">
                <h2>🌅 Morning Ritual</h2>
                <p class="workflow-date">' . date('l, F j, Y') . '</p>
            </div>
            
            <div class="workflow-content">
                <!-- Energy Level Check-in -->
                <div class="energy-checkin">
                    <h3>Energy Check-in</h3>
                    <div class="energy-slider">
                        <input type="range" id="energy-level" min="1" max="10" value="5" class="energy-input">
                        <div class="energy-labels">
                            <span>Low</span>
                            <span>High</span>
                        </div>
                    </div>
                </div>
                
                <!-- AI Selected Tasks -->
                <div class="selected-tasks">
                    <h3>🎯 Today\'s Focus (' . count($selectedTasks) . ' tasks)</h3>
                    <div class="task-list">
                        ' . self::renderTaskList($selectedTasks) . '
                    </div>
                </div>
                
                <!-- Scrap Processing -->
                ' . (count($unprocessedScraps) > 0 ? self::renderScrapProcessing($unprocessedScraps, $scrapSuggestions) : '') . '
                
                <!-- Daily Intention -->
                <div class="daily-intention">
                    <h3>🎭 Daily Intention</h3>
                    <textarea id="daily-intention" placeholder="What\'s your main intention for today?" rows="3"></textarea>
                </div>
                
                <!-- Action Buttons -->
                <div class="workflow-actions">
                    <button class="btn-primary" onclick="completeWorkflow(\'morning\')">Start My Day</button>
                    <button class="btn-secondary" onclick="adjustTasks()">Adjust Tasks</button>
                </div>
            </div>
        </div>';
    }
    
    /**
     * Generate evening workflow interface
     */
    public static function generateEveningWorkflowUI($workflowData) {
        $completionStats = $workflowData['completion_stats'];
        $reflectionPrompts = $workflowData['reflection_prompts'];
        $followupSuggestions = $workflowData['suggested_followups'];
        
        return '
        <div class="workflow-container evening-workflow" id="evening-workflow">
            <div class="workflow-header">
                <h2>🌙 Evening Reflection</h2>
                <p class="workflow-date">' . date('l, F j, Y') . '</p>
            </div>
            
            <div class="workflow-content">
                <!-- Completion Stats -->
                <div class="completion-stats">
                    <h3>📊 Today\'s Progress</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-number">' . $completionStats['tasks_completed'] . '</span>
                            <span class="stat-label">Tasks Completed</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">' . $completionStats['tasks_created'] . '</span>
                            <span class="stat-label">Tasks Created</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">' . $completionStats['notes_created'] . '</span>
                            <span class="stat-label">Notes Created</span>
                        </div>
                        <div class="stat-item productivity-score">
                            <span class="stat-number">' . number_format($completionStats['productivity_score'], 1) . '</span>
                            <span class="stat-label">Productivity Score</span>
                        </div>
                    </div>
                </div>
                
                <!-- Reflection Prompts -->
                <div class="reflection-section">
                    <h3>🤔 Reflection</h3>
                    ' . self::renderReflectionPrompts($reflectionPrompts) . '
                </div>
                
                <!-- Tomorrow Planning -->
                <div class="tomorrow-planning">
                    <h3>📅 Tomorrow\'s Focus</h3>
                    <div class="followup-suggestions">
                        ' . self::renderFollowupSuggestions($followupSuggestions) . '
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="workflow-actions">
                    <button class="btn-primary" onclick="completeWorkflow(\'evening\')">End Day</button>
                    <button class="btn-secondary" onclick="planTomorrow()">Plan Tomorrow</button>
                </div>
            </div>
        </div>';
    }
    
    /**
     * Render task list for morning workflow
     */
    private static function renderTaskList($tasks) {
        $html = '';
        foreach ($tasks as $task) {
            $priorityClass = strtolower($task['priority']);
            $effortText = isset($task['estimated_effort']) ? $task['estimated_effort'] . 'h' : '';
            $reasonText = isset($task['selection_reason']) ? $task['selection_reason'] : '';
            
            $html .= '
            <div class="task-item priority-' . $priorityClass . '" data-task-id="' . $task['id'] . '">
                <div class="task-header">
                    <h4>' . htmlspecialchars($task['title']) . '</h4>
                    ' . ($effortText ? '<span class="effort-estimate">' . $effortText . '</span>' : '') . '
                </div>
                ' . ($task['description'] ? '<p class="task-description">' . htmlspecialchars($task['description']) . '</p>' : '') . '
                ' . ($reasonText ? '<p class="selection-reason"><small>💡 ' . $reasonText . '</small></p>' : '') . '
            </div>';
        }
        return $html;
    }
    
    /**
     * Render scrap processing section
     */
    private static function renderScrapProcessing($scraps, $suggestions) {
        $html = '
        <div class="scrap-processing">
            <h3>📝 Scrap Processing (' . count($scraps) . ' items)</h3>
            <div class="scrap-list">';
            
        foreach ($scraps as $index => $scrap) {
            $suggestion = isset($suggestions[$index]) ? $suggestions[$index] : null;
            $html .= '
            <div class="scrap-item" data-scrap-id="' . $scrap['id'] . '">
                <div class="scrap-content">' . htmlspecialchars($scrap['content']) . '</div>
                ' . ($suggestion ? '
                <div class="scrap-suggestion">
                    <small>💡 Suggestion: ' . $suggestion['suggested_action'] . ' (Confidence: ' . number_format($suggestion['confidence'] * 100) . '%)</small>
                </div>' : '') . '
                <div class="scrap-actions">
                    <button class="btn-small" onclick="convertScrap(' . $scrap['id'] . ', \'task\')">→ Task</button>
                    <button class="btn-small" onclick="convertScrap(' . $scrap['id'] . ', \'note\')">→ Note</button>
                    <button class="btn-small" onclick="skipScrap(' . $scrap['id'] . ')">Skip</button>
                </div>
            </div>';
        }
        
        $html .= '</div></div>';
        return $html;
    }
    
    /**
     * Render reflection prompts
     */
    private static function renderReflectionPrompts($prompts) {
        $html = '<div class="reflection-prompts">';
        
        foreach ($prompts as $index => $prompt) {
            $html .= '
            <div class="reflection-prompt">
                <label>' . htmlspecialchars($prompt) . '</label>
                <textarea id="reflection-' . $index . '" rows="2" placeholder="Your thoughts..."></textarea>
            </div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render follow-up suggestions
     */
    private static function renderFollowupSuggestions($suggestions) {
        $html = '<div class="followup-list">';
        
        foreach ($suggestions as $index => $suggestion) {
            $html .= '
            <div class="followup-item">
                <input type="checkbox" id="followup-' . $index . '" value="' . htmlspecialchars($suggestion) . '">
                <label for="followup-' . $index . '">' . htmlspecialchars($suggestion) . '</label>
            </div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Generate workflow CSS styles
     */
    public static function generateWorkflowCSS() {
        return '
        <style>
        .workflow-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 1rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .workflow-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
        }
        
        .workflow-header h2 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
        }
        
        .workflow-date {
            margin: 0;
            opacity: 0.9;
        }
        
        .energy-checkin {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .energy-slider {
            margin-top: 0.5rem;
        }
        
        .energy-input {
            width: 100%;
            height: 8px;
            border-radius: 5px;
            background: #ddd;
            outline: none;
        }
        
        .energy-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .selected-tasks, .scrap-processing, .reflection-section, .tomorrow-planning {
            margin-bottom: 1.5rem;
        }
        
        .task-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #6c757d;
        }
        
        .task-item.priority-high {
            border-left-color: #dc3545;
        }
        
        .task-item.priority-medium {
            border-left-color: #ffc107;
        }
        
        .task-item.priority-low {
            border-left-color: #28a745;
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .task-header h4 {
            margin: 0;
            font-size: 1rem;
        }
        
        .effort-estimate {
            background: #e9ecef;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .selection-reason {
            color: #666;
            font-style: italic;
            margin: 0.5rem 0 0 0;
        }
        
        .scrap-item {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .scrap-suggestion {
            margin: 0.5rem 0;
            color: #856404;
        }
        
        .scrap-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .stat-number {
            display: block;
            font-size: 2rem;
            font-weight: bold;
            color: #495057;
        }
        
        .productivity-score .stat-number {
            color: #28a745;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #666;
        }
        
        .reflection-prompts .reflection-prompt {
            margin-bottom: 1rem;
        }
        
        .reflection-prompt label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .reflection-prompt textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        
        .daily-intention textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: vertical;
        }
        
        .workflow-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn-primary, .btn-secondary, .btn-small {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            flex: 1;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-small {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            background: #f8f9fa;
            color: #495057;
        }
        
        .btn-small:hover {
            background: #e9ecef;
        }
        
        .followup-item {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .followup-item input[type="checkbox"] {
            margin-right: 0.5rem;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .workflow-container {
                padding: 0.5rem;
            }
            
            .workflow-actions {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .scrap-actions {
                flex-direction: column;
            }
        }
        </style>';
    }
    
    /**
     * Generate workflow JavaScript
     */
    public static function generateWorkflowJS() {
        return '
        <script>
        // Workflow Management Functions
        
        function completeWorkflow(type) {
            const workflowId = getCurrentWorkflowId();
            const completionData = gatherCompletionData(type);
            
            fetch("/api/workflow/complete", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    workflow_id: workflowId,
                    completion_data: completionData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`${type} workflow completed!`, "success");
                    hideWorkflowUI();
                } else {
                    showNotification("Error completing workflow", "error");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showNotification("Network error", "error");
            });
        }
        
        function gatherCompletionData(type) {
            const data = {};
            
            if (type === "morning") {
                data.energy_level = document.getElementById("energy-level")?.value;
                data.daily_intention = document.getElementById("daily-intention")?.value;
            } else if (type === "evening") {
                // Gather reflection responses
                const reflections = {};
                document.querySelectorAll("[id^=\'reflection-\']").forEach(textarea => {
                    const index = textarea.id.replace("reflection-", "");
                    reflections[index] = textarea.value;
                });
                data.reflections = reflections;
                
                // Gather selected follow-ups
                const followups = [];
                document.querySelectorAll("[id^=\'followup-\']:checked").forEach(checkbox => {
                    followups.push(checkbox.value);
                });
                data.selected_followups = followups;
            }
            
            return data;
        }
        
        function convertScrap(scrapId, targetType) {
            const title = prompt(`Enter title for new ${targetType}:`);
            if (!title) return;
            
            const data = {
                scrap_id: scrapId,
                to: targetType,
                data: { title: title }
            };
            
            fetch("/api/workflow/convert-scrap", {
                method: "POST", 
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showNotification(`Scrap converted to ${targetType}!`, "success");
                    document.querySelector(`[data-scrap-id="${scrapId}"]`).remove();
                } else {
                    showNotification("Conversion failed", "error");
                }
            });
        }
        
        function skipScrap(scrapId) {
            document.querySelector(`[data-scrap-id="${scrapId}"]`).style.opacity = "0.5";
            showNotification("Scrap skipped", "info");
        }
        
        function adjustTasks() {
            // Show task adjustment modal
            showNotification("Task adjustment feature coming soon!", "info");
        }
        
        function planTomorrow() {
            // Navigate to planning view
            showNotification("Tomorrow planning feature coming soon!", "info");
        }
        
        function getCurrentWorkflowId() {
            return document.querySelector(".workflow-container")?.dataset.workflowId || null;
        }
        
        function hideWorkflowUI() {
            const container = document.querySelector(".workflow-container");
            if (container) {
                container.style.display = "none";
            }
        }
        
        function showNotification(message, type) {
            // Simple notification system
            const notification = document.createElement("div");
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem;
                border-radius: 4px;
                color: white;
                z-index: 1000;
                background: ${type === "success" ? "#28a745" : type === "error" ? "#dc3545" : "#17a2b8"};
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // Initialize workflow UI
        document.addEventListener("DOMContentLoaded", function() {
            // Auto-focus on workflow elements
            const energySlider = document.getElementById("energy-level");
            if (energySlider) {
                energySlider.addEventListener("input", function() {
                    const value = this.value;
                    const labels = ["Exhausted", "Very Low", "Low", "Below Average", "Moderate", 
                                   "Good", "High", "Very High", "Excellent", "Peak Energy"];
                    showNotification(`Energy Level: ${labels[value-1]}`, "info");
                });
            }
        });
        </script>';
    }
}

?>