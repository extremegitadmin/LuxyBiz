/* =========================================================
   LuxyBiz customer portal: one script for every portal page.
   The page is chosen by <body data-page="...">.
   All data comes from LuxyStore (luxy-store.js). Nothing here touches storage directly.
   ========================================================= */
(function () {
  'use strict';
  var S = window.LuxyStore;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return [].slice.call((c || document).querySelectorAll(s)); };
  var DAY = 864e5;
  var CHECK = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
  var INFO = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16h.01"/></svg>';

  /* ---------- helpers ---------- */
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fmt(d, o) { return d.toLocaleDateString('en-GB', o || { day: 'numeric', month: 'short', year: 'numeric' }); }
  function range(b) {
    var s = b.start, e = b.end;
    if (+s === +e) return fmt(s);
    var same = s.getMonth() === e.getMonth() && s.getFullYear() === e.getFullYear();
    return (same ? fmt(s, { day: 'numeric' }) : fmt(s, { day: 'numeric', month: 'short' })) + ' to ' + fmt(e);
  }
  function chip(tone, label) { return '<span class="st ' + tone + '">' + (tone === 'ok' ? CHECK : '') + esc(label) + '</span>'; }
  function usd(n) { return '$' + n.toLocaleString('en-US'); }
  function inr(n) { return '₹' + n.toLocaleString('en-IN'); }
  function ago(iso) {
    var d = Math.round((S.midnight() - S.midnight(new Date(iso))) / DAY);
    if (d <= 0) return 'Today'; if (d === 1) return 'Yesterday'; if (d < 14) return d + ' days ago';
    return fmt(new Date(iso));
  }
  function size(n) { return n < 1024 * 1024 ? Math.max(1, Math.round(n / 1024)) + ' KB' : (n / 1048576).toFixed(1) + ' MB'; }
  function qs(k) { return new URLSearchParams(location.search).get(k); }
  function notice(type, html) {
    var box = $('#notice'); if (!box) return;
    box.innerHTML = html ? '<div class="banner ' + (type || '') + '" role="' + (type === 'err' ? 'alert' : 'status') + '">' + (type === 'err' ? INFO : CHECK) + '<div>' + html + '</div></div>' : '';
    if (html) box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
  function ask(o) {
    return new Promise(function (res) {
      if (!window.HTMLDialogElement) { res({ ok: confirm(o.title + '\n\n' + o.body), value: '' }); return; }
      var d = document.createElement('dialog'); d.className = 'dlg';
      d.setAttribute('aria-labelledby', 'dlg-h');
      d.innerHTML = '<form method="dialog"><h2 id="dlg-h">' + esc(o.title) + '</h2><p>' + esc(o.body) + '</p>' +
        (o.field ? '<label class="help" for="dlg-f">' + esc(o.field) + '</label><textarea id="dlg-f" maxlength="500"></textarea>' : '') +
        '<div class="row"><button class="btn" value="cancel" type="submit">' + esc(o.cancel || 'Cancel') + '</button><button class="btn ' + (o.danger ? 'danger' : 'solid') + '" value="ok" type="submit">' + esc(o.ok || 'OK') + '</button></div></form>';
      document.body.appendChild(d);
      d.addEventListener('close', function () { var t = d.querySelector('textarea'); res({ ok: d.returnValue === 'ok', value: t ? t.value : '' }); d.remove(); });
      d.showModal(); $('button[value=cancel]', d).focus();
    });
  }
  function download(id, name) {
    S.docBlob(id).then(function (b) {
      if (!b) { notice('err', 'That file could not be found on this device.'); return; }
      var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = name; document.body.appendChild(a); a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 600);
    });
  }

  /* ---------- shell: sidebar, log out, unread badge ---------- */
  var user = S.current();
  if (!user) { if (document.body.hasAttribute('data-gate')) return; location.replace('auth.html?next=' + encodeURIComponent(location.pathname.split('/').pop() + location.search) + '#login'); return; }
  function shell() {
    user = S.current();
    var first = user.name.split(' ')[0];
    $('#who-name').textContent = user.name; $('#who-mail').textContent = user.email; $('#av').textContent = first.charAt(0).toUpperCase();
    $$('[data-logout]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); S.logout(); location.href = 'auth.html#login'; }); });
    var un = S.notes().filter(function (n) { return !n.read; }).length;
    var a = $('.pnav a[href="dashboard.html"]'); if (a) {
      var old = $('.nav-badge', a); if (old) old.remove();
      if (un) { var s = document.createElement('span'); s.className = 'nav-badge'; s.textContent = un; s.setAttribute('aria-label', un + ' unread notifications'); a.appendChild(s); }
    }
  }
  shell();

  /* ---------- DASHBOARD (P12) ---------- */
  function dashboard() {
    $('#first').textContent = user.name.split(' ')[0];
    var j = S.nextJourney();
    var all = S.bookings(), docs = S.docs();
    $('#q-b').textContent = all.length + (all.length === 1 ? ' booking' : ' bookings');
    $('#q-d').textContent = docs.length + (docs.length === 1 ? ' document' : ' documents');
    if ($('#q-c')) { var open = S.customRequests().filter(function (r) { return r.status === 'quote_requested'; }).length; $('#q-c').textContent = open ? open + (open === 1 ? ' open request' : ' open requests') : 'Build your own route'; }

    if (j) {
      $('#jny').hidden = false; $('#jny-empty').hidden = true;
      $('#jny-img').src = j.tour.image; $('#jny-img').alt = j.tour.name;
      $('#j-tour').textContent = j.tour.name; $('#j-dates').textContent = range(j);
      $('#j-len').textContent = (j.tour.nights + 1) + ' days / ' + j.tour.nights + ' nights';
      $('#j-chips').innerHTML = chip(j.tone, j.label) + chip(j.payTone, j.payLabel) + '<span class="ref">Ref <b>' + esc(j.ref) + '</b></span>';
      var left = Math.round((j.start - S.midnight()) / DAY), n = $('#j-days'), u = $('#j-unit');
      if (left > 0) { n.textContent = left; u.textContent = left === 1 ? 'day to go' : 'days to go'; } else { n.textContent = 'Now'; u.textContent = 'Your journey is under way'; }
      var total = Math.max(1, Math.round((j.start - S.midnight(new Date(j.created))) / DAY)), pct = Math.max(2, Math.min(100, 100 - left / total * 100));
      requestAnimationFrame(function () { $('#j-bar').style.width = pct.toFixed(0) + '%'; });
      $('#j-itin').href = 'booking-details.html?ref=' + j.ref; $('#j-book').href = 'booking-details.html?ref=' + j.ref + '#documents';
      $('#q-itin').href = 'booking-details.html?ref=' + j.ref;
      $('#q-i').textContent = j.tour.days.length ? j.tour.days.length + ' days planned' : 'Shared after confirmation';
      $('#subline').textContent = 'Here is what is coming up on your Kerala journey.';
    } else {
      $('#jny').hidden = true; $('#jny-empty').hidden = false;
      $('#q-itin').href = 'my-bookings.html'; $('#q-i').textContent = 'No journey yet';
      $('#subline').textContent = 'Nothing is booked yet. When you are ready, your next trip starts here.';
    }

    var list = $('#n-list'), all2 = $('#n-all'), cnt = $('#n-count'), empty = $('#n-empty');
    function notes() {
      var n = S.notes().slice(0, 6), unread = S.notes().filter(function (x) { return !x.read; }).length;
      list.innerHTML = n.map(function (x) {
        return '<li class="' + (x.read ? '' : 'unread') + '" data-id="' + x.id + '" tabindex="0" role="button" aria-label="' + esc(x.text) + (x.read ? '' : ' (unread). Press to mark as read') + '"><i class="nd" aria-hidden="true"></i><div><p>' + esc(x.text) + '</p><time>' + ago(x.at) + '</time></div></li>';
      }).join('');
      cnt.hidden = !unread; cnt.textContent = unread; all2.disabled = !unread;
      list.hidden = !n.length; empty.hidden = !!n.length;
      shell();
    }
    list.addEventListener('click', function (e) { var li = e.target.closest('li'); if (li) { S.markRead(li.dataset.id); notes(); } });
    list.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.closest('li')) { e.preventDefault(); S.markRead(e.target.closest('li').dataset.id); notes(); } });
    all2.addEventListener('click', function () { S.markAllRead(); notes(); });
    notes();
    $('#s-loading').hidden = true; $('#s-ready').hidden = false; $('#main').setAttribute('aria-busy', 'false');
  }

  /* ---------- MY BOOKINGS (P13) ---------- */
  function bookings() {
    var all = S.bookings(), f = 'all', q = '';
    var body = $('#rows'), tbl = $('#tbl'), empty = $('#none'), nomatch = $('#nomatch');
    $('#sk').hidden = true; $('#bar').hidden = false;
    var counts = { all: all.length, upcoming: 0, past: 0, cancelled: 0 };
    all.forEach(function (b) { counts[b.bucket]++; });
    $$('.seg button').forEach(function (b) { $('i', b).textContent = counts[b.dataset.f]; b.addEventListener('click', function () { f = b.dataset.f; draw(); }); });
    $('#q').addEventListener('input', function (e) { q = e.target.value.trim().toLowerCase(); draw(); });
    $('#clear').addEventListener('click', function () { f = 'all'; q = ''; $('#q').value = ''; draw(); $('#q').focus(); });
    function draw() {
      $$('.seg button').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.f === f); });
      if (!all.length) { tbl.hidden = true; $('#bar').hidden = true; $('#result-count').textContent = ''; empty.hidden = false; nomatch.hidden = true; return; }
      var rows = all.filter(function (b) { return (f === 'all' || b.bucket === f) && (!q || (b.ref + ' ' + b.tour.name).toLowerCase().indexOf(q) > -1); });
      tbl.hidden = !rows.length; nomatch.hidden = !!rows.length;
      body.innerHTML = rows.map(function (b) {
        return '<tr><td data-label="Reference" class="rf">' + esc(b.ref) + '</td>' +
          '<td data-label="Tour" class="tn">' + esc(b.tour.name) + '<small>' + (b.tour.nights + 1) + ' days, ' + b.travelers + ' travellers</small></td>' +
          '<td data-label="Travel date">' + fmt(b.start) + '</td>' +
          '<td data-label="Booking status">' + chip(b.tone, b.label) + '</td>' +
          '<td data-label="Payment status">' + chip(b.payTone, b.payLabel) + '</td>' +
          '<td class="acts-c"><a class="btn sm" href="booking-details.html?ref=' + encodeURIComponent(b.ref) + '">View details</a></td></tr>';
      }).join('');
      $('#result-count').textContent = rows.length + (rows.length === 1 ? ' booking' : ' bookings') + ' shown';
    }
    draw();
  }

  /* ---------- BOOKING DETAILS + ITINERARY (P14) ---------- */
  function detail() {
    var ref = qs('ref'), b = ref && S.booking(ref), root = $('#detail');
    if (!b) { root.innerHTML = '<div class="card empty-in"><b>We could not find that booking</b>It may belong to another account, or the link may be wrong.<br><a class="btn solid" href="my-bookings.html">Back to My Bookings</a></div>'; return; }
    document.title = b.ref + ' ' + b.tour.name + ' | LuxyBiz';
    var docs = S.docs().filter(function (d) { return d.bookingRef === b.ref; });
    var days = b.tour.days;
    var itin = days.length ? '<ol class="acc" id="acc">' + days.map(function (d, i) {
      return '<li><h3 style="margin:0"><button class="ah" type="button" id="ah' + i + '" aria-expanded="' + (i === 0) + '" aria-controls="ab' + i + '"><span class="dn">' + (i + 1) + '</span><span class="at"><b>Day ' + (i + 1) + ': ' + esc(d.t) + '</b><em>' + esc(d.s) + '</em></span>' +
        '<svg class="chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button></h3>' +
        '<div class="ab' + (i === 0 ? ' open' : '') + '" id="ab' + i + '" role="region" aria-labelledby="ah' + i + '"><ul>' + d.ev.map(function (e) { return '<li><time>' + esc(e[0]) + '</time><p>' + esc(e[1]) + '</p></li>'; }).join('') + '</ul></div></li>';
    }).join('') + '</ol>' : '<p class="muted">Your planner will share the day-by-day plan once your booking is confirmed.</p>';
    var cancelBox;
    if (b.cancel) {
      var cs = b.cancel.status, lab = cs === 'approved' ? ['ok', 'Cancellation approved'] : cs === 'declined' ? ['bad', 'Cancellation declined'] : ['warn', 'Cancellation pending review'];
      cancelBox = '<p>' + chip(lab[0], lab[1]) + '</p><p class="muted">Requested ' + fmt(new Date(b.cancel.requested)) + '.' + (cs === 'pending_review' ? ' Our team will review it and get in touch.' : '') + '</p><a class="tlink" href="cancel-booking.html?ref=' + encodeURIComponent(b.ref) + '">View request</a>';
    }
    else if (b.canCancel) cancelBox = '<a class="btn danger sm" href="cancel-booking.html?ref=' + encodeURIComponent(b.ref) + '">Request cancellation</a><p class="help" style="margin:10px 0 0">Where applicable. This sends a request for review.</p>';
    else cancelBox = '<p class="muted" style="margin:0">' + (b.bucket === 'cancelled' ? 'This booking has been cancelled.' : 'This journey has finished, so it can no longer be cancelled.') + '</p>';
    root.innerHTML =
      '<div class="dhead"><a class="crumb" href="my-bookings.html">&larr; My Bookings</a>' +
      '<h1>' + esc(b.ref) + ' &middot; ' + esc(b.tour.name) + '</h1>' +
      '<p class="when"><span>' + range(b) + '</span><span class="dot" aria-hidden="true"></span><span>' + (b.tour.nights + 1) + ' days / ' + b.tour.nights + ' nights</span></p>' +
      '<div class="chips">' + chip(b.tone, b.label) + chip(b.payTone, b.payLabel) + '</div></div>' +
      '<div class="two"><div class="vstack">' +
        '<section class="card" aria-labelledby="it-h"><div class="chead"><h2 id="it-h" style="margin:0">Itinerary</h2>' + (days.length ? '<button class="link" type="button" id="all">Expand all days</button>' : '') + '</div>' + itin + '</section>' +
        '<section class="card" aria-labelledby="ti-h"><h2 id="ti-h">Travel information</h2><dl class="kv">' +
          '<div><dt>Dates</dt><dd>' + fmt(b.start, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) + ' to ' + fmt(b.end, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) + '</dd></div>' +
          '<div><dt>Travellers</dt><dd>' + b.travelers + '</dd></div>' +
          (b.contact ? '<div><dt>Lead traveller</dt><dd>' + esc(b.contact.name) + '<br>' + esc(b.contact.email) + '<br>' + esc(b.contact.phone) + '</dd></div>' +
            (b.contact.notes ? '<div><dt>Special details</dt><dd>' + esc(b.contact.notes) + '</dd></div>' : '') : '') +
          '<div><dt>Details</dt><dd>' + esc(b.info) + '</dd></div>' +
          '<div><dt>Booked on</dt><dd>' + fmt(new Date(b.created)) + '</dd></div></dl></section>' +
      '</div><div class="vstack">' +
        '<section class="card" id="documents" aria-labelledby="dc-h"><div class="chead"><h2 id="dc-h" style="margin:0">Documents</h2></div>' +
          (docs.length ? docs.slice(0, 4).map(function (d) { return '<div class="dl"><span class="qi"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#i-doc"/></svg></span><span class="nm">' + esc(d.name) + '<small>' + d.type + ', ' + size(d.size) + '</small></span><button class="btn sm" type="button" data-dl="' + d.id + '" aria-label="Download ' + esc(d.name) + '">Download</button></div>'; }).join('') : '<p class="muted">No documents for this booking yet.</p>') +
          '<p style="margin:14px 0 0"><a class="tlink" href="documents.html">View all documents</a></p></section>' +
        '<section class="card" aria-labelledby="pr-h"><h2 id="pr-h">Price</h2>' +
          '<div class="price-row"><span>Per person</span><span>' + usd(b.tour.usd) + ' | ' + inr(b.tour.inr) + '</span></div>' +
          '<div class="price-row"><span>Travellers</span><span>' + b.travelers + '</span></div>' +
          '<div class="price-row tot"><span>Total</span><span>' + usd(b.totalUSD) + ' | ' + inr(b.totalINR) + '</span></div></section>' +
        '<section class="card" aria-labelledby="cx-h"><h2 id="cx-h">Need to change something?</h2>' + cancelBox + '</section>' +
      '</div></div>';
    $$('[data-dl]', root).forEach(function (btn) { btn.addEventListener('click', function () { var d = docs.filter(function (x) { return x.id === btn.dataset.dl; })[0]; download(d.id, d.name); }); });
    var acc = $('#acc');
    if (acc) {
      $$('.ah', acc).forEach(function (btn) { btn.addEventListener('click', function () { var open = btn.getAttribute('aria-expanded') !== 'true'; btn.setAttribute('aria-expanded', open); $('#' + btn.getAttribute('aria-controls')).classList.toggle('open', open); sync(); }); });
      var allBtn = $('#all');
      var sync = function () { var every = $$('.ah', acc).every(function (x) { return x.getAttribute('aria-expanded') === 'true'; }); allBtn.textContent = every ? 'Collapse all days' : 'Expand all days'; allBtn.dataset.on = every; };
      allBtn.addEventListener('click', function () { var on = allBtn.dataset.on !== 'true'; $$('.ah', acc).forEach(function (x) { x.setAttribute('aria-expanded', on); $('#' + x.getAttribute('aria-controls')).classList.toggle('open', on); }); sync(); });
      sync();
    }
    if (location.hash === '#documents') setTimeout(function () { $('#documents').scrollIntoView({ behavior: 'smooth' }); }, 200);
  }

  /* ---------- DOCUMENT VAULT (P15): 1 Select file, 2 Review (type / size), 3 Upload progress, 4 Success / Failure ---------- */
  function documents() {
    var drop = $('#drop'), input = $('#file'), prog = $('#prog'), sel = $('#attach'), review = $('#review'), picked = [];
    S.bookings().forEach(function (b) { var o = document.createElement('option'); o.value = b.ref; o.textContent = b.ref + ' - ' + b.tour.name; sel.appendChild(o); });
    var ERR = { type: 'File type not allowed. Use PDF, JPG, PNG or DOCX.', size: 'File is over the 5 MB limit.', empty: 'File is empty.', read: 'We could not read this file.', perm: 'This document cannot be changed.' };
    function step(n) { $$('#steps4 li').forEach(function (li, i) { li.classList.toggle('on', i + 1 === n); li.classList.toggle('done', i + 1 < n); li.setAttribute('aria-current', i + 1 === n ? 'step' : 'false'); }); }
    function check(f) {
      var ext = (f.name.split('.').pop() || '').toLowerCase();
      if (!S.ALLOWED[ext]) return 'type'; if (f.size > S.MAX_BYTES) return 'size'; if (!f.size) return 'empty'; return '';
    }
    function type(f) { return S.ALLOWED[(f.name.split('.').pop() || '').toLowerCase()] || 'Other'; }
    function drawReview() {
      review.hidden = !picked.length;
      $('#rv-list').innerHTML = picked.map(function (p, i) {
        return '<li><span class="nm">' + esc(p.f.name) + '<small>' + type(p.f) + ' &middot; ' + size(p.f.size) + '</small></span>' +
          (p.err ? '<span class="st bad">' + esc(ERR[p.err]) + '</span>' : '<span class="st ok">' + CHECK + 'Ready</span>') +
          '<button class="link" type="button" data-rm="' + i + '" aria-label="Remove ' + esc(p.f.name) + ' from the list">Remove</button></li>';
      }).join('');
      var okCount = picked.filter(function (p) { return !p.err; }).length;
      $('#up-go').disabled = !okCount; $('#up-go').textContent = okCount > 1 ? 'Upload ' + okCount + ' files' : 'Upload';
      step(picked.length ? 2 : 1);
    }
    function select(files) {
      notice('', ''); picked = [].slice.call(files).map(function (f) { return { f: f, err: check(f) }; });
      drawReview(); if (picked.length) $('#up-go').focus({ preventScroll: true });
    }
    $('#rv-list').addEventListener('click', function (e) { var r = e.target.closest('[data-rm]'); if (r) { picked.splice(+r.dataset.rm, 1); drawReview(); } });
    $('#up-cancel').addEventListener('click', function () { picked = []; input.value = ''; drawReview(); });
    $('#up-go').addEventListener('click', function () {
      var queue = picked.filter(function (p) { return !p.err; }).map(function (p) { return p.f; }), ok = 0, bad = [];
      review.hidden = true; step(3); $('#up-go').disabled = true;
      (function next() {
        var f = queue.shift();
        if (!f) {
          prog.hidden = true; input.value = ''; picked = []; list(); shell(); step(4);
          if (ok) notice('', '<b>Upload successful.</b> ' + (ok === 1 ? 'Your file is in your vault.' : ok + ' files are in your vault.'));
          if (bad.length) notice('err', '<b>Upload failed.</b><br>' + bad.map(function (b) { return esc(b.n) + ': ' + esc(b.m); }).join('<br>'));
          return;
        }
        prog.hidden = false; $('#pn-name').textContent = f.name; $('#pn-pct').textContent = '0%'; $('#pb').style.width = '0%';
        S.addDoc(f, { bookingRef: sel.value, onProgress: function (p) { $('#pb').style.width = Math.round(p * 100) + '%'; $('#pn-pct').textContent = Math.round(p * 100) + '%'; } })
          .then(function () { ok++; next(); })
          .catch(function (e) { bad.push({ n: f.name, m: ERR[(e && e.code)] || ERR.read }); next(); });
      })();
    });
    function list() {
      var docs = S.docs(), body = $('#drows');
      $('#dtable').hidden = !docs.length; $('#dnone').hidden = !!docs.length;
      body.innerHTML = docs.map(function (d) {
        var viewable = d.type === 'PDF' || d.type === 'JPG' || d.type === 'PNG', own = d.source === 'You';
        return '<tr><td data-label="Type">' + d.type + '</td>' +
          '<td data-label="File name" class="tn">' + esc(d.name) + '<small>' + (d.bookingRef ? esc(d.bookingRef) + ' &middot; ' : '') + (own ? 'Uploaded by you' : 'From LuxyBiz') + ' &middot; ' + size(d.size) + '</small></td>' +
          '<td data-label="Uploaded">' + fmt(new Date(d.uploaded)) + '</td>' +
          '<td class="acts-c">' + (viewable ? '<button class="btn sm" type="button" data-view="' + d.id + '" aria-label="View ' + esc(d.name) + '">View</button>' : '') +
          '<button class="btn sm" type="button" data-dl="' + d.id + '" aria-label="Download ' + esc(d.name) + '">Download</button>' +
          (own ? '<button class="btn sm" type="button" data-rep="' + d.id + '" aria-label="Replace ' + esc(d.name) + '">Replace</button><button class="btn sm danger" type="button" data-rm="' + d.id + '" aria-label="Remove ' + esc(d.name) + '">Remove</button>' : '') + '</td></tr>';
      }).join('');
    }
    $('#drows').addEventListener('click', function (e) {
      var dl = e.target.closest('[data-dl]'), vw = e.target.closest('[data-view]'), rp = e.target.closest('[data-rep]'), rm = e.target.closest('[data-rm]');
      var find = function (id) { return S.docs().filter(function (x) { return x.id === id; })[0]; };
      if (dl) { var d = find(dl.dataset.dl); download(d.id, d.name); }
      if (vw) {
        var doc = find(vw.dataset.view), win = window.open('', '_blank');
        S.docBlob(doc.id).then(function (b) { if (!b) { if (win) win.close(); notice('err', 'That file could not be found on this device.'); return; } var u = URL.createObjectURL(b); if (win) win.location = u; else download(doc.id, doc.name); setTimeout(function () { URL.revokeObjectURL(u); }, 60000); });
      }
      if (rp) {
        var target = find(rp.dataset.rep), fi = document.createElement('input'); fi.type = 'file'; fi.accept = '.pdf,.jpg,.jpeg,.png,.docx';
        fi.addEventListener('change', function () {
          if (!fi.files[0]) return; notice('', '');
          S.replaceDoc(target.id, fi.files[0]).then(function () { list(); notice('', '<b>Replaced.</b> ' + esc(fi.files[0].name) + ' is now in your vault.'); })
            .catch(function (er) { notice('err', esc(fi.files[0].name) + ': ' + esc(ERR[er && er.code] || ERR.read)); });
        });
        fi.click();
      }
      if (rm) {
        var gone = find(rm.dataset.rm);
        ask({ title: 'Remove this document?', body: gone.name + ' will be removed from your vault. This cannot be undone.', ok: 'Remove', cancel: 'Keep it', danger: true }).then(function (r) {
          if (r.ok) S.deleteDoc(gone.id).then(function () { list(); notice('', 'Removed ' + esc(gone.name) + '.'); });
        });
      }
    });
    input.addEventListener('change', function () { if (input.files.length) select(input.files); });
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
    drop.addEventListener('drop', function (e) { if (e.dataTransfer.files.length) select(e.dataTransfer.files); });
    step(1); list();
  }

  /* ---------- CANCELLATION AND TRAVEL CREDIT (P16) ---------- */
  function cancel() {
    var ref = qs('ref'), b = ref && S.booking(ref), root = $('#cancel');
    if (!b) { root.innerHTML = '<div class="card empty-in"><b>We could not find that booking</b><a class="btn solid" href="my-bookings.html">Back to My Bookings</a></div>'; return; }
    function draw() {
      b = S.booking(ref);
      var c = b.cancel, can = b.canCancel, st = c ? c.status : '';
      var credits = S.credits();
      var track = [['pending_review', 'Pending review'], ['approved', 'Approved'], ['declined', 'Declined']].map(function (x) {
        return '<li class="' + (st === x[0] ? 'on' : '') + '"' + (st === x[0] ? ' aria-current="true"' : '') + '>' + x[1] + '</li>';
      }).join('') + '<li class="' + (c && c.refund ? 'on' : '') + '">Refund / credit status<small>' + esc(c && c.refund ? c.refund : (c ? 'To be confirmed by staff.' : 'Not applicable yet.')) + '</small></li>';
      root.innerHTML =
        '<a class="crumb" href="booking-details.html?ref=' + encodeURIComponent(b.ref) + '">&larr; Back to booking</a>' +
        '<h1 style="font-size:clamp(1.8rem,3.2vw,2.6rem);margin-bottom:8px">Cancel booking ' + esc(b.ref) + '</h1>' +
        '<p class="when" style="margin-bottom:22px"><span>' + esc(b.tour.name) + '</span><span class="dot" aria-hidden="true"></span><span>' + range(b) + '</span></p>' +
        (!c && !can ? '<div class="banner warn" role="status">' + INFO + '<div>' + (b.bucket === 'cancelled' ? 'This booking has already been cancelled.' : 'This journey has finished, so a cancellation request is no longer possible.') + '</div></div>' : '') +
        '<div class="two" style="grid-template-columns:1fr 1fr">' +
          '<section class="card" aria-labelledby="po-h"><h2 id="po-h">Applicable policy</h2><div class="muted" data-policy><p>[Client-approved policy]</p><p style="margin:0">Replace this block with the approved cancellation policy, refund rules and credit terms.</p></div>' +
            '<h3 style="margin:22px 0 6px">Estimated eligibility (where calculable)</h3><p class="muted" style="margin:0">[Estimate &mdash; subject to staff review]</p></section>' +
          '<section class="card" aria-labelledby="rq-h"><h2 id="rq-h">Request</h2>' +
            (c ? '<p class="muted" style="margin:0 0 10px">Requested ' + fmt(new Date(c.requested)) + '.</p>' + (c.reason ? '<p style="margin:0"><b>Your reason:</b> ' + esc(c.reason) + '</p>' : '<p class="muted" style="margin:0">No reason given.</p>')
              : '<div class="fld"><label for="reason">Reason for cancelling <span class="help">(optional)</span></label><textarea id="reason" maxlength="500" rows="4"' + (can ? '' : ' disabled') + '></textarea></div>' +
                '<button class="btn danger" type="button" id="req" style="margin-top:14px"' + (can ? '' : ' disabled') + '>Submit Cancellation Request</button>') +
          '</section>' +
        '</div>' +
        '<section class="card" style="margin:clamp(16px,2vw,26px) 0" aria-labelledby="ts-h"><h2 id="ts-h">Request status</h2><ol class="track">' + track + '</ol>' +
          (st === 'pending_review' ? '<div class="preview" style="margin-top:18px"><span>Design preview: simulate the staff decision</span><button type="button" data-decide="approved">Approve</button><button type="button" data-decide="declined">Decline</button></div>' : '') + '</section>' +
        '<section class="card tcard" aria-labelledby="cr-h"><div style="padding:clamp(20px,2.4vw,30px) clamp(20px,2.4vw,30px) 8px"><h2 id="cr-h" style="margin:0">Travel Credits &amp; Vouchers</h2></div>' +
          '<table class="dt"><thead><tr><th scope="col">Credit ref</th><th scope="col">Available balance</th><th scope="col">Status</th><th scope="col">Expiry (if any)</th><th scope="col">Terms</th><th scope="col">Redemption history</th></tr></thead><tbody>' +
          (credits.length ? credits.map(function (x) { return '<tr><td data-label="Credit ref" class="rf">' + esc(x.ref) + '</td><td data-label="Available balance">' + esc(x.balance) + '</td><td data-label="Status">' + esc(x.status) + '</td><td data-label="Expiry">' + esc(x.expiry || 'None') + '</td><td data-label="Terms">' + esc(x.terms || '') + '</td><td data-label="Redemption history">' + esc(x.history || 'None yet') + '</td></tr>'; }).join('')
            : '<tr><td colspan="6" class="muted" style="padding:22px 24px">No travel credit or voucher on your account. If staff offer one, it will appear here.</td></tr>') +
          '</tbody></table></section>';
      var req = $('#req');
      if (req) req.addEventListener('click', function () {
        var reason = $('#reason').value;
        ask({ title: 'Submit cancellation request?', body: 'Submitting a request does not guarantee a refund.', ok: 'Submit request', cancel: 'Go back', danger: true }).then(function (r) {
          if (!r.ok) return;
          var res = S.requestCancellation(b.ref, reason);
          if (!res.ok) { notice('err', 'We could not send that request. Please try again.'); return; }
          draw(); shell(); notice('', 'Your cancellation request for <b>' + esc(b.ref) + '</b> has been sent for review.');
        });
      });
      $$('[data-decide]').forEach(function (btn) { btn.addEventListener('click', function () { S.demoDecide(b.ref, btn.dataset.decide); draw(); shell(); }); });
    }
    draw();
  }

  /* ---------- ACCOUNT ---------- */
  function account() {
    function fill() { user = S.current(); $('#a-name').value = user.name; $('#a-mail').value = user.email; $('#a-phone').value = user.phone; shell(); }
    fill();
    function setErr(i, id, m) { $('#' + id).textContent = m || ''; if (m) i.setAttribute('aria-invalid', 'true'); else i.removeAttribute('aria-invalid'); return !m; }
    $$('form input').forEach(function (i) { i.addEventListener('input', function () { i.removeAttribute('aria-invalid'); var e = $('#' + i.id + '-e'); if (e) e.textContent = ''; }); });
    $('#f-prof').addEventListener('submit', function (e) {
      e.preventDefault(); var n = $('#a-name'), p = $('#a-phone');
      var ok = setErr(n, 'a-name-e', !n.value.trim() ? 'Enter your full name.' : '');
      ok = setErr(p, 'a-phone-e', p.value.trim() && !/^[+\d][\d\s\-()]{6,}$/.test(p.value.trim()) ? 'Enter a valid phone number, or leave it blank.' : '') && ok;
      if (!ok) return;
      S.updateProfile({ name: n.value, phone: p.value }); fill(); notice('', 'Your details have been saved.');
    });
    $('#f-pass').addEventListener('submit', function (e) {
      e.preventDefault(); var c = $('#p-cur'), n = $('#p-new'), n2 = $('#p-new2');
      var ok = setErr(c, 'p-cur-e', !c.value ? 'Enter your current password.' : '');
      ok = setErr(n, 'p-new-e', n.value.length < 8 ? 'Use at least 8 characters.' : (n.value === c.value ? 'Choose a password you have not used here before.' : '')) && ok;
      ok = setErr(n2, 'p-new2-e', n2.value !== n.value ? 'The passwords do not match.' : '') && ok;
      if (!ok) return;
      var btn = $('button[type=submit]', e.target); btn.disabled = true;
      S.changePassword(c.value, n.value).then(function (r) {
        btn.disabled = false;
        if (!r.ok) { setErr(c, 'p-cur-e', 'That is not your current password.'); c.focus(); return; }
        e.target.reset(); notice('', 'Your password has been changed.');
      });
    });
    $$('[data-toggle]').forEach(function (b) { b.addEventListener('click', function () { var i = document.getElementById(b.dataset.toggle), s = i.type === 'password'; i.type = s ? 'text' : 'password'; b.textContent = s ? 'Hide' : 'Show'; b.setAttribute('aria-label', s ? 'Hide password' : 'Show password'); }); });
    $('#demo-reset').addEventListener('click', function () {
      ask({ title: 'Reset demo data?', body: 'This clears every account, booking and document stored in this browser and signs you out. The demo account is recreated on the next visit.', ok: 'Reset', cancel: 'Cancel', danger: true }).then(function (r) {
        if (r.ok) { S.resetDemo(); setTimeout(function () { location.href = 'auth.html#login'; }, 300); }
      });
    });
  }

  /* ---------- go ---------- */
  var pages = { dashboard: dashboard, bookings: bookings, detail: detail, documents: documents, cancel: cancel, account: account };
  S.ready.then(function () { var p = pages[document.body.dataset.page]; if (p) p(); shell(); });
})();
