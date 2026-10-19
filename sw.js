// Service worker: příjem push upozornění (nová poptávka z prodeje).
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('push', function (e) {
  e.waitUntil(self.registration.showNotification('Nová poptávka disků', {
    body: 'Někdo poslal zájem o disky z veřejného prodeje. Klepni pro zobrazení.',
    icon: 'assets/icon-192.png', badge: 'assets/icon-192.png', tag: 'poptavka', renotify: true, data: { url: 'poptavky.php' }
  }));
});
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = new URL((e.notification.data && e.notification.data.url) || 'poptavky.php', self.registration.scope).href;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var c of list) if (c.url.indexOf('poptavky.php') > -1 && 'focus' in c) return c.focus();
    return self.clients.openWindow(url);
  }));
});
