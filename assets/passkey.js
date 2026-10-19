(function () {
  function b64(buf) { var s = ''; new Uint8Array(buf).forEach(function (b) { s += String.fromCharCode(b); }); return btoa(s); }
  function walk(o) {
    var P = '=?BINARY?B?', S = '?=';
    if (o && typeof o === 'object') for (var k in o) {
      if (typeof o[k] === 'string' && o[k].indexOf(P) === 0 && o[k].slice(-2) === S) {
        var bin = atob(o[k].slice(P.length, -2)), u = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) u[i] = bin.charCodeAt(i);
        o[k] = u.buffer;
      } else walk(o[k]);
    }
  }
  function post(action, csrf, body) {
    return fetch('passkey.php?a=' + action, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf }, body: JSON.stringify(body || {}), credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }
  function msg(t, bad) { var m = document.getElementById('keymsg'); if (m) { m.textContent = t; m.style.color = bad ? 'var(--bad)' : 'var(--good)'; } }

  var add = document.getElementById('addkey');
  if (add) add.addEventListener('click', function () {
    if (!window.PublicKeyCredential) return msg('Tenhle prohlížeč otisk prstu nepodporuje.', true);
    var csrf = add.dataset.csrf;
    post('reg_args', csrf).then(function (r) {
      if (r.error) throw new Error(r.error);
      walk(r.args); return navigator.credentials.create(r.args);
    }).then(function (cred) {
      return post('reg_verify', csrf, { clientDataJSON: b64(cred.response.clientDataJSON), attestationObject: b64(cred.response.attestationObject), name: (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || 'Zařízení' });
    }).then(function (r) {
      if (r.error) throw new Error(r.message || r.error);
      msg('Otisk prstu přidán ✓'); if (document.body.dataset.reload) location.reload();
    }).catch(function (e) { msg('Nepovedlo se: ' + (e.message || e), true); });
  });

  var lg = document.getElementById('pklogin');
  if (lg) lg.addEventListener('click', function () {
    if (!window.PublicKeyCredential) return msg('Tenhle prohlížeč otisk prstu nepodporuje.', true);
    var csrf = lg.dataset.csrf;
    post('login_args', csrf).then(function (r) {
      if (r.error) throw new Error(r.error);
      walk(r.args); return navigator.credentials.get(r.args);
    }).then(function (c) {
      return post('login_verify', csrf, { id: b64(c.rawId), clientDataJSON: b64(c.response.clientDataJSON), authenticatorData: b64(c.response.authenticatorData), signature: b64(c.response.signature) });
    }).then(function (r) {
      if (r.error) throw new Error(r.message || r.error);
      location.href = 'index.php';
    }).catch(function (e) { msg('Přihlášení se nepovedlo: ' + (e.message || e), true); });
  });
})();
