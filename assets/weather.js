// Počasí: přehled na stránce turnaje a předvyplnění formuláře kola (vždy lze upravit ručně).
(function () {
  function get(ev, date, from, to) {
    return fetch('weather.php?event=' + ev + '&date=' + date + '&from=' + (from || 9) + '&to=' + (to || 16)).then(function (r) { return r.json(); });
  }
  function fmt(w) { return w.temp + ' °C · vítr ' + w.wind + (w.gust != null ? '/' + w.gust : '') + ' m/s' + (w.rain ? ' · ' + w.rain + ' mm' : '') + (w.cond ? ' · ' + w.cond : '') + (w.forecast ? ' (předpověď)' : ' (skutečnost)'); }

  var btn = document.getElementById('wxbtn');
  if (btn) btn.addEventListener('click', function () {
    var out = document.getElementById('wxout'), d = btn.dataset.date, end = btn.dataset.end || d;
    if (!d) { out.textContent = 'Nejdřív vyplň datum turnaje a ulož.'; return; }
    var days = [], t = new Date(d + 'T12:00:00'), e = new Date(end + 'T12:00:00');
    while (t <= e && days.length < 5) { days.push(t.toISOString().slice(0, 10)); t.setDate(t.getDate() + 1); }
    out.textContent = 'Načítám…';
    Promise.all(days.map(function (x) { return get(btn.dataset.event, x).then(function (w) { return new Date(x + 'T12:00:00').toLocaleDateString('cs-CZ', { weekday: 'short', day: 'numeric', month: 'numeric' }) + ': ' + (w.ok ? fmt(w) : w.error); }); }))
      .then(function (l) { out.innerHTML = ''; l.forEach(function (x) { var p = document.createElement('div'); p.textContent = x; out.appendChild(p); }); })
      .catch(function () { out.textContent = 'Počasí se nepodařilo načíst.'; });
  });

  var go = document.getElementById('wxload');
  if (go) go.addEventListener('click', function () {
    var f = go.form, msg = document.getElementById('wxmsg'), date = f.elements.rdate.value;
    if (!date) { msg.textContent = 'Vyplň datum kola.'; return; }
    msg.textContent = 'Načítám…';
    get(go.dataset.event, date, f.elements.wfrom.value, f.elements.wto.value).then(function (w) {
      if (!w.ok) { msg.textContent = w.error; return; }
      f.elements.w_temp.value = w.temp; f.elements.w_wind.value = w.wind; f.elements.w_gust.value = w.gust == null ? '' : w.gust;
      f.elements.w_rain.value = w.rain == null ? '' : w.rain; f.elements.w_cond.value = w.cond; f.elements.w_src.value = 'auto';
      msg.textContent = 'Předvyplněno (' + (w.forecast ? 'předpověď' : 'skutečnost') + ') – podle potřeby uprav.';
    }).catch(function () { msg.textContent = 'Počasí se nepodařilo načíst. Vyplň ho ručně.'; });
  });
  // ruční úprava → označit jako ruční
  document.addEventListener('input', function (e) { if (e.target.closest && e.target.closest('#wxbox') && e.target.name && e.target.name.indexOf('w_') === 0 && e.target.name !== 'w_src') { var s = document.querySelector('[name=w_src]'); if (s) s.value = 'manual'; } });
})();
