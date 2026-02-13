// Service worker: cache API responses for offline, network-first with cache fallback
const CACHE = 'fermmon-v2';

function isApiRequest(url) {
  return new URL(url).pathname.startsWith('/api/');
}

function cacheKey(request) {
  const url = new URL(request.url);
  if (isApiRequest(request.url)) {
    url.searchParams.delete('t'); // strip cache-buster for consistent cache key
  }
  return url.href;
}

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then(keys => Promise.all(
    keys.filter(k => k !== CACHE).map(k => caches.delete(k))
  )).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  if (!e.request.url.startsWith(self.location.origin)) return;

  if (isApiRequest(e.request.url)) {
    e.respondWith(handleApiFetch(e));
    return;
  }

  // Static: network first, cache on success, fallback to cache when offline
  e.respondWith(fetch(e.request).then(r => {
    if (r.ok && r.type === 'basic') {
      caches.open(CACHE).then(cache => cache.put(e.request, r.clone()));
    }
    return r;
  }).catch(() => caches.match(e.request)));
});

async function handleApiFetch(e) {
  const key = cacheKey(e.request);
  try {
    const response = await fetch(e.request);
    if (!response.ok) throw new Error('Not ok');
    const body = await response.arrayBuffer();
    const headers = new Headers(response.headers);
    headers.set('X-Cached-Date', new Date().toISOString());
    const toCache = new Response(body, { headers, status: response.status });
    const cache = await caches.open(CACHE);
    await cache.put(key, toCache.clone());
    return new Response(body, { headers: response.headers, status: response.status });
  } catch (err) {
    const cached = await caches.match(key);
    if (cached) {
      const headers = new Headers(cached.headers);
      headers.set('X-Served-From-Cache', '1');
      const body = await cached.arrayBuffer();
      return new Response(body, { headers, status: cached.status });
    }
    throw err;
  }
}
