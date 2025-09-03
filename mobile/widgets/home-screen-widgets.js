/**
 * Home Screen Widget Support for TaskFlow AI PWA
 * Implements widget functionality for supported platforms
 */

class HomeScreenWidgetManager {
  constructor() {
    this.isSupported = this.checkSupport();
    this.widgetTypes = {
      QUICK_TASKS: 'quick-tasks',
      TODAY_AGENDA: 'today-agenda',
      TASK_COUNTER: 'task-counter',
      QUICK_CAPTURE: 'quick-capture',
      AI_SUGGESTIONS: 'ai-suggestions'
    };
    this.widgetConfigs = new Map();
    this.init();
  }

  /**
   * Check if widget functionality is supported
   */
  checkSupport() {
    // Check for various widget APIs
    return {
      webAppWidgets: 'getInstalledRelatedApps' in navigator && 'widget' in window,
      webLocks: 'locks' in navigator,
      periodicBackgroundSync: 'serviceWorker' in navigator && 'periodicSync' in ServiceWorkerRegistration.prototype,
      badging: 'setAppBadge' in navigator,
      shortcuts: 'getInstalledRelatedApps' in navigator
    };
  }

  /**
   * Initialize widget manager
   */
  async init() {
    try {
      await this.loadWidgetConfigs();
      await this.setupWidgetEndpoints();
      this.setupPeriodicUpdates();
      
      if (this.isSupported.badging) {
        this.updateAppBadge();
      }
    } catch (error) {
      console.error('Widget manager initialization failed:', error);
    }
  }

  /**
   * Register widget configurations
   */
  async registerWidgets() {
    const widgets = [
      {
        type: this.widgetTypes.QUICK_TASKS,
        name: 'Quick Tasks',
        description: 'View and complete your most important tasks',
        sizes: ['small', 'medium'],
        updateFrequency: 'high',
        data: await this.getQuickTasksData()
      },
      {
        type: this.widgetTypes.TODAY_AGENDA,
        name: "Today's Agenda",
        description: 'Your schedule and tasks for today',
        sizes: ['medium', 'large'],
        updateFrequency: 'medium',
        data: await this.getTodayAgendaData()
      },
      {
        type: this.widgetTypes.TASK_COUNTER,
        name: 'Task Counter',
        description: 'Quick overview of pending tasks',
        sizes: ['small'],
        updateFrequency: 'medium',
        data: await this.getTaskCounterData()
      },
      {
        type: this.widgetTypes.QUICK_CAPTURE,
        name: 'Quick Capture',
        description: 'Quickly add new tasks or notes',
        sizes: ['small', 'medium'],
        updateFrequency: 'low',
        data: { action: 'capture' }
      },
      {
        type: this.widgetTypes.AI_SUGGESTIONS,
        name: 'AI Suggestions',
        description: 'Smart suggestions from your AI assistant',
        sizes: ['medium', 'large'],
        updateFrequency: 'medium',
        data: await this.getAISuggestionsData()
      }
    ];

    for (const widget of widgets) {
      this.widgetConfigs.set(widget.type, widget);
    }

    return widgets;
  }

  /**
   * Get widget data by type
   */
  async getWidgetData(type, size = 'medium') {
    const config = this.widgetConfigs.get(type);
    if (!config) {
      throw new Error(`Widget type ${type} not found`);
    }

    switch (type) {
      case this.widgetTypes.QUICK_TASKS:
        return this.generateQuickTasksWidget(size);
      case this.widgetTypes.TODAY_AGENDA:
        return this.generateTodayAgendaWidget(size);
      case this.widgetTypes.TASK_COUNTER:
        return this.generateTaskCounterWidget(size);
      case this.widgetTypes.QUICK_CAPTURE:
        return this.generateQuickCaptureWidget(size);
      case this.widgetTypes.AI_SUGGESTIONS:
        return this.generateAISuggestionsWidget(size);
      default:
        throw new Error(`Unknown widget type: ${type}`);
    }
  }

  /**
   * Generate Quick Tasks widget content
   */
  async generateQuickTasksWidget(size) {
    const tasks = await this.getQuickTasksData();
    const maxTasks = size === 'small' ? 3 : size === 'medium' ? 5 : 8;
    const displayTasks = tasks.slice(0, maxTasks);

    return {
      type: this.widgetTypes.QUICK_TASKS,
      size: size,
      title: 'Quick Tasks',
      data: {
        tasks: displayTasks.map(task => ({
          id: task.id,
          title: task.title,
          priority: task.priority,
          due_date: task.due_date,
          completed: task.completed || false
        })),
        totalCount: tasks.length,
        completedToday: await this.getCompletedTodayCount()
      },
      actions: [
        { type: 'complete_task', label: 'Complete' },
        { type: 'view_all', label: 'View All', url: '/?view=tasks' },
        { type: 'add_task', label: 'Add Task', url: '/?action=new-task' }
      ],
      lastUpdated: Date.now()
    };
  }

