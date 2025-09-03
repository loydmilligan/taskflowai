import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright Configuration for TaskFlow AI
 * Comprehensive testing setup for mobile-first PWA with cross-browser support
 */
export default defineConfig({
  // Test directory structure
  testDir: './tests',
  
  // Global test timeout
  timeout: 30 * 1000,
  
  // Expect timeout for assertions
  expect: {
    timeout: 5 * 1000,
  },

  // Run tests in files in parallel
  fullyParallel: true,
  
  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,
  
  // Retry on CI only
  retries: process.env.CI ? 2 : 0,
  
  // Limit the number of workers on CI, use default locally
  workers: process.env.CI ? 1 : undefined,
  
  // Reporter configuration
  reporter: [
    ['html', { outputFolder: 'test-results/html-report' }],
    ['json', { outputFile: 'test-results/results.json' }],
    ['junit', { outputFile: 'test-results/junit.xml' }],
    ['line'],
  ],
  
  // Global test setup
  globalSetup: require.resolve('./tests/global-setup.ts'),
  globalTeardown: require.resolve('./tests/global-teardown.ts'),
  
  // Shared settings for all the projects below
  use: {
    // Base URL for all tests
    baseURL: process.env.BASE_URL || 'http://localhost:8080',
    
    // Collect trace when retrying the failed test
    trace: 'on-first-retry',
    
    // Take screenshot on failure
    screenshot: 'only-on-failure',
    
    // Record video on failure
    video: 'retain-on-failure',
    
    // Global timeout for all actions
    actionTimeout: 10 * 1000,
    
    // Global timeout for navigation
    navigationTimeout: 15 * 1000,
  },

  // Configure projects for major browsers and mobile devices
  projects: [
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
      teardown: 'cleanup',
    },
    {
      name: 'cleanup',
      testMatch: /.*\.teardown\.ts/,
    },

    // Desktop Browsers
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
      dependencies: ['setup'],
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
      dependencies: ['setup'],
    },

    // Mobile devices - Primary focus for TaskFlow AI
    {
      name: 'Mobile Chrome',
      use: { ...devices['Pixel 5'] },
      dependencies: ['setup'],
    },
    {
      name: 'Mobile Safari',
      use: { ...devices['iPhone 12'] },
      dependencies: ['setup'],
    },
    {
      name: 'Mobile Samsung',
      use: { ...devices['Galaxy S21'] },
      dependencies: ['setup'],
    },

    // Tablet devices
    {
      name: 'iPad',
      use: { ...devices['iPad Pro'] },
      dependencies: ['setup'],
    },

    // PWA testing configuration
    {
      name: 'PWA Desktop',
      use: {
        ...devices['Desktop Chrome'],
        contextOptions: {
          permissions: ['notifications', 'camera', 'microphone'],
        },
      },
      dependencies: ['setup'],
    },
    {
      name: 'PWA Mobile',
      use: {
        ...devices['Pixel 5'],
        contextOptions: {
          permissions: ['notifications', 'camera', 'microphone'],
        },
      },
      dependencies: ['setup'],
    },
  ],

  // Web server configuration for local testing
  webServer: {
    command: 'php -S localhost:8080 index.php',
    port: 8080,
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
  },

  // Output directories
  outputDir: 'test-results/',
  
  // Test result reporting
  reportSlowTests: {
    threshold: 15 * 1000,
    max: 5,
  },
});