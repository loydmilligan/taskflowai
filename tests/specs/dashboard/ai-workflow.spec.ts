import { test, expect } from '@playwright/test';
import { DashboardPage } from '../../page-objects/dashboard-page';

test.describe('AI-Guided Daily Workflow Features', () => {
  let dashboardPage: DashboardPage;

  test.beforeEach(async ({ page }) => {
    dashboardPage = new DashboardPage(page);
    await dashboardPage.goto();
  });

  test.describe('Daily Workflow Initialization', () => {
    test('should display daily workflow suggestions on dashboard load', async ({ page }) => {
      // Wait for AI suggestions to load
      const workflowSuggestions = page.locator('[data-testid="workflow-suggestions"], .workflow-suggestions');
      await expect(workflowSuggestions).toBeVisible({ timeout: 10000 });
      
      // Check for at least 3 workflow suggestions
      const suggestions = workflowSuggestions.locator('.suggestion-item');
      await expect(suggestions).toHaveCount.greaterThanOrEqual(3);
      
      // Verify suggestions contain actionable content
      const firstSuggestion = suggestions.first();
      await expect(firstSuggestion).not.toBeEmpty();
    });

    test('should allow users to accept workflow suggestions', async ({ page }) => {
      const workflowSuggestions = page.locator('[data-testid="workflow-suggestions"]');
      await expect(workflowSuggestions).toBeVisible();
      
      const acceptButton = workflowSuggestions.locator('[data-testid="accept-suggestion"]').first();
      await acceptButton.click();
      
      // Verify task is created from suggestion
      await expect(dashboardPage.taskItems).toHaveCount.greaterThanOrEqual(1);
      
      // Verify success feedback
      await dashboardPage.waitForToast('Workflow suggestion accepted');
    });

    test('should provide personalized workflow based on user history', async ({ page }) => {
      // Create some task history
      await dashboardPage.addTask('Review morning emails', 'high');
      await dashboardPage.addTask('Prepare presentation', 'medium');
      await dashboardPage.completeTask(0);
      
      // Refresh to trigger AI analysis
      await page.reload();
      await dashboardPage.waitForPageLoad();
      
      // Check for personalized recommendations
      const personalizedSection = page.locator('[data-testid="personalized-workflow"]');
      await expect(personalizedSection).toBeVisible({ timeout: 15000 });
      
      const recommendations = personalizedSection.locator('.recommendation');
      await expect(recommendations).toHaveCount.greaterThanOrEqual(2);
    });
  });

  test.describe('Smart Task Prioritization', () => {
    test('should automatically prioritize tasks based on AI analysis', async ({ page }) => {
      // Add multiple tasks
      await dashboardPage.addTask('Urgent client meeting prep');
      await dashboardPage.addTask('File quarterly reports');
      await dashboardPage.addTask('Team building lunch');
      
      // Trigger AI prioritization
      const prioritizeButton = page.locator('[data-testid="ai-prioritize"], .btn-prioritize');
      await prioritizeButton.click();
      
      // Wait for AI processing
      await page.waitForTimeout(2000);
      
      // Verify tasks are reordered by priority
      const firstTask = dashboardPage.taskItems.first();
      const priorityIndicator = firstTask.locator('[data-testid="priority-high"], .priority-high');
      await expect(priorityIndicator).toBeVisible();
    });

    test('should suggest optimal work schedule', async ({ page }) => {
      // Add tasks with different time estimates
      await dashboardPage.addTask('Deep work: Code review (2 hours)');
      await dashboardPage.addTask('Quick email responses (30 minutes)');
      await dashboardPage.addTask('Research competitor analysis (1 hour)');
      
      // Open schedule optimizer
      const scheduleButton = page.locator('[data-testid="optimize-schedule"], .schedule-optimizer');
      await scheduleButton.click();
      
      // Verify schedule suggestions appear
      const scheduleModal = page.locator('[data-testid="schedule-modal"]');
      await expect(scheduleModal).toBeVisible();
      
      const timeBlocks = scheduleModal.locator('[data-testid="time-block"]');
      await expect(timeBlocks).toHaveCount.greaterThanOrEqual(3);
      
      // Check for energy-based scheduling
      const morningBlock = scheduleModal.locator('[data-testid="morning-block"]');
      await expect(morningBlock).toContainText(/deep work|focus/i);
    });
  });

  test.describe('Adaptive Workflow Intelligence', () => {
    test('should learn from user behavior and adapt suggestions', async ({ page }) => {
      // Simulate user pattern: always does emails first
      await dashboardPage.addTask('Check emails');
      await dashboardPage.addTask('Work on project');
      await dashboardPage.completeTask(0); // Complete emails first
      
      // Add similar tasks
      await dashboardPage.addTask('Reply to client emails');
      await dashboardPage.addTask('Continue project work');
      
      // Check if AI suggests emails first
      const aiSuggestion = page.locator('[data-testid="ai-suggestion"]');
      await expect(aiSuggestion).toContainText(/email/i);
    });

    test('should provide context-aware task recommendations', async ({ page }) => {
      // Set context (e.g., time of day, day of week)
      await page.evaluate(() => {
        localStorage.setItem('current_context', JSON.stringify({
          time: '09:00',
          day: 'Monday',
          energy_level: 'high'
        }));
      });
      
      await page.reload();
      await dashboardPage.waitForPageLoad();
      
      // Check for context-aware recommendations
      const contextRecommendations = page.locator('[data-testid="context-recommendations"]');
      await expect(contextRecommendations).toBeVisible();
      
      const mondayMorningTasks = contextRecommendations.locator('.task-suggestion');
      await expect(mondayMorningTasks).toHaveCount.greaterThanOrEqual(2);
    });

    test('should provide break and focus time suggestions', async ({ page }) => {
      // Simulate extended work session
      await page.evaluate(() => {
        localStorage.setItem('work_session_start', (Date.now() - 90 * 60 * 1000).toString()); // 90 minutes ago
      });
      
      await page.reload();
      await dashboardPage.waitForPageLoad();
      
      // Check for break suggestion
      const breakSuggestion = page.locator('[data-testid="break-suggestion"]');
      await expect(breakSuggestion).toBeVisible({ timeout: 5000 });
      await expect(breakSuggestion).toContainText(/break|rest/i);
    });
  });

  test.describe('Workflow Analytics and Insights', () => {
    test('should display productivity metrics', async ({ page }) => {
      // Create some completed tasks for analytics
      await dashboardPage.addTask('Task 1');
      await dashboardPage.addTask('Task 2');
      await dashboardPage.completeTask(0);
      
      // Open analytics panel
      const analyticsButton = page.locator('[data-testid="analytics"], .analytics-btn');
      await analyticsButton.click();
      
      // Verify metrics are displayed
      const metricsPanel = page.locator('[data-testid="metrics-panel"]');
      await expect(metricsPanel).toBeVisible();
      
      const completionRate = metricsPanel.locator('[data-testid="completion-rate"]');
      const avgTaskTime = metricsPanel.locator('[data-testid="avg-task-time"]');
      
      await expect(completionRate).toBeVisible();
      await expect(avgTaskTime).toBeVisible();
    });

    test('should provide weekly workflow insights', async ({ page }) => {
      // Navigate to insights section
      const insightsTab = page.locator('[data-testid="insights-tab"]');
      await insightsTab.click();
      
      // Check for weekly insights
      const weeklyInsights = page.locator('[data-testid="weekly-insights"]');
      await expect(weeklyInsights).toBeVisible();
      
      // Verify insight categories
      const categories = ['productivity', 'focus-time', 'task-patterns'];
      for (const category of categories) {
        const categorySection = weeklyInsights.locator(`[data-testid="${category}-insight"]`);
        await expect(categorySection).toBeVisible();
      }
    });
  });

  test.describe('Mobile Workflow Optimization', () => {
    test('should adapt workflow for mobile users', async ({ page, isMobile }) => {
      test.skip(!isMobile, 'Mobile-specific test');
      
      // Check for mobile-optimized workflow suggestions
      const mobileWorkflow = page.locator('[data-testid="mobile-workflow"]');
      await expect(mobileWorkflow).toBeVisible();
      
      // Verify touch-friendly task interaction
      const quickActions = page.locator('[data-testid="quick-actions"]');
      await expect(quickActions).toBeVisible();
      
      // Test swipe gestures for task management
      const firstTask = dashboardPage.taskItems.first();
      await firstTask.hover();
      
      // Simulate swipe right to complete
      await page.mouse.down();
      await page.mouse.move(100, 0);
      await page.mouse.up();
      
      // Verify task completion
      await expect(firstTask).toHaveClass(/completed/);
    });

    test('should provide voice-enabled workflow commands', async ({ page, isMobile }) => {
      test.skip(!isMobile, 'Mobile voice feature test');
      
      // Check for voice command button
      const voiceButton = page.locator('[data-testid="voice-workflow"], .voice-btn');
      await expect(voiceButton).toBeVisible();
      
      await voiceButton.click();
      
      // Mock voice input
      await page.evaluate(() => {
        const event = new CustomEvent('speechresult', {
          detail: { transcript: 'Add task call client about project update' }
        });
        window.dispatchEvent(event);
      });
      
      // Verify task was created from voice command
      await expect(dashboardPage.taskItems.last()).toContainText('call client');
    });
  });

  test.describe('Collaboration and Team Workflow', () => {
    test('should suggest collaborative workflows', async ({ page }) => {
      // Add team-related tasks
      await dashboardPage.addTask('Review team presentation');
      await dashboardPage.addTask('Prepare for client meeting');
      
      // Trigger team workflow analysis
      const teamWorkflowButton = page.locator('[data-testid="team-workflow"]');
      await teamWorkflowButton.click();
      
      // Check for collaboration suggestions
      const collabSuggestions = page.locator('[data-testid="collaboration-suggestions"]');
      await expect(collabSuggestions).toBeVisible();
      
      const suggestions = collabSuggestions.locator('.suggestion');
      await expect(suggestions).toHaveCount.greaterThanOrEqual(2);
    });

    test('should integrate with team calendar for optimal scheduling', async ({ page }) => {
      // Mock calendar integration
      await page.evaluate(() => {
        window.mockCalendarEvents = [
          { title: 'Team Standup', time: '09:00', duration: 30 },
          { title: 'Client Call', time: '14:00', duration: 60 }
        ];
      });
      
      // Open calendar-aware scheduling
      const calendarScheduleButton = page.locator('[data-testid="calendar-schedule"]');
      await calendarScheduleButton.click();
      
      // Verify tasks are scheduled around calendar events
      const scheduleView = page.locator('[data-testid="schedule-view"]');
      await expect(scheduleView).toBeVisible();
      
      const freeTimeSlots = scheduleView.locator('[data-testid="free-time-slot"]');
      await expect(freeTimeSlots).toHaveCount.greaterThanOrEqual(2);
    });
  });

  test.describe('Performance and Reliability', () => {
    test('should load AI suggestions within performance budget', async ({ page }) => {
      const startTime = Date.now();
      
      await dashboardPage.goto();
      
      // Wait for AI suggestions to load
      const suggestions = page.locator('[data-testid="workflow-suggestions"]');
      await expect(suggestions).toBeVisible();
      
      const loadTime = Date.now() - startTime;
      expect(loadTime).toBeLessThan(5000); // 5 second budget
    });

    test('should gracefully handle AI service unavailability', async ({ page }) => {
      // Mock AI service failure
      await page.route('/api/ai/**', route => {
        route.fulfill({ status: 503, body: 'Service Unavailable' });
      });
      
      await dashboardPage.goto();
      
      // Should show fallback workflow options
      const fallbackWorkflow = page.locator('[data-testid="fallback-workflow"]');
      await expect(fallbackWorkflow).toBeVisible();
      
      // Basic functionality should still work
      await dashboardPage.addTask('Manual task creation');
      await expect(dashboardPage.taskItems).toHaveCount(1);
    });

    test('should maintain workflow state during connectivity issues', async ({ page }) => {
      // Add tasks
      await dashboardPage.addTask('Important task');
      await dashboardPage.addTask('Another task');
      
      // Simulate offline
      await page.context().setOffline(true);
      
      // Interact with workflow
      await dashboardPage.completeTask(0);
      
      // Come back online
      await page.context().setOffline(false);
      await page.reload();
      await dashboardPage.waitForPageLoad();
      
      // Verify state is preserved
      const completedTasks = page.locator('[data-testid="task-item"].completed');
      await expect(completedTasks).toHaveCount(1);
    });
  });
});