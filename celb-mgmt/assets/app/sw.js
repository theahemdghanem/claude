/* CELB Studio — service worker (served from the app's own URL so its scope
   is the app). Offline shell + Web Push. Version: __CELB_VERSION__ */
var VERSION = 'celb-studio-__CELB_VERSION__';
var SCOPE = '__CELB_SCOPE__';

self.addEventListener('install', function (e) { self.skipWaiting(); });
self.addEventListener('activate', function (e) {
	e.waitUntil(caches.keys().then(function (keys) {
		return Promise.all(keys.filter(function (k) { return k.indexOf('celb-studio-') === 0 && k !== VERSION; }).map(function (k) { return caches.delete(k); }));
	}).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (e) {
	var req = e.request;
	if (req.method !== 'GET') { return; }
	var url = new URL(req.url);
	// Never cache the API or WordPress admin.
	if (url.pathname.indexOf('/wp-json/') !== -1 || url.pathname.indexOf('/wp-admin/') !== -1 || url.search.indexOf('rest_route') !== -1) { return; }
	// App page: always fresh, saved copy only when offline.
	if (req.mode === 'navigate' && url.pathname.indexOf(SCOPE) === 0) {
		e.respondWith(fetch(req).then(function (res) {
			if (res.ok && !res.redirected) { var c = res.clone(); caches.open(VERSION).then(function (ca) { ca.put(SCOPE, c); }); }
			return res;
		}).catch(function () { return caches.match(SCOPE); }));
		return;
	}
	// App assets + fonts: cached, refreshed in the background.
	if (url.pathname.indexOf('/assets/app/') !== -1 || url.hostname.indexOf('fonts.g') === 0) {
		e.respondWith(caches.open(VERSION).then(function (ca) {
			return ca.match(req).then(function (hit) {
				var net = fetch(req).then(function (res) { if (res.ok || res.type === 'opaque') { ca.put(req, res.clone()); } return res; }).catch(function () { return hit; });
				return hit || net;
			});
		}));
	}
});

self.addEventListener('push', function (e) {
	var d = {};
	try { d = e.data ? e.data.json() : {}; } catch (err) { d = { title: 'CELB Studio', body: e.data ? e.data.text() : '' }; }
	e.waitUntil(self.registration.showNotification(d.title || 'CELB Studio', {
		body: d.body || '',
		icon: d.icon || undefined,
		badge: d.icon || undefined,
		tag: d.tag || undefined,
		renotify: !!d.tag,
		data: { url: d.url || SCOPE }
	}));
});

self.addEventListener('notificationclick', function (e) {
	e.notification.close();
	var target = (e.notification.data && e.notification.data.url) || SCOPE;
	e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
		for (var i = 0; i < list.length; i++) {
			var c = list[i];
			if (c.url.indexOf(SCOPE) !== -1) {
				c.postMessage({ type: 'navigate', url: target });
				return c.focus();
			}
		}
		return self.clients.openWindow(target);
	}));
});
