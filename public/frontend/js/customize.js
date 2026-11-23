/* =========================================================
   Customizable package builder.
   - Signed-out visitors are asked to sign up or log in before opening the homepage builder.
   - Choose 1 to 6 days. Each day allows one new destination.
   - A destination ticked on an earlier day appears on every later day already ticked and READ-ONLY.
   - Submitting stores a custom journey request for a planner to quote (LuxyStore.createCustomRequest).
   ========================================================= */
(function () {
  'use strict';
  var S = window.LuxyStore, C = window.LuxyCatalog;
  var $ = function (s, c) { return (c || document).querySelector(s); }, $$ = function (s, c) { return [].slice.call((c || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]; }); };
  var MAX = C.MAX_CUSTOM_DAYS || 6, DEST = C.destinations;
  var LOCK = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/></svg>';
  var currency = 'usd';

  function syncDestinationPrices() {
    var active = document.querySelector('[data-cur][aria-pressed="true"]');
    currency = active && active.dataset.cur ? active.dataset.cur : 'usd';
    $$('[data-usd][data-inr]').forEach(function (el) {
      el.textContent = currency === 'inr' ? el.dataset.inr : el.dataset.usd;
    });
  }

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-cur]')) window.setTimeout(syncDestinationPrices, 0);
  });

  /* ---------- inline homepage builder (available to signed-in members) ---------- */
  var app = $('#cz-app');
  if (!app || !S) return;
  if (!S.current()) {
    if (location.hash === '#customize-builder' && window.LuxyGate) window.LuxyGate.open();
    return;
  }
  var builderShell = $('#customize-builder');
  if (builderShell) builderShell.hidden = false;

  var signInNote = $('.custom-note');
  if (signInNote) signInNote.hidden = true;

  function setBuilderOpen(open, scrollToBuilder) {
    app.hidden = !open;
    var floatingContact = $('#float');
    if (floatingContact) floatingContact.classList.toggle('off', open);
    var trigger = $('#cz-trigger');
    if (trigger) {
      trigger.setAttribute('aria-expanded', String(open));
      trigger.textContent = open ? 'Hide journey builder' : 'Customize Your Journey';
    }
    if (open && scrollToBuilder) {
      window.setTimeout(function () { app.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 40);
    }
  }

  $$('[data-customize]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      event.preventDefault();
      var open = link.id === 'cz-trigger' ? app.hidden : true;
      setBuilderOpen(open, open);
    });
  });

  /* ---------- state: how many days, and which destinations were ticked on each day ---------- */
  var state = { days: 3, picks: [[], [], [], [], [], []], details: {} };      /* picks[0] is Day 1 ... */
  S.clearCustomDraft();
  function save() { S.saveCustomDraft(state); }
  function say(msg) { $('#cz-live').textContent = msg; }
  function name(id) { var d = C.destination(id); return d ? d.name : id; }

  /* destinations already taken by days before this one -> { id: dayNumber } */
  function lockedBefore(dayIdx) {
    var m = {};
    for (var i = 0; i < dayIdx; i++) state.picks[i].forEach(function (id) { if (!(id in m)) m[id] = i + 1; });
    return m;
  }
  /* an earlier day always wins: drop a later pick if an earlier day now holds the same place */
  function reconcile() {
    var moved = [];
    for (var d = 1; d < MAX; d++) {
      var lk = lockedBefore(d);
      state.picks[d] = state.picks[d].filter(function (id) { if (id in lk) { moved.push(name(id) + ' (now on Day ' + lk[id] + ')'); return false; } return true; });
    }
    return moved;
  }

  /* ---------- day-count picker ---------- */
  var dwrap = $('#cz-days');
  dwrap.innerHTML = Array.apply(null, Array(MAX)).map(function (_, i) {
    var n = i + 1;
    return '<label><input type="radio" name="days" value="' + n + '"' + (n === state.days ? ' checked' : '') + '><span>' + n + '<small>' + (n === 1 ? 'day' : 'days') + '</small></span></label>';
  }).join('');
  dwrap.addEventListener('change', function (e) {
    var n = +e.target.value, lost = 0, was = state.days;
    for (var i = n; i < state.days; i++) lost += state.picks[i].length;
    state.picks = state.picks.map(function (p, i) { return i >= n ? [] : p; });
    state.days = n; save(); draw();
    say(n + (n === 1 ? ' day' : ' days') + ' selected.' + (lost ? ' ' + lost + ' destination' + (lost === 1 ? '' : 's') + ' from the removed days were cleared.' : ''));
    if (lost) $('#notice').innerHTML = '<div class="banner warn" role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16h.01"/></svg><div>' + (was - n === 1 ? 'Day ' + was + ' was' : 'Days ' + (n + 1) + ' to ' + was + ' were') + ' removed along with ' + (lost === 1 ? 'its destination' : 'their destinations') + '.</div></div>';
    else $('#notice').innerHTML = '';
  });

  /* ---------- the day cards ---------- */
  function dayHTML(i) {
    var lk = lockedBefore(i), mine = state.picks[i], dayNo = i + 1;
    var chosenNow = mine.length;
    var opts = DEST.map(function (d) {
      var locked = d.id in lk, on = mine.indexOf(d.id) > -1, id = 'd' + dayNo + '-' + d.id;
      if (locked) {
        return '<label class="cz-opt locked" for="' + id + '" title="Chosen on Day ' + lk[d.id] + '. It stays ticked and cannot be changed here.">' +
          '<input type="checkbox" id="' + id + '" checked disabled aria-describedby="' + id + '-n"><span><b>' + esc(d.name) + '</b><span class="rg">' + esc(d.region) + '</span><small>' + esc(d.blurb) + '</small>' +
          '<span class="lk" id="' + id + '-n">' + LOCK + 'Chosen on Day ' + lk[d.id] + ' (read-only)</span></span></label>';
      }
      return '<label class="cz-opt' + (on ? ' on' : '') + '" for="' + id + '"><input type="checkbox" id="' + id + '" data-day="' + i + '" value="' + d.id + '"' + (on ? ' checked' : '') + '>' +
        '<span><b>' + esc(d.name) + '</b><span class="rg">' + esc(d.region) + '</span><small>' + esc(d.blurb) + '</small></span></label>';
    }).join('');
    var carried = Object.keys(lk).length;
    return '<fieldset class="cz-day" id="day-' + dayNo + '"><legend><div class="cz-dh"><h2>Day ' + dayNo + '</h2><span class="cnt' + (chosenNow ? '' : ' zero') + '">' + (chosenNow ? chosenNow + ' chosen' : 'Choose at least one') + '</span></div></legend>' +
      '<p class="cz-hint">' + (dayNo === 1 ? 'Where would you like to go on your first day? Choose one place.'
        : carried ? 'Places you chose on earlier days stay ticked and locked. Choose one new place for this day.' : 'Choose one place for this day.') + '</p>' +
      '<div class="cz-opts">' + opts + '</div>' +
      '</fieldset>';
  }
  function draw() {
    $('#cz-daylist').innerHTML = Array.apply(null, Array(state.days)).map(function (_, i) { return dayHTML(i); }).join('');
    summary();
  }
  $('#cz-daylist').addEventListener('change', function (e) {
    var cb = e.target; if (!cb.matches('input[type=checkbox][data-day]')) return;
    var i = +cb.dataset.day, id = cb.value, list = state.picks[i], replaced = cb.checked && list.length && list[0] !== id ? list[0] : null;
    if (cb.checked) state.picks[i] = [id];
    else state.picks[i] = [];
    var moved = reconcile(); save();
    var focusId = cb.id; draw(); var again = document.getElementById(focusId); if (again) again.focus();
    say(name(id) + (cb.checked ? (replaced ? ' replaced ' + name(replaced) : ' selected') + ' for Day ' + (i + 1) + '.' : ' removed from Day ' + (i + 1) + '.'));
    $('#notice').innerHTML = moved.length ? '<div class="banner warn" role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16h.01"/></svg><div>Moved to the earlier day: ' + esc(moved.join(', ')) + '. A place can only be chosen once, on the first day you pick it.</div></div>' : '';
    if (cb.checked) openDestinationDialog(id);
  });

  /* ---------- destination places and hotel popup ---------- */
  function openDestinationDialog(destinationId) {
    var destination = C.destination(destinationId);
    if (!destination) return;
    syncDestinationPrices();
    var saved = state.details[destinationId] || { places: [], hotel: '' };
    var dlg = document.getElementById('dest-dialog');
    if (!dlg) {
      dlg = document.createElement('dialog');
      dlg.id = 'dest-dialog';
      dlg.className = 'dest-dialog';
      document.body.appendChild(dlg);
    }
    dlg.innerHTML =
      '<div class="dest-dialog-in">' +
        '<div class="dest-dialog-head"><h2 id="dest-dialog-title">' + esc(destination.name) + '</h2><button type="button" class="dest-dialog-close" aria-label="Close">&times;</button></div>' +
        '<p class="help">' + esc(destination.blurb) + '</p>' +
        '<h3>Places to visit</h3><div class="dest-place-grid">' +
        destination.places.map(function (place, index) {
          var checked = saved.places.indexOf(place[0]) > -1;
          var id = 'dest-place-' + destinationId + '-' + index;
          return '<label class="dest-place" for="' + id + '"><input type="checkbox" id="' + id + '" value="' + esc(place[0]) + '" data-usd-price="' + Number(place[2].replace(/[^0-9.]/g, '')) + '" data-inr-price="' + Number(place[1].replace(/[^0-9.]/g, '')) + '"' + (checked ? ' checked' : '') + '><b>' + esc(place[0]) + '</b><span data-usd="' + esc(place[2]) + '" data-inr="' + esc(place[1]) + '">' + esc(currency === 'inr' ? place[1] : place[2]) + '</span></label>';
        }).join('') +
        '</div><h3>Select a hotel</h3><div class="dest-hotels">' +
        destination.hotels.map(function (hotel, index) {
          var checked = saved.hotel === hotel[0];
          var id = 'dest-hotel-' + destinationId + '-' + index;
          return '<label class="dest-hotel" for="' + id + '"><input type="radio" name="dest-hotel" id="' + id + '" value="' + esc(hotel[0]) + '" data-usd-price="' + Number(hotel[2].replace(/[^0-9.]/g, '')) + '" data-inr-price="' + Number(hotel[1].replace(/[^0-9.]/g, '')) + '"' + (checked ? ' checked' : '') + '><b>' + esc(hotel[0]) + '</b><span data-usd="' + esc(hotel[2]) + '" data-inr="' + esc(hotel[1]) + '">' + esc(currency === 'inr' ? hotel[1] : hotel[2]) + '</span></label>';
        }).join('') +
        '</div><div class="dest-total"><span>Selected total</span><b id="dest-total">₹0</b></div><div class="dest-dialog-actions"><button type="button" class="btn solid dest-dialog-save">Save selection</button></div>' +
      '</div>';
    function updateTotal() {
      syncDestinationPrices();
      var total = 0;
      dlg.querySelectorAll('.dest-place input:checked,.dest-hotel input:checked').forEach(function (input) {
        total += +(currency === 'inr' ? input.dataset.inrPrice : input.dataset.usdPrice) || 0;
      });
      $('#dest-total').textContent = currency === 'inr' ? '₹' + total.toLocaleString('en-IN') : '$' + total.toLocaleString('en-US');
    }
    dlg.addEventListener('change', updateTotal);
    updateTotal();
    $('.dest-dialog-close', dlg).addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
    $('.dest-dialog-save', dlg).addEventListener('click', function () {
      state.details[destinationId] = {
        places: Array.apply(null, dlg.querySelectorAll('.dest-place input:checked')).map(function (x) { return x.value; }),
        hotel: (dlg.querySelector('.dest-hotel input:checked') || {}).value || ''
      };
      save();
      dlg.close();
      say(destination.name + ' places and hotel selection saved.');
    });
    if (dlg.showModal) dlg.showModal();
    else dlg.setAttribute('open', '');
  }

  /* ---------- summary panel + steps ---------- */
  function complete() { for (var i = 0; i < state.days; i++) if (!state.picks[i].length) return false; return true; }
  function summary() {
    var total = 0, filled = 0;
    $('#cz-sum').innerHTML = Array.apply(null, Array(state.days)).map(function (_, i) {
      var p = state.picks[i]; total += p.length; if (p.length) filled++;
      return p.length ? '<li><b>Day ' + (i + 1) + '</b>' + p.map(function (id) { return '<span>' + esc(name(id)) + '</span>'; }).join('') + '</li>'
                      : '<li class="empty"><b>Day ' + (i + 1) + '</b>Nothing chosen yet</li>';
    }).join('');
    $('#sm-count').textContent = total ? total + (total === 1 ? ' destination' : ' destinations') + ' over ' + state.days + (state.days === 1 ? ' day' : ' days') + '.' : 'No destinations chosen yet.';
    var ok = complete();
    $('#cz-next').disabled = !ok;
    $('#cz-next').textContent = ok ? 'Continue to Payment' : 'Choose a place for each day';
    $('#st-1').className = 'done'; $('#st-2').className = ok ? 'done' : 'on'; $('#st-3').className = ok ? 'on' : '';
    $('#cz-details').hidden = !ok;
  }
  $('#cz-next').addEventListener('click', function () { $('#cz-details').scrollIntoView({ behavior: 'smooth', block: 'start' }); var f = $('#z-trav'); if (f) setTimeout(function () { f.focus({ preventScroll: true }); }, 400); });
  $('#cz-reset').addEventListener('click', function () {
    if (!confirm('Clear your route and start again?')) return;
    state.picks = [[], [], [], [], [], []]; state.details = {}; S.clearCustomDraft(); $('#notice').innerHTML = ''; draw(); say('Route cleared.');
  });

  /* ---------- details form ---------- */
  var trav = $('#z-trav'), month = $('#z-month');
  trav.innerHTML = [1, 2, 3, 4, 5, 6, 7, 8].map(function (n) { return '<option value="' + n + '"' + (n === 2 ? ' selected' : '') + '>' + n + (n === 1 ? ' traveller' : ' travellers') + '</option>'; }).join('');
  var now = new Date(); month.innerHTML = Array.apply(null, Array(12)).map(function (_, i) {
    var d = new Date(now.getFullYear(), now.getMonth() + 1 + i, 1);
    return '<option>' + d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }) + '</option>';
  }).join('') + '<option>I am flexible</option>';
  var u = S.current(); if (u.phone) $('#z-phone').value = u.phone;

  $('#cz-form').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!complete()) { $('#notice').innerHTML = '<div class="banner err" role="alert"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16h.01"/></svg><div>Choose at least one place for every day first.</div></div>'; return; }
    var ph = $('#z-phone'), bad = ph.value.trim() && !/^[+\d][\d\s\-()]{6,}$/.test(ph.value.trim());
    $('#z-phone-e').textContent = bad ? 'Enter a valid phone number, or leave it blank.' : ''; if (bad) { ph.setAttribute('aria-invalid', 'true'); ph.focus(); return; } ph.removeAttribute('aria-invalid');
    var plan = []; for (var i = 0; i < state.days; i++) plan.push({ day: i + 1, destinations: state.picks[i].slice() });
    var r = S.createCustomRequest({ days: state.days, plan: plan, details: state.details, travellers: +trav.value, month: month.value, style: $('#z-style').value, phone: ph.value.trim(), notes: $('#z-notes').value.trim() });
    if (!r) return;
    S.clearCustomDraft(); state.picks = [[], [], [], [], [], []]; state.details = {}; $('#z-notes').value = '';
    draw(); requests();
    $('#notice').innerHTML = '<div class="banner" role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><div><b>Request ' + esc(r.ref) + ' sent.</b> A planner will review your route and send you a quote. You will see an update in your notifications.</div></div>';
    $('#notice').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  /* ---------- previous requests ---------- */
  function requests() {
    var list = S.customRequests(); $('#cz-requests').hidden = !list.length;
    $('#cz-reqlist').innerHTML = list.map(function (r) {
      var tone = r.status === 'withdrawn' ? 'bad' : 'warn', label = r.status === 'withdrawn' ? 'Withdrawn' : 'Quote requested';
      return '<li><div class="rt"><b>' + esc(r.ref) + '</b><span class="st ' + tone + '">' + label + '</span><span class="help">' + new Date(r.created).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) + ' &middot; ' + r.days + (r.days === 1 ? ' day' : ' days') + ' &middot; ' + r.travellers + (r.travellers === 1 ? ' traveller' : ' travellers') + ' &middot; ' + esc(r.month) + '</span></div>' +
        r.plan.map(function (d) {
          var route = d.destinations.map(function (id) {
            var details = r.details && r.details[id];
            var extra = details ? ((details.places && details.places.length ? ' - Visits: ' + details.places.map(esc).join(', ') : '') + (details.hotel ? ' - Stay: ' + esc(details.hotel) : '')) : '';
            return esc(name(id)) + extra;
          }).join(', ');
          return '<p><b>Day ' + d.day + ':</b> ' + route + '</p>';
        }).join('') +
        '<div class="acts"><button class="btn sm" type="button" data-reuse="' + esc(r.ref) + '">Use as a starting point</button>' + (r.status === 'quote_requested' ? '<button class="btn sm danger" type="button" data-withdraw="' + esc(r.ref) + '">Withdraw</button>' : '') + '</div></li>';
    }).join('');
  }
  $('#cz-reqlist').addEventListener('click', function (e) {
    var w = e.target.closest('[data-withdraw]'), r = e.target.closest('[data-reuse]');
    if (w && confirm('Withdraw request ' + w.dataset.withdraw + '?')) { S.withdrawCustomRequest(w.dataset.withdraw); requests(); }
    if (r) {
      var req = S.customRequests().filter(function (x) { return x.ref === r.dataset.reuse; })[0]; if (!req) return;
      state.days = req.days; state.picks = [[], [], [], [], [], []]; req.plan.forEach(function (d) { state.picks[d.day - 1] = d.destinations.slice(0, 1); });
      state.details = req.details || {};
      reconcile(); save(); $$('input[name=days]').forEach(function (x) { x.checked = +x.value === state.days; }); draw();
      $('#cz-daylist').scrollIntoView({ behavior: 'smooth' }); say('Route loaded from ' + req.ref + '.');
    }
  });

  reconcile(); draw(); requests();
  if (location.hash === '#customize-builder') setBuilderOpen(true, true);
})();
