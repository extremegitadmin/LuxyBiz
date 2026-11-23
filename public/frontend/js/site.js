/* =========================================================
   Public pages: sticky header state, account menu, floating CTA.
   Needs luxy-catalog.js + luxy-store.js loaded first (for the signed-in check).
   ========================================================= */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var S = window.LuxyStore, user = S && S.current();
  var hdr = $('#hdr');

  /* ---- header turns solid after ~80px (home only; see site.css) ---- */
  function onScroll() { document.body.classList.toggle('scrolled', window.scrollY > 80); }
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

  /* ---- account menu ---- */
  var ICON = {
    home: '<path d="M4 11l8-7 8 7v9H4z"/><path d="M10 20v-6h4v6"/>',
    book: '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
    doc: '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4M10 12h5M10 16h5"/>',
    user: '<circle cx="12" cy="8" r="3.6"/><path d="M5 20c0-4 3-6 7-6s7 2 7 6"/>',
    out: '<path d="M10 4H5v16h5M15 8l4 4-4 4M19 12H9"/>'
  };
  function item(href, ic, label, cls, attr) { return '<a ' + (cls ? 'class="' + cls + '" ' : '') + (attr || '') + 'href="' + href + '" role="menuitem"><svg viewBox="0 0 24 24" aria-hidden="true">' + ICON[ic] + '</svg>' + label + '</a>'; }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  var login = $('.hdr .tools .login');
  if (login && user) {
    var first = user.name.split(' ')[0];
    var wrap = document.createElement('div');
    wrap.className = 'acct'; wrap.id = 'acct';
    wrap.innerHTML =
      '<button class="acct-btn" type="button" id="acct-btn" aria-haspopup="menu" aria-expanded="false" aria-controls="acct-menu" aria-label="Account menu for ' + esc(user.name) + '">' +
        '<span class="acct-av" aria-hidden="true">' + esc(first.charAt(0).toUpperCase()) + '</span><span>' + esc(first) + '</span>' +
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>' +
      '<div class="acct-menu" id="acct-menu" role="menu" aria-label="Account">' +
        '<div class="acct-who"><b>' + esc(user.name) + '</b><small>' + esc(user.email) + '</small></div>' +
        item('dashboard.html', 'home', 'Dashboard') + item('my-bookings.html', 'book', 'My Bookings') + item('documents.html', 'doc', 'Documents') + item('account.html', 'user', 'Account') +
        '<hr>' + item('auth.html#login', 'out', 'Log out', 'out', 'data-site-logout ') +
      '</div>';
    login.replaceWith(wrap);
    var btn = $('#acct-btn'), menu = $('#acct-menu');
    var desktop = function () { return window.matchMedia('(min-width:961px)').matches; };
    function close(focus) { menu.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); if (focus) btn.focus(); }
    btn.addEventListener('click', function (e) { e.stopPropagation(); var o = menu.classList.toggle('open'); btn.setAttribute('aria-expanded', o); if (o) { var a = $('a', menu); if (a) a.focus(); } });
    document.addEventListener('click', function (e) { if (desktop() && !wrap.contains(e.target)) close(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menu.classList.contains('open')) close(true); });
    menu.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return; e.preventDefault();
      var items = [].slice.call(menu.querySelectorAll('a')), i = items.indexOf(document.activeElement);
      items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
    });
    menu.addEventListener('click', function (e) { if (e.target.closest('a[data-site-logout]')) { e.preventDefault(); S.logout(); location.href = 'auth.html#login'; } });
  }

  /* ---- footer / other "Login / Account" links follow the signed-in state ---- */
  document.querySelectorAll('[data-acct-link]').forEach(function (a) {
    if (user) { a.textContent = 'My account'; a.setAttribute('href', 'dashboard.html'); } else { a.textContent = 'Login / Account'; a.setAttribute('href', 'auth.html#login'); }
  });

  /* ---- complete country-code selector with flag emoji ---- */
  var countries = [
    ['AF','Afghanistan','+93'],['AL','Albania','+355'],['DZ','Algeria','+213'],['AS','American Samoa','+1684'],['AD','Andorra','+376'],['AO','Angola','+244'],['AI','Anguilla','+1264'],['AG','Antigua and Barbuda','+1268'],['AR','Argentina','+54'],['AM','Armenia','+374'],['AW','Aruba','+297'],['AU','Australia','+61'],['AT','Austria','+43'],['AZ','Azerbaijan','+994'],
    ['BS','Bahamas','+1242'],['BH','Bahrain','+973'],['BD','Bangladesh','+880'],['BB','Barbados','+1246'],['BY','Belarus','+375'],['BE','Belgium','+32'],['BZ','Belize','+501'],['BJ','Benin','+229'],['BM','Bermuda','+1441'],['BT','Bhutan','+975'],['BO','Bolivia','+591'],['BQ','Bonaire, Sint Eustatius and Saba','+599'],['BA','Bosnia and Herzegovina','+387'],['BW','Botswana','+267'],['BR','Brazil','+55'],['IO','British Indian Ocean Territory','+246'],['VG','British Virgin Islands','+1284'],['BN','Brunei','+673'],['BG','Bulgaria','+359'],['BF','Burkina Faso','+226'],['BI','Burundi','+257'],
    ['KH','Cambodia','+855'],['CM','Cameroon','+237'],['CA','Canada','+1'],['CV','Cape Verde','+238'],['KY','Cayman Islands','+1345'],['CF','Central African Republic','+236'],['TD','Chad','+235'],['CL','Chile','+56'],['CN','China','+86'],['CX','Christmas Island','+61'],['CC','Cocos Islands','+61'],['CO','Colombia','+57'],['KM','Comoros','+269'],['CG','Congo','+242'],['CD','DR Congo','+243'],['CK','Cook Islands','+682'],['CR','Costa Rica','+506'],['CI','Cote dIvoire','+225'],['HR','Croatia','+385'],['CU','Cuba','+53'],['CW','Curacao','+599'],['CY','Cyprus','+357'],['CZ','Czechia','+420'],
    ['DK','Denmark','+45'],['DJ','Djibouti','+253'],['DM','Dominica','+1767'],['DO','Dominican Republic','+1809'],['EC','Ecuador','+593'],['EG','Egypt','+20'],['SV','El Salvador','+503'],['GQ','Equatorial Guinea','+240'],['ER','Eritrea','+291'],['EE','Estonia','+372'],['SZ','Eswatini','+268'],['ET','Ethiopia','+251'],
    ['FK','Falkland Islands','+500'],['FO','Faroe Islands','+298'],['FJ','Fiji','+679'],['FI','Finland','+358'],['FR','France','+33'],['GF','French Guiana','+594'],['PF','French Polynesia','+689'],
    ['GA','Gabon','+241'],['GM','Gambia','+220'],['GE','Georgia','+995'],['DE','Germany','+49'],['GH','Ghana','+233'],['GI','Gibraltar','+350'],['GR','Greece','+30'],['GL','Greenland','+299'],['GD','Grenada','+1473'],['GP','Guadeloupe','+590'],['GU','Guam','+1671'],['GT','Guatemala','+502'],['GG','Guernsey','+44'],['GN','Guinea','+224'],['GW','Guinea-Bissau','+245'],['GY','Guyana','+592'],
    ['HT','Haiti','+509'],['HN','Honduras','+504'],['HK','Hong Kong','+852'],['HU','Hungary','+36'],
    ['IS','Iceland','+354'],['IN','India','+91'],['ID','Indonesia','+62'],['IR','Iran','+98'],['IQ','Iraq','+964'],['IE','Ireland','+353'],['IM','Isle of Man','+44'],['IL','Israel','+972'],['IT','Italy','+39'],
    ['JM','Jamaica','+1876'],['JP','Japan','+81'],['JE','Jersey','+44'],['JO','Jordan','+962'],
    ['KZ','Kazakhstan','+7'],['KE','Kenya','+254'],['KI','Kiribati','+686'],['KP','North Korea','+850'],['KR','South Korea','+82'],['KW','Kuwait','+965'],['KG','Kyrgyzstan','+996'],
    ['LA','Laos','+856'],['LV','Latvia','+371'],['LB','Lebanon','+961'],['LS','Lesotho','+266'],['LR','Liberia','+231'],['LY','Libya','+218'],['LI','Liechtenstein','+423'],['LT','Lithuania','+370'],['LU','Luxembourg','+352'],
    ['MO','Macau','+853'],['MG','Madagascar','+261'],['MW','Malawi','+265'],['MY','Malaysia','+60'],['MV','Maldives','+960'],['ML','Mali','+223'],['MT','Malta','+356'],['MH','Marshall Islands','+692'],['MQ','Martinique','+596'],['MR','Mauritania','+222'],['MU','Mauritius','+230'],['YT','Mayotte','+262'],['MX','Mexico','+52'],['FM','Micronesia','+691'],['MD','Moldova','+373'],['MC','Monaco','+377'],['MN','Mongolia','+976'],['ME','Montenegro','+382'],['MS','Montserrat','+1664'],['MA','Morocco','+212'],['MZ','Mozambique','+258'],['MM','Myanmar','+95'],
    ['NA','Namibia','+264'],['NR','Nauru','+674'],['NP','Nepal','+977'],['NL','Netherlands','+31'],['NC','New Caledonia','+687'],['NZ','New Zealand','+64'],['NI','Nicaragua','+505'],['NE','Niger','+227'],['NG','Nigeria','+234'],['NU','Niue','+683'],['NF','Norfolk Island','+672'],['MK','North Macedonia','+389'],['MP','Northern Mariana Islands','+1670'],['NO','Norway','+47'],
    ['OM','Oman','+968'],
    ['PK','Pakistan','+92'],['PW','Palau','+680'],['PS','Palestine','+970'],['PA','Panama','+507'],['PG','Papua New Guinea','+675'],['PY','Paraguay','+595'],['PE','Peru','+51'],['PH','Philippines','+63'],['PN','Pitcairn Islands','+64'],['PL','Poland','+48'],['PT','Portugal','+351'],['PR','Puerto Rico','+1787'],
    ['QA','Qatar','+974'],
    ['RE','Reunion','+262'],['RO','Romania','+40'],['RU','Russia','+7'],['RW','Rwanda','+250'],
    ['BL','Saint Barthelemy','+590'],['SH','Saint Helena','+290'],['KN','Saint Kitts and Nevis','+1869'],['LC','Saint Lucia','+1758'],['MF','Saint Martin','+590'],['PM','Saint Pierre and Miquelon','+508'],['VC','Saint Vincent and the Grenadines','+1784'],['WS','Samoa','+685'],['SM','San Marino','+378'],['ST','Sao Tome and Principe','+239'],['SA','Saudi Arabia','+966'],['SN','Senegal','+221'],['RS','Serbia','+381'],['SC','Seychelles','+248'],['SL','Sierra Leone','+232'],['SG','Singapore','+65'],['SX','Sint Maarten','+1721'],['SK','Slovakia','+421'],['SI','Slovenia','+386'],['SB','Solomon Islands','+677'],['SO','Somalia','+252'],['ZA','South Africa','+27'],['SS','South Sudan','+211'],['ES','Spain','+34'],['LK','Sri Lanka','+94'],['SD','Sudan','+249'],['SR','Suriname','+597'],['SJ','Svalbard and Jan Mayen','+47'],['SE','Sweden','+46'],['CH','Switzerland','+41'],['SY','Syria','+963'],
    ['TW','Taiwan','+886'],['TJ','Tajikistan','+992'],['TZ','Tanzania','+255'],['TH','Thailand','+66'],['TL','Timor-Leste','+670'],['TG','Togo','+228'],['TK','Tokelau','+690'],['TO','Tonga','+676'],['TT','Trinidad and Tobago','+1868'],['TN','Tunisia','+216'],['TR','Turkey','+90'],['TM','Turkmenistan','+993'],['TC','Turks and Caicos Islands','+1649'],['TV','Tuvalu','+688'],
    ['UG','Uganda','+256'],['UA','Ukraine','+380'],['AE','United Arab Emirates','+971'],['GB','United Kingdom','+44'],['US','United States','+1'],['UY','Uruguay','+598'],['UZ','Uzbekistan','+998'],
    ['VU','Vanuatu','+678'],['VA','Vatican City','+379'],['VE','Venezuela','+58'],['VN','Vietnam','+84'],['VI','US Virgin Islands','+1340'],
    ['WF','Wallis and Futuna','+681'],['EH','Western Sahara','+212'],['YE','Yemen','+967'],['ZM','Zambia','+260'],['ZW','Zimbabwe','+263']
  ];
  function flag(iso) { return iso.replace(/./g, function (ch) { return String.fromCodePoint(127397 + ch.charCodeAt()); }); }
  countries.sort(function (a, b) { return a[1].localeCompare(b[1]); });
  document.querySelectorAll('[data-country-code]').forEach(function (select) {
    countries.forEach(function (country) {
      var option = document.createElement('option');
      option.value = country[2];
      option.textContent = flag(country[0]) + ' ' + country[2];
      if (country[0] === 'IN') option.selected = true;
      select.appendChild(option);
    });
  });

  /* ---- floating Consultation / Contact: hidden over the hero and where it would cover the form ---- */
  var fl = $('#float');
  if (fl && document.body.dataset.float !== 'page' && 'IntersectionObserver' in window) {
    var seen = {};
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (en) { seen[en.target.id] = en.isIntersecting; });
      var builder = document.getElementById('cz-app');
      fl.classList.toggle('off', !!(seen.top || seen.plan || seen.contact || (builder && !builder.hidden)));
    }, { threshold: .12 });
    ['top', 'plan', 'contact'].forEach(function (id) { var e = document.getElementById(id); if (e) io.observe(e); });
  }
})();
