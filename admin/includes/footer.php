  </main>
</div>

<script>
(function () {
  'use strict';
  var side   = document.getElementById('adminSide');
  var burger = document.getElementById('adminBurger');
  var scrim  = document.getElementById('adminScrim');
  if (!side || !burger) return;

  function setOpen(open) {
    side.classList.toggle('is-open', open);
    if (scrim) scrim.classList.toggle('is-open', open);
    burger.setAttribute('aria-expanded', String(open));
    burger.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    document.body.style.overflow = open ? 'hidden' : '';
  }

  burger.addEventListener('click', function () {
    setOpen(!side.classList.contains('is-open'));
  });
  if (scrim) scrim.addEventListener('click', function () { setOpen(false); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && side.classList.contains('is-open')) setOpen(false);
  });

  // Password show/hide
  document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-pw-toggle'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.querySelector('[data-eye-open]').hidden  = show;
      btn.querySelector('[data-eye-close]').hidden = !show;
    });
  });

  // Confirm destructive actions
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!window.confirm(el.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Auto-submit filter selects
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form && el.form.submit(); });
  });
})();
</script>
</body>
</html>
