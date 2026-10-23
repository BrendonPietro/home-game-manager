/* @ds-bundle: {"format":4,"namespace":"RHG","components":[{"name":"Button"},{"name":"ActionButton"},{"name":"Badge"},{"name":"Chip"},{"name":"Avatar"},{"name":"Field"},{"name":"Tabs"},{"name":"Panel"},{"name":"Stat"},{"name":"DataTable"},{"name":"Modal"},{"name":"Drawer"},{"name":"PokerTable"},{"name":"Calendar"},{"name":"BarChart"},{"name":"Settlement"},{"name":"AdminShell"},{"name":"SiteShell"}]} */
/* Rodrigues Home Game — vanilla helpers (no React). Every helper returns an HTML string
   built from the rh- classes in bundle.css, except PokerTable which mounts into an element. */
(function () {
  var P = {
    home: '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>',
    dash: '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
    cal: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    trophy: '<path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4v1a3 3 0 0 0 3 3M17 6h3v1a3 3 0 0 1-3 3M12 14v4M8 21h8M9 18h6"/>',
    users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.2A5 5 0 0 1 21.5 19"/>',
    table: '<ellipse cx="12" cy="12" rx="9" ry="6"/><ellipse cx="12" cy="12" rx="5.5" ry="3"/>',
    key: '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3M14 9l2 2"/>',
    chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    gear: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 0 1-4 0v-.1a1.6 1.6 0 0 0-2.7-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3.6 14H3a2 2 0 0 1 0-4h.1a1.6 1.6 0 0 0 1.1-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 3.6V3a2 2 0 0 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 0 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1.3z"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    rotate: '<path d="M20 12a8 8 0 1 1-2.3-5.7"/><path d="M20 4v5h-5"/>',
    chevR: '<path d="M9 6l6 6-6 6"/>',
    chevL: '<path d="M15 6l-6 6 6 6"/>',
    chevD: '<path d="M6 9l6 6 6-6"/>',
    check: '<path d="M5 12l5 5 9-10"/>',
    x: '<path d="M6 6l12 12M18 6L6 18"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    out: '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/>',
    filter: '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
    down: '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
    edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
    copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    alert: '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.5"/>',
    undo: '<path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>',
    skull: '<path d="M12 3a8 8 0 0 0-5 14v3h10v-3a8 8 0 0 0-5-14z"/><circle cx="9" cy="11" r="1.5"/><circle cx="15" cy="11" r="1.5"/>',
    list: '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    move: '<path d="M5 9l-3 3 3 3M19 9l3 3-3 3M2 12h20"/>',
    arrowR: '<path d="M4 12h16M14 6l6 6-6 6"/>',
    more: '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
    menu: '<path d="M3 6h18M3 12h18M3 18h18"/>',
    upload: '<path d="M12 16V4M7 9l5-5 5 5M5 20h14"/>',
    coins: '<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v4c0 1.7 2.7 3 6 3s6-1.3 6-3V7"/><path d="M9 17c0 1.7 2.7 3 6 3s6-1.3 6-3v-4c0-1.7-2.7-3-6-3"/>'
  };
  function icon(n, cls) { return '<svg class="rh-ic ' + (cls || '') + '" viewBox="0 0 24 24" aria-hidden="true">' + (P[n] || '') + '</svg>'; }
  function brl(v, o) {
    o = o || {};
    var a = Math.abs(v), s = a.toLocaleString('pt-BR', { minimumFractionDigits: o.cents ? 2 : 0, maximumFractionDigits: o.cents ? 2 : 0 });
    var sign = o.sign ? (v > 0 ? '+' : v < 0 ? '−' : '') : (v < 0 ? '−' : '');
    return sign + 'R$ ' + s;
  }
  function chips(n) { return n.toLocaleString('pt-BR'); }
  function initials(n) { return n.split(/\s+/).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase(); }
  function avatar(n, size) { return '<span class="rh-avatar ' + (size ? 'rh-avatar--' + size : '') + '">' + initials(n) + '</span>'; }
  function chip(k, label, size) { return '<span class="rh-chip ' + (k === 'r' ? 'rh-chip--r' : k === 'a' ? 'rh-chip--a' : '') + (size ? ' rh-chip--' + size : '') + '">' + (label || k.toUpperCase()) + '</span>'; }

  /* ---------- Demo data: Poker Night #18 (consistent in every screen) ---------- */
  var BUYINS = [{ id: 's', name: 'Buy-in simples', v: 100, chips: 10000 }, { id: 'd', name: 'Buy-in duplo', v: 200, chips: 20000 }, { id: 't', name: 'Buy-in triplo', v: 300, chips: 30000 }];
  var PLAYERS = [
    { id: 1, name: 'João', mesa: 1, seat: 1, b: 100, bt: 's', r: 0, a: 1 },
    { id: 2, name: 'Lucas', mesa: 1, seat: 2, b: 100, bt: 's', r: 1, a: 0 },
    { id: 3, name: 'Carlos', mesa: 1, seat: 3, b: 100, bt: 's', r: 1, a: 1 },
    { id: 4, name: 'Rafael', mesa: 1, seat: 5, b: 200, bt: 'd', r: 0, a: 0 },
    { id: 5, name: 'André', mesa: 1, seat: 6, b: 100, bt: 's', r: 1, a: 0 },
    { id: 6, name: 'Bruno', mesa: 1, seat: 7, b: 100, bt: 's', r: 0, a: 0 },
    { id: 7, name: 'Pedro', mesa: 1, seat: 8, b: 100, bt: 's', r: 1, a: 0 },
    { id: 8, name: 'Brendon', mesa: 2, seat: 1, b: 100, bt: 's', r: 1, a: 1 },
    { id: 9, name: 'Marcão', mesa: 2, seat: 3, b: 100, bt: 's', r: 1, a: 1 },
    { id: 10, name: 'Thiago', mesa: 2, seat: 4, b: 100, bt: 's', r: 0, a: 0 },
    { id: 11, name: 'Diego', mesa: 2, seat: 6, b: 100, bt: 's', r: 0, a: 0 },
    { id: 12, name: 'Felipe', mesa: 2, seat: 8, b: 100, bt: 's', r: 0, a: 0 }
  ];
  PLAYERS.forEach(function (p) { p.total = p.b + p.r * 100 + p.a * 50; });
  var NIGHT = {
    name: 'Poker Night #18', date: '04/10/2026', dow: 'Domingo', time: '20:00', level: 6, blinds: '100 / 200', clock: '14:32',
    buyins: BUYINS, rebuy: { v: 100, chips: 10000 }, addon: { v: 50, chips: 10000 }, rake: 0.10,
    mesas: [{ id: 1, name: 'Mesa 01', seats: 9, dealer: 'Sérgio' }, { id: 2, name: 'Mesa 02', seats: 9, dealer: 'Paulo' }],
    payout: [0.5, 0.3, 0.2]
  };
  function recalc() {
    var t = { players: PLAYERS.filter(function (p) { return !p.out; }).length, buyins: PLAYERS.length, rebuys: 0, addons: 0, gross: 0 };
    PLAYERS.forEach(function (p) { p.total = p.b + p.r * NIGHT.rebuy.v + p.a * NIGHT.addon.v; t.rebuys += p.r; t.addons += p.a; t.gross += p.total; });
    t.entries = t.buyins + t.rebuys + t.addons; t.rake = Math.round(t.gross * NIGHT.rake); t.prize = t.gross - t.rake;
    NIGHT.totals = t; return t;
  }
  recalc();

  /* ---------- Shells ---------- */
  var ADMIN_NAV = [['dash', 'Dashboard'], ['cal', 'Calendário'], ['trophy', 'Torneios'], ['users', 'Jogadores'], ['table', 'Mesas'], ['key', 'Aluguéis'], ['chart', 'Relatórios'], ['gear', 'Configurações']];
  function brand(sub) { return '<div class="rh-brand"><span class="rh-brand__mark">♠</span><div><div class="rh-brand__name">Rodrigues</div><div class="rh-brand__sub">' + (sub || 'Home Game') + '</div></div></div>'; }
  function adminShell(active, o) {
    var nav = ADMIN_NAV.map(function (n) { return '<a class="rh-nav" href="#"' + (n[1] === active ? ' aria-current="page"' : '') + '>' + icon(n[0]) + n[1] + '</a>'; }).join('');
    return '<div class="rh-app"><aside class="rh-side">' + brand('Painel admin') + nav +
      '<div class="rh-side__foot">' + avatar('Brendon', 'sm') + '<div style="flex:1;min-width:0"><div style="font-weight:600;font-size:14px">Brendon</div><div class="rh-small rh-faint">Organizador</div></div>' + icon('out', 'rh-faint') + '</div></aside>' +
      '<main class="rh-main"><div class="rh-mtop"><button class="rh-btn rh-btn--ghost rh-btn--icon" aria-label="Abrir menu" onclick="this.closest(\'.rh-app\').classList.toggle(\'is-nav\')">' + icon('menu') + '</button>' + brand('Painel admin') + '<span class="rh-spacer"></span>' + avatar('Brendon', 'sm') + '</div><header class="rh-pagehd"><div><div class="rh-crumb">' + (o.crumb || 'Painel') + '</div><h1 class="rh-title">' + o.title + '</h1></div><span class="rh-spacer"></span>' + (o.actions || '') + '</header>' +
      '<div class="rh-content">' + o.body + '</div></main>' + '<div class="rh-navscrim" onclick="this.closest(\'.rh-app\').classList.remove(\'is-nav\')"></div>' + (o.overlay || '') + '</div>';
  }
  var STEPS = [['Antes', [['conf', 'Conferência'], ['jog', 'Jogadores'], ['mesas', 'Mesas']]], ['Durante', [['live', 'Ao vivo'], ['resumo', 'Resumo']]], ['Final', [['premio', 'Premiação'], ['result', 'Resultado'], ['fecha', 'Fechamento']]]];
  function siteShell(o) {
    var top = '<header class="rh-top">' + brand() + (o.ctx === false ? '' : '<div class="rh-top__ctx"><b>' + NIGHT.name + '</b>' + (o.badge || '<span class="rh-badge rh-badge--next">Próximo</span>') + '<span class="rh-small rh-muted">' + NIGHT.date + ' · ' + NIGHT.time + '</span></div>') +
      '<span class="rh-spacer"></span>' + (o.topRight || '<a class="rh-btn rh-btn--ghost rh-btn--sm" href="#">' + icon('list') + 'Torneios</a>' + avatar('Brendon', 'sm')) + '</header>';
    var flow = '';
    if (o.step) {
      var seen = true, num = 0;
      flow = '<nav class="rh-flow">' + STEPS.map(function (g) {
        return '<div class="rh-flow__grp"><span class="rh-label">' + g[0] + '</span>' + g[1].map(function (s) {
          var cur = s[0] === o.step; if (cur) seen = false; var done = seen && !cur;
          num++;
          return '<a class="rh-step' + (done ? ' is-done' : '') + '" href="#"' + (cur ? ' aria-current="step"' : '') + '><i>' + (done ? '✓' : num) + '</i>' + s[1] + '</a>';
        }).join('') + '</div>';
      }).join('') + '</nav>';
    }
    setTimeout(function () { var c = document.querySelector('.rh-step[aria-current="step"]'); if (c && c.scrollIntoView && window.innerWidth < 900) c.scrollIntoView({ inline: 'center', block: 'nearest' }); }, 0);
    return '<div style="position:relative;min-height:100vh;display:flex;flex-direction:column">' + top + flow + (o.strip || '') + '<div style="flex:1;min-width:0;' + (o.pad === false ? '' : 'padding:24px;') + '">' + o.body + '</div>' + (o.overlay || '') + '</div>';
  }
  function liveStrip() {
    var t = NIGHT.totals;
    return '<div class="rh-strip">' +
      '<div><span class="rh-label">Nível ' + NIGHT.level + '</span><span class="v">' + NIGHT.blinds + '</span></div>' +
      '<div><span class="rh-label">Relógio</span><span class="v">' + NIGHT.clock + '</span></div>' +
      '<div><span class="rh-label">Jogadores</span><span class="v">' + t.players + '</span></div>' +
      '<div><span class="rh-label">Entradas</span><span class="v">' + t.entries + '</span></div>' +
      '<div><span class="rh-label">Arrecadação</span><span class="v">' + brl(t.gross) + '</span></div>' +
      '<div style="background:var(--bg-200)"><span class="rh-label">Prize pool</span><span class="v">' + brl(t.prize) + '</span></div>' +
      '</div>';
  }

  /* ---------- Poker table ---------- */
  function seatHTML(p, i, active) {
    if (!p) return '<button class="rh-seat rh-seat--empty" data-seat="' + i + '"><span class="rh-seat__name">' + i + ' · vago</span></button>';
    return '<button class="rh-seat' + (p.out ? ' rh-seat--out' : '') + (active ? ' is-active' : '') + '" data-id="' + p.id + '">' +
      '<span class="rh-seat__hd"><span class="rh-seat__no">' + String(i).padStart(2, '0') + '</span>' + avatar(p.name, 'sm') + '<span class="rh-seat__name">' + p.name + '</span></span>' +
      '<span class="rh-seat__ft"><span class="rh-seat__total">' + brl(p.total) + '</span><span class="rh-seat__chips">' +
      chip('b', p.bt === 'd' ? '2' : p.bt === 't' ? '3' : 'B') + (p.r ? chip('r', p.r > 1 ? String(p.r) : 'R') : '') + (p.a ? chip('a', 'A') : '') + '</span></span></button>';
  }
  function pokerTable(el, o) {
    var rot = o.rotation || 0, n = o.seats || 9;
    function draw() {
      var portrait = rot % 180 === 90;
      var mob = window.innerWidth < 600, ar = portrait ? (o.arP || (mob ? 0.6 : 0.82)) : (o.ar || (mob ? 1.2 : 1.7));
      var rx = portrait ? (o.ry || 38) : (o.rx || 42), ry = portrait ? (o.rx || 42) : (o.ry || 38);
      var inset = portrait ? '9% 15%' : '15% 9%';
      var h = '<div class="rh-ptable' + (o.compact ? ' rh-ptable--compact' : '') + '" style="--ar:' + ar + ';--inset:' + inset + '"><div class="rh-ptable__rail"><div class="rh-ptable__felt"><div class="rh-ptable__center">' +
        '<span class="rh-ptable__name">' + o.name + '</span><span class="rh-label">' + (o.sub || '') + '</span><span class="rh-ptable__suits">♠♥♣♦</span></div></div></div>';
      var slots = n + 1;
      /* seats at equal arc length around the oval (in real proportions), starting at the bottom (dealer) */
      var S = 720, pts = [], len = [0];
      for (var s = 0; s <= S; s++) { var t = (90 + s * 360 / S) * Math.PI / 180; pts.push([rx * Math.cos(t), ry * Math.sin(t)]); if (s) { var dx = (pts[s][0] - pts[s - 1][0]) * ar, dy = pts[s][1] - pts[s - 1][1]; len.push(len[s - 1] + Math.sqrt(dx * dx + dy * dy)); } }
      function at(f) { f = ((f % 1) + 1) % 1; var target = f * len[S], i = 1; while (i < S && len[i] < target) i++; return pts[i]; }
      for (var k = 0; k < slots; k++) {
        var pt = at(k / slots + rot / 360);
        var x = 50 + pt[0], y = 50 + pt[1];
        var pos = 'style="left:' + x.toFixed(2) + '%;top:' + y.toFixed(2) + '%"';
        if (k === 0) {
          h += '<div class="rh-seat rh-seat--dealer" ' + pos + '><span class="rh-dealer-btn">D</span><span class="rh-label">Dealer</span><span class="rh-seat__name">' + (o.dealer || '—') + '</span></div>';
        } else {
          var p = (o.players || []).filter(function (q) { return q.seat === k; })[0];
          h += seatHTML(p, k, p && o.active === p.id).replace('class="rh-seat', pos + ' class="rh-seat');
        }
      }
      el.innerHTML = h + '</div>';
      el.querySelectorAll('[data-id]').forEach(function (b) { b.onclick = function () { o.active = +b.dataset.id; draw(); if (o.onSeat) o.onSeat(+b.dataset.id); }; });
      el.querySelectorAll('[data-seat]').forEach(function (b) { b.onclick = function () { if (o.onEmpty) o.onEmpty(+b.dataset.seat); }; });
    }
    draw();
    return { rotate: function () { rot = (rot + 90) % 360; draw(); return rot; }, set: function (k, v) { o[k] = v; draw(); }, get rotation() { return rot; } };
  }

  /* ---------- Bars ---------- */
  function bars(data, o) {
    o = o || {}; var max = Math.max.apply(null, data.map(function (d) { return d[1]; }));
    var fmt = o.fmt || function (v) { return v; };
    return '<div class="rh-bars" style="height:' + (o.h || 150) + 'px">' + data.map(function (d, i) {
      return '<div class="rh-bars__c' + (o.hl === i ? ' is-hl' : '') + '"><span class="rh-tip">' + d[0] + ': ' + fmt(d[1]) + '</span>' + (o.values === false ? '' : '<span class="rh-bars__v">' + fmt(d[1]) + '</span>') + '<div class="rh-bars__b" style="height:' + (d[1] / max * 82).toFixed(1) + '%"></div></div>';
    }).join('') + '</div><div class="rh-bars__x">' + data.map(function (d) { return '<span>' + d[0] + '</span>'; }).join('') + '</div>';
  }


  /* ---------- Live tournament screen (used by screens 07–11) ---------- */
  var LIVE_CSS = '.live{display:grid;grid-template-columns:1fr 330px;min-height:calc(100vh - 170px)}.live__stage{padding:16px 24px;display:flex;flex-direction:column;gap:12px;min-width:0}.live__bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.live__wrap{flex:1;display:grid;place-items:center;min-height:0}.live__tbl{width:100%;max-width:960px}.live__tbl.portrait{max-width:520px}.live__side{border-left:1px solid var(--line-200);background:var(--bg-100);display:flex;flex-direction:column;min-width:0}.live__sec{padding:16px 18px;border-bottom:1px solid var(--line-100)}.live__prize{font-family:var(--font-display);font-weight:700;font-size:48px;line-height:50px;font-variant-numeric:tabular-nums}.live__leg{display:flex;gap:14px;flex-wrap:wrap;font-size:13px;color:var(--ink-200)}.live__leg span{display:inline-flex;gap:6px;align-items:center}.live__feed .rh-li{padding:9px 18px;font-size:14px}@media (max-width:900px){.live{grid-template-columns:1fr}.live__side{display:none}.live__stage{padding:12px}}@media (max-width:600px){.live__leg{display:none}.live__bar .rh-btn{margin-left:auto}}';
  var FEED = [['a', 'Marcão', 'Add-on', 50, '22:41'], ['a', 'Brendon', 'Add-on', 50, '22:40'], ['a', 'Carlos', 'Add-on', 50, '22:38'], ['a', 'João', 'Add-on', 50, '22:37'], ['r', 'Pedro', 'Rebuy', 100, '22:12'], ['r', 'André', 'Rebuy', 100, '21:55'], ['r', 'Marcão', 'Rebuy', 100, '21:40']];
  function liveScreen(el, o) {
    o = o || {};
    if (!document.getElementById('rh-live-css')) { var s = document.createElement('style'); s.id = 'rh-live-css'; s.textContent = LIVE_CSS; document.head.appendChild(s); }
    var st = { mesa: o.mesa || 1, rot: o.rotation != null ? o.rotation : (window.innerWidth < 600 ? 90 : 0), active: o.active === undefined ? null : o.active, ctl: null };
    function render() {
      var T = recalc(), m = NIGHT.mesas[st.mesa - 1];
      var body = '<div class="live"><div class="live__stage"><div class="live__bar"><div class="rh-seg">' +
        NIGHT.mesas.map(function (x) { return '<button data-m="' + x.id + '" aria-pressed="' + (x.id === st.mesa) + '">' + x.name + ' · ' + PLAYERS.filter(function (p) { return p.mesa === x.id; }).length + '</button>'; }).join('') + '<button disabled style="opacity:.4">Mesa 03</button></div>' +
        '<span class="rh-spacer"></span><div class="live__leg"><span>' + chip('b') + 'Buy-in</span><span>' + chip('r') + 'Rebuy</span><span>' + chip('a') + 'Add-on</span><span><span class="rh-dealer-btn" style="width:18px;height:18px;font-size:10px">D</span>Dealer</span></div>' +
        '<button class="rh-btn" data-rot>' + icon('rotate') + 'ROTACIONAR MESA <span class="rh-small rh-muted" data-deg>' + st.rot + '°</span></button></div>' +
        '<div class="live__wrap"><div class="live__tbl' + (st.rot % 180 ? ' portrait' : '') + '" data-tbl></div></div></div>' +
        '<aside class="live__side"><div class="live__sec"><div class="rh-label">Prize pool</div><div class="live__prize">' + brl(T.prize) + '</div><div class="rh-small rh-muted">Arrecadado ' + brl(T.gross) + ' · rake ' + brl(T.rake) + '</div></div>' +
        '<div class="live__sec rh-grid" style="grid-template-columns:1fr 1fr 1fr;gap:8px">' +
        [['Buy-ins', T.buyins], ['Rebuys', T.rebuys], ['Add-ons', T.addons]].map(function (k) { return '<div class="rh-stat rh-stat--flat"><span class="rh-label">' + k[0] + '</span><span class="rh-stat__v" style="font-size:26px">' + k[1] + '</span></div>'; }).join('') + '</div>' +
        '<div class="live__sec" style="padding-bottom:8px"><div class="rh-row"><span class="rh-label">Últimos registros</span><span class="rh-spacer"></span><a class="rh-link rh-small" href="#">Resumo</a></div></div>' +
        '<div class="rh-list live__feed" style="flex:1;overflow:auto">' + FEED.map(function (f) { return '<div class="rh-li">' + chip(f[0]) + '<span style="flex:1"><b>' + f[1] + '</b> <span class="rh-muted">· ' + f[2] + '</span></span><span class="rh-num" style="font-weight:600;font-size:16px">' + brl(f[3]) + '</span><span class="rh-small rh-faint">' + f[4] + '</span></div>'; }).join('') + '</div>' +
        '<div class="live__sec" style="border-top:1px solid var(--line-200);border-bottom:0"><button class="rh-btn rh-btn--danger rh-btn--block" data-close>' + icon('lock') + 'FECHAR ENTRADAS</button></div></aside></div>';
      el.innerHTML = siteShell({ step: 'live', badge: '<span class="rh-badge rh-badge--live">Ao vivo</span>', strip: liveStrip(), body: body, pad: false, overlay: o.overlay ? o.overlay(api) : '' });
      st.ctl = pokerTable(el.querySelector('[data-tbl]'), { name: m.name, sub: 'Blinds ' + NIGHT.blinds, seats: m.seats, dealer: m.dealer, rotation: st.rot, active: st.active,
        players: PLAYERS.filter(function (p) { return p.mesa === st.mesa; }), onSeat: function (id) { st.active = id; if (o.onSeat) o.onSeat(id, api); else render(); } });
      el.querySelector('[data-rot]').onclick = function () { st.rot = st.ctl.rotate(); el.querySelector('[data-deg]').textContent = st.rot + '°'; el.querySelector('[data-tbl]').className = 'live__tbl' + (st.rot % 180 ? ' portrait' : ''); };
      el.querySelectorAll('[data-m]').forEach(function (b) { b.onclick = function () { st.mesa = +b.dataset.m; st.active = null; render(); }; });
      if (o.after) o.after(api);
    }
    var api = { render: render, state: st, player: function (id) { return PLAYERS.filter(function (p) { return p.id === id; })[0]; } };
    render();
    return api;
  }

  /* ---------- Player actions (drawer + modals) ---------- */
  function playerDrawer(p) {
    var bi = BUYINS.filter(function (x) { return x.id === p.bt; })[0];
    function blk(k, l, t, v, c, none) { return '<div class="rh-row" style="gap:12px;padding:12px 0;border-bottom:1px solid var(--line-100)">' + chip(k, l, 'lg') + '<div style="flex:1"><div class="rh-label">' + t + '</div>' + (none ? '<div class="rh-muted">Nenhum</div>' : '<div class="rh-small rh-muted">' + c + '</div>') + '</div><div class="rh-num" style="font-weight:700;font-size:24px">' + (none ? '—' : brl(v)) + '</div></div>'; }
    var hist = [['b', 'Buy-in ' + bi.name.split(' ')[1], bi.v, '20:04']];
    for (var i = 0; i < p.r; i++) hist.push(['r', 'Rebuy', NIGHT.rebuy.v, i ? '22:30' : '21:48']);
    for (var j = 0; j < p.a; j++) hist.push(['a', 'Add-on', NIGHT.addon.v, '22:44']);
    hist.reverse();
    return '<div class="rh-scrim" style="place-items:stretch;padding:0;background:rgba(5,6,7,.45)" data-dismiss></div><aside class="rh-drawer" role="dialog" aria-label="Ações do jogador">' +
      '<div class="rh-drawer__hd">' + avatar(p.name, 'lg') + '<div><div class="rh-heading" style="font-size:26px;line-height:28px;text-transform:uppercase">' + p.name + '</div><div class="rh-small rh-muted">Mesa 0' + p.mesa + ' · Lugar ' + p.seat + '</div></div><button class="rh-btn rh-btn--ghost rh-btn--icon rh-x" data-dismiss aria-label="Fechar">' + icon('x') + '</button></div>' +
      '<div class="rh-drawer__bd">' +
      '<div class="rh-grid" style="grid-template-columns:repeat(3,1fr);gap:8px">' +
      '<button class="rh-action" data-act="buyin"><span class="rh-action__top">' + chip('b') + 'Buy-in</span><span class="rh-action__meta">a partir de ' + brl(100) + '</span></button>' +
      '<button class="rh-action" data-act="rebuy"><span class="rh-action__top">' + chip('r') + 'Rebuy</span><span class="rh-action__meta">' + brl(NIGHT.rebuy.v) + '</span></button>' +
      '<button class="rh-action" data-act="addon"' + (p.a ? ' disabled' : '') + '><span class="rh-action__top">' + chip('a') + 'Add-on</span><span class="rh-action__meta">' + (p.a ? 'já feito' : brl(NIGHT.addon.v)) + '</span></button></div>' +
      '<div>' + blk('b', bi.id === 's' ? 'B' : bi.id === 'd' ? '2' : '3', 'Buy-in · ' + bi.name.split(' ')[1], bi.v, chips(bi.chips) + ' fichas') +
      blk('r', 'R', 'Rebuys · ' + p.r, p.r * NIGHT.rebuy.v, chips(p.r * NIGHT.rebuy.chips) + ' fichas', !p.r) +
      blk('a', 'A', 'Add-on', NIGHT.addon.v * p.a, chips(NIGHT.addon.chips) + ' fichas', !p.a) + '</div>' +
      '<div class="rh-row" style="padding:14px 16px;background:var(--bg-100);border:1px solid var(--line-200);border-radius:var(--radius-md)"><span class="rh-label" style="color:var(--ink-100)">Total investido</span><span class="rh-spacer"></span><span class="rh-num" style="font-weight:700;font-size:34px;line-height:36px">' + brl(p.total) + '</span></div>' +
      '<div><div class="rh-label" style="margin-bottom:6px">Histórico de entradas</div><div class="rh-list" style="border:1px solid var(--line-200);border-radius:var(--radius-md)">' +
      hist.map(function (h, i) { return '<div class="rh-li" style="padding:9px 12px">' + chip(h[0]) + '<span style="flex:1">' + h[1] + ' <span class="rh-faint rh-small">· ' + h[3] + '</span></span><span class="rh-num" style="font-weight:600">' + brl(h[2]) + '</span>' + (i === 0 ? '<button class="rh-btn rh-btn--ghost rh-btn--sm" data-undo>' + icon('undo') + 'Desfazer</button>' : '<span style="width:40px"></span>') + '</div>'; }).join('') + '</div>' +
      '<div class="rh-field__help" style="margin-top:6px">Só o último registro pode ser desfeito, com confirmação.</div></div>' +
      '<button class="rh-btn rh-btn--danger rh-btn--block" style="height:46px;margin-top:auto">' + icon('skull') + 'REGISTRAR ELIMINAÇÃO</button>' +
      '</div></aside>';
  }
  function buyinPicker(p, sel) {
    sel = sel || 's';
    return '<div class="rh-scrim"><div class="rh-modal" style="max-width:460px"><div class="rh-modal__hd"><div><div class="rh-label">Novo buy-in · ' + p.name + '</div><h2 class="rh-heading" style="font-size:24px">Escolha o tipo</h2></div><button class="rh-btn rh-btn--ghost rh-btn--icon rh-x" data-dismiss>' + icon('x') + '</button></div>' +
      '<div class="rh-modal__bd rh-col" style="gap:10px">' + BUYINS.map(function (x) {
        return '<button class="rh-pick" data-pick="' + x.id + '" aria-pressed="' + (x.id === sel) + '">' + chip('b', x.id === 's' ? 'B' : x.id === 'd' ? '2' : '3', 'lg') + '<span class="rh-pick__t">' + x.name + '</span><span class="rh-pick__v">' + brl(x.v) + '<small>' + chips(x.chips) + ' fichas</small></span></button>';
      }).join('') + '</div><div class="rh-modal__ft"><button class="rh-btn rh-btn--lg" data-dismiss>Voltar</button><button class="rh-btn rh-btn--primary rh-btn--lg" data-ok>Confirmar buy-in</button></div></div></div>';
  }
  function confirmModal(p, kind, inline) {
    var k = kind === 'rebuy' ? NIGHT.rebuy : NIGHT.addon, L = kind === 'rebuy' ? 'Rebuy' : 'Add-on';
    var m = '<div class="rh-modal" style="max-width:360px"><div class="rh-modal__hd"><span class="rh-label">Registrar ' + L.toLowerCase() + '</span><button class="rh-btn rh-btn--ghost rh-btn--icon rh-x" data-dismiss>' + icon('x') + '</button></div>' +
      '<div class="rh-modal__bd"><div class="rh-label">Jogador</div><div class="rh-row" style="margin:6px 0 18px">' + avatar(p.name) + '<b style="font-size:22px">' + p.name + '</b></div>' +
      '<div style="display:flex;align-items:center;gap:14px;padding:16px;border-radius:var(--radius-lg);background:var(--bg-100);border:1px solid var(--line-200)">' + chip(kind === 'rebuy' ? 'r' : 'a', null, 'lg') + '<div><div class="rh-label">' + L + '</div><div class="rh-small rh-muted">' + chips(k.chips) + ' fichas</div></div><span class="rh-spacer"></span><span class="rh-num" style="font-weight:700;font-size:40px;line-height:40px">' + brl(k.v) + '</span></div>' +
      '<div class="rh-small rh-muted" style="margin-top:12px">Total do jogador passa de ' + brl(p.total) + ' para <b style="color:var(--ink-100)">' + brl(p.total + k.v) + '</b>.</div></div>' +
      '<div class="rh-modal__ft"><button class="rh-btn rh-btn--primary rh-btn--lg" data-ok="' + kind + '">Confirmar ' + L + '</button></div></div>';
    return inline ? m : '<div class="rh-scrim">' + m + '</div>';
  }
  /* overlay state machine used by screens 07–09: null | {view:'drawer'|'buyin'|'rebuy'|'addon', id} */
  function liveWithActions(el, init) {
    var ov = init && init.view ? init : null, toast = '';
    var api = liveScreen(el, {
      mesa: init && init.id ? PLAYERS.filter(function (p) { return p.id === init.id; })[0].mesa : 1,
      active: init ? init.id : null, rotation: init && init.rotation,
      onSeat: function (id, a) { ov = { view: 'drawer', id: id }; a.state.active = id; a.render(); },
      overlay: function (a) {
        var t = toast ? '<div style="position:absolute;left:50%;bottom:24px;transform:translateX(-50%);z-index:30" class="rh-warn" role="status">' + icon('check') + '<span>' + toast + '</span></div>' : '';
        if (!ov) return t;
        var p = a.player(ov.id);
        if (ov.view === 'drawer') return playerDrawer(p) + t;
        if (ov.view === 'buyin') return buyinPicker(p, ov.sel);
        return confirmModal(p, ov.view);
      },
      after: function (a) {
        el.querySelectorAll('[data-dismiss]').forEach(function (b) { b.onclick = function () { ov = ov && ov.view !== 'drawer' ? { view: 'drawer', id: ov.id } : null; toast = ''; a.render(); }; });
        el.querySelectorAll('[data-act]').forEach(function (b) { b.onclick = function () { ov = { view: b.dataset.act, id: ov.id }; a.render(); }; });
        el.querySelectorAll('[data-pick]').forEach(function (b) { b.onclick = function () { ov.sel = b.dataset.pick; a.render(); }; });
        el.querySelectorAll('[data-ok]').forEach(function (b) { b.onclick = function () {
          var p = a.player(ov.id), k = b.dataset.ok;
          if (k === 'rebuy') p.r++; else if (k === 'addon') p.a = 1; else { p.b += BUYINS.filter(function (x) { return x.id === (ov.sel || 's'); })[0].v; }
          toast = (k === 'rebuy' ? 'Rebuy' : k === 'addon' ? 'Add-on' : 'Buy-in') + ' registrado para ' + p.name + ' — prize pool atualizado.';
          ov = { view: 'drawer', id: p.id }; a.render(); }; });
      }
    });
    return api;
  }

  window.RHG = { liveWithActions: liveWithActions, playerDrawer: playerDrawer, buyinPicker: buyinPicker, confirmModal: confirmModal, liveScreen: liveScreen, recalc: recalc, FEED: FEED, icon: icon, brl: brl, chips: chips, avatar: avatar, initials: initials, chip: chip, brand: brand, adminShell: adminShell, siteShell: siteShell, liveStrip: liveStrip, pokerTable: pokerTable, seatHTML: seatHTML, bars: bars, PLAYERS: PLAYERS, NIGHT: NIGHT, BUYINS: BUYINS };
})();
