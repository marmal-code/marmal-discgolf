(function () {
  // zámek: čas poslední aktivity; po návratu po >5 min se vyžádá otisk znovu
  try {
    var seen = function () { localStorage.setItem('dgseen', String(Date.now())); };
    if (sessionStorage.getItem('dgun') === '1') { seen(); setInterval(function () { if (!document.hidden) seen(); }, 20000); }
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { if (sessionStorage.getItem('dgun') === '1') seen(); return; }
      var s = +localStorage.getItem('dgseen') || 0;
      if (sessionStorage.getItem('dgun') === '1' && Date.now() - s > 300000) { sessionStorage.removeItem('dgun'); location.reload(); }
    });
  } catch (e) {}
  // přepínač světlý / tmavý režim (výchozí je světlý)
  var tb = document.getElementById('themebtn');
  if (tb) tb.addEventListener('click', function () {
    var dark = document.documentElement.getAttribute('data-theme') !== 'dark';
    if (dark) document.documentElement.setAttribute('data-theme', 'dark'); else document.documentElement.removeAttribute('data-theme');
    try { localStorage.setItem('dgtheme', dark ? 'dark' : 'light'); } catch (e) {}
    var m = document.querySelector('meta[name=theme-color]'); if (m) m.setAttribute('content', dark ? '#0D1218' : '#EEF2F6');
  });
  // potvrzení smazání
  document.addEventListener('submit', function (e) {
    var m = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (m && !window.confirm(m)) e.preventDefault();
  });

  // ---- Bag: filtry (štítky = OR, ostatní = AND)
  var grid = document.getElementById('grid');
  if (grid) {
    var st = { s: {} }, q = document.getElementById('q'), ids = ['fcat', 'fspeed', 'fturn', 'ffade'];
    try { var saved = JSON.parse(sessionStorage.getItem('bagf') || '{}'); if (saved.s) st.s = saved.s; if (saved.q) q.value = saved.q; ids.forEach(function (id) { if (saved[id]) document.getElementById(id).value = saved[id]; }); } catch (e) {}
    var apply = function () {
      var cat = document.getElementById('fcat').value, sp = document.getElementById('fspeed').value, tu = document.getElementById('fturn').value, fa = document.getElementById('ffade').value;
      var term = q.value.trim().toLowerCase(), act = Object.keys(st.s).filter(function (k) { return st.s[k]; }), shown = 0;
      grid.querySelectorAll('.disc').forEach(function (c) {
        var tags = (c.dataset.tags || '').split(' ');
        var ok = (!cat || c.dataset.cat === cat) && (!sp || c.dataset.speed === sp) && (!tu || c.dataset.turn === tu) && (!fa || c.dataset.fade === fa) &&
          (!term || c.dataset.t.indexOf(term) > -1) && (!act.length || act.some(function (k) { return tags.indexOf(k) > -1; }));
        c.hidden = !ok; if (ok) shown++;
      });
      document.getElementById('none').hidden = shown > 0;
      document.querySelectorAll('#fstatus .chip').forEach(function (b) { b.setAttribute('aria-pressed', st.s[b.dataset.s] ? 'true' : 'false'); });
      grid.querySelectorAll('.tg[data-s]').forEach(function (b) { b.classList.toggle('on', !!st.s[b.dataset.s]); });
      try { var o = { s: st.s, q: q.value }; ids.forEach(function (id) { o[id] = document.getElementById(id).value; }); sessionStorage.setItem('bagf', JSON.stringify(o)); } catch (e) {}
    };
    var toggle = function (e) { var b = e.target.closest('[data-s]'); if (!b) return; st.s[b.dataset.s] = !st.s[b.dataset.s]; apply(); };
    document.getElementById('filters').addEventListener('input', apply);
    document.getElementById('fstatus').addEventListener('click', toggle);
    grid.addEventListener('click', toggle);
    apply();
  }

  // ---- Disk: cena jen když je na prodej
  var fs = document.getElementById('f_sale'), fo = document.getElementById('f_sold'), pb = document.getElementById('pricebox');
  if (fs && pb) { var t = function () { pb.hidden = !(fs.checked || (fo && fo.checked)); }; fs.addEventListener('change', t); if (fo) fo.addEventListener('change', t); t(); }

  // ---- Veřejný prodej: modál s poptávkou

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
