/**
 * Mobile-specific enhancements for the Advanced Chat Interface
 * Touch gestures, haptic feedback, and mobile UX optimizations
 */

class MobileChatEnhancements {
    constructor(chatInterface) {
        this.chatInterface = chatInterface;
        this.touchStartTime = 0;
        this.longPressThreshold = 500;
        this.swipeThreshold = 50;
        this.vibrationEnabled = 'vibrate' in navigator;
        
        this.init();
    }

    init() {
        this.setupTouchGestures();
        this.setupKeyboardHandling();
        this.setupHapticFeedback();
        this.setupPullToRefresh();
        this.optimizeForMobile();
    }

    setupTouchGestures() {
        const chatContainer = document.getElementById('chat-container');
        let touchStart = { x: 0, y: 0, time: 0 };
        let touchEnd = { x: 0, y: 0, time: 0 };
        let isLongPress = false;
        let longPressTimer;

        // Touch start handler
        chatContainer.addEventListener('touchstart', (e) => {
            touchStart.x = e.touches[0].clientX;
            touchStart.y = e.touches[0].clientY;
            touchStart.time = Date.now();
            isLongPress = false;

            // Start long press timer
            longPressTimer = setTimeout(() => {
                isLongPress = true;
                this.handleLongPress(e);
            }, this.longPressThreshold);
        }, { passive: false });

        // Touch move handler
        chatContainer.addEventListener('touchmove', (e) => {
            // Clear long press if finger moves significantly
            const moveX = Math.abs(e.touches[0].clientX - touchStart.x);
            const moveY = Math.abs(e.touches[0].clientY - touchStart.y);
            
            if (moveX > 10 || moveY > 10) {
                clearTimeout(longPressTimer);
                isLongPress = false;
            }

            // Handle drag gestures for message actions
            this.handleDragGesture(e, touchStart);
        }, { passive: false });

        // Touch end handler
        chatContainer.addEventListener('touchend', (e) => {
            clearTimeout(longPressTimer);
            
            touchEnd.x = e.changedTouches[0].clientX;
            touchEnd.y = e.changedTouches[0].clientY;
            touchEnd.time = Date.now();

            if (!isLongPress) {
                this.handleSwipeGestures(touchStart, touchEnd);
            }
        }, { passive: false });
    }

    handleLongPress(e) {
        const target = e.target.closest('.message');
        if (target) {
            this.vibrate(50);
            this.showMessageContextMenu(target, e.touches[0]);
        }
    }

    handleSwipeGestures(start, end) {
        const deltaX = end.x - start.x;
        const deltaY = end.y - start.y;
        const deltaTime = end.time - start.time;

        // Ignore if gesture is too slow
        if (deltaTime > 300) return;

        const absX = Math.abs(deltaX);
        const absY = Math.abs(deltaY);

        // Horizontal swipes
        if (absX > absY && absX > this.swipeThreshold) {
            if (deltaX > 0) {
                this.handleSwipeRight();
            } else {
                this.handleSwipeLeft();
            }
        }
        // Vertical swipes
        else if (absY > this.swipeThreshold) {
            if (deltaY > 0) {
                this.handleSwipeDown();
            } else {
                this.handleSwipeUp();
            }
        }
    }

    handleSwipeRight() {
        // Close chat or go back
        this.chatInterface.closeChat();
        this.vibrate(25);
    }

    handleSwipeLeft() {
        // Open shortcuts panel
        this.chatInterface.toggleShortcutPanel();
        this.vibrate(25);
    }

    handleSwipeUp() {
        // Show search
        this.chatInterface.toggleSearchMode();
        this.vibrate(25);
    }

    handleSwipeDown() {
        // Refresh or scroll to top
        const messagesContainer = document.getElementById('chat-messages');
        messagesContainer.scrollTo({ top: 0, behavior: 'smooth' });
        this.vibrate(25);
    }

    handleDragGesture(e, touchStart) {
        const currentX = e.touches[0].clientX;
        const deltaX = currentX - touchStart.x;
        
        // Visual feedback for drag-to-action
        const messageElement = e.target.closest('.message');
        if (messageElement && Math.abs(deltaX) > 20) {
            const opacity = Math.max(0.7, 1 - Math.abs(deltaX) / 200);
            messageElement.style.opacity = opacity;
            messageElement.style.transform = `translateX(${deltaX * 0.5}px)`;
        }
    }

