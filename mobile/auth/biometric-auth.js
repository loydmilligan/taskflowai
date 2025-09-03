/**
 * Biometric Authentication Module for TaskFlow AI
 * Implements Web Authentication API (WebAuthn) for biometric authentication
 */

class BiometricAuth {
  constructor() {
    this.isSupported = this.checkSupport();
    this.credentials = new Map();
    this.authConfig = {
      timeout: 60000,
      userVerification: 'preferred',
      authenticatorAttachment: 'platform'
    };
  }

  /**
   * Check if biometric authentication is supported
   */
  checkSupport() {
    return (
      window.PublicKeyCredential &&
      typeof window.PublicKeyCredential === 'function' &&
      typeof navigator.credentials !== 'undefined'
    );
  }

  /**
   * Get available authentication methods
   */
  async getAvailableAuthenticators() {
    if (!this.isSupported) return [];
    
    try {
      const available = await Promise.allSettled([
        PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable(),
        PublicKeyCredential.isConditionalMediationAvailable?.() || Promise.resolve(false)
      ]);

      const authenticators = [];
      
      if (available[0].status === 'fulfilled' && available[0].value) {
        authenticators.push({
          type: 'platform',
          name: 'Built-in Authenticator',
          description: 'Face ID, Touch ID, Windows Hello, or Android Biometric'
        });
      }

      if (available[1].status === 'fulfilled' && available[1].value) {
        authenticators.push({
          type: 'conditional',
          name: 'Conditional UI',
          description: 'Passwordless sign-in suggestions'
        });
      }

      // Check for external authenticators
      authenticators.push({
        type: 'cross-platform',
        name: 'Security Key',
        description: 'External security keys (USB, NFC, Bluetooth)'
      });

      return authenticators;
    } catch (error) {
      console.error('Error checking authenticators:', error);
      return [];
    }
  }

  /**
   * Register a new biometric credential
   */
  async registerBiometric(userInfo) {
    if (!this.isSupported) {
      throw new Error('Biometric authentication not supported');
    }

    const { username, displayName, userId } = userInfo;
    
    try {
      // Generate challenge on server (mock for demo)
      const challenge = this.generateChallenge();
      
      const publicKeyCredentialCreationOptions = {
        challenge: challenge,
        rp: {
          name: 'TaskFlow AI',
          id: window.location.hostname
        },
        user: {
          id: this.stringToArrayBuffer(userId),
          name: username,
          displayName: displayName
        },
        pubKeyCredParams: [
          { alg: -7, type: 'public-key' }, // ES256
          { alg: -35, type: 'public-key' }, // ES384
          { alg: -36, type: 'public-key' }, // ES512
          { alg: -257, type: 'public-key' } // RS256
        ],
        authenticatorSelection: {
          authenticatorAttachment: this.authConfig.authenticatorAttachment,
          userVerification: this.authConfig.userVerification,
          requireResidentKey: true,
          residentKey: 'preferred'
        },
        timeout: this.authConfig.timeout,
        attestation: 'direct'
      };

      const credential = await navigator.credentials.create({
        publicKey: publicKeyCredentialCreationOptions
      });

      if (!credential) {
        throw new Error('Credential creation failed');
      }

      // Store credential info
      const credentialData = {
        id: credential.id,
        rawId: this.arrayBufferToBase64(credential.rawId),
        type: credential.type,
        response: {
          attestationObject: this.arrayBufferToBase64(credential.response.attestationObject),
          clientDataJSON: this.arrayBufferToBase64(credential.response.clientDataJSON)
        },
        created: Date.now(),
        userId: userId
      };

      // Save to local storage (in production, send to server)
      this.saveCredential(credentialData);

      return {
        success: true,
        credentialId: credential.id,
        message: 'Biometric authentication set up successfully'
      };

    } catch (error) {
      console.error('Biometric registration failed:', error);
      throw new Error(`Registration failed: ${error.message}`);
    }
  }

  /**
   * Authenticate using biometrics
   */
  async authenticateBiometric(options = {}) {
    if (!this.isSupported) {
      throw new Error('Biometric authentication not supported');
    }

    try {
      const challenge = this.generateChallenge();
      const allowCredentials = this.getAllowedCredentials();

      const publicKeyCredentialRequestOptions = {
        challenge: challenge,
        allowCredentials: allowCredentials,
        userVerification: this.authConfig.userVerification,
        timeout: this.authConfig.timeout,
        ...options
      };

      const credential = await navigator.credentials.get({
        publicKey: publicKeyCredentialRequestOptions,
        mediation: options.conditional ? 'conditional' : 'optional'
      });

      if (!credential) {
        throw new Error('Authentication failed');
      }

      // Verify credential (mock verification for demo)
      const authData = {
        id: credential.id,
        rawId: this.arrayBufferToBase64(credential.rawId),
        type: credential.type,
        response: {
          authenticatorData: this.arrayBufferToBase64(credential.response.authenticatorData),
          clientDataJSON: this.arrayBufferToBase64(credential.response.clientDataJSON),
          signature: this.arrayBufferToBase64(credential.response.signature),
          userHandle: credential.response.userHandle ? 
            this.arrayBufferToBase64(credential.response.userHandle) : null
        }
      };

      // In production, verify on server
      const isValid = await this.verifyAuthentication(authData);

      if (!isValid) {
        throw new Error('Authentication verification failed');
      }

      return {
        success: true,
        credentialId: credential.id,
        timestamp: Date.now(),
        message: 'Authentication successful'
      };

    } catch (error) {
      console.error('Biometric authentication failed:', error);
      throw new Error(`Authentication failed: ${error.message}`);
    }
  }

