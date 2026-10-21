/* Disc golf cvičení – herní logika. Stav se vždy přepočítá z posloupnosti událostí (kvůli tlačítku Zpět). */
(function () {
  var pct = function (a, b) { return b ? Math.round(a / b * 100) : 0; };

  var DRILLS = {
    // ---------- ZLATÁ ZÓNA ----------
    zlata_zona: {
      init: function () { return { r: 0, di: 0, k: 0, total: 0, rounds: [0, 0, 0], dist: { 30: 0, 40: 0, 50: 0 }, types: { BH: [0, 0, 0], FH: [0, 0, 0], V: [0, 0, 0] }, n: 0, zone: 0, ob: 0, done: false }; },
      dists: [30, 40, 50], types: ['BH', 'BH', 'FH', 'FH', 'V'], typeNames: { BH: 'backhand', FH: 'forhand', V: 'volba' },
      roundNames: ['OB za zónou – nepřehazuj', 'OB před zónou – dohoď', 'OB před i za zónou'],
      apply: function (s, v) {
        var t = this.types[s.k], d = this.dists[s.di];
        s.total += v; s.rounds[s.r] += v; s.dist[d] += v; s.n++;
        s.types[t][0]++; if (v === 2) { s.zone++; s.types[t][1]++; } if (v === -2) { s.ob++; s.types[t][2]++; }
        s.k++; if (s.k === 5) { s.k = 0; s.di++; } if (s.di === 3) { s.di = 0; s.r++; } if (s.r === 3) s.done = true;
      },
      buttons: function (s) {
        var b = [{ v: 2, l: 'Zóna', sub: '+2', c: 'good' }];
        if (s.r < 2) { b.push({ v: 1, l: 'Safe', sub: '+1', c: 'ok' }); b.push({ v: 0, l: 'Safe daleko', sub: '0', c: 'neutral' }); }
        b.push({ v: -2, l: 'OB', sub: '−2', c: 'bad' });
        return b;
      },
      status: function (s) {
        if (s.done) return { big: s.total, bigLabel: 'bodů z 90', line: 'Hotovo!' };
        return { big: s.total, bigLabel: 'bodů', line: 'Kolo ' + (s.r + 1) + '/3 · ' + this.roundNames[s.r],
          sub: this.dists[s.di] + ' m · hod ' + (s.k + 1) + '/5 · <b>' + this.typeNames[this.types[s.k]] + '</b>' };
      },
      label: function (v) { return { '2': 'zóna', '1': 'safe', '0': 'daleko', '-2': 'OB' }[String(v)]; },
      canFinish: function (s) { return s.done; },
      summary: function (s) {
        return { score: s.total, rounds: s.rounds, dist: s.dist, zonePct: pct(s.zone, s.n), obPct: pct(s.ob, s.n),
          bhZone: pct(s.types.BH[1], s.types.BH[0]), fhZone: pct(s.types.FH[1], s.types.FH[0]), throws: s.n };
      }
    },

    // ---------- MUST-MAKE ----------
    must_make: {
      init: function () { return { round: 0, pos: 0, phase: 'a', app: null, cur: 0, rounds: [], nA: 0, c1: 0, c2: 0, out: 0, c1P: 0, c1M: 0, done: false }; },
      apply: function (s, v) {
        if (s.phase === 'a') {
          s.app = v; s.nA++;
          if (v === 'c1') { s.c1++; s.cur += 1; } else if (v === 'c2') { s.c2++; } else { s.out++; s.cur -= 1; }
          s.phase = 'p';
        } else {
          if (s.app === 'c1') { s.c1P++; if (v) { s.c1M++; s.cur += 2; } else { s.cur -= 2; } }
          s.phase = 'a'; s.pos++;
          if (s.pos === 5) { s.rounds.push(s.cur); s.cur = 0; s.pos = 0; s.round++; if (s.round === 5) s.done = true; }
        }
      },
      buttons: function (s) {
        if (s.phase === 'a') return [{ v: 'c1', l: 'C1', sub: 'do 10 m · +1', c: 'good' }, { v: 'c2', l: 'C2', sub: 'do 20 m · 0', c: 'neutral' }, { v: 'out', l: 'Mimo C2', sub: '−1', c: 'bad' }];
        return [{ v: 1, l: 'Putt dal', sub: s.app === 'c1' ? '+2' : '0', c: 'good' }, { v: 0, l: 'Putt nedal', sub: s.app === 'c1' ? '−2' : '0', c: 'bad' }];
      },
      status: function (s) {
        var total = s.rounds.reduce(function (a, b) { return a + b; }, 0);
        if (s.done) return { big: total, bigLabel: 'bodů celkem', line: 'Hotovo – 5 kol' };
        return { big: s.cur, bigLabel: 'bodů v kole', line: 'Kolo ' + (s.round + 1) + ' · pozice ' + (s.pos + 1) + '/5',
          sub: s.phase === 'a' ? '<b>Přihoz</b> – kam dopadl?' : '<b>Putt</b> po přihozu do ' + (s.app === 'c1' ? 'C1' : s.app === 'c2' ? 'C2' : 'mimo C2'),
          extra: s.rounds.length ? 'Kola: ' + s.rounds.join(' · ') : '' };
      },
      label: function (v) { return { c1: 'C1', c2: 'C2', out: 'mimo', '1': 'dal', '0': 'nedal' }[String(v)]; },
      canFinish: function (s) { return s.done || (s.rounds.length >= 1 && s.pos === 0 && s.phase === 'a'); },
      finishHint: 'Ukončit lze po dokončeném kole (doporučeno 3–5 kol).',
      summary: function (s) {
        var total = s.rounds.reduce(function (a, b) { return a + b; }, 0);
        return { score: s.rounds.length ? Math.round(total / s.rounds.length * 10) / 10 : 0, total: total, rounds: s.rounds,
          c1AppPct: pct(s.c1, s.nA), c1PuttPct: pct(s.c1M, s.c1P), outPct: pct(s.out, s.nA) };
      }
    },

    // ---------- 21 ----------
    dvacetjedna: {
      init: function (variant) {
        var tri = variant === 'tri';
        return { v: variant, n: tri ? 3 : 5, target: tri ? 18 : 21, miss: tri ? -1 : -2, dists: [5, 7, 9], cyc: 0, k: 0,
          score: 0, rounds: 0, resets: 0, putts: 0, made: 0, by: { 5: [0, 0], 7: [0, 0], 9: [0, 0] }, done: false };
      },
      dist: function (s) { return s.v === 'hardcore' && s.score >= 17 ? 9 : s.dists[s.cyc]; },
      apply: function (s, ok) {
        var d = this.dist(s);
        s.putts++; s.by[d][0]++; if (ok) { s.made++; s.by[d][1]++; }
        s.score += ok ? 1 : s.miss; s.k++;
        if (s.score >= s.target) { s.rounds++; s.done = true; return; }
        if (s.score <= -5) { s.score = 0; s.resets++; s.rounds++; s.cyc = 0; s.k = 0; return; }
        if (s.k === s.n) { s.k = 0; s.rounds++; s.cyc = (s.cyc + 1) % 3; }
      },
      buttons: function () { return [{ v: 1, l: 'Dal', sub: '+1', c: 'good' }, { v: 0, l: 'Minul', sub: '', c: 'bad' }]; },
      status: function (s) {
        if (s.done) return { big: s.rounds, bigLabel: 'kol do ' + s.target, line: 'Hotovo! Resetů: ' + s.resets };
        return { big: s.score, bigLabel: 'bodů z ' + s.target, line: '<b>' + this.dist(s) + ' m</b> · putt ' + (s.k + 1) + '/' + s.n,
          sub: 'Kolo ' + (s.rounds + 1) + ' · resetů ' + s.resets + (s.score <= -3 ? ' · <span class="warn">pozor, −5 = reset</span>' : '') };
      },
      label: function (v) { return v ? '✓' : '✗'; },
      canFinish: function (s) { return s.done; },
      summary: function (s) {
        return { score: s.rounds, rounds: s.rounds, resets: s.resets, putts: s.putts, pct: pct(s.made, s.putts),
          by: { 5: pct(s.by[5][1], s.by[5][0]), 7: pct(s.by[7][1], s.by[7][0]), 9: pct(s.by[9][1], s.by[9][0]) } };
      }
    },

    // ---------- TLAKOVÝ HRNEC ----------
    tlakovy_hrnec: {
      init: function (variant) { return { v: variant, pos: [5, 6, 7, 8, 9], idx: 0, att: 0, rounds: [], backs: 0, miss: { 5: 0, 6: 0, 7: 0, 8: 0, 9: 0 }, putts: 0, made: 0, done: false }; },
      apply: function (s, ok) {
        var d = s.pos[s.idx]; s.att++; s.putts++;
        if (ok) {
          s.made++;
          if (s.idx === s.pos.length - 1) { s.rounds.push(s.att); s.att = 0; s.idx = 0; if (s.rounds.length === 5) s.done = true; }
          else s.idx++;
        } else {
          s.miss[d]++;
          if (s.idx > 0) s.backs++;
          s.idx = s.v === 'hardcore' ? 0 : Math.max(0, s.idx - 1);
        }
      },
      buttons: function () { return [{ v: 1, l: 'Dal', sub: 'dál', c: 'good' }, { v: 0, l: 'Minul', sub: 'zpět', c: 'bad' }]; },
      status: function (s) {
        if (s.done) return { big: this.summary(s).score, bigLabel: 'pokusů na kolo', line: 'Hotovo – kola: ' + s.rounds.join(' · ') };
        var dots = s.pos.map(function (p, i) { return '<span class="pdot' + (i < s.idx ? ' ok' : i === s.idx ? ' cur' : '') + '">' + p + '</span>'; }).join('');
        return { big: s.pos[s.idx] + ' m', bigLabel: 'teď puttuješ', line: 'Kolo ' + (s.rounds.length + 1) + '/5 · pokus ' + (s.att + 1), sub: dots,
          extra: s.rounds.length ? 'Hotová kola: ' + s.rounds.join(' · ') + ' pokusů' : '' };
      },
      label: function (v) { return v ? '✓' : '✗'; },
      canFinish: function (s) { return s.done || (s.rounds.length >= 1 && s.att === 0); },
      finishHint: 'Ukončit lze po dokončeném kole (doporučeno 5 kol).',
      summary: function (s) {
        var sum = s.rounds.reduce(function (a, b) { return a + b; }, 0);
        var worst = null, wv = 0; for (var d in s.miss) if (s.miss[d] > wv) { wv = s.miss[d]; worst = d; }
        return { score: s.rounds.length ? Math.round(sum / s.rounds.length * 10) / 10 : 0, rounds: s.rounds, backs: s.backs, miss: s.miss, worst: worst, pct: pct(s.made, s.putts), putts: s.putts };
      }
    },

    // ---------- RISK JE ZISK ----------
    risk: {
      init: function () { return { n: 0, score: 0, max: 0, misses: 0, ch: { 5: [0, 0], 6: [0, 0], 7: [0, 0] }, done: false }; },
      pts: { 5: 1, 6: 2, 7: 3 },
      apply: function (s, v) {
        var d = v.d; s.n++; s.ch[d][0]++;
        if (v.ok) { s.ch[d][1]++; s.score += this.pts[d]; if (s.score > s.max) s.max = s.score; }
        else { s.score = 0; s.misses++; }
        if (s.n === 10) s.done = true;
      },
      buttons: function () {
        var b = [];
        [5, 6, 7].forEach(function (d) {
          b.push({ v: { d: d, ok: 1 }, l: d + ' m ✓', sub: '+' + { 5: 1, 6: 2, 7: 3 }[d], c: 'good' });
          b.push({ v: { d: d, ok: 0 }, l: d + ' m ✗', sub: 'na 0', c: 'bad' });
        });
        return b;
      },
      grid: 2,
      status: function (s) {
        if (s.done) return { big: s.score, bigLabel: 'bodů z 30', line: s.misses ? 'Missů: ' + s.misses + ' · nejvýš jsi měl ' + s.max : 'Čistá hra bez missu! 🔥' };
        return { big: s.score, bigLabel: 'bodů', line: 'Putt ' + (s.n + 1) + '/10', sub: 'Vyber vzdálenost a zapiš výsledek · max v této hře ' + s.max };
      },
      label: function (v) { return v.d + 'm' + (v.ok ? '✓' : '✗'); },
      canFinish: function (s) { return s.done; },
      summary: function (s) {
        return { score: s.score, max: s.max, misses: s.misses, clean: s.misses === 0 ? 1 : 0,
          ch: { 5: s.ch[5][0], 6: s.ch[6][0], 7: s.ch[7][0] }, pct: { 5: pct(s.ch[5][1], s.ch[5][0]), 6: pct(s.ch[6][1], s.ch[6][0]), 7: pct(s.ch[7][1], s.ch[7][0]) } };
      }
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('drill');
    if (!root) return;
    var key = root.dataset.drill, def = DRILLS[key];
    var variantSel = document.getElementById('variant');
    var storeKey = 'drill:' + key;
    var st = null;
    try { st = JSON.parse(localStorage.getItem(storeKey) || 'null'); } catch (e) {}
    if (!st || typeof st !== 'object' || !st.started || !Array.isArray(st.events) || Date.now() - st.started > 6 * 3600 * 1000) st = { started: 0, variant: variantSel ? variantSel.value : 'normal', events: [] };
    if (variantSel && !Array.prototype.some.call(variantSel.options, function (o) { return o.value === st.variant; })) st.variant = variantSel.value;
    var persist = function () { try { localStorage.setItem(storeKey, JSON.stringify(st)); } catch (e) {} };

    var $ = function (id) { return document.getElementById(id); };
    var compute = function () { var s = def.init(st.variant); st.events.forEach(function (ev) { def.apply(s, ev); }); return s; };

    var render = function () {
      var s;
      try { s = compute(); } catch (e) { st = { started: 0, variant: st.variant, events: [] }; persist(); s = compute(); }
      var active = st.started > 0;
      $('drill-start').hidden = active;
      $('drill-play').hidden = !active;
      if (variantSel) variantSel.value = st.variant;
      if (!active) return;
      var v = def.status(s);
      var rs = $('d-resume'); if (rs) { rs.hidden = !st.events.length; var tm = new Date(st.started); $('d-resume-t').textContent = ('0' + tm.getHours()).slice(-2) + ':' + ('0' + tm.getMinutes()).slice(-2); }
      $('d-big').innerHTML = v.big;
      $('d-biglabel').textContent = v.bigLabel;
      $('d-line').innerHTML = v.line || '';
      $('d-sub').innerHTML = v.sub || '';
      $('d-extra').innerHTML = v.extra || '';
      var box = $('d-buttons'); box.innerHTML = '';
      box.className = 'dbtns' + (def.grid ? ' g' + def.grid : '');
      if (!s.done) def.buttons(s).forEach(function (b) {
        var el = document.createElement('button');
        el.type = 'button'; el.className = 'dbtn ' + b.c;
        el.innerHTML = '<b>' + b.l + '</b>' + (b.sub ? '<small>' + b.sub + '</small>' : '');
        el.addEventListener('click', function () {
          st.events.push(b.v); persist(); render();
          if (navigator.vibrate) navigator.vibrate(30);
        });
        box.appendChild(el);
      });
      $('d-log').textContent = st.events.slice(-12).map(function (e) { return def.label(e); }).join('  ');
      $('d-undo').disabled = !st.events.length;
      var can = def.canFinish(s);
      $('d-finish').disabled = !can;
      $('d-finish').classList.toggle('primary', s.done);
      $('d-hint').textContent = s.done ? 'Cvičení dokončeno – ulož výsledek.' : (def.finishHint || '');
    };

    $('d-go').addEventListener('click', function () {
      st = { started: Date.now(), variant: variantSel ? variantSel.value : 'normal', events: [] }; persist(); render();
    });
    $('d-undo').addEventListener('click', function () { st.events.pop(); persist(); render(); });
    var armed = 0, cancelBtns = [$('d-cancel'), $('d-restart')];
    cancelBtns.forEach(function (btn) {
      if (!btn) return; var label = btn.textContent;
      btn.addEventListener('click', function () {
        if (!armed) { armed = 1; btn.textContent = 'Opravdu? Klepni znovu'; setTimeout(function () { armed = 0; btn.textContent = label; }, 3500); return; }
        armed = 0; btn.textContent = label;
        st = { started: 0, variant: st.variant, events: [] }; persist(); render();
      });
    });
    $('d-finish').addEventListener('click', function () {
      var s = compute(), sum = def.summary(s);
      var f = $('d-save');
      f.querySelector('[name=variant]').value = st.variant;
      f.querySelector('[name=events]').value = JSON.stringify(st.events);
      f.querySelector('[name=summary]').value = JSON.stringify(sum);
      f.querySelector('[name=score]').value = sum.score;
      f.querySelector('[name=minutes]').value = Math.max(1, Math.round((Date.now() - st.started) / 60000));
      try { localStorage.removeItem(storeKey); } catch (e) {}
      f.submit();
    });
    render();
  });
})();
