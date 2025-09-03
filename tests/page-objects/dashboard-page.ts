import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './base-page';

/**
 * Dashboard Page Object for TaskFlow AI
 * Handles interactions with the main dashboard and task management interface
 */
export class DashboardPage extends BasePage {
  // Main navigation and layout
  get header(): Locator {
    return this.page.locator('[data-testid="header"], header, .app-header');
  }

  get sidebar(): Locator {
    return this.page.locator('[data-testid="sidebar"], .sidebar, nav');
  }

  get mainContent(): Locator {
    return this.page.locator('[data-testid="main-content"], main, .main-content');
  }

  // Task-related locators
  get taskList(): Locator {
    return this.page.locator('[data-testid="task-list"], .task-list, .tasks');
  }

  get taskItems(): Locator {
    return this.page.locator('[data-testid="task-item"], .task-item, .task');
  }

  get addTaskButton(): Locator {
    return this.page.locator('[data-testid="add-task"], .add-task, .btn-add-task');
  }

  get taskInput(): Locator {
    return this.page.locator('[data-testid="task-input"], input[name="task"], .task-input');
  }

  get submitTaskButton(): Locator {
    return this.page.locator('[data-testid="submit-task"], .submit-task, .btn-submit');
  }

  // Filter and search
  get searchInput(): Locator {
    return this.page.locator('[data-testid="search-input"], input[type="search"], .search-input');
  }

  get filterButtons(): Locator {
    return this.page.locator('[data-testid="filter-button"], .filter-btn, .filter');
  }

  get statusFilter(): Locator {
    return this.page.locator('[data-testid="status-filter"], select[name="status"], .status-filter');
  }

  get priorityFilter(): Locator {
    return this.page.locator('[data-testid="priority-filter"], select[name="priority"], .priority-filter');
  }

  get projectFilter(): Locator {
    return this.page.locator('[data-testid="project-filter"], select[name="project"], .project-filter');
  }

  // AI Chat Interface
  get chatInterface(): Locator {
    return this.page.locator('[data-testid="chat-interface"], .chat-interface, .ai-chat');
  }

  get chatInput(): Locator {
    return this.page.locator('[data-testid="chat-input"], .chat-input, input[placeholder*="chat"]');
  }

  get sendChatButton(): Locator {
    return this.page.locator('[data-testid="send-chat"], .send-chat, .btn-send');
  }

  get chatMessages(): Locator {
    return this.page.locator('[data-testid="chat-message"], .chat-message, .message');
  }

  get aiResponse(): Locator {
    return this.page.locator('[data-testid="ai-response"], .ai-response, .assistant-message');
  }

  // Project management
  get projectList(): Locator {
    return this.page.locator('[data-testid="project-list"], .project-list, .projects');
  }

  get createProjectButton(): Locator {
    return this.page.locator('[data-testid="create-project"], .create-project, .btn-create-project');
  }

  get projectItems(): Locator {
    return this.page.locator('[data-testid="project-item"], .project-item, .project');
  }

  // Statistics and analytics
  get statsSection(): Locator {
    return this.page.locator('[data-testid="stats-section"], .stats, .analytics');
  }

  get taskCount(): Locator {
    return this.page.locator('[data-testid="task-count"], .task-count, .count');
  }

  get completionRate(): Locator {
    return this.page.locator('[data-testid="completion-rate"], .completion-rate, .progress');
  }

  // Actions
  async goto(): Promise<void> {
    await this.page.goto('/');
    await this.waitForPageLoad();
    await expect(this.mainContent).toBeVisible();
  }

  async addTask(taskText: string, priority?: 'high' | 'medium' | 'low'): Promise<void> {
    await this.addTaskButton.click();
    await this.taskInput.fill(taskText);
    
    if (priority) {
      const prioritySelect = this.page.locator('[data-testid="priority-select"], select[name="priority"]');
      if (await prioritySelect.isVisible()) {
        await prioritySelect.selectOption(priority);
      }
    }
    
    await this.submitTaskButton.click();
    await this.waitForNetworkIdle();
  }

  async searchTasks(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.page.keyboard.press('Enter');
    await this.waitForNetworkIdle();
  }

