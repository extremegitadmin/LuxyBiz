(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return [].slice.call((c || document).querySelectorAll(s)); };
  var CAT = window.LuxyCatalog, S = window.LuxyStore, params = new URLSearchParams(location.search);
  var slug = params.get('package') || '', tour = CAT.get(slug), form = $('#booking-form');
  if (!tour || !S) {
    $('#booking-notfound').hidden = false;
    return;
  }

  var root = document.documentElement, date = $('#booking-date'), name = $('#booking-name');
  var email = $('#booking-email'), phone = $('#booking-phone'), travelers = $('#booking-travelers');
  var notes = $('#booking-notes'), submit = $('#payment-button');
  var pendingKey = 'luxy.pending.booking.' + tour.slug;
  var today = new Date();
  date.min = today.getFullYear() + '-' + ('0' + (today.getMonth() + 1)).slice(-2) + '-' + ('0' + today.getDate()).slice(-2);
  date.value = params.get('date') || '';
  if (date.value < date.min) date.value = '';
  var duration = (tour.nights + 1) + ' days / ' + tour.nights + ' nights';
  $('#package-name').textContent = tour.name;
  $('#package-duration').textContent = duration + (date.value ? ' · ' + new Date(date.value + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) : '');
  $('#price-package').textContent = tour.name;
  $('#back-package').href = 'tour-detail.html?package=' + encodeURIComponent(tour.slug) + (params.get('tier') ? '&tier=' + encodeURIComponent(params.get('tier')) : '');
  $('#booking-content').hidden = false;
  document.title = 'Complete booking: ' + tour.name + ' | LuxyBiz';

  var initialCurrency = params.get('currency');
  if (initialCurrency !== 'inr') initialCurrency = 'usd';
  root.setAttribute('data-cur', initialCurrency);
  $$('.booking-currency [data-cur]').forEach(function (button) {
    button.setAttribute('aria-pressed', String(button.dataset.cur === initialCurrency));
    button.addEventListener('click', function () {
      root.setAttribute('data-cur', button.dataset.cur);
      $$('.booking-currency [data-cur]').forEach(function (item) { item.setAttribute('aria-pressed', String(item === button)); });
      updateTotal();
    });
  });

  function usd(value) { return '$' + value.toLocaleString('en-US'); }
  function inr(value) { return '₹' + value.toLocaleString('en-IN'); }
  function updateTotal() {
    var count = +travelers.value;
    $('#price-count').textContent = count + (count === 1 ? ' traveller' : ' travellers');
    $('#price-unit').textContent = root.getAttribute('data-cur') === 'inr' ? inr(tour.inr) : usd(tour.usd);
    $('#price-total').textContent = root.getAttribute('data-cur') === 'inr' ? inr(tour.inr * count) : usd(tour.usd * count);
  }
  travelers.addEventListener('change', updateTotal);
  updateTotal();

  var saved = null;
  try { saved = JSON.parse(sessionStorage.getItem(pendingKey) || 'null'); } catch (e) { saved = null; }
  if (saved) {
    if (!params.get('date') && saved.date && saved.date >= date.min) date.value = saved.date;
    if (saved.travelers >= 1 && saved.travelers <= 8) travelers.value = String(saved.travelers);
    if (saved.contact) {
      name.value = saved.contact.name || '';
      email.value = saved.contact.email || '';
      phone.value = saved.contact.phone || '';
      notes.value = saved.contact.notes || '';
    }
    updateTotal();
  }
  var user = S.current();
  if (user) {
    if (!name.value) name.value = user.name;
    if (!email.value) email.value = user.email;
    if (!phone.value) phone.value = user.phone;
  }
  $('#booking-form').addEventListener('input', function (e) {
    if (e.target.hasAttribute('aria-invalid')) e.target.removeAttribute('aria-invalid');
    var error = document.getElementById(e.target.id + '-err');
    if (error) error.textContent = '';
    $('#booking-form-error').hidden = true;
  });
  date.addEventListener('change', function () {
    $('#package-duration').textContent = duration + (date.value ? ' · ' + new Date(date.value + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) : '');
  });

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
  }
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var valid = true;
    var generalError = $('#booking-form-error');
    function setError(input, id, message) {
      $('#' + id).textContent = message;
      if (message) { input.setAttribute('aria-invalid', 'true'); valid = false; }
      else input.removeAttribute('aria-invalid');
    }
    setError(date, 'booking-date-err', !date.value || date.value < date.min ? 'Choose today or a future travel date.' : '');
    setError(name, 'booking-name-err', !name.value.trim() ? 'Enter the lead traveller’s full name.' : '');
    setError(email, 'booking-email-err', !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value.trim()) ? 'Enter a valid email address.' : '');
    setError(phone, 'booking-phone-err', !/^[+\d][\d\s\-()]{6,}$/.test(phone.value.trim()) ? 'Enter a valid phone number.' : '');
    if (!valid) {
      generalError.hidden = true;
      var firstInvalid = $('[aria-invalid=true]', form);
      if (firstInvalid) firstInvalid.focus();
      return;
    }

    var bookingDate = date.value;
    var count = +travelers.value;
    var contact = { name: name.value.trim(), email: email.value.trim(), phone: phone.value.trim(), notes: notes.value.trim() };
    if (!S.current()) {
      try { sessionStorage.setItem(pendingKey, JSON.stringify({ date: bookingDate, travelers: count, contact: contact })); }
      catch (error) {
        generalError.textContent = 'Your booking details could not be saved for sign-in. Please enable session storage and try again.';
        generalError.hidden = false;
        return;
      }
      var next = 'booking.html?package=' + encodeURIComponent(tour.slug) + '&date=' + encodeURIComponent(bookingDate) + '&currency=' + encodeURIComponent(root.getAttribute('data-cur'));
      location.href = 'auth.html?next=' + encodeURIComponent(next) + '#login';
      return;
    }

    submit.disabled = true;
    S.ready.then(function () {
      var booking = S.createBooking(tour.slug, bookingDate, count, contact);
      if (!booking) throw new Error('Booking could not be created.');
      try { sessionStorage.removeItem(pendingKey); } catch (error) { }
      $('#booking-success').innerHTML = 'Booking request <b>' + escapeHtml(booking.ref) + '</b> received. Online payment is not available yet, so no payment was taken. <a href="booking-details.html?ref=' + encodeURIComponent(booking.ref) + '">View your booking</a>';
      $('#booking-success').hidden = false;
      submit.disabled = true;
    }).catch(function () {
      submit.disabled = false;
      generalError.textContent = 'We could not submit your booking right now. Please try again.';
      generalError.hidden = false;
    });
  });
})();
