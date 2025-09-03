import { Page, Locator, expect } from '@playwright/test';

/**
 * Test Helper Utilities for TaskFlow AI
 * Common functions and utilities shared across test suites
 */

export class TestHelpers {
  private page: Page;

  constructor(page: Page) {
    this.page = page;
  }

  /**
   * Wait for element to be visible with custom timeout and error message
   */
  async waitForElement(selector: string, timeout: number = 5000, errorMessage?: string): Promise<Locator> {
    const element = this.page.locator(selector);
    await expect(element).toBeVisible({ timeout });
    return element;
  }

  /**
   * Generate random test data
   */
  static generateTestData() {
    const timestamp = Date.now();
    const randomId = Math.random().toString(36).substring(2, 15);
    
    return {
      taskTitle: `Test Task ${timestamp}`,
      projectName: `Test Project ${randomId}`,
      userEmail: `test+${randomId}@taskflow.ai`,
      timestamp,
      randomId
    };
  }

  /**
   * Create test tasks with various properties
   */
  async createTestTasks(count: number = 5): Promise<string[]> {
    const taskIds: string[] = [];
    
    for (let i = 0; i < count; i++) {
      const testData = TestHelpers.generateTestData();
      const taskTitle = `${testData.taskTitle} ${i + 1}`;
      
      await this.page.locator('[data-testid="add-task"], .add-task-btn').click();
      await this.page.locator('[data-testid="task-input"], .task-input').fill(taskTitle);
      await this.page.locator('[data-testid="submit-task"], .submit-btn').click();
      
      // Wait for task to be created and get its ID
      const taskElement = this.page.locator('[data-testid="task-item"]').last();
      await expect(taskElement).toBeVisible();
      
      const taskId = await taskElement.getAttribute('data-id') || `task-${i}`;
      taskIds.push(taskId);
    }
    
    return taskIds;
  }

  /**
   * Clean up test data
   */
  async cleanupTestData(): Promise<void> {
    // Delete all test tasks
    const testTasks = this.page.locator('[data-testid="task-item"]').filter({ hasText: /Test Task/ });
    const taskCount = await testTasks.count();
    
    for (let i = 0; i < taskCount; i++) {
      const task = testTasks.nth(i);
      const deleteButton = task.locator('[data-testid="delete-task"], .delete-btn');
      if (await deleteButton.isVisible()) {
        await deleteButton.click();
        
        // Handle confirmation dialog
        const confirmButton = this.page.locator('[data-testid="confirm-delete"], .confirm-btn');
        if (await confirmButton.isVisible()) {
          await confirmButton.click();
        }
      }
    }
    
    // Clear any test projects
    const testProjects = this.page.locator('[data-testid="project-item"]').filter({ hasText: /Test Project/ });
    const projectCount = await testProjects.count();
    
    for (let i = 0; i < projectCount; i++) {
      const project = testProjects.nth(i);
      const deleteButton = project.locator('[data-testid="delete-project"], .delete-btn');
      if (await deleteButton.isVisible()) {
        await deleteButton.click();
        
        const confirmButton = this.page.locator('[data-testid="confirm-delete"], .confirm-btn');
        if (await confirmButton.isVisible()) {
          await confirmButton.click();
        }
      }
    }
  }

  /**
   * Take screenshot with timestamp
   */
  async takeTimestampedScreenshot(name: string): Promise<void> {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    await this.page.screenshot({
      path: `test-results/screenshots/${name}-${timestamp}.png`,
      fullPage: true
    });
  }

  /**
   * Mock API responses for testing
   */
  async mockApiResponse(endpoint: string, response: any, status: number = 200): Promise<void> {
    await this.page.route(endpoint, route => {
      route.fulfill({
        status,
        contentType: 'application/json',
        body: JSON.stringify(response)
      });
    });
  }

  /**
   * Simulate network conditions
   */
  async simulateSlowNetwork(delay: number = 1000): Promise<void> {
    await this.page.route('**/*', async route => {
      await new Promise(resolve => setTimeout(resolve, delay));
      await route.continue();
    });
  }

  /**
   * Check for console errors
   */
  async checkConsoleErrors(): Promise<string[]> {
    const errors: string[] = [];
    
    this.page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    return errors;
  }

  /**
   * Wait for all network requests to complete
   */
  async waitForNetworkIdle(timeout: number = 5000): Promise<void> {
    await this.page.waitForLoadState('networkidle', { timeout });
  }