  async filterTasksByStatus(status: 'all' | 'active' | 'completed' | 'pending'): Promise<void> {
    await this.statusFilter.selectOption(status);
    await this.waitForNetworkIdle();
  }

  async filterTasksByProject(projectName: string): Promise<void> {
    await this.projectFilter.selectOption(projectName);
    await this.waitForNetworkIdle();
  }

  async sendChatMessage(message: string): Promise<void> {
    await this.chatInput.fill(message);
    await this.sendChatButton.click();
    await expect(this.aiResponse).toBeVisible({ timeout: 10000 });
  }

  async completeTask(taskIndex: number = 0): Promise<void> {
    const task = this.taskItems.nth(taskIndex);
    const completeButton = task.locator('[data-testid="complete-task"], .complete-btn, input[type="checkbox"]');
    await completeButton.click();
    await this.waitForNetworkIdle();
  }

  async deleteTask(taskIndex: number = 0): Promise<void> {
    const task = this.taskItems.nth(taskIndex);
    const deleteButton = task.locator('[data-testid="delete-task"], .delete-btn, .btn-delete');
    await deleteButton.click();
    
    // Handle confirmation dialog if present
    const confirmDialog = this.page.locator('[data-testid="confirm-delete"], .confirm-dialog');
    if (await confirmDialog.isVisible()) {
      await this.page.locator('[data-testid="confirm-yes"], .confirm-btn').click();
    }
    
    await this.waitForNetworkIdle();
  }

  async editTask(taskIndex: number, newText: string): Promise<void> {
    const task = this.taskItems.nth(taskIndex);
    const editButton = task.locator('[data-testid="edit-task"], .edit-btn, .btn-edit');
    await editButton.click();
    
    const editInput = task.locator('[data-testid="edit-input"], input, .edit-input');
    await editInput.fill(newText);
    
    const saveButton = task.locator('[data-testid="save-edit"], .save-btn, .btn-save');
    await saveButton.click();
    await this.waitForNetworkIdle();
  }

  async getTaskCount(): Promise<number> {
    return await this.taskItems.count();
  }

  async getTaskText(taskIndex: number = 0): Promise<string> {
    const task = this.taskItems.nth(taskIndex);
    const taskText = task.locator('[data-testid="task-text"], .task-text, .task-title');
    return await taskText.textContent() || '';
  }

  async createProject(projectName: string, description?: string): Promise<void> {
    await this.createProjectButton.click();
    
    const projectNameInput = this.page.locator('[data-testid="project-name"], input[name="name"]');
    await projectNameInput.fill(projectName);
    
    if (description) {
      const descriptionInput = this.page.locator('[data-testid="project-description"], textarea[name="description"]');
      await descriptionInput.fill(description);
    }
    
    const createButton = this.page.locator('[data-testid="create-project-submit"], .btn-create');
    await createButton.click();
    await this.waitForNetworkIdle();
  }

  async selectProject(projectName: string): Promise<void> {
    const project = this.projectItems.filter({ hasText: projectName });
    await project.click();
    await this.waitForNetworkIdle();
  }

  // Mobile-specific actions
  async openMobileTaskMenu(): Promise<void> {
    await this.openMobileMenu();
    const taskMenu = this.page.locator('[data-testid="mobile-task-menu"], .mobile-task-menu');
    await expect(taskMenu).toBeVisible();
  }

  // PWA-specific actions
  async checkOfflineFunctionality(): Promise<boolean> {
    // Simulate offline mode
    await this.page.context().setOffline(true);
    
    // Try to add a task offline
    try {
      await this.addTask('Offline task test');
      return true;
    } catch {
      return false;
    } finally {
      await this.page.context().setOffline(false);
    }
  }

  async syncOfflineChanges(): Promise<void> {
    await this.page.context().setOffline(false);
    await this.waitForNetworkIdle();
    
    // Look for sync indicator
    const syncIndicator = this.page.locator('[data-testid="sync-indicator"], .sync-status');
    if (await syncIndicator.isVisible()) {
      await expect(syncIndicator).toContainText(/synced|online/i, { timeout: 10000 });
    }
  }
}