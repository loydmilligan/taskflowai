import { test as setup, expect } from '@playwright/test';
import path from 'path';

/**
 * Authentication setup for TaskFlow AI tests
 * Handles login flow and saves authentication state
 */
const authFile = path.join(__dirname, '../auth-state.json');

setup('authenticate', async ({ page }) => {
  console.log('🔐 Setting up authentication...');
  
  // Navigate to the TaskFlow AI application
  await page.goto('/');
  
  // Wait for the application to load
  await expect(page.locator('body')).toBeVisible();
  
  // Check if authentication is required
  const hasLoginForm = await page.locator('[data-testid="login-form"]').isVisible().catch(() => false);
  
  if (hasLoginForm) {
    // Fill in test credentials if login form exists
    await page.fill('[data-testid="email-input"]', 'test@taskflow.ai');
    await page.fill('[data-testid="password-input"]', 'testpassword123');
    await page.click('[data-testid="login-button"]');
    
    // Wait for successful login
    await expect(page.locator('[data-testid="dashboard"]')).toBeVisible({ timeout: 10000 });
  } else {
    // For applications without authentication, just ensure the app is ready
    await expect(page.locator('main, .app-container, #app')).toBeVisible({ timeout: 10000 });
  }
  
  // Verify the application is fully loaded
  await page.waitForLoadState('networkidle');
  
  // Set test mode flag
  await page.evaluate(() => {
    localStorage.setItem('playwright_test_mode', 'true');
    localStorage.setItem('test_session_id', Date.now().toString());
  });
  
  // Save signed-in state to reuse in other tests
  await page.context().storageState({ path: authFile });
  
  console.log('✅ Authentication setup completed');
});