  /**
   * Generate Today's Agenda widget content
   */
  async generateTodayAgendaWidget(size) {
    const agenda = await this.getTodayAgendaData();
    const maxItems = size === 'medium' ? 4 : 8;

    return {
      type: this.widgetTypes.TODAY_AGENDA,
      size: size,
      title: "Today's Agenda",
      data: {
        date: new Date().toLocaleDateString(),
        items: agenda.slice(0, maxItems),
        weather: await this.getWeatherData(),
        summary: await this.getDaySummary()
      },
      actions: [
        { type: 'view_plan', label: 'View Plan', url: '/?view=plan' },
        { type: 'add_event', label: 'Add Event', url: '/?action=new-event' }
      ],
      lastUpdated: Date.now()
    };
  }

  /**
   * Generate Task Counter widget content
   */
  async generateTaskCounterWidget(size) {
    const counter = await this.getTaskCounterData();

    return {
      type: this.widgetTypes.TASK_COUNTER,
      size: size,
      title: 'Tasks',
      data: {
        pending: counter.pending,
        completed: counter.completed,
        overdue: counter.overdue,
        dueToday: counter.dueToday
      },
      actions: [
        { type: 'view_tasks', label: 'View Tasks', url: '/?view=tasks' }
      ],
      lastUpdated: Date.now()
    };
  }

  /**
   * Generate Quick Capture widget content
   */
  generateQuickCaptureWidget(size) {
    return {
      type: this.widgetTypes.QUICK_CAPTURE,
      size: size,
      title: 'Quick Capture',
      data: {
        placeholder: 'What needs to be done?',
        suggestions: [
          'Add task',
          'Create note',
          'Set reminder',
          'Voice memo'
        ]
      },
      actions: [
        { type: 'quick_task', label: 'Quick Task', url: '/?action=quick-task' },
        { type: 'voice_note', label: 'Voice Note', url: '/?action=voice-note' },
        { type: 'camera_note', label: 'Photo Note', url: '/?action=photo-note' }
      ],
      lastUpdated: Date.now()
    };
  }

  /**
   * Generate AI Suggestions widget content
   */
  async generateAISuggestionsWidget(size) {
    const suggestions = await this.getAISuggestionsData();
    const maxSuggestions = size === 'medium' ? 2 : 4;

    return {
      type: this.widgetTypes.AI_SUGGESTIONS,
      size: size,
      title: 'AI Suggestions',
      data: {
        suggestions: suggestions.slice(0, maxSuggestions),
        insight: await this.getDailyInsight()
      },
      actions: [
        { type: 'view_suggestion', label: 'View' },
        { type: 'dismiss_suggestion', label: 'Dismiss' },
        { type: 'chat_ai', label: 'Chat with AI', url: '/?action=chat' }
      ],
      lastUpdated: Date.now()
    };
  }

  /**
   * Setup widget endpoints for external access
   */
  async setupWidgetEndpoints() {
    // Register service worker endpoints for widget data
    if ('serviceWorker' in navigator) {
      const registration = await navigator.serviceWorker.ready;
      
      // Send widget configurations to service worker
      registration.active?.postMessage({
        type: 'SETUP_WIDGET_ENDPOINTS',
        widgets: Array.from(this.widgetConfigs.values())
      });
    }
  }

  /**
   * Update app badge with task count
   */
  async updateAppBadge() {
    if (!this.isSupported.badging) return;

    try {
      const counter = await this.getTaskCounterData();
      const badgeCount = counter.pending + counter.overdue;
      
      if (badgeCount > 0) {
        await navigator.setAppBadge(badgeCount);
      } else {
        await navigator.clearAppBadge();
      }
    } catch (error) {
      console.error('Failed to update app badge:', error);
    }
  }

  /**
   * Setup periodic updates for widgets
   */
  setupPeriodicUpdates() {
    // Update widgets every 15 minutes
    setInterval(() => {
      this.updateAllWidgets();
    }, 15 * 60 * 1000);

    // Update badge more frequently
    setInterval(() => {
      if (this.isSupported.badging) {
        this.updateAppBadge();
      }
    }, 2 * 60 * 1000);
  }

