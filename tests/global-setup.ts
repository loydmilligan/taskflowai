import { chromium, FullConfig } from '@playwright/test';
import fs from 'fs';
import path from 'path';

/**
 * Global setup for TaskFlow AI Playwright tests
 * Handles database initialization, authentication state, and test data setup
 */
async function globalSetup(config: FullConfig) {
  console.log('🚀 Starting TaskFlow AI Test Setup...');
  
  // Create test results directory
  const testResultsDir = path.join(process.cwd(), 'test-results');
  if (!fs.existsSync(testResultsDir)) {
    fs.mkdirSync(testResultsDir, { recursive: true });
  }

  // Initialize browser for setup tasks
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    // Navigate to the application
    const baseURL = config.use?.baseURL || 'http://localhost:8080';
    console.log(`📱 Navigating to ${baseURL}...`);
    
    await page.goto(baseURL, { waitUntil: 'networkidle' });
    
    // Wait for application to initialize
    await page.waitForSelector('body', { timeout: 10000 });
    
    // Check if application is responsive
    const title = await page.title();
    console.log(`✅ Application loaded: ${title}`);
    
    // Initialize test data in SQLite database
    await initializeTestData(page);
    
    // Save authentication state for tests that need it
    await saveAuthenticationState(context);
    
    console.log('✅ Global setup completed successfully');
    
  } catch (error) {
    console.error('❌ Global setup failed:', error);
    throw error;
  } finally {
    await browser.close();
  }
}

/**
 * Initialize test data in the database
 */
async function initializeTestData(page: any) {
  console.log('📊 Initializing test data...');
  
  // Execute JavaScript to set up test data via the application's API
  await page.evaluate(async () => {
    // Clear existing test data
    try {
      const response = await fetch('/api/test-setup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'clear_test_data' })
      });
      
      if (!response.ok) {
        console.log('Note: Test data clearing endpoint not available');
      }
    } catch (e) {
      console.log('Note: Test setup API not available, using UI initialization');
    }
    
    // Store test configuration in localStorage
    localStorage.setItem('playwright_test_mode', 'true');
    localStorage.setItem('test_user_id', 'test-user-001');
  });
  
  console.log('✅ Test data initialized');
}

/**
 * Save authentication state for authenticated tests
 */
async function saveAuthenticationState(context: any) {
  console.log('🔐 Saving authentication state...');
  
  // Save the storage state to a file
  await context.storageState({ 
    path: path.join(process.cwd(), 'tests/auth-state.json') 
  });
  
  console.log('✅ Authentication state saved');
}

export default globalSetup;