/**
 * Background Sync Manager for TaskFlow AI
 * Handles offline data synchronization and background updates
 */

class BackgroundSyncManager {
  constructor() {
    this.isSupported = this.checkSupport();
    this.syncTags = {
      TASK_SYNC: 'task-sync',
      PROJECT_SYNC: 'project-sync',
      NOTE_SYNC: 'note-sync',
      SCRAP_SYNC: 'scrap-sync',
      SETTINGS_SYNC: 'settings-sync',
      FULL_SYNC: 'full-sync'
    };
    this.dbName = 'TaskFlowSyncDB';
    this.dbVersion = 1;
    this.db = null;
    this.syncQueue = [];
    this.isOnline = navigator.onLine;
    this.lastSyncTime = this.getLastSyncTime();
    
    this.init();
  }

  /**
   * Check if background sync is supported
   */
  checkSupport() {
    return (
      'serviceWorker' in navigator &&
      'sync' in window.ServiceWorkerRegistration.prototype
    );
  }

  /**
   * Initialize background sync manager
   */
  async init() {
    try {
      await this.initDatabase();
      this.setupEventListeners();
      await this.loadSyncQueue();
      
      if (this.isOnline) {
        await this.processSyncQueue();
      }
    } catch (error) {
      console.error('Failed to initialize BackgroundSyncManager:', error);
    }
  }

