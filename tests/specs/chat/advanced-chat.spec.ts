import { test, expect } from '@playwright/test';
import { ChatPage } from '../../page-objects/chat-page';

test.describe('Advanced Chat Interface Components', () => {
  let chatPage: ChatPage;

  test.beforeEach(async ({ page }) => {
    chatPage = new ChatPage(page);
    await chatPage.goto();
  });

  test.describe('Core Chat Functionality', () => {
    test('should send and receive messages with proper formatting', async ({ page }) => {
      const testMessage = 'Hello, can you help me organize my tasks?';
      
      await chatPage.sendMessage(testMessage);
      
      // Verify user message appears
      await expect(chatPage.userMessages.last()).toContainText(testMessage);
      
      // Verify AI response appears
      await expect(chatPage.aiMessages.last()).toBeVisible();
      await expect(chatPage.aiMessages.last()).not.toBeEmpty();
      
      // Check message formatting
      const lastAiMessage = chatPage.aiMessages.last();
      await expect(lastAiMessage).toHaveClass(/message|ai-message/);
    });

    test('should display typing indicator during AI response', async ({ page }) => {
      await chatPage.chatInput.fill('What should I work on today?');
      await chatPage.sendButton.click();
      
      // Typing indicator should appear quickly
      await expect(chatPage.typingIndicator).toBeVisible({ timeout: 3000 });
      
      // Typing indicator should disappear when response is ready
      await expect(chatPage.typingIndicator).not.toBeVisible({ timeout: 15000 });
      
      // AI response should be visible
      await expect(chatPage.aiMessages.last()).toBeVisible();
    });

    test('should handle empty message submission gracefully', async ({ page }) => {
      // Try to send empty message
      await chatPage.sendButton.click();
      
      // Should not create empty user message
      const messageCountBefore = await chatPage.getMessageCount();
      expect(messageCountBefore.user).toBe(0);
      
      // Input should remain focused or show validation
      await expect(chatPage.chatInput).toBeFocused().catch(() => {
        // Alternative: check for validation message
        expect(page.locator('[data-testid="validation-message"]')).toBeVisible();
      });
    });
  });

  test.describe('Rich Message Features', () => {
    test('should support markdown formatting in messages', async ({ page }) => {
      const markdownMessage = 'Please create a task with **high priority** and `code formatting`';
      
      await chatPage.sendMessage(markdownMessage);
      
      // Check if markdown is rendered in user message
      const userMessage = chatPage.userMessages.last();
      await expect(userMessage.locator('strong')).toContainText('high priority');
      await expect(userMessage.locator('code')).toContainText('code formatting');
    });

    test('should display message timestamps', async ({ page }) => {
      await chatPage.sendMessage('Test message with timestamp');
      
      // Check for timestamp on user message
      const userMessage = chatPage.userMessages.last();
      const timestamp = userMessage.locator('[data-testid="message-time"], .timestamp, .message-time');
      await expect(timestamp).toBeVisible();
      
      // Timestamp should have reasonable format (contains : for time)
      const timestampText = await timestamp.textContent();
      expect(timestampText).toMatch(/\d+:\d+|\d+ (minutes?|hours?) ago|just now/i);
    });

    test('should support message reactions and interactions', async ({ page }) => {
      await chatPage.sendMessage('Test message for interactions');
      
      // Wait for AI response
      await expect(chatPage.aiMessages.last()).toBeVisible();
      
      // Test like functionality
      await chatPage.likeMessage(0);
      
      const likeButton = chatPage.aiMessages.first().locator('[data-testid="like-message"]');
      await expect(likeButton).toHaveClass(/active|liked/);
      
      // Test copy functionality
      await chatPage.copyMessage(0);
      
      // Should show copy success feedback
      await chatPage.waitForToast('Copied');
    });
  });

  test.describe('Quick Actions and Suggestions', () => {
    test('should provide quick reply suggestions', async ({ page }) => {
      await chatPage.sendMessage('I need help with my daily tasks');
      
      // Wait for AI response and quick replies
      await expect(chatPage.aiMessages.last()).toBeVisible();
      await expect(chatPage.quickReplies).toBeVisible({ timeout: 5000 });
      
      // Should have multiple quick reply options
      await expect(chatPage.quickReplies).toHaveCount.greaterThanOrEqual(2);
      
      // Quick replies should be actionable
      const firstQuickReply = chatPage.quickReplies.first();
      await expect(firstQuickReply).not.toBeEmpty();
      
      // Test selecting a quick reply
      await chatPage.selectQuickReply(0);
      
      // Should add the quick reply as user message
      await expect(chatPage.userMessages.last()).toBeVisible();
    });

    test('should suggest task-related actions', async ({ page }) => {
      await chatPage.sendMessage('I need to prepare for tomorrow\'s presentation');
      
      // Look for task creation suggestions
      const taskSuggestions = chatPage.taskSuggestions;
      await expect(taskSuggestions).toBeVisible({ timeout: 10000 });
      
      // Should have specific task suggestions
      const suggestions = taskSuggestions.locator('.task-suggestion, [data-testid="task-suggestion"]');
      await expect(suggestions).toHaveCount.greaterThanOrEqual(2);
      
      // Test creating task from suggestion
      await suggestions.first().click();
      
      // Should create task and show confirmation
      await chatPage.waitForToast(/task created|task added/i);
    });

    test('should provide contextual help and documentation links', async ({ page }) => {
      await chatPage.sendMessage('How do I use the advanced features?');
      
      // Wait for response with help content
      await expect(chatPage.aiMessages.last()).toBeVisible();
      
      // Should contain help links or buttons
      const helpLinks = chatPage.aiMessages.last().locator('a, [data-testid="help-link"]');
      await expect(helpLinks).toHaveCount.greaterThanOrEqual(1);
      
      // Test help link functionality
      if (await helpLinks.first().isVisible()) {
        await helpLinks.first().click();
        // Should open help content or modal
        const helpModal = page.locator('[data-testid="help-modal"], .help-content');
        await expect(helpModal).toBeVisible();
      }
    });
  });

  test.describe('Chat History and Search', () => {
    test('should maintain chat history across sessions', async ({ page }) => {
      // Send initial messages
      await chatPage.sendMessage('First test message');
      await chatPage.sendMessage('Second test message');
      
      // Reload page to simulate session restart
      await page.reload();
      await chatPage.waitForPageLoad();
      
      // History should be preserved
      await expect(chatPage.userMessages).toHaveCount.greaterThanOrEqual(2);
      await expect(chatPage.userMessages.first()).toContainText('First test message');
    });

    test('should support searching through chat history', async ({ page }) => {
      // Create some searchable content
      await chatPage.sendMessage('Create a task for project planning');
      await chatPage.sendMessage('Help me with time management');
      
      // Use search functionality
      await chatPage.searchMessages('project planning');
      
      // Should highlight or filter relevant messages
      const searchResults = page.locator('[data-testid="search-results"], .search-highlight');
      await expect(searchResults).toBeVisible();
      
      // Should show relevant message
      await expect(searchResults).toContainText('project planning');
    });

    test('should allow exporting chat history', async ({ page }) => {
      // Create some chat content
      await chatPage.sendMessage('Test message for export');
      
      // Test export functionality
      const downloadPromise = page.waitForEvent('download');
      await chatPage.exportChat();
      
      const download = await downloadPromise;
      expect(download.suggestedFilename()).toMatch(/chat|conversation|export/i);
    });
  });

  test.describe('Advanced AI Features', () => {
    test('should support different AI models/modes', async ({ page }) => {
      // Check if model selector is available
      if (await chatPage.modelSelector.isVisible()) {
        const currentModel = await chatPage.modelSelector.inputValue();
        
        // Switch to different model
        const options = await chatPage.modelSelector.locator('option').all();
        if (options.length > 1) {
          await chatPage.switchAIModel(await options[1].textContent() || 'alternative');
          
          // Verify model switch
          await expect(chatPage.aiStatus).toContainText('alternative');
          
          // Test response with new model
          await chatPage.sendMessage('Hello from new model');
          await expect(chatPage.aiMessages.last()).toBeVisible();
        }
      }
    });

    test('should provide context-aware responses', async ({ page }) => {
      // Set up context by creating tasks
      await page.goto('/');
      await page.locator('[data-testid="add-task"]').click();
      await page.locator('[data-testid="task-input"]').fill('Important presentation due tomorrow');
      await page.locator('[data-testid="submit-task"]').click();
      
      // Return to chat
      await chatPage.goto();
      
      // Ask context-aware question
      await chatPage.sendMessage('What should I focus on today?');
      
      // Response should reference the created task
      const aiResponse = await chatPage.getLastAIMessage();
      expect(aiResponse.toLowerCase()).toMatch(/presentation|tomorrow|important/);
    });

    test('should handle complex multi-turn conversations', async ({ page }) => {
      // Start conversation thread
      await chatPage.sendMessage('I want to improve my productivity');
      await expect(chatPage.aiMessages.last()).toBeVisible();
      
      // Continue conversation with context
      await chatPage.sendMessage('What specific strategies would you recommend?');
      await expect(chatPage.aiMessages.last()).toBeVisible();
      
      // Ask follow-up question
      await chatPage.sendMessage('Can you help me implement the first strategy?');
      
      // AI should maintain context from previous messages
      const finalResponse = await chatPage.getLastAIMessage();
      expect(finalResponse.length).toBeGreaterThan(50); // Substantial response
      expect(finalResponse.toLowerCase()).toMatch(/strategy|implement|productivity/);
    });
  });

  test.describe('Mobile Chat Experience', () => {
    test('should optimize chat interface for mobile devices', async ({ page, isMobile }) => {
      test.skip(!isMobile, 'Mobile-specific test');
      
      // Verify mobile-optimized layout
      const chatContainer = chatPage.chatContainer;
      await expect(chatContainer).toHaveCSS('width', /100%|375px/);
      
      // Check for mobile-friendly input
      await expect(chatPage.chatInput).toHaveAttribute('type', 'text');
      await expect(chatPage.sendButton).toBeVisible();
      
      // Test touch interactions
      await chatPage.chatInput.tap();
      await expect(chatPage.chatInput).toBeFocused();
    });

    test('should support voice input on mobile', async ({ page, isMobile }) => {
      test.skip(!isMobile, 'Mobile voice feature test');
      
      // Check for voice input button
      await expect(chatPage.voiceInputButton).toBeVisible();
      
      // Test voice input activation
      await chatPage.voiceInputButton.click();
      
      // Should show voice recording interface
      const voiceInterface = page.locator('[data-testid="voice-recording"], .voice-active');
      await expect(voiceInterface).toBeVisible();
    });

    test('should handle keyboard interactions properly', async ({ page }) => {
      // Test Enter key to send message
      await chatPage.chatInput.fill('Test message via Enter key');
      await chatPage.chatInput.press('Enter');
      
      await expect(chatPage.userMessages.last()).toContainText('Test message via Enter key');
      
      // Test Shift+Enter for new line (if supported)
      await chatPage.chatInput.fill('Line 1');
      await chatPage.chatInput.press('Shift+Enter');
      await chatPage.chatInput.type('Line 2');
      
      // Should create multiline input
      const inputValue = await chatPage.chatInput.inputValue();
      expect(inputValue).toContain('\n');
    });
  });

  test.describe('Error Handling and Edge Cases', () => {
    test('should handle network connectivity issues gracefully', async ({ page }) => {
      // Send message while online
      await chatPage.sendMessage('Test message before offline');
      await expect(chatPage.aiMessages.last()).toBeVisible();
      
      // Go offline
      await page.context().setOffline(true);
      
      // Try to send message offline
      await chatPage.chatInput.fill('Offline message');
      await chatPage.sendButton.click();
      
      // Should show offline indicator
      const offlineStatus = page.locator('[data-testid="offline"], .offline-indicator');
      await expect(offlineStatus).toBeVisible();
      
      // Message should be queued
      await expect(chatPage.userMessages.last()).toContainText('Offline message');
      
      // Come back online
      await page.context().setOffline(false);
      
      // Should sync queued messages
      await page.waitForTimeout(2000);
      await expect(offlineStatus).not.toBeVisible();
    });

    test('should handle AI service errors gracefully', async ({ page }) => {
      // Mock AI service error
      await page.route('/api/chat/**', route => {
        route.fulfill({ status: 500, json: { error: 'AI service unavailable' } });
      });
      
      await chatPage.sendMessage('Test message with AI error');
      
      // Should show error message instead of hanging
      const errorMessage = page.locator('[data-testid="error-message"], .error, .chat-error');
      await expect(errorMessage).toBeVisible({ timeout: 10000 });
      await expect(errorMessage).toContainText(/error|unavailable|try again/i);
      
      // Should allow retry
      const retryButton = errorMessage.locator('[data-testid="retry"], .retry-btn');
      if (await retryButton.isVisible()) {
        await retryButton.click();
      }
    });

    test('should validate message length limits', async ({ page }) => {
      // Create very long message
      const longMessage = 'A'.repeat(5000);
      
      await chatPage.chatInput.fill(longMessage);
      await chatPage.sendButton.click();
      
      // Should either truncate, show validation, or handle gracefully
      const validation = page.locator('[data-testid="message-too-long"], .validation-error');
      const userMessage = chatPage.userMessages.last();
      
      // Either validation appears OR message is properly handled
      const validationVisible = await validation.isVisible();
      const messageVisible = await userMessage.isVisible();
      
      expect(validationVisible || messageVisible).toBeTruthy();
      
      if (messageVisible) {
        const messageText = await userMessage.textContent();
        expect(messageText!.length).toBeLessThanOrEqual(5000);
      }
    });
  });

  test.describe('Performance and Accessibility', () => {
    test('should meet performance benchmarks for chat interactions', async ({ page }) => {
      const responseTime = await chatPage.checkChatResponsiveness();
      
      // Response time should be reasonable (under 5 seconds)
      expect(responseTime).toBeLessThan(5000);
      
      // Page should remain responsive during chat
      const navigationPromise = page.evaluate(() => {
        return new Promise(resolve => {
          const startTime = performance.now();
          setTimeout(() => resolve(performance.now() - startTime), 100);
        });
      });
      
      const navigationTime = await navigationPromise as number;
      expect(navigationTime).toBeLessThan(200); // Should be close to 100ms
    });

    test('should be accessible with keyboard navigation', async ({ page }) => {
      // Test tab navigation
      await page.keyboard.press('Tab');
      await expect(chatPage.chatInput).toBeFocused();
      
      await page.keyboard.press('Tab');
      await expect(chatPage.sendButton).toBeFocused();
      
      // Test ARIA labels
      await chatPage.checkAriaLabel(chatPage.chatInput, /message|chat|input/);
      await chatPage.checkAriaLabel(chatPage.sendButton, /send|submit/);
    });

    test('should support screen reader accessibility', async ({ page }) => {
      // Check for proper ARIA live regions for messages
      const messagesContainer = chatPage.messagesContainer;
      const ariaLive = await messagesContainer.getAttribute('aria-live');
      expect(ariaLive).toBe('polite');
      
      // Check for proper message roles
      await chatPage.sendMessage('Accessibility test message');
      
      const userMessage = chatPage.userMessages.last();
      const messageRole = await userMessage.getAttribute('role');
      expect(messageRole).toMatch(/message|listitem/);
    });
  });
});