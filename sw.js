

const CACHE_NAME = 'chmews-v1';
const OFFLINE_URL = 'offline.html';

const STATIC_ASSETS = [
    '/',
    '/index.php',
    '/offline.html',
    '/assets/css/style.css',
    '/assets/css/dashboard.css',
    '/assets/js/main.js',
    '/assets/js/validation.js'
];


self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting())
    );
});


self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});


self.addEventListener('fetch', (event) => {
    
    if (event.request.method !== 'GET') {
        return;
    }

    // Skip API requests from being cached (except specific ones)
    if (event.request.url.includes('/api/')) {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    PI calls
                    return new Response(
                        JSON.stringify({ offline: true, message: 'You are offline' }),
                        { headers: { 'Content-Type': 'application/json' } }
                    );
                })
        );
        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then((cachedResponse) => {
                if (cachedResponse) {
                    // Return cached response
                    return cachedResponse;
                }

                // Fetch from network and cache
                return fetch(event.request)
                    .then((response) => {
                        // Don't cache non-successful responses
                        if (!response || response.status !== 200) {
                            return response;
                        }

                        // Clone the response
                        const responseToCache = response.clone();

                        caches.open(CACHE_NAME)
                            .then((cache) => {
                                cache.put(event.request, responseToCache);
                            });

                        return response;
                    })
                    .catch(() => {
                        // Return offline page for navigation requests
                        if (event.request.mode === 'navigate') {
                            return caches.match('/offline.html');
                        }
                        return new Response('Offline', { status: 503 });
                    });
            })
    );
});

// Handle background sync for offline reports
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-reports') {
        event.waitUntil(syncOfflineReports());
    }
});

// Sync offline reports when back online
async function syncOfflineReports() {
    try {
        const db = await openDB();
        const tx = db.transaction('pending_reports', 'readonly');
        const store = tx.objectStore('pending_reports');
        const reports = await store.getAll();

        for (const report of reports) {
            try {
                const response = await fetch('/api/sync_report.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(report)
                });

                if (response.ok) {
                    // Delete from local storage after successful sync
                    const deleteTx = db.transaction('pending_reports', 'readwrite');
                    await deleteTx.objectStore('pending_reports').delete(report.id);
                    
                    // Notify the app
                    self.clients.matchAll().then(clients => {
                        clients.forEach(client => {
                            client.postMessage({
                                type: 'REPORT_SYNCED',
                                reportId: report.id
                            });
                        });
                    });
                }
            } catch (error) {
                console.error('Failed to sync report:', error);
            }
        }
    } catch (error) {
        console.error('Sync failed:', error);
    }
}

// Open IndexedDB
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('chmews_offline', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('pending_reports')) {
                db.createObjectStore('pending_reports', { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('cached_data')) {
                db.createObjectStore('cached_data', { keyPath: 'key' });
            }
        };
    });
}

// Handle push notifications
self.addEventListener('push', (event) => {
    const data = event.data?.json() ?? { title: 'CHMEWS', body: 'New notification' };
    
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/images/icon-192.png',
            badge: '/images/badge-72.png',
            data: data.url
        })
    );
});

// Handle notification click
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    event.waitUntil(
        self.clients.openWindow(event.notification.data || '/')
    );
});
