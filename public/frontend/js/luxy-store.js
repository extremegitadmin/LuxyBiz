/* =========================================================
   LuxyStore: the data layer for the customer portal.

   WHAT IT IS: a working stand-in for a backend. Accounts, sessions, bookings,
   notifications and uploaded documents are kept in this browser (localStorage for
   records, IndexedDB for uploaded files). Every page talks only to this object, so
   when a real server exists you replace the bodies of these functions with fetch()
   calls and the pages keep working unchanged.

   WHAT IT IS NOT: secure. Anyone with access to this browser can read this data.
   Do not use it to protect real customer information. It exists so the whole
   portal can be built, reviewed and used end to end before the API is ready.

   Demo account created on first load:  demo@luxybiz.example  /  Demo@1234
   ========================================================= */
(function (w) {
  'use strict';

  /* ---------- storage ---------- */
  var mem = {};
  var ls = (function () { try { var t = '__luxy'; localStorage.setItem(t, '1'); localStorage.removeItem(t); return localStorage; } catch (e) { return null; } })();
  function get(k) { try { return ls ? ls.getItem(k) : (k in mem ? mem[k] : null); } catch (e) { return null; } }
  function set(k, v) { try { if (ls) ls.setItem(k, v); else mem[k] = v; } catch (e) { mem[k] = v; } }
  function del(k) { try { if (ls) ls.removeItem(k); else delete mem[k]; } catch (e) { delete mem[k]; } }
  function read(k, d) { var v = get(k); if (!v) return d; try { return JSON.parse(v); } catch (e) { return d; } }
  function write(k, v) { set(k, JSON.stringify(v)); }
  var K = { users: 'luxy.users', session: 'luxy.session', bookings: 'luxy.bookings', notes: 'luxy.notes', docs: 'luxy.docs', reset: 'luxy.reset', seed: 'luxy.seed.v2', seq: 'luxy.refseq' };

  /* ---------- dates ---------- */
  var DAY = 864e5;
  function midnight(d) { d = d ? new Date(d) : new Date(); d.setHours(0, 0, 0, 0); return d; }
  function iso(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function parse(s) { var p = String(s).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function addDays(d, n) { return new Date(+midnight(d) + n * DAY); }
  var uid = function (p) { return (p || 'x') + Math.random().toString(36).slice(2, 9) + Date.now().toString(36).slice(-4); };
  function delay(v, ms) { return new Promise(function (r) { setTimeout(function () { r(v); }, ms == null ? 220 : ms); }); }

  /* ---------- tour catalogue (luxy-catalog.js must be loaded before this file) ---------- */
  var CAT = w.LuxyCatalog || { tours: {} };
  var TOURS = {};
  Object.keys(CAT.tours).forEach(function (k) {
    var t = CAT.tours[k];
    TOURS[k] = { slug: t.slug, name: t.name, nights: t.nights, usd: t.usd, inr: t.inr, region: t.region, image: t.card, days: t.days };
  });

  /* ---------- password hashing (demo-grade; a real backend does this server-side) ---------- */
  function enc(s) { return new TextEncoder().encode(s); }
  function hex(buf) { return Array.prototype.map.call(new Uint8Array(buf), function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''); }
  function weak(s) { var h1 = 0x811c9dc5, h2 = 5381; for (var i = 0; i < s.length; i++) { h1 ^= s.charCodeAt(i); h1 = Math.imul(h1, 16777619); h2 = ((h2 << 5) + h2) ^ s.charCodeAt(i); } return (h1 >>> 0).toString(16) + (h2 >>> 0).toString(16); }
  function hash(pw, salt) {
    if (w.crypto && w.crypto.subtle && w.TextEncoder) {
      return w.crypto.subtle.importKey('raw', enc(pw), 'PBKDF2', false, ['deriveBits'])
        .then(function (k) { return w.crypto.subtle.deriveBits({ name: 'PBKDF2', salt: enc(salt), iterations: 60000, hash: 'SHA-256' }, k, 256); })
        .then(hex).catch(function () { return weak(pw + salt); });
    }
    return Promise.resolve(weak(pw + salt));
  }

  /* ---------- uploaded files (IndexedDB, with a memory fallback) ---------- */
  var blobMem = {}, dbp = null;
  function db() {
    if (dbp) return dbp;
    dbp = new Promise(function (res, rej) {
      if (!w.indexedDB) { rej(new Error('no idb')); return; }
      var r = indexedDB.open('luxy-portal', 1);
      r.onupgradeneeded = function () { r.result.createObjectStore('blobs'); };
      r.onsuccess = function () { res(r.result); };
      r.onerror = function () { rej(r.error); };
    });
    return dbp;
  }
  function putBlob(id, blob) {
    return db().then(function (d) { return new Promise(function (res, rej) { var t = d.transaction('blobs', 'readwrite'); t.objectStore('blobs').put(blob, id); t.oncomplete = res; t.onerror = function () { rej(t.error); }; }); })
      .catch(function () { blobMem[id] = blob; });
  }
  function getBlob(id) {
    if (blobMem[id]) return Promise.resolve(blobMem[id]);
    return db().then(function (d) { return new Promise(function (res, rej) { var r = d.transaction('blobs').objectStore('blobs').get(id); r.onsuccess = function () { res(r.result || null); }; r.onerror = function () { rej(r.error); }; }); }).catch(function () { return null; });
  }
  function delBlob(id) {
    delete blobMem[id];
    return db().then(function (d) { return new Promise(function (res) { var t = d.transaction('blobs', 'readwrite'); t.objectStore('blobs').delete(id); t.oncomplete = res; t.onerror = res; }); }).catch(function () { });
  }
  function makePdf(title, lines) {
    function esc(s) { return String(s).replace(/[\\()]/g, '\\$&'); }
    var c = 'BT /F1 22 Tf 60 780 Td (' + esc(title) + ') Tj ET\n', y = 745;
    lines.forEach(function (l) { c += 'BT /F1 12 Tf 60 ' + y + ' Td (' + esc(l) + ') Tj ET\n'; y -= 22; });
    var objs = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
      '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 5 0 R /Resources << /Font << /F1 4 0 R >> >> >>',
      '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Length ' + c.length + ' >>\nstream\n' + c + 'endstream'];
    var out = '%PDF-1.4\n', off = [];
    objs.forEach(function (o, i) { off.push(out.length); out += (i + 1) + ' 0 obj\n' + o + '\nendobj\n'; });
    var x = out.length;
    out += 'xref\n0 ' + (objs.length + 1) + '\n0000000000 65535 f \n';
    off.forEach(function (o) { out += ('0000000000' + o).slice(-10) + ' 00000 n \n'; });
    out += 'trailer\n<< /Size ' + (objs.length + 1) + ' /Root 1 0 R >>\nstartxref\n' + x + '\n%%EOF';
    return new Blob([out], { type: 'application/pdf' });
  }

  /* ---------- session ---------- */
  function users() { return read(K.users, []); }
  function current() {
    var s = read(K.session, null); if (!s) return null;
    var u = users().filter(function (x) { return x.id === s.userId; })[0];
    return u ? pub(u) : null;
  }
  function pub(u) { return { id: u.id, name: u.name, email: u.email, phone: u.phone || '', created: u.created }; }
  function uidNow() { var u = current(); return u ? u.id : null; }

  /* ---------- derived booking view ---------- */
  function view(b) {
    var t = TOURS[b.tour] || { name: b.tour, nights: 0, days: [], usd: 0, inr: 0, image: '' };
    var start = parse(b.start), end = new Date(+start + t.nights * DAY), today = midnight();
    var past = +end < +today, label, tone, c = b.cancel;
    if (c && c.status === 'pending_review') { label = 'Cancellation pending review'; tone = 'warn'; }
    else if (c && c.status === 'approved') { label = 'Cancelled'; tone = 'bad'; }
    else if (b.status === 'cancelled') { label = 'Cancelled'; tone = 'bad'; }
    else if (b.status === 'pending') { label = 'Pending confirmation'; tone = 'warn'; }
    else if (past) { label = 'Completed'; tone = 'ok'; }
    else { label = 'Confirmed'; tone = 'ok'; }
    var cancelled = b.status === 'cancelled' || (c && c.status === 'approved');
    return {
      ref: b.ref, raw: b, tour: t, slug: b.tour, start: start, end: end, startISO: b.start, travelers: b.travelers, info: b.info || '', contact: b.contact || null, created: b.created,
      label: label, tone: tone, payLabel: b.payment === 'paid' ? 'Paid' : 'Payment pending', payTone: b.payment === 'paid' ? 'ok' : 'warn',
      cancel: b.cancel || null, past: past, totalUSD: t.usd * b.travelers, totalINR: t.inr * b.travelers,
      bucket: cancelled ? 'cancelled' : (past ? 'past' : 'upcoming'),
      canCancel: !past && !cancelled && !c
    };
  }

  /* ---------- seed (first load only) ---------- */
  function seed() {
    if (get(K.seed)) return Promise.resolve();
    var today = midnight(), id = 'u-demo';
    return hash('Demo@1234', 'demo-salt').then(function (ph) {
      write(K.users, [{ id: id, name: 'Meera Kurian', email: 'demo@luxybiz.example', phone: '+91 98470 00000', salt: 'demo-salt', pass: ph, created: new Date().toISOString() }]);
      write(K.bookings, [
        { ref: 'LB-1042', userId: id, tour: 'munnar-mist-tea-trails', start: iso(addDays(today, 30)), travelers: 2, status: 'confirmed', payment: 'paid', created: addDays(today, -25).toISOString(), cancel: null,
          info: 'Pick-up: Kochi airport arrivals, 10:00. Your driver sends their name and number 48 hours before you travel.' },
        { ref: 'LB-0968', userId: id, tour: 'alleppey-private-houseboat-escape', start: iso(addDays(today, -120)), travelers: 2, status: 'confirmed', payment: 'paid', created: addDays(today, -160).toISOString(), cancel: null,
          info: 'Houseboat boarding at 12:00, Alleppey jetty.' }
      ]);
      write(K.seq, 1042);
      write(K.notes, [
        { id: uid('n'), userId: id, text: 'Your day-by-day itinerary is ready to read.', at: new Date().toISOString(), read: false },
        { id: uid('n'), userId: id, text: 'Payment received. Your receipt is in Documents.', at: addDays(today, -2).toISOString(), read: false },
        { id: uid('n'), userId: id, text: 'Your planner added airport pickup details.', at: addDays(today, -5).toISOString(), read: true }
      ]);
      write(K.docs, []);
      var defs = [
        ['Booking confirmation LB-1042.pdf', 'LB-1042', 'Booking confirmation', ['Booking LB-1042', 'Munnar Mist & Tea Trails', 'Travellers: 2', 'Status: Confirmed', 'Demo document for design review.']],
        ['Payment receipt LB-1042.pdf', 'LB-1042', 'Payment receipt', ['Receipt for booking LB-1042', 'Total: USD 2,840 / INR 2,50,000', 'Status: Paid', 'Demo document for design review.']],
        ['Day-by-day itinerary LB-1042.pdf', 'LB-1042', 'Itinerary', ['Itinerary for booking LB-1042', 'Six days in the Munnar tea country.', 'Demo document for design review.']],
        ['Invoice LB-0968.pdf', 'LB-0968', 'Invoice', ['Invoice for booking LB-0968', 'Alleppey Private Houseboat Escape', 'Demo document for design review.']]
      ];
      var docs = [];
      return Promise.all(defs.map(function (d, i) {
        var did = 'd-seed' + i, blob = makePdf(d[2], d[3]);
        docs.push({ id: did, userId: id, name: d[0], type: 'PDF', size: blob.size, uploaded: addDays(today, i < 3 ? -3 - i : -118).toISOString(), bookingRef: d[1], source: 'LuxyBiz' });
        return putBlob(did, blob);
      })).then(function () { write(K.docs, docs); set(K.seed, '1'); });
    });
  }
  var ready = seed();

  /* ---------- the API ---------- */
  var S = {
    ready: ready, tours: TOURS, catalog: CAT, midnight: midnight, parse: parse, iso: iso, addDays: addDays,
    /* auth */
    current: current,
    login: function (email, pw) {
      email = String(email || '').trim().toLowerCase();
      return ready.then(function () {
        var u = users().filter(function (x) { return x.email === email; })[0];
        if (!u) return hash(pw, 'x').then(function () { return delay({ ok: false }); });   /* same timing whether or not the account exists */
        return hash(pw, u.salt).then(function (h) {
          if (h !== u.pass) return delay({ ok: false });
          write(K.session, { userId: u.id, at: Date.now() });
          return delay({ ok: true, user: pub(u) });
        });
      });
    },
    register: function (d) {
      var email = String(d.email || '').trim().toLowerCase();
      return ready.then(function () {
        if (users().some(function (x) { return x.email === email; })) return delay({ ok: false, code: 'exists' });
        var salt = uid('s');
        return hash(d.password, salt).then(function (h) {
          var u = { id: uid('u'), name: String(d.name).trim(), email: email, phone: String(d.phone || '').trim(), salt: salt, pass: h, created: new Date().toISOString() };
          var all = users(); all.push(u); write(K.users, all); write(K.session, { userId: u.id, at: Date.now() });
          var n = read(K.notes, []); n.push({ id: uid('n'), userId: u.id, text: 'Welcome to LuxyBiz. Your account is ready.', at: new Date().toISOString(), read: false }); write(K.notes, n);
          return delay({ ok: true, user: pub(u) });
        });
      });
    },
    logout: function () { del(K.session); },
    updateProfile: function (d) {
      var all = users(), u = all.filter(function (x) { return x.id === uidNow(); })[0]; if (!u) return { ok: false };
      u.name = String(d.name).trim(); u.phone = String(d.phone || '').trim(); write(K.users, all); return { ok: true, user: pub(u) };
    },
    changePassword: function (cur, next) {
      var all = users(), u = all.filter(function (x) { return x.id === uidNow(); })[0]; if (!u) return Promise.resolve({ ok: false });
      return hash(cur, u.salt).then(function (h) {
        if (h !== u.pass) return delay({ ok: false, code: 'wrong' });
        var salt = uid('s');
        return hash(next, salt).then(function (nh) { u.salt = salt; u.pass = nh; write(K.users, all); return delay({ ok: true }); });
      });
    },
    requestReset: function (email) {
      email = String(email || '').trim().toLowerCase();
      var u = users().filter(function (x) { return x.email === email; })[0];
      if (u) write(K.reset, { email: email, token: uid('r'), exp: Date.now() + 3600e3 }); else del(K.reset);
      return delay({ ok: true });       /* always the same answer, so the page never reveals whether an account exists */
    },
    /* DEMO ONLY: a real backend emails the link. Here the page can offer it directly. */
    peekReset: function (email) { var r = read(K.reset, null); return r && r.email === String(email).trim().toLowerCase() && r.exp > Date.now() ? r.token : null; },
    resetPassword: function (token, pw) {
      var r = read(K.reset, null); if (!r || r.token !== token || r.exp < Date.now()) return Promise.resolve({ ok: false });
      var all = users(), u = all.filter(function (x) { return x.email === r.email; })[0]; if (!u) return Promise.resolve({ ok: false });
      var salt = uid('s');
      return hash(pw, salt).then(function (h) { u.salt = salt; u.pass = h; write(K.users, all); del(K.reset); return delay({ ok: true }); });
    },
    /* bookings */
    bookings: function () {
      return read(K.bookings, []).filter(function (b) { return b.userId === uidNow(); }).map(view)
        .sort(function (a, b) { return +b.start - +a.start; });
    },
    booking: function (ref) { return S.bookings().filter(function (b) { return b.ref === ref; })[0] || null; },
    nextJourney: function () {
      return S.bookings().filter(function (b) { return b.bucket === 'upcoming'; }).sort(function (a, b) { return +a.start - +b.start; })[0] || null;
    },
    createBooking: function (slug, dateISO, travelers, contact) {
      var t = TOURS[slug]; if (!t || !uidNow()) return null;
      var seq = (read(K.seq, 1042) || 1042) + 1; write(K.seq, seq);
      var all = read(K.bookings, []);
      var b = { ref: 'LB-' + seq, userId: uidNow(), tour: slug, start: dateISO, travelers: travelers || 2, status: 'pending', payment: 'pending', created: new Date().toISOString(), cancel: null,
        info: 'Your planner will confirm pick-up and travel details once your booking is confirmed.', contact: contact || null };
      all.push(b); write(K.bookings, all);
      S.addNote('Booking request ' + b.ref + ' received for ' + t.name + '. Your planner will confirm it shortly.');
      return view(b);
    },
    requestCancellation: function (ref, reason) {
      var all = read(K.bookings, []), b = all.filter(function (x) { return x.ref === ref && x.userId === uidNow(); })[0];
      if (!b || !view(b).canCancel) return { ok: false };
      b.cancel = { status: 'pending_review', requested: new Date().toISOString(), reason: String(reason || '').trim() };
      write(K.bookings, all);
      S.addNote('Cancellation request for ' + ref + ' sent. Our team will review it and get in touch. This is not an automatic refund.');
      return { ok: true };
    },
    /* Travel credits / vouchers are issued by staff in the admin (not built yet).
       Each credit: { ref, balance, status, expiry, terms, history } */
    credits: function () { return read('luxy.credits', []).filter(function (c) { return c.userId === uidNow(); }); },
    /* DEMO ONLY: stands in for the staff decision the admin screen (P23) will make. */
    demoDecide: function (ref, decision) {
      var all = read(K.bookings, []), b = all.filter(function (x) { return x.ref === ref && x.userId === uidNow(); })[0];
      if (!b || !b.cancel) return { ok: false };
      b.cancel.status = decision === 'approved' ? 'approved' : 'declined';
      b.cancel.refund = decision === 'approved' ? 'Refund approved. Your planner will confirm the amount and timing.' : 'Refund declined. Your booking stays as it was.';
      write(K.bookings, all);
      S.addNote(decision === 'approved' ? 'Your cancellation for ' + ref + ' was approved.' : 'Your cancellation request for ' + ref + ' was declined.');
      return { ok: true };
    },
    /* notifications */
    notes: function () { return read(K.notes, []).filter(function (n) { return n.userId === uidNow(); }).sort(function (a, b) { return b.at < a.at ? -1 : 1; }); },
    addNote: function (text) { var n = read(K.notes, []); n.push({ id: uid('n'), userId: uidNow(), text: text, at: new Date().toISOString(), read: false }); write(K.notes, n); },
    markRead: function (id) { var n = read(K.notes, []); n.forEach(function (x) { if (x.id === id && x.userId === uidNow()) x.read = true; }); write(K.notes, n); },
    markAllRead: function () { var n = read(K.notes, []); n.forEach(function (x) { if (x.userId === uidNow()) x.read = true; }); write(K.notes, n); },
    /* documents */
    docs: function () { return read(K.docs, []).filter(function (d) { return d.userId === uidNow(); }).sort(function (a, b) { return b.uploaded < a.uploaded ? -1 : 1; }); },
    MAX_BYTES: 5 * 1024 * 1024,
    ALLOWED: { pdf: 'PDF', jpg: 'JPG', jpeg: 'JPG', png: 'PNG', docx: 'DOCX' },
    addDoc: function (file, opts) {
      opts = opts || {};
      var ext = (file.name.split('.').pop() || '').toLowerCase();
      if (!S.ALLOWED[ext]) return Promise.reject({ code: 'type' });
      if (file.size > S.MAX_BYTES) return Promise.reject({ code: 'size' });
      if (!file.size) return Promise.reject({ code: 'empty' });
      return new Promise(function (res, rej) {
        var r = new FileReader();
        r.onprogress = function (e) { if (e.lengthComputable && opts.onProgress) opts.onProgress(e.loaded / e.total); };
        r.onerror = function () { rej({ code: 'read' }); };
        r.onload = function () { if (opts.onProgress) opts.onProgress(1); res(new Blob([r.result], { type: file.type || 'application/octet-stream' })); };
        r.readAsArrayBuffer(file);
      }).then(function (blob) {
        var id = uid('d');
        return putBlob(id, blob).then(function () {
          var all = read(K.docs, []);
          var meta = { id: id, userId: uidNow(), name: file.name, type: S.ALLOWED[ext], size: file.size, uploaded: new Date().toISOString(), bookingRef: opts.bookingRef || '', source: 'You' };
          all.push(meta); write(K.docs, all);
          S.addNote('Document uploaded: ' + file.name);
          return meta;
        });
      });
    },
    docBlob: function (id) {
      var d = S.docs().filter(function (x) { return x.id === id; })[0]; if (!d) return Promise.resolve(null);
      return getBlob(id);
    },
    replaceDoc: function (id, file, opts) {
      var all = read(K.docs, []), d = all.filter(function (x) { return x.id === id && x.userId === uidNow(); })[0];
      if (!d || d.source !== 'You') return Promise.reject({ code: 'perm' });
      var ext = (file.name.split('.').pop() || '').toLowerCase();
      if (!S.ALLOWED[ext]) return Promise.reject({ code: 'type' });
      if (file.size > S.MAX_BYTES) return Promise.reject({ code: 'size' });
      if (!file.size) return Promise.reject({ code: 'empty' });
      return new Promise(function (res, rej) {
        var r = new FileReader();
        r.onprogress = function (e) { if (e.lengthComputable && opts && opts.onProgress) opts.onProgress(e.loaded / e.total); };
        r.onerror = function () { rej({ code: 'read' }); };
        r.onload = function () { res(new Blob([r.result], { type: file.type || 'application/octet-stream' })); };
        r.readAsArrayBuffer(file);
      }).then(function (blob) {
        return putBlob(id, blob).then(function () {
          d.name = file.name; d.type = S.ALLOWED[ext]; d.size = file.size; d.uploaded = new Date().toISOString();
          write(K.docs, all); S.addNote('Document replaced: ' + file.name); return d;
        });
      });
    },
    deleteDoc: function (id) {
      var all = read(K.docs, []), d = all.filter(function (x) { return x.id === id && x.userId === uidNow(); })[0];
      if (!d || d.source !== 'You') return Promise.resolve({ ok: false });
      write(K.docs, all.filter(function (x) { return x.id !== id; }));
      return delBlob(id).then(function () { return { ok: true }; });
    },
    /* ---------- customizable package requests (homepage builder) ---------- */
    customRequests: function () { return read('luxy.customReq', []).filter(function (r) { return r.userId === uidNow(); }).sort(function (a, b) { return b.created < a.created ? -1 : 1; }); },
    createCustomRequest: function (d) {
      if (!uidNow()) return null;
      var seq = (read('luxy.customSeq', 1000) || 1000) + 1; write('luxy.customSeq', seq);
      var all = read('luxy.customReq', []);
      var r = { ref: 'CP-' + seq, userId: uidNow(), days: d.days, plan: d.plan, details: d.details || {}, travellers: d.travellers, month: d.month, style: d.style, phone: d.phone || '', notes: d.notes || '', status: 'quote_requested', created: new Date().toISOString() };
      all.push(r); write('luxy.customReq', all);
      S.addNote('Custom journey request ' + r.ref + ' received. A planner will review your route and send a quote.');
      return r;
    },
    withdrawCustomRequest: function (ref) {
      var all = read('luxy.customReq', []), r = all.filter(function (x) { return x.ref === ref && x.userId === uidNow(); })[0];
      if (!r || r.status !== 'quote_requested') return { ok: false };
      r.status = 'withdrawn'; write('luxy.customReq', all); return { ok: true };
    },
    customDraft: function () { return read('luxy.customDraft.' + uidNow(), null); },
    saveCustomDraft: function (d) { if (uidNow()) write('luxy.customDraft.' + uidNow(), d); },
    clearCustomDraft: function () { del('luxy.customDraft.' + uidNow()); },
    /* demo utilities */
    resetDemo: function () {
      [K.users, K.session, K.bookings, K.notes, K.docs, K.reset, K.seed, K.seq, 'luxy.credits', 'luxy.customReq', 'luxy.customSeq'].forEach(del);
      try { indexedDB.deleteDatabase('luxy-portal'); } catch (e) { }
    }
  };
  w.LuxyStore = S;
})(window);