    showMessageContextMenu(messageElement, touch) {
        // Create context menu for message actions
        const menu = document.createElement('div');
        menu.className = 'message-context-menu';
        menu.style.position = 'fixed';
        menu.style.left = touch.clientX + 'px';
        menu.style.top = touch.clientY + 'px';
        menu.style.zIndex = '10000';
        
        menu.innerHTML = `
            <div class="context-menu-item" data-action="copy">Copy</div>
            <div class="context-menu-item" data-action="quote">Quote</div>
            <div class="context-menu-item" data-action="share">Share</div>
        `;
        
        document.body.appendChild(menu);
        
        // Handle menu item clicks
        menu.addEventListener('click', (e) => {
            const action = e.target.dataset.action;
            const messageText = messageElement.textContent;
            
            switch (action) {
                case 'copy':
                    this.copyToClipboard(messageText);
                    break;
                case 'quote':
                    this.quoteMessage(messageText);
                    break;
                case 'share':
                    this.shareMessage(messageText);
                    break;
            }
            
            menu.remove();
        });
        
        // Remove menu on outside click
        setTimeout(() => {
            document.addEventListener('click', () => menu.remove(), { once: true });
        }, 100);
    }

    setupKeyboardHandling() {
        const chatInput = document.getElementById('chat-input');
        
        // Handle virtual keyboard appearance
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', () => {
                this.handleVirtualKeyboard();
            });
        }
        
        // Auto-scroll when typing
        chatInput.addEventListener('focus', () => {
            setTimeout(() => {
                chatInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 300);
        });
    }

    handleVirtualKeyboard() {
        const viewport = window.visualViewport;
        const chatContainer = document.getElementById('chat-container');
        
        if (viewport) {
            const keyboardHeight = window.innerHeight - viewport.height;
            
            if (keyboardHeight > 100) {
                // Keyboard is open
                chatContainer.style.paddingBottom = keyboardHeight + 'px';
                chatContainer.style.maxHeight = viewport.height + 'px';
            } else {
                // Keyboard is closed
                chatContainer.style.paddingBottom = '';
                chatContainer.style.maxHeight = '';
            }
        }
    }

    setupHapticFeedback() {
        if (!this.vibrationEnabled) return;

        // Add haptic feedback to key interactions
        const buttons = document.querySelectorAll('.chat-send, .chat-action, .chat-input-btn');
        buttons.forEach(button => {
            button.addEventListener('click', () => this.vibrate(25));
        });

        // Voice button special feedback
        const voiceButton = document.getElementById('voice-button');
        if (voiceButton) {
            voiceButton.addEventListener('click', () => this.vibrate([50, 50, 100]));
        }
    }

    setupPullToRefresh() {
        const messagesContainer = document.getElementById('chat-messages');
        let startY = 0;
        let pullDistance = 0;
        let isPulling = false;
        
        messagesContainer.addEventListener('touchstart', (e) => {
            if (messagesContainer.scrollTop === 0) {
                startY = e.touches[0].clientY;
                isPulling = true;
            }
        }, { passive: false });
        
        messagesContainer.addEventListener('touchmove', (e) => {
            if (!isPulling) return;
            
            const currentY = e.touches[0].clientY;
            pullDistance = Math.max(0, currentY - startY);
            
            if (pullDistance > 100) {
                e.preventDefault();
                this.showPullToRefreshIndicator(pullDistance);
            }
        }, { passive: false });
        
        messagesContainer.addEventListener('touchend', () => {
            if (isPulling && pullDistance > 100) {
                this.triggerRefresh();
                this.vibrate(50);
            }
            
            isPulling = false;
            pullDistance = 0;
            this.hidePullToRefreshIndicator();
        });
    }

    showPullToRefreshIndicator(distance) {
        let indicator = document.getElementById('pull-refresh-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'pull-refresh-indicator';
            indicator.innerHTML = '↓ Pull to refresh';
            indicator.style.cssText = `
                position: absolute;
                top: 0;
                left: 50%;
                transform: translateX(-50%);
                padding: 10px;
                background: rgba(26, 115, 232, 0.1);
                border-radius: 20px;
                font-size: 14px;
                color: var(--primary-500);
                z-index: 1000;
            `;
            document.getElementById('chat-messages').appendChild(indicator);
        }
        
        const progress = Math.min(distance / 100, 1);
        indicator.style.opacity = progress;
        indicator.style.transform = `translateX(-50%) translateY(${distance * 0.3}px)`;
    }

    hidePullToRefreshIndicator() {
        const indicator = document.getElementById('pull-refresh-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    triggerRefresh() {
        // Refresh chat history
        if (this.chatInterface && this.chatInterface.loadChatHistory) {
            this.chatInterface.loadChatHistory();
        }
    }

    optimizeForMobile() {
        // Prevent zoom on input focus
        const metaViewport = document.querySelector('meta[name="viewport"]');
        if (metaViewport) {
            metaViewport.setAttribute('content', 
                'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'
            );
        }

        // Add mobile-specific CSS classes
        document.body.classList.add('mobile-optimized');
        
        // Optimize touch targets
        this.optimizeTouchTargets();
        
        // Handle safe area insets for modern phones
        this.handleSafeAreaInsets();
    }

    optimizeTouchTargets() {
        const minTouchSize = 44; // 44px minimum touch target
        const elements = document.querySelectorAll('button, .clickable, .touch-target');
        
        elements.forEach(element => {
            const rect = element.getBoundingClientRect();
            if (rect.width < minTouchSize || rect.height < minTouchSize) {
                element.style.minWidth = minTouchSize + 'px';
                element.style.minHeight = minTouchSize + 'px';
                element.style.display = 'flex';
                element.style.alignItems = 'center';
                element.style.justifyContent = 'center';
            }
        });
    }

    handleSafeAreaInsets() {
        // Handle iPhone X+ safe areas
        const style = document.createElement('style');
        style.textContent = `
            @supports (padding: max(0px)) {
                .chat-container {
                    padding-left: max(16px, env(safe-area-inset-left));
                    padding-right: max(16px, env(safe-area-inset-right));
                }
                .chat-header {
                    padding-top: max(0px, env(safe-area-inset-top));
                }
                .chat-input-container {
                    padding-bottom: max(16px, env(safe-area-inset-bottom));
                }
            }
        `;
        document.head.appendChild(style);
    }

    vibrate(pattern) {
        if (this.vibrationEnabled) {
            try {
                navigator.vibrate(pattern);
            } catch (e) {
                // Ignore vibration errors
            }
        }
    }

    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                this.showToast('Copied to clipboard');
                this.vibrate(25);
            });
        }
    }

    quoteMessage(text) {
        const chatInput = document.getElementById('chat-input');
        const quotedText = `> ${text}\n\n`;
        chatInput.value = quotedText + chatInput.value;
        chatInput.focus();
    }

    shareMessage(text) {
        if (navigator.share) {
            navigator.share({
                text: text,
                title: 'TaskFlow AI Chat'
            });
        } else {
            this.copyToClipboard(text);
        }
    }

    showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'mobile-toast';
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            z-index: 10000;
            animation: toastSlideIn 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'toastSlideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }
}

// Auto-initialize when advanced chat is ready
document.addEventListener('DOMContentLoaded', () => {
    // Wait for advanced chat to initialize
    const checkForChat = () => {
        if (window.advancedChat) {
            new MobileChatEnhancements(window.advancedChat);
        } else {
            setTimeout(checkForChat, 100);
        }
    };
    checkForChat();
});

// Add required CSS animations
const mobileStyles = document.createElement('style');
mobileStyles.textContent = `
    @keyframes toastSlideIn {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    }
    
    @keyframes toastSlideOut {
        from {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        to {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
    }
    
    .message-context-menu {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        overflow: hidden;
    }
    
    .context-menu-item {
        padding: 12px 16px;
        cursor: pointer;
        border-bottom: 1px solid var(--gray-100);
        font-size: 14px;
    }
    
    .context-menu-item:last-child {
        border-bottom: none;
    }
    
    .context-menu-item:hover {
        background: var(--gray-50);
    }
    
    .mobile-optimized .chat-input {
        font-size: 16px; /* Prevent zoom on iOS */
    }
`;
document.head.appendChild(mobileStyles);