  /**
   * Update all registered widgets
   */
  async updateAllWidgets() {
    for (const [type, config] of this.widgetConfigs) {
      try {
        await this.updateWidget(type);
      } catch (error) {
        console.error(`Failed to update widget ${type}:`, error);
      }
    }
  }

  /**
   * Update specific widget
   */
  async updateWidget(type) {
    const widgetData = await this.getWidgetData(type);
    
    // Store updated data
    localStorage.setItem(`widget_${type}`, JSON.stringify(widgetData));
    
    // Notify service worker about update
    if ('serviceWorker' in navigator) {
      const registration = await navigator.serviceWorker.ready;
      registration.active?.postMessage({
        type: 'WIDGET_UPDATE',
        widgetType: type,
        data: widgetData
      });
    }

    // Dispatch event for app components
    window.dispatchEvent(new CustomEvent('widget-updated', {
      detail: { type, data: widgetData }
    }));
  }

  /**
   * Handle widget interaction
   */
  async handleWidgetAction(widgetType, action, data = {}) {
    switch (action.type) {
      case 'complete_task':
        await this.completeTask(data.taskId);
        await this.updateWidget(widgetType);
        break;
        
      case 'quick_task':
        return this.createQuickTask(data.title);
        
      case 'view_all':
      case 'view_plan':
      case 'view_tasks':
      case 'chat_ai':
        // These are handled by URL navigation
        return { action: 'navigate', url: action.url };
        
      case 'dismiss_suggestion':
        await this.dismissSuggestion(data.suggestionId);
        await this.updateWidget(widgetType);
        break;
        
      default:
        console.warn(`Unknown widget action: ${action.type}`);
    }
  }

  /**
   * Create dynamic app shortcuts
   */
  async updateAppShortcuts() {
    if (!('navigator' in window) || !('getInstalledRelatedApps' in navigator)) {
      return;
    }

    try {
      const tasks = await this.getQuickTasksData();
      const recentTasks = tasks.slice(0, 4);
      
      const shortcuts = recentTasks.map((task, index) => ({
        name: task.title,
        short_name: task.title.substring(0, 12),
        description: `Complete: ${task.title}`,
        url: `/?complete=${task.id}`,
        icons: [
          {
            src: '/icons/shortcut-task.png',
            sizes: '192x192',
            type: 'image/png'
          }
        ]
      }));

      // Add static shortcuts
      shortcuts.unshift(
        {
          name: 'Quick Task',
          short_name: 'New Task',
          description: 'Create a new task quickly',
          url: '/?action=new-task',
          icons: [{ src: '/icons/shortcut-new-task.png', sizes: '192x192', type: 'image/png' }]
        },
        {
          name: 'AI Chat',
          short_name: 'Chat',
          description: 'Open AI assistant',
          url: '/?action=chat',
          icons: [{ src: '/icons/shortcut-chat.png', sizes: '192x192', type: 'image/png' }]
        }
      );

      // Store for manifest update
      localStorage.setItem('dynamic_shortcuts', JSON.stringify(shortcuts));
      
    } catch (error) {
      console.error('Failed to update app shortcuts:', error);
    }
  }

  // Data fetching methods (these would connect to your actual data layer)

  async getQuickTasksData() {
    try {
      const response = await fetch('/api/tasks?filter=priority&limit=10');
      const data = await response.json();
      return data.success ? data.data : [];
    } catch (error) {
      // Fallback to cached data
      return this.getCachedData('tasks') || [];
    }
  }

  async getTodayAgendaData() {
    try {
      const response = await fetch('/api/plan?date=today');
      const data = await response.json();
      return data.success ? data.data.items || [] : [];
    } catch (error) {
      return this.getCachedData('agenda') || [];
    }
  }

  async getTaskCounterData() {
    try {
      const response = await fetch('/api/tasks/stats');
      const data = await response.json();
      return data.success ? data.data : { pending: 0, completed: 0, overdue: 0, dueToday: 0 };
    } catch (error) {
      return this.getCachedData('task_stats') || { pending: 0, completed: 0, overdue: 0, dueToday: 0 };
    }
  }

  async getAISuggestionsData() {
    try {
      const response = await fetch('/api/ai/suggestions');
      const data = await response.json();
      return data.success ? data.suggestions || [] : [];
    } catch (error) {
      return this.getCachedData('ai_suggestions') || [];
    }
  }

  async getCompletedTodayCount() {
    try {
      const response = await fetch('/api/tasks/completed-today');
      const data = await response.json();
      return data.success ? data.count : 0;
    } catch (error) {
      return 0;
    }
  }

