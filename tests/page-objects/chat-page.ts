import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './base-page';

/**
 * Chat Page Object for TaskFlow AI Advanced Chat Interface
 * Handles interactions with the AI-powered chat system
 */
export class ChatPage extends BasePage {
  // Chat interface elements
  get chatContainer(): Locator {
    return this.page.locator('[data-testid="chat-container"], .chat-container, .chat-wrapper');
  }

  get chatHeader(): Locator {
    return this.page.locator('[data-testid="chat-header"], .chat-header, .chat-title');
  }

  get messagesContainer(): Locator {
    return this.page.locator('[data-testid="messages-container"], .messages, .chat-messages');
  }

  get chatInput(): Locator {
    return this.page.locator('[data-testid="chat-input"], .chat-input, textarea[placeholder*="message"], input[placeholder*="message"]');
  }

  get sendButton(): Locator {
    return this.page.locator('[data-testid="send-button"], .send-btn, .btn-send, button[type="submit"]');
  }

  get voiceInputButton(): Locator {
    return this.page.locator('[data-testid="voice-input"], .voice-btn, .microphone-btn');
  }

  get attachmentButton(): Locator {
    return this.page.locator('[data-testid="attachment-btn"], .attachment, .file-upload-btn');
  }

  // Message elements
  get userMessages(): Locator {
    return this.page.locator('[data-testid="user-message"], .user-message, .message-user');
  }

  get aiMessages(): Locator {
    return this.page.locator('[data-testid="ai-message"], .ai-message, .message-assistant');
  }

  get typingIndicator(): Locator {
    return this.page.locator('[data-testid="typing-indicator"], .typing, .is-typing');
  }

  get messageTimestamp(): Locator {
    return this.page.locator('[data-testid="message-time"], .message-time, .timestamp');
  }

  // Chat features
  get quickReplies(): Locator {
    return this.page.locator('[data-testid="quick-reply"], .quick-reply, .suggested-response');
  }

  get emojiPicker(): Locator {
    return this.page.locator('[data-testid="emoji-picker"], .emoji-picker, .emoji-selector');
  }

  get chatSettings(): Locator {
    return this.page.locator('[data-testid="chat-settings"], .chat-settings, .settings-btn');
  }

  get clearChatButton(): Locator {
    return this.page.locator('[data-testid="clear-chat"], .clear-chat, .btn-clear');
  }

  get exportChatButton(): Locator {
    return this.page.locator('[data-testid="export-chat"], .export-chat, .btn-export');
  }

  // Context and AI features
  get contextPanel(): Locator {
    return this.page.locator('[data-testid="context-panel"], .context, .ai-context');
  }

  get taskSuggestions(): Locator {
    return this.page.locator('[data-testid="task-suggestions"], .task-suggestions, .suggested-tasks');
  }

  get aiStatus(): Locator {
    return this.page.locator('[data-testid="ai-status"], .ai-status, .connection-status');
  }

  get modelSelector(): Locator {
    return this.page.locator('[data-testid="model-selector"], .model-select, select[name="model"]');
  }

  // Chat history and search
  get searchInput(): Locator {
    return this.page.locator('[data-testid="search-messages"], .search-input, input[placeholder*="search"]');
  }

  get chatHistory(): Locator {
    return this.page.locator('[data-testid="chat-history"], .chat-history, .conversation-list');
  }

  get newChatButton(): Locator {
    return this.page.locator('[data-testid="new-chat"], .new-chat, .btn-new-conversation');
  }

  // Actions
  async goto(): Promise<void> {
    await this.page.goto('/chat');
    await this.waitForPageLoad();
    await expect(this.chatContainer).toBeVisible();
  }

  async sendMessage(message: string, waitForResponse: boolean = true): Promise<void> {
    await this.chatInput.fill(message);
    await this.sendButton.click();
    
    // Wait for message to appear in chat
    await expect(this.userMessages.last()).toContainText(message);
    
    if (waitForResponse) {
      // Wait for AI typing indicator
      await expect(this.typingIndicator).toBeVisible({ timeout: 2000 }).catch(() => {});
      
      // Wait for AI response
      const initialAiMessageCount = await this.aiMessages.count();
      await expect(this.aiMessages).toHaveCount(initialAiMessageCount + 1, { timeout: 15000 });
      
      // Wait for typing indicator to disappear
      await expect(this.typingIndicator).not.toBeVisible({ timeout: 10000 }).catch(() => {});
    }
  }

  async sendVoiceMessage(): Promise<void> {
    // Note: This would require special browser permissions and media setup
    await this.voiceInputButton.click();
    
    // Simulate voice input (in real tests, you'd need actual audio)
    await this.page.evaluate(() => {
      // Mock speech recognition result
      const event = new CustomEvent('speechresult', { 
        detail: { transcript: 'Test voice message' }
      });
      window.dispatchEvent(event);
    });
  }

  async attachFile(filePath: string): Promise<void> {
    await this.attachmentButton.click();
    
    const fileInput = this.page.locator('input[type="file"]');
    await fileInput.setInputFiles(filePath);
    
    // Wait for file to be processed
    await this.waitForNetworkIdle();
  }

