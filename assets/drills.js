/* Disc golf cvičení – herní logika. Stav se vždy přepočítá z posloupnosti událostí (kvůli tlačítku Zpět). */
(function () {
  var pct = function (a, b) { return b ? Math.round(a / b * 100) : 0; };

  var DRILLS = {
    // ---------- ZLATÁ ZÓNA ----------
    zlata_zona: {
      init: function (v, ds) { ds = ds || this.dists; var dd = {}; ds.forEach(function (d) { dd[d] = 0; }); return { ds: ds, r: 0, di: 0, k: 0, total: 0, rounds: [0, 0, 0], dist: dd, types: { BH: [0, 0, 0], FH: [0, 0, 0], V: [0, 0, 0] }, n: 0, zone: 0, ob: 0, done: false }; },
      dists: [30, 40, 50], types: ['BH', 'BH', 'FH', 'FH', 'V'], typeNames: { BH: 'backhand', FH: 'forhand', V: 'volba' },
      roundNames: ['OB za zónou – nepřehazuj', 'OB před zónou – dohoď', 'OB před i za zónou'],
      apply: function (s, v) {
        var t = this.types[s.k], d = s.ds[s.di];
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
          sub: s.ds[s.di] + ' m · hod ' + (s.k + 1) + '/5 · <b>' + this.typeNames[this.types[s.k]] + '</b>' };
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
      dists: [35, 42, 48, 54, 60], free: true,
      init: function (v, ds) { ds = ds || this.dists; var pd = {}; ds.forEach(function (d) { pd[d] = { n: 0, c1: 0, p: 0, m: 0 }; }); return { ds: ds, pd: pd, round: 0, pos: 0, phase: 'a', app: null, cur: 0, rounds: [], nA: 0, c1: 0, c2: 0, out: 0, c1P: 0, c1M: 0, done: false }; },
      apply: function (s, v) {
        if (s.phase === 'a') {
          s.app = v; s.nA++; var q0 = s.pd[s.ds[s.pos]]; q0.n++; if (v === 'c1') q0.c1++;
          if (v === 'c1') { s.c1++; s.cur += 1; } else if (v === 'c2') { s.c2++; } else { s.out++; s.cur -= 1; }
          s.phase = 'p';
        } else {
          if (s.app === 'c1') { var q1 = s.pd[s.ds[s.pos]]; q1.p++; if (v) q1.m++; s.c1P++; if (v) { s.c1M++; s.cur += 2; } else { s.cur -= 2; } }
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
        return { big: s.cur, bigLabel: 'bodů v kole', line: 'Kolo ' + (s.round + 1) + ' · pozice ' + (s.pos + 1) + '/5 · <b>' + s.ds[s.pos] + ' m</b>',
          sub: s.phase === 'a' ? '<b>Přihoz</b> – kam dopadl?' : '<b>Putt</b> po přihozu do ' + (s.app === 'c1' ? 'C1' : s.app === 'c2' ? 'C2' : 'mimo C2'),
          extra: s.rounds.length ? 'Kola: ' + s.rounds.join(' · ') : '' };
      },
      label: function (v) { return { c1: 'C1', c2: 'C2', out: 'mimo', '1': 'dal', '0': 'nedal' }[String(v)]; },
      canFinish: function (s) { return s.done || (s.rounds.length >= 1 && s.pos === 0 && s.phase === 'a'); },
      finishHint: 'Ukončit lze po dokončeném kole (doporučeno 3–5 kol).',
      summary: function (s) {
        var total = s.rounds.reduce(function (a, b) { return a + b; }, 0);
        return { score: s.rounds.length ? Math.round(total / s.rounds.length * 10) / 10 : 0, total: total, rounds: s.rounds,
          c1AppPct: pct(s.c1, s.nA), c1PuttPct: pct(s.c1M, s.c1P), outPct: pct(s.out, s.nA), pos: s.pd };
      }
    },

    // ---------- 21 ----------
    dvacetjedna: {
      init: function (variant) {
        var tri = variant === 'tri', ds = arguments[1] || this.dists, by = {};
        ds.forEach(function (d) { by[d] = [0, 0]; });
        return { v: variant, n: tri ? 3 : 5, target: tri ? 18 : 21, miss: tri ? -1 : -2, dists: ds, cyc: 0, k: 0,
          score: 0, rounds: 0, resets: 0, putts: 0, made: 0, by: by, done: false };
      },
      dists: [5, 7, 9],
      dist: function (s) { return s.v === 'hardcore' && s.score >= 17 ? Math.max.apply(null, s.dists) : s.dists[s.cyc]; },
      apply: function (s, ok) {
        var d = this.dist(s);
        s.putts++; s.by[d][0]++; if (ok) { s.made++; s.by[d][1]++; }
        s.score += ok ? 1 : s.miss; s.k++;
        if (s.score >= s.target) { s.rounds++; s.done = true; return; }
        if (s.score <= -5) { s.score = 0; s.resets++; s.rounds++; s.cyc = 0; s.k = 0; return; }
        if (s.k === s.n) { s.k = 0; s.rounds++; s.cyc = (s.cyc + 1) % s.dists.length; }
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
          by: (function () { var o = {}; s.dists.forEach(function (d) { o[d] = pct(s.by[d][1], s.by[d][0]); }); return o; })(),
          byN: (function () { var o = {}; s.dists.forEach(function (d) { o[d] = s.by[d][0]; }); return o; })() };
      }
    },

    // ---------- TLAKOVÝ HRNEC ----------
    tlakovy_hrnec: {
      dists: [5, 6, 7, 8, 9],
      init: function (variant, ds) { ds = ds || this.dists; var m = {}; ds.forEach(function (d) { m[d] = 0; }); var c = {}; ds.forEach(function (d) { c[d] = 0; }); return { v: variant, pos: ds, idx: 0, att: 0, rounds: [], backs: 0, miss: m, cnt: c, putts: 0, made: 0, done: false }; },
      apply: function (s, ok) {
        var d = s.pos[s.idx]; s.att++; s.putts++; s.cnt[d]++;
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
        return { score: s.rounds.length ? Math.round(sum / s.rounds.length * 10) / 10 : 0, rounds: s.rounds, backs: s.backs, miss: s.miss, worst: worst, pct: pct(s.made, s.putts), putts: s.putts, cnt: s.cnt };
      }
    },

    // ---------- OSMDESÁTKA ----------
    osmdesatka: {
      dists: [8],
      init: function (variant, ds) { return { v: variant, d: (ds || this.dists)[0], ser: [], cur: 0, curMade: 0, putts: 0, made: 0, done: false }; },
      apply: function (s, ok) {
        s.cur++; s.putts++; if (ok) { s.curMade++; s.made++; }
        if (s.cur === 5) { s.ser.push(s.curMade); s.cur = 0; s.curMade = 0; if (s.ser.length === 10) s.done = true; }
      },
      buttons: function () { return [{ v: 1, l: 'Dal', sub: '', c: 'good' }, { v: 0, l: 'Minul', sub: '', c: 'bad' }]; },
      pts: function (s) { return s.ser.filter(function (m) { return m >= 4; }).length; },
      status: function (s) {
        var p = this.pts(s);
        var dots = s.ser.map(function (m) { return '<span class="pdot' + (m >= 4 ? ' ok' : '') + '">' + m + '/5</span>'; }).join('');
        if (s.done) return { big: p, bigLabel: 'bodů z 10', line: p >= 8 ? 'Splněno! Cíl 8 bodů dosažen.' : 'Dohráno – cíl 8 bodů nesplněn.', sub: dots };
        var lost = s.cur - s.curMade;
        return { big: p, bigLabel: 'bodů (cíl 8)', line: s.d + ' m · série ' + (s.ser.length + 1) + '/10 · putt ' + (s.cur + 1) + '/5 · dáno ' + s.curMade, sub: dots,
          extra: lost >= 2 ? 'Bod z téhle série už nezískáš – dohraj ji se stejnou rutinou.' : (p + (10 - s.ser.length) < 8 ? 'Cíl 8 bodů už nejde splnit – dohraj i tak, trénuješ návyk.' : '') };
      },
      label: function (v) { return v ? '✓' : '✗'; },
      canFinish: function (s) { return s.done; },
      finishHint: 'Dohraj všech 10 sérií, i když už cíl nejde splnit.',
      summary: function (s) {
        return { score: this.pts(s), series: s.ser, made: s.made, putts: s.putts, pct: pct(s.made, s.putts), goal: this.pts(s) >= 8 };
      }
    },

    // ---------- RISK JE ZISK ----------
    risk: {
      dists: [5, 6, 7],
      init: function (v, ds) { ds = ds || this.dists; var ch = {}, pts = {}; ds.forEach(function (d, i) { ch[d] = [0, 0]; pts[d] = i + 1; }); return { ds: ds, n: 0, score: 0, max: 0, misses: 0, ch: ch, pts: pts, done: false }; },
      apply: function (s, v) {
        var d = v.d; s.n++; s.ch[d][0]++;
        if (v.ok) { s.ch[d][1]++; s.score += s.pts[d]; if (s.score > s.max) s.max = s.score; }
        else { s.score = 0; s.misses++; }
        if (s.n === 10) s.done = true;
      },
      buttons: function (s) {
        var b = [];
        s.ds.forEach(function (d, i) {
          b.push({ v: { d: d, ok: 1 }, l: d + ' m ✓', sub: '+' + (i + 1), c: 'good' });
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
          ch: (function () { var o = {}; s.ds.forEach(function (d) { o[d] = s.ch[d][0]; }); return o; })(), pct: (function () { var o = {}; s.ds.forEach(function (d) { o[d] = pct(s.ch[d][1], s.ch[d][0]); }); return o; })() };
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
    var okD = function (d) { return d == null || (Array.isArray(d) && d.length === (def.dists || []).length && d.every(function (x) { return x > 0; })); };
    if (!okD(st.dists)) st.dists = null;
    var compute = function () { var s = def.init(st.variant, st.dists); st.events.forEach(function (ev) { def.apply(s, ev); }); return s; };

    var render = function () {
      var s;
      try { s = compute(); } catch (e) { st = { started: 0, variant: st.variant, events: [] }; persist(); s = compute(); }
      var active = st.started > 0;
      $('drill-start').hidden = active;
      $('drill-play').hidden = !active;
      if (variantSel) variantSel.value = st.variant;
      if (!active) return;
      var v = def.status(s);
      var rs = $('d-resume'); if (rs) { rs.hidden = !st.events.length || !!s.done; var tm = new Date(st.started); $('d-resume-t').textContent = ('0' + tm.getHours()).slice(-2) + ':' + ('0' + tm.getMinutes()).slice(-2); }
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

    // vzdálenosti před hrou (pamatují se naposledy použité)
    var dbox = $('d-dists'), dinputs = [], dkey = 'dists:' + key;
    var loadDists = function () { var d = null; try { d = JSON.parse(localStorage.getItem(dkey) || 'null'); } catch (e) {} return (Array.isArray(d) && d.length === def.dists.length && d.every(function (x) { return x > 0; })) ? d : def.dists.slice(); };
    if (dbox && def.dists) {
      var cur = loadDists();
      dbox.className = 'dists';
      dbox.innerHTML = '<div class="lbl">Vzdálenosti (m)' + (def.dists.length > 1 ? (def.free ? ' – pro jednotlivé pozice' : ' – od nejkratší po nejdelší') : '') + '</div><div class="dgrid2"></div><p class="sub dmsg" style="margin:4px 0 8px;color:var(--bad)" hidden></p>';
      var g = dbox.querySelector('.dgrid2');
      def.dists.forEach(function (dv, i) {
        var f = document.createElement('input'); f.type = 'number'; f.inputMode = 'decimal'; f.step = '0.5'; f.min = '1'; f.max = '100'; f.value = String(cur[i]); f.setAttribute('aria-label', (i + 1) + '. vzdálenost v metrech');
        g.appendChild(f); dinputs.push(f);
      });
      var rst = document.createElement('button'); rst.type = 'button'; rst.className = 'btn sm ghost'; rst.textContent = 'Výchozí'; rst.addEventListener('click', function () { dinputs.forEach(function (f, i) { f.value = String(def.dists[i]); }); });
      g.appendChild(rst);
    }
    $('d-go').addEventListener('click', function () {
      var ds = def.dists ? def.dists.slice() : null;
      if (dbox && ds) {
        var vals = dinputs.map(function (f) { return parseFloat(String(f.value).replace(',', '.')); }), msg = dbox.querySelector('.dmsg'), bad = '';
        if (vals.some(function (x) { return !(x >= 1 && x <= 100); })) bad = 'Zadej vzdálenosti 1–100 m.';
        else if (!def.free) for (var i = 1; i < vals.length; i++) if (vals[i] <= vals[i - 1]) bad = 'Zadej vzdálenosti od nejkratší po nejdelší (každá větší než předchozí).';
        if (bad) { msg.textContent = bad; msg.hidden = false; return; }
        msg.hidden = true; ds = vals; try { localStorage.setItem(dkey, JSON.stringify(ds)); } catch (e) {}
      }
      st = { started: Date.now(), variant: variantSel ? variantSel.value : 'normal', dists: ds, events: [] }; persist(); render();
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
      if (st.dists) sum.dists = st.dists;
      f.querySelector('[name=summary]').value = JSON.stringify(sum);
      f.querySelector('[name=score]').value = sum.score;
      f.querySelector('[name=minutes]').value = Math.max(1, Math.round((Date.now() - st.started) / 60000));
      try { localStorage.removeItem(storeKey); } catch (e) {}
      f.submit();
    });
    render();
  });
})();
