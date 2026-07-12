/**
 * EO Finance — landing.js
 *
 * Script khusus untuk index.php (landing page publik).
 * Berisi:
 *   1. Scroll-reveal (fade + slide up) via IntersectionObserver
 *   2. Progress bar scroll di bagian atas layar
 *   3. Navbar menyusut & berbayang saat halaman discroll
 *   4. Efek 3D tilt + spotlight kursor pada kartu fitur
 *   5. Smooth scroll untuk tautan anchor (#fitur, #cara-kerja, dst.)
 *
 * Semua efek menghormati preferensi "prefers-reduced-motion" dan
 * tetap menampilkan konten dengan baik meski JS gagal dimuat
 * (lihat rule ".no-js" di style.css sebagai fallback).
 */

(function () {
  // Hapus penanda "no-js" karena JS berhasil berjalan.
  document.documentElement.classList.remove('no-js');

  var prefersReducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)'
  ).matches;

  document.addEventListener('DOMContentLoaded', function () {

    /* ====================================================================
       1. SCROLL PROGRESS BAR
       ==================================================================== */
    var progressBar = document.createElement('div');
    progressBar.className = 'scroll-progress';
    progressBar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(progressBar);

    function updateScrollProgress() {
      var scrollTop = window.scrollY || document.documentElement.scrollTop;
      var docHeight = document.documentElement.scrollHeight - window.innerHeight;
      var pct = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
      progressBar.style.width = pct + '%';
    }

    /* ====================================================================
       2. NAVBAR — menyusut & berbayang saat discroll
       ==================================================================== */
    var navbar = document.querySelector('.navbar');

    function updateNavbarState() {
      if (!navbar) return;
      if (window.scrollY > 12) {
        navbar.classList.add('navbar--scrolled');
      } else {
        navbar.classList.remove('navbar--scrolled');
      }
    }

    var ticking = false;
    function onScroll() {
      if (!ticking) {
        window.requestAnimationFrame(function () {
          updateScrollProgress();
          updateNavbarState();
          ticking = false;
        });
        ticking = true;
      }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    updateScrollProgress();
    updateNavbarState();

    /* ====================================================================
       3. HERO — blob dekoratif di background
       ==================================================================== */
    var hero = document.querySelector('.hero');
    if (hero && !hero.querySelector('.hero__blob--1')) {
      ['hero__blob hero__blob--1', 'hero__blob hero__blob--2', 'hero__blob hero__blob--3']
        .forEach(function (cls) {
          var blob = document.createElement('div');
          blob.className = cls;
          blob.setAttribute('aria-hidden', 'true');
          hero.insertBefore(blob, hero.firstChild);
        });
    }

    /* ====================================================================
       4. SCROLL-REVEAL — fade + slide up saat elemen masuk viewport
       ==================================================================== */
    var revealTargets = document.querySelectorAll(
      '.reveal, .features__grid, .roles__grid, .steps__grid'
    );

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
      revealTargets.forEach(function (el) {
        el.classList.add('is-visible');
      });
    } else {
      document.querySelectorAll('.features__grid, .roles__grid, .steps__grid')
        .forEach(function (grid) {
          grid.classList.add('reveal-stagger');
        });

      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      }, {
        threshold: 0.15,
        rootMargin: '0px 0px -60px 0px'
      });

      revealTargets.forEach(function (el) {
        observer.observe(el);
      });
    }

    /* ====================================================================
       5. FEATURE CARDS — efek 3D tilt + spotlight mengikuti kursor
       ==================================================================== */
    if (!prefersReducedMotion && window.matchMedia('(hover: hover)').matches) {
      var maxTilt = 6; // derajat maksimum kemiringan

      document.querySelectorAll('.feature-card').forEach(function (card) {
        card.addEventListener('mousemove', function (e) {
          var rect = card.getBoundingClientRect();
          var x = e.clientX - rect.left;
          var y = e.clientY - rect.top;

          var px = x / rect.width;   // 0..1
          var py = y / rect.height;  // 0..1

          var ry = (px - 0.5) * (maxTilt * 2);
          var rx = -(py - 0.5) * (maxTilt * 2);

          card.style.setProperty('--rx', rx.toFixed(2) + 'deg');
          card.style.setProperty('--ry', ry.toFixed(2) + 'deg');
          card.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
          card.style.setProperty('--my', (py * 100).toFixed(1) + '%');
        });

        card.addEventListener('mouseleave', function () {
          card.style.setProperty('--rx', '0deg');
          card.style.setProperty('--ry', '0deg');
        });
      });
    }

    /* ====================================================================
       6. SMOOTH SCROLL — tautan anchor (#fitur, #cara-kerja, #tentang)
       ==================================================================== */
    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        var targetId = link.getAttribute('href');
        if (targetId.length < 2) return;
        var target = document.querySelector(targetId);
        if (!target) return;

        e.preventDefault();
        var navbarHeight = navbar ? navbar.offsetHeight : 0;
        var top = target.getBoundingClientRect().top + window.scrollY - navbarHeight - 16;

        window.scrollTo({
          top: top,
          behavior: prefersReducedMotion ? 'auto' : 'smooth'
        });
      });
    });

  });
})();