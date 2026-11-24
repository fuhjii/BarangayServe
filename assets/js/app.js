/**
 * BarangayServe: small helpers for the pages.
 *
 * The site's security policy allows only files from this site, so there is no
 * JavaScript written inside the pages. Everything is switched on by data-
 * attributes in the HTML:
 *
 *   data-confirm="Question?"            ask "Are you sure?" before a form is sent
 *   data-confirm-button="Yes, do it"    label of the confirm button
 *   data-toggle-password="#field-id"    show / hide a password
 *   data-copy-target="#element-id"      copy the text of an element
 *   data-counter                        show "123 / 500" under a text box
 *   data-auto-submit                    submit the filter form when a list changes
 *
 * The server always checks everything again. This file only makes the pages
 * easier to use, so the website still works if JavaScript is switched off.
 */
(function () {
  'use strict';

  /* ---------- 1. "Are you sure?" dialog (Bootstrap modal) ---------- */
  var confirmModal = null;     // the Bootstrap modal object
  var confirmEl = null;        // the dialog element
  var pending = null;          // the form waiting for the answer

  function buildConfirmDialog() {
    if (confirmEl) { return; }
    confirmEl = document.createElement('div');
    confirmEl.className = 'modal fade';
    confirmEl.tabIndex = -1;
    confirmEl.setAttribute('aria-labelledby', 'confirm-title');
    confirmEl.setAttribute('aria-hidden', 'true');
    confirmEl.innerHTML =
      '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
      '<div class="modal-header"><h2 class="modal-title h5" id="confirm-title">Please confirm</h2>' +
      '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
      '<div class="modal-body"><p class="mb-0" id="confirm-text"></p></div>' +
      '<div class="modal-footer">' +
      '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>' +
      '<button type="button" class="btn btn-primary" id="confirm-yes">Yes, continue</button>' +
      '</div></div></div>';
    document.body.appendChild(confirmEl);
    confirmModal = new bootstrap.Modal(confirmEl);

    confirmEl.querySelector('#confirm-yes').addEventListener('click', function () {
      var form = pending;
      pending = null;
      confirmModal.hide();
      if (form) {
        form.dataset.confirmed = '1';
        if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
      }
    });
    confirmEl.addEventListener('hidden.bs.modal', function () { pending = null; });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) { return; }

    // Which element asked for the confirmation: the form itself, or the button that was pressed.
    var source = form.hasAttribute('data-confirm') ? form
      : (event.submitter && event.submitter.hasAttribute('data-confirm') ? event.submitter : null);

    // A chosen radio button can bring its own, stronger question (for example "Reject this request?").
    var pickedChoice = form.querySelector('input[type="radio"][data-confirm]:checked');
    if (pickedChoice) { source = pickedChoice; }

    if (source && form.dataset.confirmed !== '1' && window.bootstrap) {
      event.preventDefault();
      buildConfirmDialog();
      confirmEl.querySelector('#confirm-text').textContent = source.getAttribute('data-confirm');
      confirmEl.querySelector('#confirm-yes').textContent = source.getAttribute('data-confirm-button') || 'Yes, continue';
      pending = form;
      confirmModal.show();
      return;
    }

    /* ---------- 2. Stop double clicks: lock the buttons once the form is on its way ---------- */
    if (!event.defaultPrevented && (form.method || '').toLowerCase() === 'post') {
      var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
      window.setTimeout(function () {       // wait one tick so the button's own value is still sent
        buttons.forEach(function (b) {
          b.disabled = true;
          b.setAttribute('aria-busy', 'true');
        });
      }, 0);
    }
  });

  // Coming back with the Back button: unlock the buttons again.
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      document.querySelectorAll('button[aria-busy="true"]').forEach(function (b) {
        b.disabled = false;
        b.removeAttribute('aria-busy');
      });
      document.querySelectorAll('form[data-confirmed]').forEach(function (f) { delete f.dataset.confirmed; });
    }
  });

  /* ---------- 3. Show / hide password ---------- */
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-toggle-password]');
    if (!button) { return; }
    var input = document.querySelector(button.getAttribute('data-toggle-password'));
    if (!input) { return; }
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    var icon = button.querySelector('.bi');
    if (icon) { icon.className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye'); }
  });

  /* ---------- 4. Copy the reference number ---------- */
  document.querySelectorAll('[data-copy-target]').forEach(function (button) {
    if (!(navigator.clipboard && navigator.clipboard.writeText)) { return; }   // keep it hidden when unsupported
    button.hidden = false;
    button.addEventListener('click', function () {
      var target = document.querySelector(button.getAttribute('data-copy-target'));
      if (!target) { return; }
      navigator.clipboard.writeText(target.textContent.trim()).then(function () {
        var label = button.querySelector('span');
        if (label) {
          label.textContent = 'Copied';
          window.setTimeout(function () { label.textContent = 'Copy'; }, 2000);
        }
      });
    });
  });

  /* ---------- 5. Character counters ---------- */
  document.querySelectorAll('textarea[data-counter]').forEach(function (box) {
    var max = parseInt(box.getAttribute('maxlength'), 10);
    if (!max) { return; }
    var counter = document.createElement('div');
    counter.className = 'char-counter';
    counter.setAttribute('aria-hidden', 'true');
    box.insertAdjacentElement('afterend', counter);
    function update() { counter.textContent = box.value.length + ' / ' + max; }
    box.addEventListener('input', update);
    update();
  });

  /* ---------- 6. Filters that apply as soon as a choice is made ---------- */
  document.querySelectorAll('select[data-auto-submit]').forEach(function (select) {
    select.addEventListener('change', function () { if (select.form) { select.form.submit(); } });
  });

  /* ---------- 8. Staff list and dashboard reload themselves when a request changes ----------
     Every few seconds the page asks staff/check_new.php for ONE number (the newest history
     entry). The page is reloaded once, only when that number is different from the one the
     page was built with. Nothing is asked while the tab is hidden, and a reload waits while
     the staff member is typing in a field. */
  var live = document.querySelector('[data-live-refresh]');
  if (live && window.fetch) {
    (function () {
      var endpoint = live.getAttribute('data-live-refresh');
      var seen = live.getAttribute('data-live-version');
      var delay = Math.max(2, parseInt(live.getAttribute('data-live-seconds'), 10) || 5) * 1000;
      var timer = null;
      var asking = false;
      var finished = false;

      function start() { if (!timer && !finished) { timer = window.setInterval(check, delay); } }
      function stop() { if (timer) { window.clearInterval(timer); timer = null; } }
      function isTyping() {
        var el = document.activeElement;
        return !!el && /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName);
      }

      function check() {
        if (asking || finished || document.hidden) { return; }
        asking = true;
        fetch(endpoint, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
          .then(function (response) {
            if (response.status === 401) { finished = true; stop(); return null; }   // logged out: stop asking
            return response.ok ? response.json() : null;
          })
          .then(function (data) {
            asking = false;
            if (data && String(data.version) !== seen && !isTyping()) {
              finished = true;
              stop();
              window.location.reload();
            }
          })
          .catch(function () { asking = false; });   // a failed check is simply tried again next time
      }

      document.addEventListener('visibilitychange', function () {
        if (document.hidden) { stop(); } else { check(); start(); }
      });
      start();
    })();
  }

  /* ---------- 7. Put the keyboard focus on the list of form errors ---------- */
  var summary = document.getElementById('error-summary');
  if (summary) { summary.focus(); }
})();
