/**
 * EO Finance — landing-pro.js
 * Tambahan interaksi untuk index.php, berjalan berdampingan
 * dengan landing.js (tidak menimpa, hanya melengkapi):
 *   1. Menu mobile (hamburger + drawer)
 *   2. Scrollspy — link navbar aktif sesuai section yang terlihat
 *   3. Accordion FAQ
 *   4. Tombol back-to-top
 *
 * Catatan: init() dipanggil langsung jika dokumen sudah siap,
 * atau menunggu DOMContentLoaded jika belum — supaya fitur tetap
 * jalan walau script dimuat/di-cache dengan cara berbeda.
 */
(function () {
  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function init() {

    /* ==================================================
       1. MOBILE NAV — hamburger + drawer
       ================================================== */
    var navbar = document.querySelector('.navbar');
    var toggle = document.querySelector('.navbar__toggle');
    var menu = document.querySelector('.navbar__menu');

    if (toggle && navbar) {
      toggle.addEventListener('click', function () {
        var isOpen = navbar.classList.toggle('navbar--open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.body.style.overflow = isOpen ? 'hidden' : '';
      });

      if (menu) {
        menu.querySelectorAll('a').forEach(function (link) {
          link.addEventListener('click', function () {
            navbar.classList.remove('navbar--open');
            toggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
          });
        });
      }
    }

    /* ==================================================
       2. SCROLLSPY — tandai link navbar yang aktif
       ================================================== */
    var sections = document.querySelectorAll('main section[id]');
    var navLinks = document.querySelectorAll('.navbar__link');

    if (sections.length && 'IntersectionObserver' in window) {
      var spy = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          var id = entry.target.getAttribute('id');
          var link = document.querySelector('.navbar__link[href="#' + id + '"]');
          if (!link) return;
          if (entry.isIntersecting) {
            navLinks.forEach(function (l) { l.classList.remove('is-active'); });
            link.classList.add('is-active');
          }
        });
      }, { rootMargin: '-45% 0px -45% 0px' });

      sections.forEach(function (s) { spy.observe(s); });
    }

    /* ==================================================
       3. BACK TO TOP
       ================================================== */
    var backToTop = document.createElement('button');
    backToTop.className = 'back-to-top';
    backToTop.type = 'button';
    backToTop.setAttribute('aria-label', 'Kembali ke atas');
    backToTop.innerHTML = '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 14V3M4 8l5-5 5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    document.body.appendChild(backToTop);

    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
    });

    window.addEventListener('scroll', function () {
      if (window.scrollY > 480) {
        backToTop.classList.add('is-visible');
      } else {
        backToTop.classList.remove('is-visible');
      }
    }, { passive: true });

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();