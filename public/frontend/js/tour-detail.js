/* =========================================================
   Tour Details (wireframe P05). Reads ?package=<slug> and renders that package from LuxyCatalog.
   Booking creates a real booking request for the signed-in customer (LuxyStore).
   ========================================================= */
document.documentElement.className += ' js';
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); }, $$ = function (s, c) { return [].slice.call((c || document).querySelectorAll(s)); };
  var clamp = function (v, a, b) { return Math.max(a, Math.min(b, v)); };
  var rm = matchMedia('(prefers-reduced-motion:reduce)'), root = document.documentElement;
  var S = window.LuxyStore, CAT = window.LuxyCatalog;
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var usd = function (n) { return '$' + n.toLocaleString('en-US'); }, inr = function (n) { return '₹' + n.toLocaleString('en-IN'); };

  /* ---- header menu ---- */
  var hdr = $('#hdr'), burger = $('#burger');
  function closeMenu() { hdr.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); burger.setAttribute('aria-label', 'Open menu'); }
  burger.addEventListener('click', function () { var o = hdr.classList.toggle('open'); burger.setAttribute('aria-expanded', o); burger.setAttribute('aria-label', o ? 'Close menu' : 'Open menu'); });
  $('#menu').addEventListener('click', function (e) { if (e.target.closest('a')) closeMenu(); });
  addEventListener('keydown', function (e) { if (e.key === 'Escape' && hdr.classList.contains('open')) { closeMenu(); burger.focus(); } });

  /* ---- which package? ---- */
  var slug = new URLSearchParams(location.search).get('package') || 'munnar-mist-tea-trails';
  var t = CAT.get(slug);
  if (!t) { $('#top').hidden = true; $('#tdmain').hidden = true; $('#notfound').hidden = false; document.title = 'Package not found | LuxyBiz'; $('#mbar').hidden = true; return; }
  var tier = (new URLSearchParams(location.search).get('tier') || '').toLowerCase();
  var tierNames = { silver: 'Silver', gold: 'Gold', platinum: 'Platinum' };
  var selectedTier = tierNames[tier] || '';

  /* ---- render ---- */
  document.title = t.name + ' | LuxyBiz Tours & Wellness';
  $('meta[name=description]').setAttribute('content', t.short + ' Private, paced for you.');
  $('#hero-img').src = t.hero; $('#hero-img').alt = t.heroAlt;
  $('#crumb').textContent = t.name; $('#h1').textContent = t.name + (selectedTier ? ' - ' + selectedTier : ''); $('#m-dest').textContent = t.dest;
  var days = t.nights + 1, dur = days + ' days / ' + t.nights + ' nights';
  $('#m-dur').textContent = dur; $('#m-senior').hidden = !t.senior; $('#b-senior').hidden = !t.senior;
  $('#pp-usd').textContent = 'USD ' + usd(t.usd); $('#pp-inr').textContent = 'INR ' + inr(t.inr);

  $('#ov-lead').textContent = t.lead;
  $('#ov-body').innerHTML = t.overview.map(function (p) { return '<p class="body">' + esc(p) + '</p>'; }).join('');
  $('#glance').innerHTML = [['Duration', dur]].concat(t.glance).map(function (g) { return '<div><dt>' + esc(g[0]) + '</dt><dd>' + esc(g[1]) + '</dd></div>'; }).join('');

  $('#gal').innerHTML = t.gallery.map(function (g, i) {
    return '<button type="button" data-full="' + esc(g.src.replace(/w=\d+&h=\d+/, 'w=1600&h=1100')) + '" data-alt="' + esc(g.alt) + '" data-cap="' + esc(g.cap) + '" aria-label="Open photo ' + (i + 1) + ': ' + esc(g.cap) + '"><img src="' + esc(g.src) + '" alt="' + esc(g.alt) + '" loading="lazy" decoding="async"><span class="cap">' + esc(g.cap) + '</span></button>';
  }).join('');

  $('#itin').innerHTML = t.days.map(function (d, i) {
    return '<li><span class="dn" aria-hidden="true">' + ('0' + (i + 1)).slice(-2) + '</span><h3><button class="ih" type="button" id="ih' + (i + 1) + '" aria-expanded="' + (i === 0) + '" aria-controls="d' + (i + 1) + '"><span><b>Day ' + (i + 1) + ': ' + esc(d.t) + '</b><em>' + esc(d.s) + '</em></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button></h3>' +
      '<div class="ib' + (i === 0 ? ' open' : '') + '" id="d' + (i + 1) + '" role="region" aria-labelledby="ih' + (i + 1) + '"><div><ul class="ev">' + d.ev.map(function (e) { return '<li><time>' + esc(e[0]) + '</time><p>' + esc(e[1]) + '</p></li>'; }).join('') + '</ul></div></li>';
  }).join('');

  var chk = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>', crs = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
  $('#inc').innerHTML = t.include.map(function (x) { return '<li>' + chk + '<span>' + esc(x) + '</span></li>'; }).join('');
  $('#exc').innerHTML = t.exclude.map(function (x) { return '<li>' + crs + '<span>' + esc(x) + '</span></li>'; }).join('');

  var p = t.pro;
  $('#pro').innerHTML = '<div class="pro-av" aria-hidden="true"><svg viewBox="0 0 34 34"><use href="#i-person"/></svg></div><div class="pro-t"><b>' + esc(p.name) + '</b> <span>' + esc(p.designation) + '</span><p class="pc">' + esc(p.creds) + '</p><p>' + esc(p.bio) + '</p>' +
    (p.link ? '<a class="tlink" href="' + esc(p.link) + '">Read the full biography</a>' : '') + '<p class="pn">Supplied by LuxyBiz for this package.</p></div>';
  $('#know').innerHTML = t.know.map(function (k) { return '<li><h3>' + esc(k[0]) + '</h3><p>' + esc(k[1]) + '</p></li>'; }).join('');

  $('#bk-h').textContent = t.name; $('#b-dur').textContent = dur;
  [$('#b-price'), $('#mb-price')].forEach(function (e) { e.dataset.usd = usd(t.usd); e.dataset.inr = inr(t.inr); e.textContent = usd(t.usd); });
  $('#f-pkg').value = t.slug;
  $('#f-msg').placeholder = "I'm interested in " + t.name + ". Who is travelling, what pace suits you, anything you'd love to do.";
  var intMap = { Highlands: 'The Highlands', Backwaters: 'The Backwaters', Coast: 'The Coast', Wellness: 'A wellness stay', Culture: 'Heritage and culture' };
  $('#f-int').value = intMap[t.region] || 'Not sure yet';

  var rel = CAT.related(t.slug, 3);
  $('#relgrid').innerHTML = rel.map(function (r) {
    return '<article class="jc"><div class="arch zoom rv"><img src="' + esc(r.card) + '" alt="' + esc(r.heroAlt) + '" loading="lazy" decoding="async"></div><h3>' + esc(r.name) + '</h3><p class="d">' + esc(r.dest) + ' &middot; ' + (r.nights + 1) + ' days / ' + r.nights + ' nights</p>' +
      '<div class="jmeta"><span class="price">From <b data-usd="' + usd(r.usd) + '" data-inr="' + inr(r.inr) + '">' + usd(r.usd) + '</b></span><span class="nights">' + r.nights + ' nights</span></div>' +
      '<a class="tlink" href="package-tiers.html?package=' + r.slug + '">View Details</a></article>';
  }).join('');
  $('#related').hidden = rel.length < 2;                      /* hidden if fewer than 2 matches */

  /* ---- currency: USD | INR (prices stored per package, no live conversion) ---- */
  $$('button[data-cur]').forEach(function (b) {
    b.addEventListener('click', function () {
      var c = b.dataset.cur; root.setAttribute('data-cur', c);
      $$('button[data-cur]').forEach(function (x) { x.setAttribute('aria-pressed', x.dataset.cur === c); });
      $$('[data-usd]').forEach(function (e) { e.textContent = e.dataset[c]; });
    });
  });

  /* ---- itinerary accordion ---- */
  var itin = $('#itin'), items = $$('li', itin).filter(function (li) { return li.parentNode === itin; }), allbtn = $('#allbtn');
  function setOpen(li, open) { $('.ih', li).setAttribute('aria-expanded', open); $('.ib', li).classList.toggle('open', open); }
  function syncAll() {
    var all = items.every(function (li) { return $('.ih', li).getAttribute('aria-expanded') === 'true'; });
    allbtn.setAttribute('aria-pressed', all); allbtn.textContent = all ? 'Collapse all days' : 'Expand all days';
  }
  items.forEach(function (li) { $('.ih', li).addEventListener('click', function () { setOpen(li, this.getAttribute('aria-expanded') !== 'true'); syncAll(); }); });
  allbtn.addEventListener('click', function () { var open = allbtn.getAttribute('aria-pressed') !== 'true'; items.forEach(function (li) { setOpen(li, open); }); syncAll(); });
  syncAll();

  /* ---- gallery lightbox ---- */
  var lb = $('#lb'), shots = $$('#gal button'), cur = 0;
  function show(i) { cur = (i + shots.length) % shots.length; var s = shots[cur]; $('#lb-img').src = s.dataset.full; $('#lb-img').alt = s.dataset.alt; $('#lb-cap').textContent = s.dataset.cap + '  (' + (cur + 1) + ' of ' + shots.length + ')'; }
  shots.forEach(function (s, i) { s.addEventListener('click', function () { if (!lb.showModal) { window.open(s.dataset.full, '_blank'); return; } show(i); lb.showModal(); }); });
  $('#lb-x').addEventListener('click', function () { lb.close(); });
  $('#lb-p').addEventListener('click', function () { show(cur - 1); });
  $('#lb-n').addEventListener('click', function () { show(cur + 1); });
  lb.addEventListener('click', function (e) { if (e.target === lb || e.target.classList.contains('lb-in')) lb.close(); });
  lb.addEventListener('keydown', function (e) { if (e.key === 'ArrowLeft') show(cur - 1); if (e.key === 'ArrowRight') show(cur + 1); });

  /* ---- booking widget: signed-in customers create a real booking request ---- */
  var bookbtn = $('#bookbtn'), date = $('#b-date'), berr = $('#b-err'), bmsg = $('#bmsg');
  var today = new Date();
  date.min = today.getFullYear() + '-' + ('0' + (today.getMonth() + 1)).slice(-2) + '-' + ('0' + today.getDate()).slice(-2);
  date.addEventListener('change', function () { berr.textContent = ''; date.removeAttribute('aria-invalid'); bmsg.hidden = true; });
  bookbtn.addEventListener('click', function () {
    if (!date.value || date.value < date.min) { berr.textContent = 'Choose today or a future travel date to continue.'; date.setAttribute('aria-invalid', 'true'); date.focus(); return; }
    date.removeAttribute('aria-invalid');
    berr.textContent = '';
    location.href = 'booking.html?package=' + encodeURIComponent(t.slug) + '&date=' + encodeURIComponent(date.value) + '&currency=' + encodeURIComponent(root.getAttribute('data-cur') || 'usd') + (selectedTier ? '&tier=' + encodeURIComponent(tier) : '');
  });

  /* ---- enquiry form (front-end only, same as home) ---- */
  if (S.current()) { var u = S.current(); $('#f-name').value = u.name; $('#f-mail').value = u.email; }
  $('#enq').addEventListener('submit', function (e) {
    e.preventDefault();
    var n = $('#f-name'), m = $('#f-mail'), p = $('#f-phone'), ok = true;
    function bad(el, id, msg) { $('#' + id).textContent = msg; el.setAttribute('aria-invalid', 'true'); ok = false; }
    function good(el, id) { $('#' + id).textContent = ''; el.removeAttribute('aria-invalid'); }
    good(n, 'e-name'); good(m, 'e-mail'); good(p, 'e-phone');
    if (!n.value.trim()) bad(n, 'e-name', 'Please enter your name.');
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(m.value.trim())) bad(m, 'e-mail', 'Please enter a valid email address.');
    if (p.value.trim() && !/^[0-9][0-9\s-]{5,14}$/.test(p.value.trim())) bad(p, 'e-phone', 'Please enter a valid phone number.');
    var msg = $('#ok');
    if (!ok) { msg.hidden = true; (n.hasAttribute('aria-invalid') ? n : m.hasAttribute('aria-invalid') ? m : p).focus(); return; }
    msg.textContent = 'Thank you, ' + n.value.trim().split(' ')[0] + '. Your enquiry is ready. Once the form is connected to your backend, it will reach your planner.';
    msg.hidden = false;
  });

  /* ---- scroll engine ---- */
  var heroImg = $('#heroimg'), hero = $('#top'), frames = $$('.arch,.frame'), rvs = $$('.rv'), fxs = $$('.fx');
  var subLinks = $$('#subnav a'), secs = subLinks.map(function (a) { return document.getElementById(a.getAttribute('href').slice(1)); });
  var tick = false;
  function frame() {
    tick = false;
    var y = scrollY, vh = innerHeight;
    root.classList.toggle('hdr-off', false);
    if (!rm.matches && y < hero.offsetHeight) heroImg.style.transform = 'translate3d(0,' + (y * .16).toFixed(1) + 'px,0)';
    if (!rm.matches) frames.concat(shots).forEach(function (f) {
      var r = f.getBoundingClientRect(); if (r.bottom < -50 || r.top > vh + 50) return;
      var im = f.querySelector('img'); if (!im) return;
      im.style.transform = 'translate3d(0,' + (((r.top + r.height / 2 - vh / 2) / vh) * -r.height * .1).toFixed(1) + 'px,0)';
    });
    var ir = itin.getBoundingClientRect(), ip = clamp((vh * .6 - ir.top) / ir.height, 0, 1);
    itin.style.setProperty('--fp', rm.matches ? 1 : ip.toFixed(3));
    items.forEach(function (li) { var r = li.getBoundingClientRect(); li.classList.toggle('act', rm.matches || r.top < vh * .6); });
    rvs = rvs.filter(function (e) { var q = e.getBoundingClientRect(); if (q.top < vh * .92 && q.bottom > 0) { e.classList.add('in'); return false; } return true; });
    fxs = fxs.filter(function (e) { var q = e.getBoundingClientRect(); if (q.top < vh * .9 && q.bottom > 0) { e.classList.add('in'); return false; } return true; });
    var active = 0; secs.forEach(function (s, i) { if (s && s.getBoundingClientRect().top < vh * .35) active = i; });
    subLinks.forEach(function (a, i) { if (i === active) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current'); });
  }
  addEventListener('scroll', function () { if (!tick) { tick = true; requestAnimationFrame(frame); } }, { passive: true });
  addEventListener('resize', function () { requestAnimationFrame(frame); });
  frame();

  /* ---- the mobile booking bar hides where it would cover the widget, form or footer ---- */
  var mbar = $('#mbar');
  if ('IntersectionObserver' in window) {
    var seen = {};
    var io = new IntersectionObserver(function (es) { es.forEach(function (en) { seen[en.target.id] = en.isIntersecting; }); mbar.classList.toggle('off', !!(seen.book || seen.plan || seen.contact)); }, { threshold: .15 });
    ['plan', 'contact', 'book'].forEach(function (id) { io.observe(document.getElementById(id)); });
  }
})();