  async getWeatherData() {
    // Mock weather data - integrate with weather API
    return {
      temperature: '72°F',
      condition: 'Partly Cloudy',
      icon: '⛅'
    };
  }

  async getDaySummary() {
    return 'You have 5 tasks due today and 2 meetings scheduled.';
  }

  async getDailyInsight() {
    return 'You\'re most productive in the mornings. Consider scheduling important tasks before 11 AM.';
  }

  async completeTask(taskId) {
    try {
      const response = await fetch(`/api/tasks/${taskId}/complete`, {
        method: 'POST'
      });
      return response.ok;
    } catch (error) {
      console.error('Failed to complete task:', error);
      return false;
    }
  }

  async createQuickTask(title) {
    try {
      const response = await fetch('/api/tasks', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, priority: 'medium' })
      });
      const data = await response.json();
      return { success: response.ok, task: data.data };
    } catch (error) {
      console.error('Failed to create quick task:', error);
      return { success: false, error: error.message };
    }
  }

  async dismissSuggestion(suggestionId) {
    try {
      const response = await fetch(`/api/ai/suggestions/${suggestionId}/dismiss`, {
        method: 'POST'
      });
      return response.ok;
    } catch (error) {
      console.error('Failed to dismiss suggestion:', error);
      return false;
    }
  }

  getCachedData(key) {
    try {
      const cached = localStorage.getItem(`cached_${key}`);
      return cached ? JSON.parse(cached) : null;
    } catch (error) {
      return null;
    }
  }

  async loadWidgetConfigs() {
    // Load any saved widget configurations
    try {
      const saved = localStorage.getItem('widget_configs');
      if (saved) {
        const configs = JSON.parse(saved);
        for (const [type, config] of Object.entries(configs)) {
          this.widgetConfigs.set(type, config);
        }
      }
    } catch (error) {
      console.error('Failed to load widget configs:', error);
    }
  }

  saveWidgetConfigs() {
    try {
      const configs = Object.fromEntries(this.widgetConfigs);
      localStorage.setItem('widget_configs', JSON.stringify(configs));
    } catch (error) {
      console.error('Failed to save widget configs:', error);
    }
  }
}

// Widget UI Helper
class WidgetUIHelper {
  static generateWidgetHTML(widgetData) {
    const { type, size, title, data, actions } = widgetData;
    
    switch (type) {
      case 'quick-tasks':
        return this.generateTaskWidget(widgetData);
      case 'today-agenda':
        return this.generateAgendaWidget(widgetData);
      case 'task-counter':
        return this.generateCounterWidget(widgetData);
      case 'quick-capture':
        return this.generateCaptureWidget(widgetData);
      case 'ai-suggestions':
        return this.generateSuggestionWidget(widgetData);
      default:
        return this.generateGenericWidget(widgetData);
    }
  }

  static generateTaskWidget(widgetData) {
    const { data, actions } = widgetData;
    const tasks = data.tasks.map(task => `
      <div class="widget-task ${task.completed ? 'completed' : ''}">
        <span class="task-title">${task.title}</span>
        <span class="task-priority priority-${task.priority}">${task.priority}</span>
      </div>
    `).join('');

    return `
      <div class="widget-content">
        <div class="widget-header">
          <h3>Quick Tasks</h3>
          <span class="task-count">${data.tasks.length}</span>
        </div>
        <div class="widget-tasks">
          ${tasks}
        </div>
      </div>
    `;
  }

  static generateCounterWidget(widgetData) {
    const { data } = widgetData;
    return `
      <div class="widget-content counter-widget">
        <div class="counter-main">
          <div class="counter-number">${data.pending}</div>
          <div class="counter-label">Pending</div>
        </div>
        <div class="counter-stats">
          <div class="stat">
            <span class="stat-number">${data.overdue}</span>
            <span class="stat-label">Overdue</span>
          </div>
          <div class="stat">
            <span class="stat-number">${data.completed}</span>
            <span class="stat-label">Done</span>
          </div>
        </div>
      </div>
    `;
  }

  static generateGenericWidget(widgetData) {
    return `
      <div class="widget-content">
        <div class="widget-header">
          <h3>${widgetData.title}</h3>
        </div>
        <div class="widget-body">
          <pre>${JSON.stringify(widgetData.data, null, 2)}</pre>
        </div>
      </div>
    `;
  }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { HomeScreenWidgetManager, WidgetUIHelper };
} else {
  window.HomeScreenWidgetManager = HomeScreenWidgetManager;
  window.WidgetUIHelper = WidgetUIHelper;
}