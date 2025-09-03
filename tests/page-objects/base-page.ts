import { Page, Locator, expect } from '@playwright/test';

/**
 * Base Page Object for TaskFlow AI
 * Contains common functionality shared across all page objects
 */
export abstract class BasePage {
  protected page: Page;

  constructor(page: Page) {
    this.page = page;
  }

  // Common locators
  get loadingSpinner(): Locator {
    return this.page.locator('[data-testid="loading-spinner"], .loading, .spinner');
  }

  get errorMessage(): Locator {
    return this.page.locator('[data-testid="error-message"], .error-message, .alert-error');
  }

  get successMessage(): Locator {
    return this.page.locator('[data-testid="success-message"], .success-message, .alert-success');
  }

  get modalDialog(): Locator {
    return this.page.locator('[data-testid="modal"], .modal, .dialog');
  }

  get mobileMenuButton(): Locator {
    return this.page.locator('[data-testid="mobile-menu-button"], .mobile-menu-toggle, .hamburger');
  }

  // Common actions
  async waitForPageLoad(): Promise<void> {
    await this.page.waitForLoadState('networkidle');
    await expect(this.loadingSpinner).not.toBeVisible({ timeout: 10000 }).catch(() => {});
  }

  async clickAndWait(locator: Locator, timeout: number = 5000): Promise<void> {
    await locator.click();
    await this.page.waitForTimeout(500); // Small delay for animations
  }

  async typeWithDelay(locator: Locator, text: string, delay: number = 50): Promise<void> {
    await locator.click();
    await locator.fill('');
    await locator.type(text, { delay });
  }

  async waitForToast(message?: string): Promise<void> {
    const toast = this.page.locator('.toast, .notification, [data-testid="toast"]').first();
    await expect(toast).toBeVisible({ timeout: 5000 });
    
    if (message) {
      await expect(toast).toContainText(message);
    }
  }

  async dismissModal(): Promise<void> {
    const modal = this.modalDialog;
    if (await modal.isVisible()) {
      const closeButton = modal.locator('[data-testid="close-modal"], .close, .modal-close').first();
      if (await closeButton.isVisible()) {
        await closeButton.click();
      } else {
        await this.page.keyboard.press('Escape');
      }
    }
  }

  async scrollToElement(locator: Locator): Promise<void> {
    await locator.scrollIntoViewIfNeeded();
  }

  async waitForNetworkIdle(timeout: number = 5000): Promise<void> {
    await this.page.waitForLoadState('networkidle', { timeout });
  }

  // Mobile-specific helpers
  async openMobileMenu(): Promise<void> {
    if (await this.mobileMenuButton.isVisible()) {
      await this.mobileMenuButton.click();
      await this.page.waitForTimeout(300); // Animation delay
    }
  }

  async closeMobileMenu(): Promise<void> {
    // Try clicking outside the menu or pressing escape
    await this.page.keyboard.press('Escape');
    await this.page.waitForTimeout(300);
  }

  // PWA-specific helpers
  async checkPWAInstallPrompt(): Promise<boolean> {
    return await this.page.evaluate(() => {
      return 'serviceWorker' in navigator && 'onbeforeinstallprompt' in window;
    });
  }

  async triggerPWAInstall(): Promise<void> {
    await this.page.evaluate(() => {
      window.dispatchEvent(new Event('beforeinstallprompt'));
    });
  }

  // Accessibility helpers
  async checkAriaLabel(locator: Locator, expectedLabel: string): Promise<void> {
    await expect(locator).toHaveAttribute('aria-label', expectedLabel);
  }

  async checkFocusManagement(locator: Locator): Promise<void> {
    await locator.focus();
    await expect(locator).toBeFocused();
  }

  // Performance helpers
  async measurePageLoadTime(): Promise<number> {
    return await this.page.evaluate(() => {
      const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
      return navigation.loadEventEnd - navigation.navigationStart;
    });
  }

  async measureInteractionTime(action: () => Promise<void>): Promise<number> {
    const startTime = Date.now();
    await action();
    return Date.now() - startTime;
  }

  // Screenshot helpers
  async takeScreenshot(name: string): Promise<void> {
    await this.page.screenshot({ 
      path: `test-results/screenshots/${name}-${Date.now()}.png`,
      fullPage: true 
    });
  }

  // Responsive design helpers
  async setViewportSize(width: number, height: number): Promise<void> {
    await this.page.setViewportSize({ width, height });
  }

  async testResponsiveBreakpoints(): Promise<void> {
    const breakpoints = [
      { name: 'mobile', width: 375, height: 667 },
      { name: 'tablet', width: 768, height: 1024 },
      { name: 'desktop', width: 1920, height: 1080 }
    ];

    for (const breakpoint of breakpoints) {
      await this.setViewportSize(breakpoint.width, breakpoint.height);
      await this.waitForPageLoad();
      await this.takeScreenshot(`responsive-${breakpoint.name}`);
    }
  }
}