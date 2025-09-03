import { test as teardown } from '@playwright/test';

/**
 * Cleanup teardown for TaskFlow AI tests
 * Handles final cleanup after all tests complete
 */
teardown('cleanup test data', async ({ page }) => {
  console.log('🧹 Running final cleanup...');
  
  await page.goto('/');
  
  // Execute cleanup scripts
  await page.evaluate(async () => {
    // Clear all localStorage related to tests
    Object.keys(localStorage).forEach(key => {
      if (key.includes('test') || key.includes('playwright')) {
        localStorage.removeItem(key);
      }
    });
    
    // Clear sessionStorage
    sessionStorage.clear();
    
    // Clear any test-related IndexedDB data if applicable
    if ('indexedDB' in window) {
      try {
        // This would need to be customized based on your app's IndexedDB usage
        const dbs = await indexedDB.databases();
        for (const db of dbs) {
          if (db.name && db.name.includes('test')) {
            indexedDB.deleteDatabase(db.name);
          }
        }
      } catch (e) {
        console.log('IndexedDB cleanup not needed or failed:', e);
      }
    }
  });
  
  console.log('✅ Final cleanup completed');
});