  /**
   * Get registered credentials for conditional UI
   */
  async getConditionalCredentials() {
    if (!this.isSupported) return [];

    try {
      const isConditionalAvailable = await PublicKeyCredential.isConditionalMediationAvailable?.();
      if (!isConditionalAvailable) return [];

      const storedCredentials = this.getStoredCredentials();
      return storedCredentials.map(cred => ({
        id: cred.id,
        name: cred.displayName || 'Biometric Login',
        lastUsed: cred.lastUsed || cred.created
      }));
    } catch (error) {
      console.error('Error getting conditional credentials:', error);
      return [];
    }
  }

  /**
   * Remove a biometric credential
   */
  async removeBiometric(credentialId) {
    try {
      const credentials = this.getStoredCredentials();
      const updatedCredentials = credentials.filter(cred => cred.id !== credentialId);
      
      localStorage.setItem('taskflow_biometric_credentials', JSON.stringify(updatedCredentials));
      
      return {
        success: true,
        message: 'Biometric credential removed successfully'
      };
    } catch (error) {
      console.error('Error removing biometric:', error);
      throw new Error('Failed to remove biometric credential');
    }
  }

  /**
   * Check if user has registered biometrics
   */
  hasRegisteredBiometrics() {
    const credentials = this.getStoredCredentials();
    return credentials.length > 0;
  }

  /**
   * Get biometric authentication status
   */
  async getAuthenticationStatus() {
    const isSupported = this.isSupported;
    const hasCredentials = this.hasRegisteredBiometrics();
    const availableAuthenticators = await this.getAvailableAuthenticators();

    return {
      isSupported,
      hasCredentials,
      availableAuthenticators,
      isReady: isSupported && availableAuthenticators.length > 0
    };
  }

  // Utility methods
  generateChallenge() {
    const array = new Uint8Array(32);
    crypto.getRandomValues(array);
    return array;
  }

  stringToArrayBuffer(str) {
    const encoder = new TextEncoder();
    return encoder.encode(str);
  }

  arrayBufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
  }

  base64ToArrayBuffer(base64) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  }

  saveCredential(credentialData) {
    const credentials = this.getStoredCredentials();
    credentials.push(credentialData);
    localStorage.setItem('taskflow_biometric_credentials', JSON.stringify(credentials));
  }

  getStoredCredentials() {
    try {
      const stored = localStorage.getItem('taskflow_biometric_credentials');
      return stored ? JSON.parse(stored) : [];
    } catch (error) {
      console.error('Error getting stored credentials:', error);
      return [];
    }
  }

  getAllowedCredentials() {
    const credentials = this.getStoredCredentials();
    return credentials.map(cred => ({
      id: this.base64ToArrayBuffer(cred.rawId),
      type: 'public-key'
    }));
  }

  async verifyAuthentication(authData) {
    // Mock verification - in production, this would be done on the server
    // Check if the credential ID exists in stored credentials
    const credentials = this.getStoredCredentials();
    const credential = credentials.find(cred => cred.id === authData.id);
    
    if (!credential) {
      return false;
    }

    // Update last used timestamp
    credential.lastUsed = Date.now();
    this.saveCredential(credential);

    return true;
  }
}

// Biometric Authentication UI Component
class BiometricAuthUI {
  constructor(biometricAuth) {
    this.auth = biometricAuth;
    this.modal = null;
  }

  /**
   * Show biometric setup modal
   */
  async showSetupModal() {
    const status = await this.auth.getAuthenticationStatus();
    
    if (!status.isSupported) {
      this.showError('Biometric authentication is not supported on this device');
      return;
    }

    if (!status.isReady) {
      this.showError('No biometric authenticators available');
      return;
    }

    this.createModal('setup');
  }

  /**
   * Show biometric authentication modal
   */
  async showAuthModal() {
    const status = await this.auth.getAuthenticationStatus();
    
    if (!status.hasCredentials) {
      this.showError('No biometric credentials registered');
      return;
    }

    this.createModal('auth');
    
    // Auto-trigger authentication
    try {
      const result = await this.auth.authenticateBiometric();
      this.hideModal();
      this.onAuthSuccess(result);
    } catch (error) {
      this.showError(error.message);
    }
  }

