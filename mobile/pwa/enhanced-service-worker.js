// Enhanced TaskFlow AI Service Worker
// Advanced PWA functionality with offline sync, background updates, and caching strategies

const CACHE_VERSION = '2.0.0';
const CACHE_PREFIX = 'taskflow-ai';
const STATIC_CACHE = `${CACHE_PREFIX}-static-v${CACHE_VERSION}`;
const DYNAMIC_CACHE = `${CACHE_PREFIX}-dynamic-v${CACHE_VERSION}`;
const API_CACHE = `${CACHE_PREFIX}-api-v${CACHE_VERSION}`;
const OFFLINE_PAGE = '/offline.html';

// Cache strategies configuration
const CACHE_STRATEGIES = {
  static: 'cache-first',
  dynamic: 'network-first',
  api: 'network-first-with-fallback',
  images: 'cache-first'
};

// URLs to cache on install
const STATIC_URLS = [
  '/',
  '/mobile-frontend.html',
  '/offline.html',
  '/mobile/pwa/manifest.json',
  // Core CSS and JS will be added dynamically
];

// API endpoints to cache
const API_ENDPOINTS = [
  '/api/tasks',
  '/api/scraps',
  '/api/projects',
  '/api/notes',
  '/api/plan',
  '/api/settings'
];

// Background sync tags
const SYNC_TAGS = {
  TASK_SYNC: 'task-sync',
  DATA_SYNC: 'data-sync',
  SETTINGS_SYNC: 'settings-sync'
};

// IndexedDB setup for offline data
const DB_NAME = 'TaskFlowDB';
const DB_VERSION = 1;
const STORES = {
  TASKS: 'tasks',
  SCRAPS: 'scraps',
  PROJECTS: 'projects',
  NOTES: 'notes',
  SYNC_QUEUE: 'sync_queue'
};

class TaskFlowServiceWorker {
  constructor() {
    this.dbPromise = this.initDB();
  }

