// Veřejný prodej: výběr více disků, filtr a odeslání poptávky.
(function () {
  var grid = document.getElementById('pgrid'); if (!grid) return;
  var cards = [].slice.call(grid.querySelectorAll('.disc')), bar = document.getElementById('selbar'), modal = document.getElementById('inq');
  var q = document.getElementById('pq'), cat = document.getElementById('pcat'), sort = document.getElementById('psort');
  var origin = cards.slice();
  function sel() { return cards.filter(function (c) { return c.querySelector('input').checked; }); }
  function refresh() {
    var s = sel(), sum = s.reduce(function (a, c) { return a + (+c.dataset.price || 0); }, 0);
    cards.forEach(function (c) { c.classList.toggle('sel', c.querySelector('input').checked); });
    document.getElementById('selcnt').textContent = s.length;
    document.getElementById('selsum').textContent = sum ? ' · ' + sum.toLocaleString('cs-CZ') + ' Kč' : '';
    bar.classList.toggle('on', s.length > 0);
  }
  function filter() {
    var t = q.value.trim().toLowerCase(), k = cat.value, list = origin.slice();
    if (sort.value === 'asc') list.sort(function (a, b) { return a.dataset.price - b.dataset.price; });
    if (sort.value === 'desc') list.sort(function (a, b) { return b.dataset.price - a.dataset.price; });
    if (sort.value === 'model') list.sort(function (a, b) { return a.dataset.model.localeCompare(b.dataset.model, 'cs'); });
    list.forEach(function (c) { grid.appendChild(c); c.hidden = !((!t || c.dataset.t.indexOf(t) > -1) && (!k || c.dataset.cat === k)); });
  }
  grid.addEventListener('change', refresh);
  [q, cat, sort].forEach(function (e) { e.addEventListener('input', filter); });
  document.getElementById('selgo').addEventListener('click', function () {
    var ul = document.getElementById('sellist'); ul.innerHTML = '';
    sel().forEach(function (c) { var li = document.createElement('li'); li.innerHTML = '<span></span><b></b>'; li.firstChild.textContent = c.dataset.label; li.lastChild.textContent = +c.dataset.price ? c.dataset.price + ' Kč' : 'dohodou'; ul.appendChild(li); });
    modal.classList.add('open');
  });
  modal.addEventListener('click', function (e) { if (e.target === modal || e.target.closest('[data-close]')) modal.classList.remove('open'); });
  refresh();
})();