  /**
   * Simulate user typing with realistic delays
   */
  async typeRealistic(locator: Locator, text: string, delay: number = 100): Promise<void> {
    await locator.click();
    await locator.fill('');
    
    for (const char of text) {
      await locator.type(char, { delay: delay + Math.random() * 50 });
    }
  }

  /**
   * Wait for element to disappear
   */
  async waitForElementToDisappear(selector: string, timeout: number = 5000): Promise<void> {
    const element = this.page.locator(selector);
    await expect(element).not.toBeVisible({ timeout });
  }

  /**
   * Check if element has specific CSS property value
   */
  async checkCSSProperty(locator: Locator, property: string, expectedValue: string): Promise<void> {
    await expect(locator).toHaveCSS(property, expectedValue);
  }

  /**
   * Scroll element into view and wait
   */
  async scrollToAndWait(locator: Locator, timeout: number = 1000): Promise<void> {
    await locator.scrollIntoViewIfNeeded();
    await this.page.waitForTimeout(timeout);
  }

  /**
   * Check accessibility compliance
   */
  async checkAccessibility(locator: Locator): Promise<void> {
    // Check for ARIA labels
    const ariaLabel = await locator.getAttribute('aria-label');
    const ariaLabelledBy = await locator.getAttribute('aria-labelledby');
    const title = await locator.getAttribute('title');
    
    // At least one accessibility label should be present
    expect(ariaLabel || ariaLabelledBy || title).toBeTruthy();
    
    // Check for keyboard focusability if interactive
    const role = await locator.getAttribute('role');
    const tagName = await locator.evaluate(el => el.tagName.toLowerCase());
    
    const interactiveTags = ['button', 'a', 'input', 'select', 'textarea'];
    const interactiveRoles = ['button', 'link', 'textbox', 'combobox'];
    
    if (interactiveTags.includes(tagName) || (role && interactiveRoles.includes(role))) {
      await locator.focus();
      await expect(locator).toBeFocused();
    }
  }

  /**
   * Performance measurement utilities
   */
  async measurePerformance<T>(action: () => Promise<T>, name: string): Promise<{ result: T; duration: number }> {
    const startTime = Date.now();
    const result = await action();
    const duration = Date.now() - startTime;
    
    console.log(`Performance: ${name} took ${duration}ms`);
    
    return { result, duration };
  }

  /**
   * Local storage utilities
   */
  async setLocalStorage(key: string, value: any): Promise<void> {
    await this.page.evaluate(([k, v]) => {
      localStorage.setItem(k, JSON.stringify(v));
    }, [key, value]);
  }

  async getLocalStorage(key: string): Promise<any> {
    return await this.page.evaluate((k) => {
      const value = localStorage.getItem(k);
      return value ? JSON.parse(value) : null;
    }, key);
  }

  async clearLocalStorage(): Promise<void> {
    await this.page.evaluate(() => localStorage.clear());
  }

  /**
   * Database utilities (for API testing)
   */
  async setupTestDatabase(): Promise<void> {
    await this.mockApiResponse('/api/test-setup', {
      action: 'initialize',
      success: true
    });
    
    // Set test mode
    await this.setLocalStorage('test_mode', true);
    await this.setLocalStorage('test_session_id', TestHelpers.generateTestData().randomId);
  }

  async cleanupTestDatabase(): Promise<void> {
    await this.mockApiResponse('/api/test-cleanup', {
      action: 'cleanup',
      success: true
    });
    
    await this.clearLocalStorage();
  }

  /**
   * Mobile-specific utilities
   */
  async simulateSwipeGesture(
    locator: Locator, 
    direction: 'left' | 'right' | 'up' | 'down',
    distance: number = 100
  ): Promise<void> {
    const boundingBox = await locator.boundingBox();
    if (!boundingBox) return;
    
    const startX = boundingBox.x + boundingBox.width / 2;
    const startY = boundingBox.y + boundingBox.height / 2;
    
    let endX = startX;
    let endY = startY;
    
    switch (direction) {
      case 'left':
        endX = startX - distance;
        break;
      case 'right':
        endX = startX + distance;
        break;
      case 'up':
        endY = startY - distance;
        break;
      case 'down':
        endY = startY + distance;
        break;
    }
    
    await this.page.mouse.move(startX, startY);
    await this.page.mouse.down();
    await this.page.mouse.move(endX, endY);
    await this.page.mouse.up();
  }