  // Initialize IndexedDB
  initDB() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      
      request.onerror = () => reject(request.error);
      request.onsuccess = () => resolve(request.result);
      
      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        
        // Create object stores
        Object.values(STORES).forEach(storeName => {
          if (!db.objectStoreNames.contains(storeName)) {
            const store = db.createObjectStore(storeName, { keyPath: 'id', autoIncrement: true });
            
            if (storeName === STORES.SYNC_QUEUE) {
              store.createIndex('timestamp', 'timestamp');
              store.createIndex('type', 'type');
            } else {
              store.createIndex('updated_at', 'updated_at');
            }
          }
        });
      };
    });
  }

  // Cache management utilities
  async cleanupCaches() {
    const cacheNames = await caches.keys();
    const oldCaches = cacheNames.filter(name => 
      name.startsWith(CACHE_PREFIX) && !name.includes(CACHE_VERSION)
    );
    
    return Promise.all(oldCaches.map(name => caches.delete(name)));
  }

  // Network-first with cache fallback strategy
  async networkFirstWithCache(request, cacheName) {
    try {
      const networkResponse = await fetch(request.clone());
      
      if (networkResponse.ok) {
        const cache = await caches.open(cacheName);
        cache.put(request, networkResponse.clone());
      }
      
      return networkResponse;
    } catch (error) {
      const cachedResponse = await caches.match(request);
      if (cachedResponse) {
        return cachedResponse;
      }
      
      // Return offline page for navigation requests
      if (request.mode === 'navigate') {
        return caches.match(OFFLINE_PAGE);
      }
      
      throw error;
    }
  }

  // Cache-first with network update strategy
  async cacheFirstWithUpdate(request, cacheName) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
      // Update cache in background
      this.updateCache(request, cacheName);
      return cachedResponse;
    }
    
    return this.networkFirstWithCache(request, cacheName);
  }

  // Background cache update
  async updateCache(request, cacheName) {
    try {
      const response = await fetch(request);
      if (response.ok) {
        const cache = await caches.open(cacheName);
        cache.put(request, response);
      }
    } catch (error) {
      console.log('Background cache update failed:', error);
    }
  }

  // Handle API requests with offline support
  async handleAPIRequest(request) {
    const url = new URL(request.url);
    const isRead = request.method === 'GET';
    
    if (isRead) {
      return this.networkFirstWithCache(request, API_CACHE);
    }
    
    // Handle write operations
    try {
      const response = await fetch(request.clone());
      
      if (response.ok) {
        // Update local cache/DB with successful write
        await this.updateLocalData(request, response.clone());
      }
      
      return response;
    } catch (error) {
      // Queue for background sync
      await this.queueForSync(request);
      
      return new Response(
        JSON.stringify({ 
          success: true, 
          offline: true, 
          message: 'Saved offline, will sync when online' 
        }),
        { 
          status: 200, 
          headers: { 'Content-Type': 'application/json' } 
        }
      );
    }
  }

  // Update local data after successful API calls
  async updateLocalData(request, response) {
    try {
      const db = await this.dbPromise;
      const url = new URL(request.url);
      const data = await response.json();
      
      let storeName;
      if (url.pathname.includes('/api/tasks')) storeName = STORES.TASKS;
      else if (url.pathname.includes('/api/scraps')) storeName = STORES.SCRAPS;
      else if (url.pathname.includes('/api/projects')) storeName = STORES.PROJECTS;
      else if (url.pathname.includes('/api/notes')) storeName = STORES.NOTES;
      
      if (storeName && data.data) {
        const tx = db.transaction([storeName], 'readwrite');
        const store = tx.objectStore(storeName);
        
        if (Array.isArray(data.data)) {
          data.data.forEach(item => store.put({ ...item, updated_at: Date.now() }));
        } else {
          store.put({ ...data.data, updated_at: Date.now() });
        }
      }
    } catch (error) {
      console.log('Failed to update local data:', error);
    }
  }

  // Queue failed requests for background sync
  async queueForSync(request) {
    try {
      const db = await this.dbPromise;
      const tx = db.transaction([STORES.SYNC_QUEUE], 'readwrite');
      const store = tx.objectStore(STORES.SYNC_QUEUE);
      
      const requestData = {
        url: request.url,
        method: request.method,
        headers: Object.fromEntries(request.headers.entries()),
        body: request.method !== 'GET' ? await request.text() : null,
        timestamp: Date.now(),
        type: this.getSyncType(request.url)
      };
      
      store.add(requestData);
    } catch (error) {
      console.log('Failed to queue request for sync:', error);
    }
  }

  // Get sync type from URL
  getSyncType(url) {
    if (url.includes('/api/tasks')) return 'tasks';
    if (url.includes('/api/scraps')) return 'scraps';
    if (url.includes('/api/projects')) return 'projects';
    if (url.includes('/api/notes')) return 'notes';
    if (url.includes('/api/settings')) return 'settings';
    return 'general';
  }

  // Process background sync
  async processBackgroundSync(tag) {
    try {
      const db = await this.dbPromise;
      const tx = db.transaction([STORES.SYNC_QUEUE], 'readwrite');
      const store = tx.objectStore(STORES.SYNC_QUEUE);
      const index = store.index('type');
      
      const requests = await index.getAll(tag === SYNC_TAGS.TASK_SYNC ? 'tasks' : null);
      
      for (const requestData of requests) {
        try {
          const fetchOptions = {
            method: requestData.method,
            headers: requestData.headers
          };
          
          if (requestData.body) {
            fetchOptions.body = requestData.body;
          }
          
          const response = await fetch(requestData.url, fetchOptions);
          
          if (response.ok) {
            // Remove from sync queue
            store.delete(requestData.id);
          }
        } catch (error) {
          console.log('Failed to sync request:', requestData.url, error);
        }
      }
    } catch (error) {
      console.log('Background sync failed:', error);
    }
  }

  // Handle share target
  async handleShareTarget(request) {
    try {
      const formData = await request.formData();
      const title = formData.get('title') || '';
      const text = formData.get('text') || '';
      const url = formData.get('url') || '';
      const files = formData.getAll('files');
      
      // Store shared content for processing
      const sharedContent = {
        title,
        text,
        url,
        files: files.map(file => ({
          name: file.name,
          type: file.type,
          size: file.size
        })),
        timestamp: Date.now()
      };
      
      // Redirect to app with shared content
      return Response.redirect('/?shared=' + encodeURIComponent(JSON.stringify(sharedContent)));
    } catch (error) {
      console.log('Share target handling failed:', error);
      return Response.redirect('/');
    }
  }

  // Generate offline response for navigation
  getOfflineResponse() {
    return new Response(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>TaskFlow AI - Offline</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
          body { 
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            text-align: center;
            padding: 2rem;
            background: #f9fafb;
          }
          .offline-message {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 400px;
            margin: 2rem auto;
          }
        </style>
      </head>
      <body>
        <div class="offline-message">
          <h1>You're Offline</h1>
          <p>TaskFlow AI works offline! Your data will sync when you're back online.</p>
          <button onclick="location.reload()">Try Again</button>
        </div>
      </body>
      </html>
    `, {
      headers: { 'Content-Type': 'text/html' }
    });
  }
}

// Initialize service worker instance
const taskFlowSW = new TaskFlowServiceWorker();

// Install event
self.addEventListener('install', event => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(STATIC_CACHE);
      await cache.addAll(STATIC_URLS);
      self.skipWaiting();
    })()
  );
});

// Activate event
self.addEventListener('activate', event => {
  event.waitUntil(
    (async () => {
      await taskFlowSW.cleanupCaches();
      self.clients.claim();
    })()
  );
});

// Fetch event
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Handle different types of requests
  if (url.pathname.startsWith('/api/')) {
    // API requests
    event.respondWith(taskFlowSW.handleAPIRequest(request));
  } else if (url.pathname === '/share') {
    // Share target
    event.respondWith(taskFlowSW.handleShareTarget(request));
  } else if (request.mode === 'navigate') {
    // Navigation requests
    event.respondWith(taskFlowSW.networkFirstWithCache(request, DYNAMIC_CACHE));
  } else if (request.destination === 'image') {
    // Images
    event.respondWith(taskFlowSW.cacheFirstWithUpdate(request, STATIC_CACHE));
  } else {
    // Other static resources
    event.respondWith(taskFlowSW.networkFirstWithCache(request, STATIC_CACHE));
  }
});

// Background sync
self.addEventListener('sync', event => {
  if (event.tag === SYNC_TAGS.TASK_SYNC) {
    event.waitUntil(taskFlowSW.processBackgroundSync(event.tag));
  } else if (event.tag === SYNC_TAGS.DATA_SYNC) {
    event.waitUntil(taskFlowSW.processBackgroundSync(event.tag));
  }
});

// Push notification
self.addEventListener('push', event => {
  if (!event.data) return;
  
  const data = event.data.json();
  const options = {
    body: data.body || 'New notification from TaskFlow AI',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/badge-72x72.png',
    data: data.data || {},
    actions: [
      {
        action: 'view',
        title: 'View',
        icon: '/icons/action-view.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss',
        icon: '/icons/action-dismiss.png'
      }
    ],
    vibrate: [200, 100, 200],
    requireInteraction: true
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title || 'TaskFlow AI', options)
  );
});

// Notification click
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'view') {
    event.waitUntil(
      clients.openWindow(event.notification.data.url || '/')
    );
  }
});

// Message handling from main app
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});