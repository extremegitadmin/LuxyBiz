(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var params = new URLSearchParams(location.search);
  var slug = params.get('package');
  var tour = slug && window.LuxyCatalog ? window.LuxyCatalog.get(slug) : null;
  document.querySelectorAll('.header-cur [data-cur]').forEach(function (button) {
    button.addEventListener('click', function () {
      document.documentElement.setAttribute('data-cur', button.dataset.cur);
      document.querySelectorAll('.header-cur [data-cur]').forEach(function (item) {
        item.setAttribute('aria-pressed', String(item === button));
      });
    });
  });
  if (!tour) {
    $('#tier-grid').hidden = true;
    $('#tier-notfound').hidden = false;
    $('#tier-name').textContent = 'Package not found';
    document.title = 'Package not found | LuxyBiz';
    return;
  }
  document.title = tour.name + ' - Choose your tier | LuxyBiz';
  $('#tier-name').textContent = tour.name;
  $('#tier-summary').textContent = tour.short;
  $('#tier-hero').src = tour.hero;
  $('#tier-hero').alt = tour.heroAlt;
  $('#tier-back').href = 'index.html#journeys';
  document.querySelectorAll('[data-tier]').forEach(function (link) {
    link.href = 'tour-detail.html?package=' + encodeURIComponent(tour.slug) + '&tier=' + encodeURIComponent(link.dataset.tier);
  });
})();
