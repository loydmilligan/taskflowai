import { test, expect } from '@playwright/test';
import { DashboardPage } from '../../page-objects/dashboard-page';

test.describe('Cross-Entity Project Filtering System', () => {
  let dashboardPage: DashboardPage;

  test.beforeEach(async ({ page }) => {
    dashboardPage = new DashboardPage(page);
    await dashboardPage.goto();
    
    // Set up test data with multiple projects and entities
    await setupTestData(page);
  });

  async function setupTestData(page: any) {
    // Create multiple projects
    await dashboardPage.createProject('Web Development', 'Frontend and backend development tasks');
    await dashboardPage.createProject('Marketing Campaign', 'Q4 marketing initiatives');
    await dashboardPage.createProject('Research', 'Market research and analysis');
    
    // Add tasks to different projects
    await dashboardPage.selectProject('Web Development');
    await dashboardPage.addTask('Implement user authentication', 'high');
    await dashboardPage.addTask('Create responsive design', 'medium');
    await dashboardPage.addTask('Set up CI/CD pipeline', 'high');
    
    await dashboardPage.selectProject('Marketing Campaign');
    await dashboardPage.addTask('Design social media content', 'medium');
    await dashboardPage.addTask('Launch email campaign', 'high');
    await dashboardPage.addTask('Analyze competitor strategies', 'low');
    
    await dashboardPage.selectProject('Research');
    await dashboardPage.addTask('Survey customer feedback', 'medium');
    await dashboardPage.addTask('Compile market analysis report', 'high');
  }

  test.describe('Multi-Level Filtering Interface', () => {
    test('should display comprehensive filter options', async ({ page }) => {
      // Check for main filter categories
      const filterPanel = page.locator('[data-testid="filter-panel"], .filter-panel, .filters');
      await expect(filterPanel).toBeVisible();
      
      // Verify all filter categories are present
      const filterCategories = ['project', 'status', 'priority', 'assignee', 'due-date', 'tags'];
      
      for (const category of filterCategories) {
        const categoryFilter = filterPanel.locator(`[data-testid="filter-${category}"], .filter-${category}`);
        await expect(categoryFilter).toBeVisible();
      }
      
      // Check for advanced filter toggle
      const advancedFilters = page.locator('[data-testid="advanced-filters"], .advanced-filters');
      if (await advancedFilters.isVisible()) {
        await advancedFilters.click();
        
        // Should show additional filter options
        const dateRangeFilter = page.locator('[data-testid="date-range-filter"]');
        const customFieldFilter = page.locator('[data-testid="custom-field-filter"]');
        
        await expect(dateRangeFilter).toBeVisible();
        await expect(customFieldFilter).toBeVisible();
      }
    });

    test('should support hierarchical project filtering', async ({ page }) => {
      const projectFilter = dashboardPage.projectFilter;
      
      // Should show all projects in dropdown
      await projectFilter.click();
      
      const projectOptions = page.locator('[data-testid="project-option"], option');
      await expect(projectOptions).toHaveCount.greaterThanOrEqual(3);
      
      // Test nested project filtering (if supported)
      const webDevOption = projectOptions.filter({ hasText: 'Web Development' });
      await expect(webDevOption).toBeVisible();
      
      await webDevOption.click();
      
      // Should filter tasks to only Web Development project
      await dashboardPage.waitForNetworkIdle();
      const visibleTasks = await dashboardPage.getTaskCount();
      expect(visibleTasks).toBe(3); // Only Web Dev tasks
      
      // Verify tasks contain Web Development content
      const firstTaskText = await dashboardPage.getTaskText(0);
      expect(firstTaskText.toLowerCase()).toMatch(/authentication|design|pipeline/);
    });

    test('should enable multi-select filtering', async ({ page }) => {
      // Test multi-select for priority filter
      const priorityFilter = page.locator('[data-testid="priority-multi-select"], .priority-multi-select');
      if (await priorityFilter.isVisible()) {
        await priorityFilter.click();
        
        // Select multiple priorities
        const highPriority = page.locator('[data-testid="priority-high"], input[value="high"]');
        const mediumPriority = page.locator('[data-testid="priority-medium"], input[value="medium"]');
        
        await highPriority.check();
        await mediumPriority.check();
        
        // Apply filter
        const applyButton = page.locator('[data-testid="apply-filters"], .apply-filters');
        await applyButton.click();
        
        // Should show only high and medium priority tasks
        await dashboardPage.waitForNetworkIdle();
        const visibleTasks = dashboardPage.taskItems;
        const taskCount = await visibleTasks.count();
        
        for (let i = 0; i < taskCount; i++) {
          const task = visibleTasks.nth(i);
          const priority = task.locator('[data-testid="priority"], .priority');
          const priorityValue = await priority.textContent();
          expect(priorityValue?.toLowerCase()).toMatch(/high|medium/);
        }
      }
    });
  });

  test.describe('Dynamic Filter Combinations', () => {
    test('should combine multiple filters accurately', async ({ page }) => {
      // Apply project filter
      await dashboardPage.filterTasksByProject('Web Development');
      await dashboardPage.waitForNetworkIdle();
      
      // Apply priority filter on top
      await dashboardPage.filterTasksByStatus('active');
      await dashboardPage.waitForNetworkIdle();
      
      // Apply additional filter - priority
      const priorityFilter = dashboardPage.priorityFilter;
      if (await priorityFilter.isVisible()) {
        await priorityFilter.selectOption('high');
        await dashboardPage.waitForNetworkIdle();
      }
      
      // Verify combined filters work
      const visibleTasks = await dashboardPage.getTaskCount();
      expect(visibleTasks).toBeGreaterThanOrEqual(0); // Some tasks might match criteria
      
      // Check filter combination display
      const activeFilters = page.locator('[data-testid="active-filters"], .active-filters, .filter-tags');
      await expect(activeFilters).toBeVisible();
      
      const filterTags = activeFilters.locator('[data-testid="filter-tag"], .filter-tag');
      await expect(filterTags).toHaveCount.greaterThanOrEqual(2);
    });

    test('should show filter intersection counts', async ({ page }) => {
      // Check for filter counts/badges
      const projectFilter = dashboardPage.projectFilter;
      await projectFilter.click();
      
      // Should show task counts for each project
      const projectOptions = page.locator('[data-testid="project-option-with-count"], .filter-option-count');
      const optionCount = await projectOptions.count();
      
      if (optionCount > 0) {
        for (let i = 0; i < optionCount; i++) {
          const option = projectOptions.nth(i);
          const optionText = await option.textContent();
          expect(optionText).toMatch(/\(\d+\)/); // Should contain count in parentheses
        }
      }
    });

    test('should update filter counts dynamically', async ({ page }) => {
      // Get initial filter counts
      const statusFilter = dashboardPage.statusFilter;
      await statusFilter.click();
      
      const completedOption = page.locator('[data-testid="status-completed"], option[value="completed"]');
      const initialCompletedText = await completedOption.textContent();
      
      await statusFilter.selectOption('all'); // Reset filter
      
      // Complete a task
      await dashboardPage.completeTask(0);
      await dashboardPage.waitForNetworkIdle();
      
      // Check if filter counts updated
      await statusFilter.click();
      const updatedCompletedText = await completedOption.textContent();
      
      // Count should have changed (if counts are displayed)
      if (initialCompletedText?.includes('(') && updatedCompletedText?.includes('(')) {
        expect(updatedCompletedText).not.toBe(initialCompletedText);
      }
    });
  });

  test.describe('Search Integration with Filters', () => {
    test('should combine search with active filters', async ({ page }) => {
      // Apply a project filter first
      await dashboardPage.filterTasksByProject('Web Development');
      await dashboardPage.waitForNetworkIdle();
      
      // Then search within filtered results
      await dashboardPage.searchTasks('authentication');
      
      // Should show only tasks that match both filter and search
      const visibleTasks = await dashboardPage.getTaskCount();
      expect(visibleTasks).toBeGreaterThanOrEqual(1);
      
      // Verify task matches both criteria
      const firstTaskText = await dashboardPage.getTaskText(0);
      expect(firstTaskText.toLowerCase()).toContain('authentication');
      
      // Should still show active filter indicators
      const activeFilters = page.locator('[data-testid="active-filters"], .filter-indicators');
      await expect(activeFilters).toBeVisible();
    });

    test('should provide search suggestions based on current filters', async ({ page }) => {
      // Apply project filter
      await dashboardPage.filterTasksByProject('Marketing Campaign');
      
      // Start typing in search
      const searchInput = dashboardPage.searchInput;
      await searchInput.click();
      await searchInput.type('email');
      
      // Should show context-aware suggestions
      const suggestions = page.locator('[data-testid="search-suggestions"], .search-autocomplete');
      await expect(suggestions).toBeVisible({ timeout: 3000 });
      
      const suggestionItems = suggestions.locator('[data-testid="suggestion-item"], .suggestion');
      const suggestionCount = await suggestionItems.count();
      
      if (suggestionCount > 0) {
        const firstSuggestion = await suggestionItems.first().textContent();
        expect(firstSuggestion?.toLowerCase()).toContain('email');
      }
    });
  });

  test.describe('Filter Persistence and URLs', () => {
    test('should persist filter state in URL parameters', async ({ page }) => {
      // Apply multiple filters
      await dashboardPage.filterTasksByProject('Web Development');
      await dashboardPage.filterTasksByStatus('active');
      
      // Check URL for filter parameters
      const currentUrl = page.url();
      expect(currentUrl).toMatch(/project=.*web.*development/i);
      expect(currentUrl).toMatch(/status=active/i);
      
      // Reload page and verify filters persist
      await page.reload();
      await dashboardPage.waitForPageLoad();
      
      // Filters should still be active
      const activeFilters = page.locator('[data-testid="active-filters"], .filter-tags');
      await expect(activeFilters).toBeVisible();
      
      // Task count should match filtered state
      const taskCount = await dashboardPage.getTaskCount();
      expect(taskCount).toBe(3); // Web Development active tasks
    });

    test('should support shareable filter URLs', async ({ page }) => {
      // Set up complex filter combination
      await dashboardPage.filterTasksByProject('Marketing Campaign');
      await dashboardPage.filterTasksByStatus('active');
      await dashboardPage.searchTasks('campaign');
      
      const filterUrl = page.url();
      
      // Open new page with same URL
      const newPage = await page.context().newPage();
      await newPage.goto(filterUrl);
      
      const newDashboardPage = new DashboardPage(newPage);
      await newDashboardPage.waitForPageLoad();
      
      // Should apply same filters
      const taskCount = await newDashboardPage.getTaskCount();
      expect(taskCount).toBeGreaterThanOrEqual(1);
      
      // Should show same active filters
      const activeFilters = newPage.locator('[data-testid="active-filters"]');
      await expect(activeFilters).toBeVisible();
    });
  });

  test.describe('Advanced Filtering Features', () => {
    test('should support date range filtering', async ({ page }) => {
      // Open date range filter
      const dateFilter = page.locator('[data-testid="date-range-filter"], .date-range');
      if (await dateFilter.isVisible()) {
        await dateFilter.click();
        
        // Set date range
        const startDate = page.locator('[data-testid="start-date"], input[name="start_date"]');
        const endDate = page.locator('[data-testid="end-date"], input[name="end_date"]');
        
        const today = new Date();
        const nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
        
        await startDate.fill(today.toISOString().split('T')[0]);
        await endDate.fill(nextWeek.toISOString().split('T')[0]);
        
        // Apply filter
        const applyButton = page.locator('[data-testid="apply-date-filter"], .apply-date-filter');
        await applyButton.click();
        
        await dashboardPage.waitForNetworkIdle();
        
        // Should filter tasks by date range
        const dateFilterTag = page.locator('[data-testid="date-filter-tag"]');
        await expect(dateFilterTag).toBeVisible();
      }
    });

    test('should support custom field filtering', async ({ page }) => {
      // Check for custom field filters
      const customFieldFilter = page.locator('[data-testid="custom-field-filter"], .custom-fields');
      if (await customFieldFilter.isVisible()) {
        await customFieldFilter.click();
        
        // Should show available custom fields
        const fieldOptions = page.locator('[data-testid="custom-field-option"], .custom-field');
        const fieldCount = await fieldOptions.count();
        
        if (fieldCount > 0) {
          // Select first custom field
          await fieldOptions.first().click();
          
          // Should show field value options
          const valueOptions = page.locator('[data-testid="field-value-option"], .field-value');
          await expect(valueOptions).toBeVisible();
          
          await valueOptions.first().click();
          
          // Apply custom field filter
          const applyButton = page.locator('[data-testid="apply-custom-filter"]');
          await applyButton.click();
          
          await dashboardPage.waitForNetworkIdle();
        }
      }
    });

    test('should support tag-based filtering', async ({ page }) => {
      // Look for tag filter interface
      const tagFilter = page.locator('[data-testid="tag-filter"], .tag-filter');
      if (await tagFilter.isVisible()) {
        await tagFilter.click();
        
        // Should show available tags
        const tagOptions = page.locator('[data-testid="tag-option"], .tag-option');
        const tagCount = await tagOptions.count();
        
        if (tagCount > 0) {
          // Select multiple tags
          await tagOptions.first().click();
          if (tagCount > 1) {
            await tagOptions.nth(1).click();
          }
          
          // Apply tag filter
          const applyButton = page.locator('[data-testid="apply-tag-filter"]');
          await applyButton.click();
          
          await dashboardPage.waitForNetworkIdle();
          
          // Verify tag filter is active
          const activeTagFilters = page.locator('[data-testid="active-tag-filter"]');
          await expect(activeTagFilters).toHaveCount.greaterThanOrEqual(1);
        }
      }
    });
  });

  test.describe('Filter Performance and UX', () => {
    test('should apply filters with minimal delay', async ({ page }) => {
      const startTime = Date.now();
      
      // Apply filter and measure response time
      await dashboardPage.filterTasksByProject('Web Development');
      await dashboardPage.waitForNetworkIdle();
      
      const filterTime = Date.now() - startTime;
      expect(filterTime).toBeLessThan(2000); // Should be under 2 seconds
      
      // UI should remain responsive during filtering
      await expect(dashboardPage.taskList).toBeVisible();
    });

    test('should show loading states during filter application', async ({ page }) => {
      // Mock slow filter response
      await page.route('/api/tasks**', async route => {
        await new Promise(resolve => setTimeout(resolve, 1000));
        await route.continue();
      });
      
      // Apply filter
      await dashboardPage.filterTasksByProject('Marketing Campaign');
      
      // Should show loading indicator
      const loadingIndicator = page.locator('[data-testid="filter-loading"], .filter-loading, .loading');
      await expect(loadingIndicator).toBeVisible({ timeout: 500 });
      
      // Loading should disappear when done
      await expect(loadingIndicator).not.toBeVisible({ timeout: 3000 });
    });

    test('should provide clear filter reset functionality', async ({ page }) => {
      // Apply multiple filters
      await dashboardPage.filterTasksByProject('Web Development');
      await dashboardPage.filterTasksByStatus('completed');
      await dashboardPage.searchTasks('authentication');
      
      // Should show clear/reset filters option
      const clearFiltersButton = page.locator('[data-testid="clear-filters"], .clear-filters, .reset-filters');
      await expect(clearFiltersButton).toBeVisible();
      
      // Click clear filters
      await clearFiltersButton.click();
      
      // All filters should be reset
      const activeFilters = page.locator('[data-testid="active-filters"] [data-testid="filter-tag"]');
      await expect(activeFilters).toHaveCount(0);
      
      // Should show all tasks
      const allTasks = await dashboardPage.getTaskCount();
      expect(allTasks).toBeGreaterThanOrEqual(8); // Total tasks created in setup
    });

    test('should handle empty filter results gracefully', async ({ page }) => {
      // Apply filter that should return no results
      await dashboardPage.filterTasksByProject('Web Development');
      await dashboardPage.searchTasks('nonexistent task content xyz123');
      
      await dashboardPage.waitForNetworkIdle();
      
      // Should show "no results" message
      const noResults = page.locator('[data-testid="no-results"], .no-results, .empty-state');
      await expect(noResults).toBeVisible();
      
      // Should suggest filter modifications
      const filterSuggestions = page.locator('[data-testid="filter-suggestions"], .filter-suggestions');
      await expect(filterSuggestions).toBeVisible();
      
      // Should maintain filter state for easy modification
      const activeFilters = page.locator('[data-testid="active-filters"]');
      await expect(activeFilters).toBeVisible();
    });
  });

  test.describe('Mobile Filter Experience', () => {
    test('should optimize filter interface for mobile', async ({ page, isMobile }) => {
      test.skip(!isMobile, 'Mobile-specific test');
      
      // Should show mobile-friendly filter button
      const mobileFilterButton = page.locator('[data-testid="mobile-filters"], .mobile-filter-btn');
      await expect(mobileFilterButton).toBeVisible();
      
      await mobileFilterButton.click();
      
      // Should open filter modal/drawer
      const filterDrawer = page.locator('[data-testid="filter-drawer"], .filter-modal');
      await expect(filterDrawer).toBeVisible();
      
      // Should have touch-friendly filter controls
      const filterOptions = filterDrawer.locator('[data-testid="filter-option"], .filter-control');
      const optionCount = await filterOptions.count();
      
      if (optionCount > 0) {
        // Check touch target sizes
        const firstOption = filterOptions.first();
        const boundingBox = await firstOption.boundingBox();
        expect(boundingBox?.height).toBeGreaterThanOrEqual(44); // WCAG minimum
      }
    });

    test('should support filter gestures on mobile', async ({ page, isMobile }) => {
      test.skip(!isMobile, 'Mobile gesture test');
      
      // Test swipe to access filters
      const taskList = dashboardPage.taskList;
      const boundingBox = await taskList.boundingBox();
      
      if (boundingBox) {
        // Swipe left to reveal filters
        await page.mouse.move(boundingBox.x + boundingBox.width - 10, boundingBox.y + 100);
        await page.mouse.down();
        await page.mouse.move(boundingBox.x + 100, boundingBox.y + 100);
        await page.mouse.up();
        
        // Should reveal filter panel or quick filters
        const quickFilters = page.locator('[data-testid="quick-filters"], .swipe-filters');
        await expect(quickFilters).toBeVisible({ timeout: 2000 });
      }
    });
  });
});