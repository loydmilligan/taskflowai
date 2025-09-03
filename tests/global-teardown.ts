import { chromium, FullConfig } from '@playwright/test';
import fs from 'fs';
import path from 'path';

/**
 * Global teardown for TaskFlow AI Playwright tests
 * Handles cleanup of test data and resource management
 */
async function globalTeardown(config: FullConfig) {
  console.log('🧹 Starting TaskFlow AI Test Teardown...');
  
  // Initialize browser for cleanup tasks
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    // Navigate to the application for cleanup
    const baseURL = config.use?.baseURL || 'http://localhost:8080';
    await page.goto(baseURL, { waitUntil: 'networkidle' });
    
    // Clean up test data
    await cleanupTestData(page);
    
    // Clean up temporary files
    await cleanupTempFiles();
    
    console.log('✅ Global teardown completed successfully');
    
  } catch (error) {
    console.error('❌ Global teardown failed:', error);
    // Don't throw error to avoid failing the entire test suite
  } finally {
    await browser.close();
  }
}

/**
 * Clean up test data from the database
 */
async function cleanupTestData(page: any) {
  console.log('🗑️  Cleaning up test data...');
  
  await page.evaluate(async () => {
    try {
      // Clean up test data via API
      const response = await fetch('/api/test-setup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'cleanup_test_data' })
      });
      
      if (!response.ok) {
        console.log('Note: Test cleanup endpoint not available');
      }
    } catch (e) {
      console.log('Note: Test cleanup API not available');
    }
    
    // Clear test configuration from localStorage
    localStorage.removeItem('playwright_test_mode');
    localStorage.removeItem('test_user_id');
  });
  
  console.log('✅ Test data cleaned up');
}

/**
 * Clean up temporary test files
 */
async function cleanupTempFiles() {
  console.log('📁 Cleaning up temporary files...');
  
  const tempFiles = [
    path.join(process.cwd(), 'tests/auth-state.json'),
    // Add other temporary files that need cleanup
  ];
  
  for (const filePath of tempFiles) {
    try {
      if (fs.existsSync(filePath)) {
        fs.unlinkSync(filePath);
        console.log(`Removed: ${filePath}`);
      }
    } catch (error) {
      console.warn(`Could not remove ${filePath}:`, error);
    }
  }
  
  console.log('✅ Temporary files cleaned up');
}

export default globalTeardown;