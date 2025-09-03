import { test, expect } from '@playwright/test';
import { DashboardPage } from '../../page-objects/dashboard-page';

test.describe('Mobile PWA Functionality', () => {
  let dashboardPage: DashboardPage;

  test.beforeEach(async ({ page, isMobile }) => {
    test.skip(!isMobile, 'PWA tests require mobile context');
    dashboardPage = new DashboardPage(page);
    await dashboardPage.goto();
  });

  test.describe('PWA Installation and Manifest', () => {
    test('should have valid PWA manifest', async ({ page }) => {
      // Check for manifest link
      const manifestLink = page.locator('link[rel="manifest"]');
      await expect(manifestLink).toBeVisible();
      
      // Fetch and validate manifest
      const manifestUrl = await manifestLink.getAttribute('href');
      expect(manifestUrl).toBeTruthy();
      
      const response = await page.request.get(manifestUrl!);
      expect(response.status()).toBe(200);
      
      const manifest = await response.json();
      
      // Validate required manifest properties
      expect(manifest.name).toBeTruthy();
      expect(manifest.short_name).toBeTruthy();
      expect(manifest.start_url).toBeTruthy();
      expect(manifest.display).toMatch(/standalone|fullscreen|minimal-ui/);
      expect(manifest.icons).toHaveLength.greaterThanOrEqual(1);
      
      // Check for proper icon sizes
      const hasRequiredIcons = manifest.icons.some((icon: any) => 
        icon.sizes.includes('192x192') || icon.sizes.includes('512x512')
      );
      expect(hasRequiredIcons).toBeTruthy();
    });

    test('should register service worker', async ({ page }) => {
      // Check for service worker registration
      const swRegistered = await page.evaluate(() => {
        return 'serviceWorker' in navigator;
      });
      expect(swRegistered).toBeTruthy();
      
      // Check if service worker is actually registered
      const swActive = await page.evaluate(() => {
        return navigator.serviceWorker.ready.then(registration => {
          return registration.active !== null;
        });
      });
      expect(swActive).toBeTruthy();
    });

    test('should support PWA install prompt', async ({ page }) => {
      // Check for beforeinstallprompt event support
      const installPromptSupport = await dashboardPage.checkPWAInstallPrompt();
      expect(installPromptSupport).toBeTruthy();
      
      // Trigger install prompt
      await dashboardPage.triggerPWAInstall();
      
      // Look for install button or prompt
      const installButton = page.locator('[data-testid="install-pwa"], .install-app, .add-to-home');
      await expect(installButton).toBeVisible({ timeout: 5000 });
    });

    test('should display app in standalone mode when installed', async ({ page }) => {
      // Simulate standalone mode
      await page.addInitScript(() => {
        Object.defineProperty(window, 'matchMedia', {
          value: (query: string) => ({
            matches: query === '(display-mode: standalone)',
            media: query,
            onchange: null,
            addListener: () => {},
            removeListener: () => {},
            addEventListener: () => {},
            removeEventListener: () => {},
            dispatchEvent: () => {},
          }),
        });
      });
      
      await page.reload();
      await dashboardPage.waitForPageLoad();
      
      // Check for standalone-specific UI adjustments
      const standaloneUI = page.locator('[data-standalone="true"], .pwa-standalone');
      await expect(standaloneUI).toBeVisible();
    });
  });

  test.describe('Offline Functionality', () => {
    test('should cache critical resources for offline use', async ({ page }) => {
      // Load the app while online
      await dashboardPage.waitForPageLoad();
      
      // Go offline
      await page.context().setOffline(true);
      
      // Reload page - should load from cache
      await page.reload();
      
      // Basic UI should still be available
      await expect(dashboardPage.mainContent).toBeVisible({ timeout: 10000 });
      await expect(dashboardPage.header).toBeVisible();
    });

    test('should show offline indicator when network is unavailable', async ({ page }) => {
      // Go offline
      await page.context().setOffline(true);
      
      // Check for offline indicator
      const offlineIndicator = page.locator('[data-testid="offline-indicator"], .offline-status, .network-offline');
      await expect(offlineIndicator).toBeVisible({ timeout: 5000 });
      
      // Should indicate offline mode
      await expect(offlineIndicator).toContainText(/offline|no connection|disconnected/i);
    });

    test('should allow task creation and management offline', async ({ page }) => {
      // Go offline
      await page.context().setOffline(true);
      
      const offlineSupport = await dashboardPage.checkOfflineFunctionality();
      expect(offlineSupport).toBeTruthy();
      
      // Verify task appears in UI
      await expect(dashboardPage.taskItems.last()).toContainText('Offline task test');
      
      // Task should be marked as pending sync
      const pendingSync = dashboardPage.taskItems.last().locator('[data-testid="sync-pending"], .pending-sync');
      await expect(pendingSync).toBeVisible();
    });

    test('should sync offline changes when connection is restored', async ({ page }) => {
      // Create task offline
      await page.context().setOffline(true);
      await dashboardPage.addTask('Offline sync test');
      
      // Come back online
      await page.context().setOffline(false);
      
      // Trigger sync
      await dashboardPage.syncOfflineChanges();
      
      // Verify sync completion
      const syncIndicator = page.locator('[data-testid="sync-indicator"], .sync-status');
      await expect(syncIndicator).toContainText(/synced|up to date/i);
      
      // Pending sync indicators should be removed
      const pendingSync = page.locator('[data-testid="sync-pending"], .pending-sync');
      await expect(pendingSync).not.toBeVisible();
    });

    test('should handle offline data conflicts gracefully', async ({ page }) => {
      // Create task while online
      await dashboardPage.addTask('Conflict test task');
      const taskId = await page.locator('[data-testid="task-item"]').first().getAttribute('data-id');
      
      // Simulate offline editing
      await page.context().setOffline(true);
      await dashboardPage.editTask(0, 'Offline edit');
      
      // Simulate server-side change while offline
      await page.context().setOffline(false);
      await page.evaluate((id) => {
        localStorage.setItem(`conflict_task_${id}`, JSON.stringify({
          title: 'Server edit',
          modified: Date.now()
        }));
      }, taskId);
      
      // Sync should detect conflict
      await dashboardPage.syncOfflineChanges();
      
      // Should show conflict resolution UI
      const conflictDialog = page.locator('[data-testid="conflict-resolution"], .conflict-dialog');
      await expect(conflictDialog).toBeVisible({ timeout: 5000 });
    });
  });

  test.describe('Mobile-Optimized UI/UX', () => {
    test('should adapt layout for mobile screens', async ({ page }) => {
      // Verify mobile-friendly viewport
      const viewport = page.viewportSize();
      expect(viewport!.width).toBeLessThanOrEqual(768);
      
      // Check for mobile-optimized navigation
      await expect(dashboardPage.mobileMenuButton).toBeVisible();
      
      // Test mobile menu functionality
      await dashboardPage.openMobileTaskMenu();
      
      const mobileMenu = page.locator('[data-testid="mobile-menu"], .mobile-nav');
      await expect(mobileMenu).toBeVisible();
    });

    test('should support touch gestures for task management', async ({ page }) => {
      await dashboardPage.addTask('Touch gesture test');
      
      const taskItem = dashboardPage.taskItems.first();
      
      // Test swipe to complete gesture
      const boundingBox = await taskItem.boundingBox();
      expect(boundingBox).toBeTruthy();
      
      // Simulate swipe right
      await page.mouse.move(boundingBox!.x + 10, boundingBox!.y + boundingBox!.height / 2);
      await page.mouse.down();
      await page.mouse.move(boundingBox!.x + 100, boundingBox!.y + boundingBox!.height / 2);
      await page.mouse.up();
      
      // Should reveal completion action or complete task
      const completedTask = taskItem.locator('[data-testid="completed"], .completed');
      await expect(completedTask).toBeVisible({ timeout: 3000 });
    });

    test('should handle long press actions', async ({ page }) => {
      await dashboardPage.addTask('Long press test');
      
      const taskItem = dashboardPage.taskItems.first();
      
      // Simulate long press (touch and hold)
      await taskItem.hover();
      await page.mouse.down();
      await page.waitForTimeout(800); // Long press duration
      await page.mouse.up();
      
      // Should show context menu or action sheet
      const contextMenu = page.locator('[data-testid="context-menu"], .action-sheet, .context-actions');
      await expect(contextMenu).toBeVisible({ timeout: 2000 });
      
      // Should have task actions
      const actions = contextMenu.locator('[data-testid="task-action"], .action-item');
      await expect(actions).toHaveCount.greaterThanOrEqual(2);
    });

    test('should optimize form inputs for mobile', async ({ page }) => {
      // Test task creation form
      await dashboardPage.addTaskButton.click();
      
      const taskInput = dashboardPage.taskInput;
      
      // Should have mobile-optimized input attributes
      const inputType = await taskInput.getAttribute('type');
      const autocomplete = await taskInput.getAttribute('autocomplete');
      
      expect(inputType).toMatch(/text|search/);
      expect(autocomplete).toBeTruthy();
      
      // Should show mobile keyboard
      await taskInput.tap();
      await expect(taskInput).toBeFocused();
      
      // Test autocomplete/suggestions
      await taskInput.type('mee');
      
      const suggestions = page.locator('[data-testid="autocomplete"], .suggestions, datalist option');
      await expect(suggestions).toBeVisible({ timeout: 3000 });
    });
  });

  test.describe('Push Notifications', () => {
    test('should request notification permissions', async ({ page }) => {
      // Mock notification API
      await page.addInitScript(() => {
        Object.defineProperty(Notification, 'permission', {
          value: 'default',
          writable: true
        });
        
        Notification.requestPermission = () => Promise.resolve('granted');
      });
      
      // Look for notification permission request
      const notificationButton = page.locator('[data-testid="enable-notifications"], .notification-prompt');
      if (await notificationButton.isVisible()) {
        await notificationButton.click();
        
        // Should update permission status
        const permissionGranted = await page.evaluate(() => {
          return Notification.permission === 'granted';
        });
        expect(permissionGranted).toBeTruthy();
      }
    });

    test('should send task reminder notifications', async ({ page }) => {
      // Setup notification permission
      await page.addInitScript(() => {
        Object.defineProperty(Notification, 'permission', { value: 'granted' });
        
        window.mockNotifications = [];
        window.Notification = class MockNotification {
          constructor(title: string, options?: NotificationOptions) {
            (window as any).mockNotifications.push({ title, ...options });
          }
        } as any;
      });
      
      // Create task with reminder
      await dashboardPage.addTaskButton.click();
      await dashboardPage.taskInput.fill('Important meeting');
      
      // Set reminder if available
      const reminderSelect = page.locator('[data-testid="reminder-select"], select[name="reminder"]');
      if (await reminderSelect.isVisible()) {
        await reminderSelect.selectOption('5min');
      }
      
      await dashboardPage.submitTaskButton.click();
      
      // Simulate time passing to trigger notification
      await page.evaluate(() => {
        // Simulate reminder trigger
        const event = new CustomEvent('reminder-due', {
          detail: { taskId: '1', title: 'Important meeting' }
        });
        window.dispatchEvent(event);
      });
      
      // Check if notification was created
      const notifications = await page.evaluate(() => (window as any).mockNotifications);
      expect(notifications).toHaveLength.greaterThanOrEqual(1);
      expect(notifications[0].title).toContain('Important meeting');
    });
  });

  test.describe('Device Integration', () => {
    test('should adapt to device orientation changes', async ({ page }) => {
      // Test portrait orientation (default mobile)
      await expect(dashboardPage.mainContent).toBeVisible();
      
      // Simulate landscape orientation
      await page.setViewportSize({ width: 667, height: 375 });
      
      // UI should adapt to landscape
      await dashboardPage.waitForPageLoad();
      
      // Check for landscape-specific layout adjustments
      const landscapeLayout = page.locator('[data-orientation="landscape"], .landscape-mode');
      await expect(landscapeLayout).toBeVisible();
      
      // Navigation should still be accessible
      await expect(dashboardPage.header).toBeVisible();
    });

    test('should integrate with device sharing capabilities', async ({ page }) => {
      // Check for Web Share API support
      const shareSupport = await page.evaluate(() => {
        return 'share' in navigator;
      });
      
      if (shareSupport) {
        // Mock share API
        await page.addInitScript(() => {
          (navigator as any).share = (data: any) => {
            (window as any).lastSharedData = data;
            return Promise.resolve();
          };
        });
        
        // Find and test share functionality
        const shareButton = page.locator('[data-testid="share"], .share-btn');
        if (await shareButton.isVisible()) {
          await shareButton.click();
          
          // Should trigger share
          const sharedData = await page.evaluate(() => (window as any).lastSharedData);
          expect(sharedData).toBeTruthy();
          expect(sharedData.title || sharedData.text).toBeTruthy();
        }
      }
    });

    test('should work with device back button', async ({ page }) => {
      // Navigate to a sub-page or modal
      await dashboardPage.addTaskButton.click();
      
      const modal = page.locator('[data-testid="modal"], .modal');
      await expect(modal).toBeVisible();
      
      // Simulate back button press
      await page.goBack();
      
      // Modal should close or navigate back
      await expect(modal).not.toBeVisible({ timeout: 3000 });
      
      // Should be back on main dashboard
      await expect(dashboardPage.mainContent).toBeVisible();
    });
  });

  test.describe('Performance on Mobile Devices', () => {
    test('should load quickly on mobile connections', async ({ page }) => {
      // Simulate slow 3G connection
      await page.context().route('**/*', async route => {
        await new Promise(resolve => setTimeout(resolve, 100)); // Add latency
        await route.continue();
      });
      
      const startTime = Date.now();
      await dashboardPage.goto();
      const loadTime = Date.now() - startTime;
      
      // Should load within reasonable time even on slow connection
      expect(loadTime).toBeLessThan(10000); // 10 seconds max
      
      // Critical content should be visible
      await expect(dashboardPage.mainContent).toBeVisible();
    });

    test('should handle memory constraints gracefully', async ({ page }) => {
      // Create many tasks to test memory usage
      for (let i = 0; i < 50; i++) {
        await dashboardPage.addTask(`Task ${i}`);
      }
      
      // Check that app remains responsive
      const responseTime = await dashboardPage.measureInteractionTime(async () => {
        await dashboardPage.addTask('Performance test task');
      });
      
      expect(responseTime).toBeLessThan(3000);
      
      // UI should still be functional
      await expect(dashboardPage.taskItems.last()).toContainText('Performance test task');
    });

    test('should optimize images and assets for mobile', async ({ page }) => {
      // Check for responsive images
      const images = page.locator('img');
      const imageCount = await images.count();
      
      for (let i = 0; i < imageCount; i++) {
        const img = images.nth(i);
        const src = await img.getAttribute('src');
        
        if (src) {
          // Should use appropriate image formats and sizes
          expect(src).toMatch(/\.(webp|jpg|jpeg|png|svg)$/i);
          
          // Check for srcset or responsive images
          const srcset = await img.getAttribute('srcset');
          if (srcset) {
            expect(srcset).toContain('w,'); // Width descriptors
          }
        }
      }
    });
  });

  test.describe('Accessibility on Mobile', () => {
    test('should support screen readers on mobile', async ({ page }) => {
      // Check for proper ARIA labels on interactive elements
      await dashboardPage.checkAriaLabel(dashboardPage.addTaskButton, /add|create|new/i);
      await dashboardPage.checkAriaLabel(dashboardPage.mobileMenuButton, /menu|navigation/i);
      
      // Check for proper heading structure
      const headings = page.locator('h1, h2, h3, h4, h5, h6');
      const headingCount = await headings.count();
      expect(headingCount).toBeGreaterThanOrEqual(1);
      
      // Main heading should be h1
      const h1 = page.locator('h1').first();
      await expect(h1).toBeVisible();
    });

    test('should have adequate touch target sizes', async ({ page }) => {
      // Check interactive elements have minimum touch target size
      const interactiveElements = page.locator('button, a, [role="button"], input, select, textarea');
      const elementCount = await interactiveElements.count();
      
      for (let i = 0; i < Math.min(elementCount, 10); i++) {
        const element = interactiveElements.nth(i);
        const boundingBox = await element.boundingBox();
        
        if (boundingBox) {
          // WCAG recommends minimum 44x44 pixels
          expect(Math.min(boundingBox.width, boundingBox.height)).toBeGreaterThanOrEqual(44);
        }
      }
    });

    test('should maintain focus management for keyboard users', async ({ page }) => {
      // Test tab navigation
      await page.keyboard.press('Tab');
      const firstFocusable = await page.locator(':focus').first();
      await expect(firstFocusable).toBeVisible();
      
      // Focus should move logically through the interface
      await page.keyboard.press('Tab');
      const secondFocusable = await page.locator(':focus').first();
      await expect(secondFocusable).toBeVisible();
      
      // Should not be the same element
      const firstId = await firstFocusable.getAttribute('id');
      const secondId = await secondFocusable.getAttribute('id');
      expect(firstId).not.toBe(secondId);
    });
  });
});