  /**
   * Initialize IndexedDB for sync queue
   */
  async initDatabase() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.dbVersion);
      
      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        this.db = request.result;
        resolve(this.db);
      };
      
      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        
        // Create sync queue store
        if (!db.objectStoreNames.contains('syncQueue')) {
          const syncStore = db.createObjectStore('syncQueue', { 
            keyPath: 'id', 
            autoIncrement: true 
          });
          syncStore.createIndex('type', 'type');
          syncStore.createIndex('timestamp', 'timestamp');
          syncStore.createIndex('priority', 'priority');
        }

        // Create data stores for offline caching
        const stores = ['tasks', 'projects', 'notes', 'scraps', 'settings'];
        stores.forEach(storeName => {
          if (!db.objectStoreNames.contains(storeName)) {
            const store = db.createObjectStore(storeName, { 
              keyPath: 'id' 
            });
            store.createIndex('updated_at', 'updated_at');
            store.createIndex('sync_status', 'sync_status');
          }
        });

        // Create conflict resolution store
        if (!db.objectStoreNames.contains('conflicts')) {
          const conflictStore = db.createObjectStore('conflicts', { 
            keyPath: 'id', 
            autoIncrement: true 
          });
          conflictStore.createIndex('entity_type', 'entity_type');
          conflictStore.createIndex('entity_id', 'entity_id');
        }
      };
    });
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Online/offline detection
    window.addEventListener('online', () => {
      console.log('Connection restored');
      this.isOnline = true;
      this.processSyncQueue();
    });

    window.addEventListener('offline', () => {
      console.log('Connection lost');
      this.isOnline = false;
    });

    // Visibility change for background sync
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden && this.isOnline) {
        this.checkForUpdates();
      }
    });

    // Periodic sync check
    setInterval(() => {
      if (this.isOnline && this.shouldPerformPeriodicSync()) {
        this.performPeriodicSync();
      }
    }, 5 * 60 * 1000); // Every 5 minutes
  }

  /**
   * Queue operation for background sync
   */
  async queueOperation(operation) {
    const syncItem = {
      type: operation.type,
      data: operation.data,
      url: operation.url,
      method: operation.method || 'POST',
      headers: operation.headers || { 'Content-Type': 'application/json' },
      timestamp: Date.now(),
      priority: operation.priority || 'normal',
      retryCount: 0,
      maxRetries: 3
    };

    // Add to IndexedDB
    await this.addToSyncQueue(syncItem);
    
    // Add to memory queue
    this.syncQueue.push(syncItem);

    // Try to sync immediately if online
    if (this.isOnline) {
      await this.processSyncQueue();
    } else {
      // Register background sync
      await this.registerBackgroundSync(operation.type);
    }

    return syncItem;
  }

  /**
   * Add item to sync queue in IndexedDB
   */
  async addToSyncQueue(item) {
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction(['syncQueue'], 'readwrite');
      const store = transaction.objectStore('syncQueue');
      const request = store.add(item);
      
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Load sync queue from IndexedDB
   */
  async loadSyncQueue() {
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction(['syncQueue'], 'readonly');
      const store = transaction.objectStore('syncQueue');
      const request = store.getAll();
      
      request.onsuccess = () => {
        this.syncQueue = request.result;
        resolve(this.syncQueue);
      };
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Process sync queue
   */
  async processSyncQueue() {
    if (!this.isOnline || this.syncQueue.length === 0) {
      return;
    }

    console.log(`Processing ${this.syncQueue.length} sync items`);
    
    // Sort by priority and timestamp
    this.syncQueue.sort((a, b) => {
      const priorityOrder = { high: 3, normal: 2, low: 1 };
      const aPriority = priorityOrder[a.priority] || 2;
      const bPriority = priorityOrder[b.priority] || 2;
      
      if (aPriority !== bPriority) {
        return bPriority - aPriority;
      }
      return a.timestamp - b.timestamp;
    });

    const processed = [];
    const failed = [];

    for (const item of this.syncQueue) {
      try {
        const result = await this.processSyncItem(item);
        if (result.success) {
          processed.push(item);
          await this.removeFromSyncQueue(item.id);
        } else {
          item.retryCount++;
          if (item.retryCount >= item.maxRetries) {
            failed.push(item);
            await this.removeFromSyncQueue(item.id);
          } else {
            await this.updateSyncItem(item);
          }
        }
      } catch (error) {
        console.error('Sync item failed:', error);
        item.retryCount++;
        item.lastError = error.message;
        
        if (item.retryCount >= item.maxRetries) {
          failed.push(item);
          await this.removeFromSyncQueue(item.id);
        } else {
          await this.updateSyncItem(item);
        }
      }
    }

    // Update in-memory queue
    this.syncQueue = this.syncQueue.filter(item => 
      !processed.some(p => p.id === item.id) && 
      !failed.some(f => f.id === item.id)
    );

    // Update last sync time
    this.updateLastSyncTime();

    // Handle conflicts if any
    if (failed.length > 0) {
      await this.handleSyncFailures(failed);
    }

    console.log(`Sync complete: ${processed.length} succeeded, ${failed.length} failed`);
  }

  /**
   * Process individual sync item
   */
  async processSyncItem(item) {
    try {
      const response = await fetch(item.url, {
        method: item.method,
        headers: item.headers,
        body: item.method !== 'GET' ? JSON.stringify(item.data) : undefined
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const result = await response.json();

      // Update local data if successful
      await this.updateLocalData(item.type, item.data, result);

      return { success: true, result };
    } catch (error) {
      console.error(`Sync item failed (${item.type}):`, error);
      return { success: false, error: error.message };
    }
  }

  /**
   * Update local data after successful sync
   */
  async updateLocalData(type, originalData, serverResult) {
    if (!serverResult.data) return;

    const storeName = this.getStoreNameFromType(type);
    if (!storeName) return;

    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([storeName], 'readwrite');
      const store = transaction.objectStore(storeName);
      
      const data = {
        ...serverResult.data,
        sync_status: 'synced',
        last_sync: Date.now()
      };

      const request = store.put(data);
      
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Register background sync with service worker
   */
  async registerBackgroundSync(tag) {
    if (!this.isSupported) return;

    try {
      const registration = await navigator.serviceWorker.ready;
      await registration.sync.register(tag);
      console.log(`Background sync registered: ${tag}`);
    } catch (error) {
      console.error('Failed to register background sync:', error);
    }
  }

  /**
   * Remove item from sync queue
   */
  async removeFromSyncQueue(itemId) {
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction(['syncQueue'], 'readwrite');
      const store = transaction.objectStore('syncQueue');
      const request = store.delete(itemId);
      
      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Update sync item in queue
   */
  async updateSyncItem(item) {
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction(['syncQueue'], 'readwrite');
      const store = transaction.objectStore('syncQueue');
      const request = store.put(item);
      
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Handle sync failures and conflicts
   */
  async handleSyncFailures(failedItems) {
    for (const item of failedItems) {
      // Store conflict for user resolution
      const conflict = {
        entity_type: this.getEntityTypeFromSyncType(item.type),
        entity_id: item.data.id,
        local_data: item.data,
        error_message: item.lastError,
        timestamp: Date.now(),
        resolved: false
      };

      await this.storeConflict(conflict);
    }

    // Notify user about conflicts
    this.notifyConflicts(failedItems.length);
  }

  /**
   * Store conflict in database
   */
  async storeConflict(conflict) {
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction(['conflicts'], 'readwrite');
      const store = transaction.objectStore('conflicts');
      const request = store.add(conflict);
      
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Get unresolved conflicts
   */
  async getUnresolvedConflicts() {
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction(['conflicts'], 'readonly');
      const store = transaction.objectStore('conflicts');
      const request = store.getAll();
      
      request.onsuccess = () => {
        const conflicts = request.result.filter(c => !c.resolved);
        resolve(conflicts);
      };
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Resolve conflict
   */
  async resolveConflict(conflictId, resolution) {
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction(['conflicts'], 'readwrite');
      const store = transaction.objectStore('conflicts');
      const getRequest = store.get(conflictId);
      
      getRequest.onsuccess = () => {
        const conflict = getRequest.result;
        if (conflict) {
          conflict.resolved = true;
          conflict.resolution = resolution;
          conflict.resolved_at = Date.now();
          
          const putRequest = store.put(conflict);
          putRequest.onsuccess = () => resolve(conflict);
          putRequest.onerror = () => reject(putRequest.error);
        } else {
          reject(new Error('Conflict not found'));
        }
      };
      getRequest.onerror = () => reject(getRequest.error);
    });
  }

  /**
   * Check for updates from server
   */
  async checkForUpdates() {
    if (!this.isOnline) return;

    try {
      const response = await fetch('/api/sync/check-updates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          lastSync: this.lastSyncTime,
          entities: ['tasks', 'projects', 'notes', 'scraps']
        })
      });

      if (!response.ok) return;

      const updates = await response.json();
      
      if (updates.hasUpdates) {
        await this.pullUpdates(updates.entities);
      }
    } catch (error) {
      console.error('Failed to check for updates:', error);
    }
  }

  /**
   * Pull updates from server
   */
  async pullUpdates(entities) {
    for (const entity of entities) {
      try {
        const response = await fetch(`/api/sync/pull/${entity}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ since: this.lastSyncTime })
        });

        if (response.ok) {
          const data = await response.json();
          await this.mergeServerUpdates(entity, data.items);
        }
      } catch (error) {
        console.error(`Failed to pull ${entity} updates:`, error);
      }
    }
  }

  /**
   * Merge server updates with local data
   */
  async mergeServerUpdates(entityType, serverItems) {
    if (!serverItems || serverItems.length === 0) return;

    const storeName = entityType;
    
    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([storeName], 'readwrite');
      const store = transaction.objectStore('storeName');
      
      let processed = 0;
      const total = serverItems.length;

      serverItems.forEach(serverItem => {
        const getRequest = store.get(serverItem.id);
        
        getRequest.onsuccess = () => {
          const localItem = getRequest.result;
          let shouldUpdate = true;

          if (localItem) {
            // Check for conflicts
            if (localItem.updated_at > serverItem.updated_at && 
                localItem.sync_status === 'pending') {
              // Local version is newer and pending sync - create conflict
              shouldUpdate = false;
              this.storeConflict({
                entity_type: entityType,
                entity_id: serverItem.id,
                local_data: localItem,
                server_data: serverItem,
                timestamp: Date.now(),
                resolved: false
              });
            }
          }

          if (shouldUpdate) {
            const putRequest = store.put({
              ...serverItem,
              sync_status: 'synced',
              last_sync: Date.now()
            });
            
            putRequest.onsuccess = () => {
              processed++;
              if (processed === total) resolve();
            };
          } else {
            processed++;
            if (processed === total) resolve();
          }
        };
      });
    });
  }

  /**
   * Perform periodic sync
   */
  async performPeriodicSync() {
    console.log('Performing periodic sync');
    await this.checkForUpdates();
    await this.processSyncQueue();
  }

  /**
   * Check if periodic sync should be performed
   */
  shouldPerformPeriodicSync() {
    const now = Date.now();
    const fiveMinutes = 5 * 60 * 1000;
    return (now - this.lastSyncTime) > fiveMinutes;
  }

  /**
   * Get sync status
   */
  getSyncStatus() {
    return {
      isOnline: this.isOnline,
      isSupported: this.isSupported,
      queueLength: this.syncQueue.length,
      lastSync: this.lastSyncTime,
      hasPendingSync: this.syncQueue.length > 0
    };
  }

  /**
   * Force full sync
   */
  async forceFullSync() {
    if (!this.isOnline) {
      throw new Error('Cannot perform full sync while offline');
    }

    await this.registerBackgroundSync(this.syncTags.FULL_SYNC);
    await this.checkForUpdates();
    await this.processSyncQueue();
  }

  // Utility methods

  getStoreNameFromType(syncType) {
    if (syncType.includes('task')) return 'tasks';
    if (syncType.includes('project')) return 'projects';
    if (syncType.includes('note')) return 'notes';
    if (syncType.includes('scrap')) return 'scraps';
    if (syncType.includes('settings')) return 'settings';
    return null;
  }

  getEntityTypeFromSyncType(syncType) {
    if (syncType.includes('task')) return 'task';
    if (syncType.includes('project')) return 'project';
    if (syncType.includes('note')) return 'note';
    if (syncType.includes('scrap')) return 'scrap';
    if (syncType.includes('settings')) return 'settings';
    return 'unknown';
  }

  getLastSyncTime() {
    const stored = localStorage.getItem('taskflow_last_sync');
    return stored ? parseInt(stored) : 0;
  }

  updateLastSyncTime() {
    this.lastSyncTime = Date.now();
    localStorage.setItem('taskflow_last_sync', this.lastSyncTime.toString());
  }

  notifyConflicts(count) {
    // Create notification about sync conflicts
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification('Sync Conflicts Detected', {
        body: `${count} items couldn't sync. Please review conflicts.`,
        icon: '/icons/icon-192x192.png',
        tag: 'sync-conflicts'
      });
    }

    // Also dispatch custom event
    window.dispatchEvent(new CustomEvent('sync-conflicts', {
      detail: { count }
    }));
  }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = BackgroundSyncManager;
} else {
  window.BackgroundSyncManager = BackgroundSyncManager;
}