  /**
   * Wait for animations to complete
   */
  async waitForAnimations(timeout: number = 1000): Promise<void> {
    await this.page.waitForTimeout(timeout);
    
    // Wait for CSS animations to complete
    await this.page.evaluate(() => {
      return Promise.all(
        document.getAnimations().map(animation => animation.finished)
      );
    });
  }

  /**
   * Responsive design testing
   */
  async testResponsiveBreakpoints(locator: Locator): Promise<void> {
    const breakpoints = [
      { name: 'mobile', width: 375, height: 667 },
      { name: 'tablet', width: 768, height: 1024 },
      { name: 'desktop', width: 1200, height: 800 }
    ];
    
    for (const breakpoint of breakpoints) {
      await this.page.setViewportSize({ 
        width: breakpoint.width, 
        height: breakpoint.height 
      });
      
      await this.waitForAnimations();
      await expect(locator).toBeVisible();
      
      // Take screenshot at each breakpoint
      await this.page.screenshot({
        path: `test-results/responsive/${breakpoint.name}.png`,
        fullPage: true
      });
    }
  }
}

/**
 * Data generation utilities
 */
export class TestDataGenerator {
  static generateTask(overrides: Partial<any> = {}) {
    const timestamp = Date.now();
    return {
      title: `Test Task ${timestamp}`,
      description: `Description for test task created at ${new Date().toISOString()}`,
      priority: 'medium',
      status: 'active',
      project_id: null,
      due_date: null,
      ...overrides
    };
  }

  static generateProject(overrides: Partial<any> = {}) {
    const timestamp = Date.now();
    return {
      name: `Test Project ${timestamp}`,
      description: `Test project created for automated testing`,
      color: '#3B82F6',
      ...overrides
    };
  }

  static generateUser(overrides: Partial<any> = {}) {
    const randomId = Math.random().toString(36).substring(2, 15);
    return {
      name: `Test User ${randomId}`,
      email: `test+${randomId}@taskflow.ai`,
      role: 'user',
      ...overrides
    };
  }

  static generateChatMessage(overrides: Partial<any> = {}) {
    const messages = [
      'How can I improve my productivity today?',
      'What tasks should I prioritize?',
      'Can you help me organize my schedule?',
      'Show me my overdue tasks',
      'Create a task for team meeting preparation'
    ];

    return {
      content: messages[Math.floor(Math.random() * messages.length)],
      timestamp: new Date().toISOString(),
      type: 'user',
      ...overrides
    };
  }
}

/**
 * Assertion helpers
 */
export class AssertionHelpers {
  static async expectElementToBeAccessible(locator: Locator): Promise<void> {
    // Check visibility
    await expect(locator).toBeVisible();
    
    // Check focusability for interactive elements
    const tagName = await locator.evaluate(el => el.tagName.toLowerCase());
    const role = await locator.getAttribute('role');
    
    const interactiveElements = ['button', 'a', 'input', 'select', 'textarea'];
    const interactiveRoles = ['button', 'link', 'textbox'];
    
    if (interactiveElements.includes(tagName) || (role && interactiveRoles.includes(role))) {
      await locator.focus();
      await expect(locator).toBeFocused();
    }
    
    // Check for accessibility attributes
    const ariaLabel = await locator.getAttribute('aria-label');
    const ariaLabelledBy = await locator.getAttribute('aria-labelledby');
    const title = await locator.getAttribute('title');
    
    expect(ariaLabel || ariaLabelledBy || title).toBeTruthy();
  }

  static async expectPerformanceWithinBudget(
    action: () => Promise<void>,
    budgetMs: number,
    actionName: string
  ): Promise<void> {
    const startTime = Date.now();
    await action();
    const duration = Date.now() - startTime;
    
    expect(duration).toBeLessThanOrEqual(budgetMs);
    console.log(`✓ ${actionName} completed in ${duration}ms (budget: ${budgetMs}ms)`);
  }

  static async expectNoConsoleErrors(page: Page): Promise<void> {
    const errors: string[] = [];
    
    page.on('console', msg => {
      if (msg.type() === 'error' && !msg.text().includes('favicon')) {
        errors.push(msg.text());
      }
    });
    
    // Check after a small delay to catch any async errors
    await page.waitForTimeout(1000);
    expect(errors).toHaveLength(0);
  }
}