  async selectQuickReply(replyIndex: number = 0): Promise<void> {
    await this.quickReplies.nth(replyIndex).click();
    await this.waitForNetworkIdle();
  }

  async clearChat(): Promise<void> {
    await this.clearChatButton.click();
    
    // Handle confirmation dialog
    const confirmButton = this.page.locator('[data-testid="confirm-clear"], .confirm-btn');
    if (await confirmButton.isVisible()) {
      await confirmButton.click();
    }
    
    await expect(this.messagesContainer).toBeEmpty();
  }

  async searchMessages(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.page.keyboard.press('Enter');
    await this.waitForNetworkIdle();
  }

  async exportChat(): Promise<void> {
    await this.exportChatButton.click();
    
    // Wait for download to start
    const downloadPromise = this.page.waitForEvent('download');
    await downloadPromise;
  }

  async switchAIModel(modelName: string): Promise<void> {
    await this.modelSelector.selectOption(modelName);
    await this.waitForNetworkIdle();
    
    // Verify model switch
    await expect(this.aiStatus).toContainText(modelName, { timeout: 5000 });
  }

  async startNewChat(): Promise<void> {
    await this.newChatButton.click();
    await expect(this.messagesContainer).toBeEmpty();
  }

  // Message interaction methods
  async getLastAIMessage(): Promise<string> {
    return await this.aiMessages.last().textContent() || '';
  }

  async getLastUserMessage(): Promise<string> {
    return await this.userMessages.last().textContent() || '';
  }

  async getMessageCount(): Promise<{ user: number; ai: number }> {
    const userCount = await this.userMessages.count();
    const aiCount = await this.aiMessages.count();
    return { user: userCount, ai: aiCount };
  }

  async likeMessage(messageIndex: number): Promise<void> {
    const message = this.aiMessages.nth(messageIndex);
    const likeButton = message.locator('[data-testid="like-message"], .like-btn, .thumbs-up');
    await likeButton.click();
  }

  async dislikeMessage(messageIndex: number): Promise<void> {
    const message = this.aiMessages.nth(messageIndex);
    const dislikeButton = message.locator('[data-testid="dislike-message"], .dislike-btn, .thumbs-down');
    await dislikeButton.click();
  }

  async copyMessage(messageIndex: number): Promise<void> {
    const message = this.aiMessages.nth(messageIndex);
    const copyButton = message.locator('[data-testid="copy-message"], .copy-btn');
    await copyButton.click();
    
    // Verify copy success (if there's a toast/notification)
    await this.waitForToast('Copied').catch(() => {});
  }

  async regenerateResponse(messageIndex: number): Promise<void> {
    const message = this.aiMessages.nth(messageIndex);
    const regenerateButton = message.locator('[data-testid="regenerate"], .regenerate-btn');
    await regenerateButton.click();
    
    // Wait for new response
    await expect(this.typingIndicator).toBeVisible({ timeout: 2000 });
    await expect(this.typingIndicator).not.toBeVisible({ timeout: 15000 });
  }

  // Context-aware features
  async enableContextMode(): Promise<void> {
    const contextToggle = this.page.locator('[data-testid="context-toggle"], .context-toggle');
    if (await contextToggle.isVisible()) {
      await contextToggle.check();
    }
  }

  async addTaskFromChat(taskText: string): Promise<void> {
    await this.sendMessage(`Create a task: ${taskText}`);
    
    // Look for task creation confirmation
    const taskCreatedMessage = this.aiMessages.last();
    await expect(taskCreatedMessage).toContainText(/task created|task added/i, { timeout: 10000 });
  }

  async askForTaskSuggestions(): Promise<void> {
    await this.sendMessage('What tasks should I work on today?');
    
    // Wait for suggestions to appear
    await expect(this.taskSuggestions).toBeVisible({ timeout: 10000 });
  }

  // Mobile-specific actions
  async swipeToDeleteMessage(messageIndex: number): Promise<void> {
    const message = this.userMessages.nth(messageIndex);
    
    // Perform swipe gesture (mobile-specific)
    await message.hover();
    await this.page.mouse.down();
    await this.page.mouse.move(-100, 0);
    await this.page.mouse.up();
    
    // Confirm deletion if dialog appears
    const deleteButton = this.page.locator('[data-testid="delete-message"], .delete-btn');
    if (await deleteButton.isVisible()) {
      await deleteButton.click();
    }
  }

  // Performance and reliability checks
  async checkChatResponsiveness(): Promise<number> {
    const testMessage = 'Hello, this is a test message';
    
    return await this.measureInteractionTime(async () => {
      await this.sendMessage(testMessage, true);
    });
  }

  async checkOfflineChatBehavior(): Promise<boolean> {
    // Go offline
    await this.page.context().setOffline(true);
    
    try {
      await this.sendMessage('Offline test message', false);
      
      // Check if there's an offline indicator
      const offlineIndicator = this.page.locator('[data-testid="offline"], .offline-status');
      return await offlineIndicator.isVisible();
    } finally {
      await this.page.context().setOffline(false);
    }
  }
}