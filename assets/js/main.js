/* ===================================================================
   Prime Luxury Rides Toronto — site behaviour
   Vanilla JS, no dependencies. Loaded with `defer`.
   =================================================================== */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ── Sticky header ───────────────────────────────────────────── */
  var header = document.getElementById('siteHeader');
  if (header) {
    var ticking = false;
    var onScroll = function () {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(function () {
        header.classList.toggle('is-scrolled', window.scrollY > 24);
        ticking = false;
      });
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ── Mobile navigation: header drawer + app-bar sheet ────────── */
  var links   = document.getElementById('navLinks');
  var scrim   = document.getElementById('navScrim');
  var toggle  = document.getElementById('navToggle');   // tablet hamburger
  var menuBtn = document.getElementById('appMenuBtn');  // phone app bar

  if (links) {
    var openers = [toggle, menuBtn].filter(Boolean);
    var lastFocused = null;
    var isOpen = false;

    var setNav = function (open) {
      isOpen = open;

      openers.forEach(function (btn) {
        btn.setAttribute('aria-expanded', String(open));
        if (btn === toggle) {
          btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        }
      });

      links.classList.toggle('is-open', open);

      if (scrim) {
        scrim.hidden = !open;
        if (open) { void scrim.offsetWidth; }   // reflow so the fade runs
        scrim.classList.toggle('is-open', open);
      }
      // Also lifts .site-header above the scrim — see main.css.
      document.body.classList.toggle('nav-open', open);
      document.body.style.overflow = open ? 'hidden' : '';

      if (open) {
        lastFocused = document.activeElement;
        var first = links.querySelector('.nav-sheet__close, a');
        if (first) first.focus();
      } else if (lastFocused) {
        lastFocused.focus();
        lastFocused = null;
      }
    };

    openers.forEach(function (btn) {
      btn.addEventListener('click', function () { setNav(!isOpen); });
    });

    if (scrim) scrim.addEventListener('click', function () { setNav(false); });

    // Close on link tap or on the sheet's close button.
    links.addEventListener('click', function (ev) {
      if (ev.target.closest('a') || ev.target.closest('[data-nav-close]')) {
        setNav(false);
      }
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && isOpen) setNav(false);
    });

    // Keep focus inside the sheet while it is open.
    links.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Tab' || !isOpen) return;
      var f = links.querySelectorAll('a[href], button:not([disabled])');
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (ev.shiftKey && document.activeElement === first) {
        ev.preventDefault(); last.focus();
      } else if (!ev.shiftKey && document.activeElement === last) {
        ev.preventDefault(); first.focus();
      }
    });

    // Swipe down on the sheet to dismiss (phones).
    var startY = null;
    links.addEventListener('touchstart', function (ev) {
      startY = links.scrollTop <= 0 ? ev.touches[0].clientY : null;
    }, { passive: true });
    links.addEventListener('touchmove', function (ev) {
      if (startY === null) return;
      var dy = ev.touches[0].clientY - startY;
      if (dy > 90) { setNav(false); startY = null; }
    }, { passive: true });

    // Reset when resizing up to desktop.
    var mq = window.matchMedia('(min-width: 1024px)');
    var onMq = function (e) { if (e.matches && isOpen) setNav(false); };
    if (mq.addEventListener) mq.addEventListener('change', onMq);
    else if (mq.addListener) mq.addListener(onMq);
  }

  /* ── Reveal on scroll ────────────────────────────────────────── */
  var revealTargets = document.querySelectorAll('.reveal, .reveal-group');

  if (!revealTargets.length) {
    /* nothing to do */
  } else if (reduceMotion || !('IntersectionObserver' in window)) {
    revealTargets.forEach(function (el) { el.classList.add('is-visible'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

    revealTargets.forEach(function (el) { io.observe(el); });
  }

  /* ── Rate table tabs (rates.php) ─────────────────────────────── */
  var rateTabs = document.querySelectorAll('[data-rate-tab]');
  if (rateTabs.length) {
    rateTabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var target = tab.getAttribute('data-rate-tab');

        rateTabs.forEach(function (t) {
          t.setAttribute('aria-selected', String(t === tab));
        });
        document.querySelectorAll('[data-rate-panel]').forEach(function (panel) {
          var match = panel.getAttribute('data-rate-panel') === target;
          panel.hidden = !match;
        });
      });
    });
  }

  /* ── :has() fallback for option cards ────────────────────────── */
  var supportsHas = (function () {
    try { return CSS.supports('selector(:has(*))'); } catch (e) { return false; }
  })();

  if (!supportsHas) {
    var syncOptions = function () {
      document.querySelectorAll('.option input').forEach(function (input) {
        var label = input.closest('.option');
        if (label) label.classList.toggle('is-selected', input.checked);
      });
    };
    document.addEventListener('change', function (ev) {
      if (ev.target.matches('.option input')) syncOptions();
    });
    syncOptions();
  }

  /* ── Footer year (in case of cached HTML) ────────────────────── */
  document.querySelectorAll('[data-year]').forEach(function (el) {
    el.textContent = String(new Date().getFullYear());
  });
})();
