/* =========================================================
   Gate for the customizable package option.
   Anyone who is not signed in gets a pop-up: "Sign up or log in to get access to the customizable package option."
   Works on any page: add data-customize to a button, or link to the custom-journey section, and it is intercepted when signed out.
   ========================================================= */
(function () {
  'use strict';
  var S = window.LuxyStore, TARGET = 'index.html#customize-builder';
  function signedIn() { return !!(S && S.current()); }
  function url(mode) { return 'auth.html?next=' + encodeURIComponent(TARGET) + '#' + mode; }

  var dlg, lastFocus;
  function build() {
    dlg = document.createElement('dialog');
    dlg.className = 'gate'; dlg.setAttribute('aria-labelledby', 'gate-h'); dlg.setAttribute('aria-describedby', 'gate-p');
    dlg.innerHTML =
      '<div class="gate-in">' +
        '<button type="button" class="gate-x" aria-label="Close">&times;</button>' +
        '<span class="gate-ic" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/></svg></span>' +
        '<h2 id="gate-h">Sign up or log in to continue</h2>' +
        '<p id="gate-p">The customizable package option is for members. Sign up or log in to get access to it, then build your own route day by day.</p>' +
        '<div class="gate-act"><a class="btn solid" href="' + url('register') + '" id="gate-up">Sign up</a><a class="btn" href="' + url('login') + '" id="gate-in">Log in</a></div>' +
        '<button type="button" class="gate-later">Maybe later</button>' +
      '</div>';
    document.body.appendChild(dlg);
    var close = function () { dlg.close(); };
    dlg.querySelector('.gate-x').addEventListener('click', close);
    dlg.querySelector('.gate-later').addEventListener('click', close);
    dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); });
    dlg.addEventListener('close', function () { if (lastFocus && lastFocus.focus) lastFocus.focus(); if (dlg.dataset.leave) location.href = dlg.dataset.leave; });
  }
  /* opts.leave: where to go if the pop-up is dismissed (used when dismissing the member gate) */
  function open(opts) {
    if (!dlg) build();
    lastFocus = document.activeElement;
    dlg.dataset.leave = (opts && opts.leave) || '';
    if (dlg.showModal) { if (!dlg.open) dlg.showModal(); } else { location.href = url('login'); return; }
    dlg.querySelector('#gate-up').focus();
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href$="' + TARGET + '"], [data-customize]');
    if (!a || signedIn()) return;
    e.preventDefault(); open();
  });
  window.LuxyGate = { open: open, signedIn: signedIn };
})();
