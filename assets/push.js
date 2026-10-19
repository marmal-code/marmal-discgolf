// Zapnutí/vypnutí push upozornění na tomto zařízení + zkušební notifikace.
(function () {
  var on = document.getElementById('pushon'), test = document.getElementById('pushtest'), msg = document.getElementById('pushmsg'); if (!on) return;
  function say(t) { msg.textContent = t; }
  function post(o) { return fetch('push.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': window.CSRF }, body: JSON.stringify(o) }).then(function (r) { return r.json(); }); }
  function key(b64) { var p = '='.repeat((4 - b64.length % 4) % 4), s = atob((b64 + p).replace(/-/g, '+').replace(/_/g, '/')), a = new Uint8Array(s.length); for (var i = 0; i < s.length; i++) a[i] = s.charCodeAt(i); return a; }
  var ok = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  if (!ok) { on.disabled = true; say('Tento prohlížeč push upozornění nepodporuje. Na iPhonu přidej aplikaci na plochu a otevři ji odtud.'); }
  function state() {
    if (!ok) return;
    navigator.serviceWorker.getRegistration('sw.js').then(function (r) { return r && r.pushManager.getSubscription(); }).then(function (s) {
      on.textContent = s ? 'Vypnout na tomto zařízení' : 'Zapnout na tomto zařízení'; on.dataset.on = s ? '1' : '';
    });
  }
  on.addEventListener('click', function () {
    if (!ok) return; say('');
    if (on.dataset.on) {
      navigator.serviceWorker.getRegistration('sw.js').then(function (r) { return r.pushManager.getSubscription(); }).then(function (s) {
        if (!s) return; return post({ a: 'unsubscribe', endpoint: s.endpoint }).then(function () { return s.unsubscribe(); });
      }).then(function () { say('Vypnuto.'); state(); setTimeout(function () { location.reload(); }, 600); });
      return;
    }
    Notification.requestPermission().then(function (p) {
      if (p !== 'granted') { say('Upozornění nejsou povolená v nastavení prohlížeče/telefonu.'); return; }
      return navigator.serviceWorker.register('sw.js').then(function () { return navigator.serviceWorker.ready; }).then(function (reg) {
        return fetch('push.php').then(function (r) { return r.json(); }).then(function (k) { return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key(k.key) }); });
      }).then(function (sub) {
        var name = /iPhone|iPad/.test(navigator.userAgent) ? 'iPhone/iPad' : /Android/.test(navigator.userAgent) ? 'Android' : 'Počítač';
        return post({ a: 'subscribe', endpoint: sub.endpoint, name: name });
      }).then(function (r) { if (r && r.ok) { say('Zapnuto. Zkus zkušební upozornění.'); state(); setTimeout(function () { location.reload(); }, 800); } else say('Uložení selhalo.'); });
    }).catch(function (e) { say('Chyba: ' + (e && e.message || e)); });
  });
  test.addEventListener('click', function () { say('Posílám…'); post({ a: 'test' }).then(function (r) { say(r.ok ? 'Odesláno na ' + r.sent + ' zařízení.' : (r.error || 'Nepodařilo se.')); }); });
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-pushrm]'); if (b) post({ a: 'remove', id: +b.dataset.pushrm }).then(function () { location.reload(); }); });
  state();
})();
