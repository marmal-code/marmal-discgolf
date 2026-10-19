(function () {
  // potvrzení smazání
  document.addEventListener('submit', function (e) {
    var m = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (m && !window.confirm(m)) e.preventDefault();
  });

  // ---- Bag: filtry
  var grid = document.getElementById('grid');
  if (grid) {
    var st = { s: {} }, q = document.getElementById('q');
    try { var saved = JSON.parse(sessionStorage.getItem('bagf') || '{}'); if (saved.s) st.s = saved.s; if (saved.q) q.value = saved.q; ['fcat', 'fspeed', 'fturn', 'ffade'].forEach(function (id) { if (saved[id]) document.getElementById(id).value = saved[id]; }); } catch (e) {}
    var apply = function () {
      var cat = document.getElementById('fcat').value, sp = document.getElementById('fspeed').value, tu = document.getElementById('fturn').value, fa = document.getElementById('ffade').value;
      var term = q.value.trim().toLowerCase(), any = Object.keys(st.s).some(function (k) { return st.s[k]; }), shown = 0;
      grid.querySelectorAll('.disc').forEach(function (c) {
        var ok = (!cat || c.dataset.cat === cat) && (!sp || c.dataset.speed === sp) && (!tu || c.dataset.turn === tu) && (!fa || c.dataset.fade === fa) &&
          (!term || c.dataset.t.indexOf(term) > -1) && (!any || st.s[c.dataset.s]);
        c.hidden = !ok; if (ok) shown++;
      });
      document.getElementById('none').hidden = shown > 0;
      document.querySelectorAll('#fstatus .chip').forEach(function (b) { b.setAttribute('aria-pressed', st.s[b.dataset.s] ? 'true' : 'false'); });
      try { var o = { s: st.s, q: q.value }; ['fcat', 'fspeed', 'fturn', 'ffade'].forEach(function (id) { o[id] = document.getElementById(id).value; }); sessionStorage.setItem('bagf', JSON.stringify(o)); } catch (e) {}
    };
    document.getElementById('filters').addEventListener('input', apply);
    document.getElementById('fstatus').addEventListener('click', function (e) { var b = e.target.closest('.chip'); if (!b) return; st.s[b.dataset.s] = !st.s[b.dataset.s]; apply(); });
    apply();

    grid.addEventListener('click', function (e) {
      var b = e.target.closest('[data-toggle]'); if (!b) return;
      var card = b.closest('.disc'), field = b.dataset.toggle; b.disabled = true;
      fetch('action.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': window.CSRF }, body: JSON.stringify({ id: +card.dataset.id, field: field }) })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (r.needPrice) { location.href = 'disc.php?id=' + card.dataset.id + '#pricebox'; return; }
          location.reload();
        }).catch(function () { b.disabled = false; });
    });
  }

  // ---- Disk: cena jen když je na prodej
  var fs = document.getElementById('forsale'), pb = document.getElementById('pricebox');
  if (fs && pb) { var t = function () { pb.hidden = !fs.checked; }; fs.addEventListener('change', t); t(); }

  // ---- Veřejný prodej: modál s poptávkou
  var modal = document.getElementById('inq');
  if (modal) {
    document.addEventListener('click', function (e) {
      var o = e.target.closest('[data-inq]');
      if (o) { modal.querySelector('[name=disc_id]').value = o.dataset.inq; modal.querySelector('#inqt').textContent = o.dataset.name; modal.classList.add('open'); return; }
      if (e.target === modal || e.target.closest('[data-close]')) modal.classList.remove('open');
    });
  }

  // ---- Aktualizace aplikace
  var up = document.getElementById('doupdate');
  if (up) up.addEventListener('click', function () {
    up.disabled = true; up.textContent = 'Aktualizuji…';
    var log = document.getElementById('uplog'); log.hidden = false; log.textContent = 'Stahuji z GitHubu…';
    fetch('update.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': window.CSRF }, body: '{}' })
      .then(function (r) { return r.json(); })
      .then(function (r) { log.textContent = (r.log || []).join('\n') || r.error || ''; up.textContent = r.ok ? 'Hotovo – načíst znovu' : 'Zkusit znovu'; up.disabled = false; if (r.ok) setTimeout(function () { location.reload(); }, 1500); })
      .catch(function (e) { log.textContent = 'Chyba: ' + e; up.disabled = false; up.textContent = 'Zkusit znovu'; });
  });
})();