  /**
   * Create modal UI
   */
  createModal(type) {
    // Remove existing modal
    this.hideModal();

    const modalHTML = `
      <div id="biometric-modal" class="biometric-modal">
        <div class="biometric-modal-content">
          <div class="biometric-icon">
            ${type === 'setup' ? '🔐' : '👆'}
          </div>
          <h2>${type === 'setup' ? 'Set Up Biometric Authentication' : 'Authenticate'}</h2>
          <p id="biometric-message">
            ${type === 'setup' 
              ? 'Use your fingerprint, face, or other biometric to secure your TaskFlow AI account'
              : 'Use your biometric authentication to sign in'
            }
          </p>
          <div class="biometric-actions">
            ${type === 'setup' 
              ? `<button id="biometric-setup-btn" class="btn-primary">Set Up Now</button>`
              : ''
            }
            <button id="biometric-cancel-btn" class="btn-secondary">Cancel</button>
          </div>
          <div id="biometric-error" class="biometric-error hidden"></div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    this.modal = document.getElementById('biometric-modal');
    
    // Event listeners
    document.getElementById('biometric-cancel-btn').onclick = () => this.hideModal();
    
    if (type === 'setup') {
      document.getElementById('biometric-setup-btn').onclick = () => this.handleSetup();
    }

    // Add styles if not already added
    this.addStyles();
  }

  /**
   * Handle biometric setup
   */
  async handleSetup() {
    try {
      const setupBtn = document.getElementById('biometric-setup-btn');
      setupBtn.disabled = true;
      setupBtn.textContent = 'Setting up...';

      // Get user info (mock for demo)
      const userInfo = {
        username: 'user@taskflow.ai',
        displayName: 'TaskFlow User',
        userId: 'user-' + Date.now()
      };

      const result = await this.auth.registerBiometric(userInfo);
      
      this.hideModal();
      this.showSuccess(result.message);
      
    } catch (error) {
      this.showError(error.message);
      document.getElementById('biometric-setup-btn').disabled = false;
      document.getElementById('biometric-setup-btn').textContent = 'Set Up Now';
    }
  }

  /**
   * Show error message
   */
  showError(message) {
    if (this.modal) {
      const errorEl = document.getElementById('biometric-error');
      errorEl.textContent = message;
      errorEl.classList.remove('hidden');
    } else {
      alert(message); // Fallback
    }
  }

  /**
   * Show success message
   */
  showSuccess(message) {
    // Create success notification
    const notification = document.createElement('div');
    notification.className = 'biometric-success-notification';
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
      notification.remove();
    }, 3000);
  }

  /**
   * Hide modal
   */
  hideModal() {
    if (this.modal) {
      this.modal.remove();
      this.modal = null;
    }
  }

  /**
   * Authentication success callback
   */
  onAuthSuccess(result) {
    // Dispatch custom event
    window.dispatchEvent(new CustomEvent('biometric-auth-success', {
      detail: result
    }));
  }

  /**
   * Add required styles
   */
  addStyles() {
    if (document.getElementById('biometric-styles')) return;

    const styles = `
      <style id="biometric-styles">
        .biometric-modal {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.5);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 10000;
        }
        
        .biometric-modal-content {
          background: white;
          padding: 2rem;
          border-radius: 16px;
          max-width: 400px;
          width: 90%;
          text-align: center;
          box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .biometric-icon {
          font-size: 4rem;
          margin-bottom: 1rem;
        }
        
        .biometric-modal h2 {
          margin-bottom: 1rem;
          color: #1a73e8;
        }
        
        .biometric-modal p {
          margin-bottom: 2rem;
          color: #666;
          line-height: 1.5;
        }
        
        .biometric-actions {
          display: flex;
          gap: 1rem;
          justify-content: center;
          flex-wrap: wrap;
        }
        
        .biometric-error {
          margin-top: 1rem;
          padding: 0.75rem;
          background: #fee;
          color: #c53030;
          border-radius: 8px;
          font-size: 0.875rem;
        }
        
        .biometric-error.hidden {
          display: none;
        }
        
        .biometric-success-notification {
          position: fixed;
          top: 20px;
          right: 20px;
          background: #10b981;
          color: white;
          padding: 1rem 1.5rem;
          border-radius: 8px;
          box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
          z-index: 10001;
          animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
          from { transform: translateX(100%); opacity: 0; }
          to { transform: translateX(0); opacity: 1; }
        }
        
        .btn-primary, .btn-secondary {
          padding: 0.75rem 1.5rem;
          border: none;
          border-radius: 8px;
          font-weight: 500;
          cursor: pointer;
          transition: all 0.2s;
        }
        
        .btn-primary {
          background: #1a73e8;
          color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
          background: #1557b0;
        }
        
        .btn-primary:disabled {
          background: #9ca3af;
          cursor: not-allowed;
        }
        
        .btn-secondary {
          background: #f3f4f6;
          color: #374151;
        }
        
        .btn-secondary:hover {
          background: #e5e7eb;
        }
      </style>
    `;

    document.head.insertAdjacentHTML('beforeend', styles);
  }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { BiometricAuth, BiometricAuthUI };
} else {
  window.BiometricAuth = BiometricAuth;
  window.BiometricAuthUI = BiometricAuthUI;
}