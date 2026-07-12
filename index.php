<?php
/**
 * EO Finance — Landing page
 * Halaman publik pertama yang dilihat calon pengguna.
 * Tidak memerlukan sesi login.
 *
 * @package EOFinance
 */
?>
<!DOCTYPE html>
<html lang="id" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="EO Finance — Platform manajemen keuangan event organizer yang modern, aman, dan mudah digunakan.">
  <meta name="theme-color" content="#0071E3">
  <title>EO Finance — Keuangan Event yang Selalu Terkendali</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/landing-pro.css">
</head>
<body>

  <!-- ═══════════════════════════════════════════════════
       NAVBAR
  ═══════════════════════════════════════════════════ -->
  <header class="navbar" role="banner">
    <div class="navbar__container">

      <!-- Logo -->
      <a href="index.php" class="navbar__brand" aria-label="EO Finance — Beranda">
        <span class="navbar__logo-icon" aria-hidden="true">
          <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="28" height="28" rx="8" fill="#0071E3"/>
            <path d="M8 20L12 13L16 16.5L20 8" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="20" cy="8" r="2" fill="white"/>
          </svg>
        </span>
        <span class="navbar__brand-name">EO Finance</span>
      </a>

      <!-- Menu tengah -->
      <nav class="navbar__menu" aria-label="Menu utama">
        <a href="#fitur" class="navbar__link">Fitur</a>
        <a href="#cara-kerja" class="navbar__link">Cara Kerja</a>
        <a href="#tentang" class="navbar__link">Tentang</a>
        <a href="#faq" class="navbar__link">FAQ</a>

        <div class="navbar__mobile-actions">
          <a href="login.php" class="btn btn--ghost">Masuk</a>
          <a href="register.php" class="btn btn--primary">Daftar Gratis</a>
        </div>
      </nav>

      <!-- Tombol auth (desktop) -->
      <div class="navbar__actions">
        <a href="login.php" class="btn btn--ghost">Masuk</a>
        <a href="register.php" class="btn btn--primary">Daftar</a>
      </div>

      <!-- Hamburger (mobile) -->
      <button class="navbar__toggle" type="button" aria-label="Buka menu" aria-expanded="false">
        <span class="navbar__toggle-icon" aria-hidden="true"></span>
      </button>

    </div>
  </header>


  <!-- ═══════════════════════════════════════════════════
       HERO SECTION
  ═══════════════════════════════════════════════════ -->
  <main>
    <section class="hero" aria-labelledby="hero-headline">
      <div class="hero__mesh" aria-hidden="true"></div>
      <div class="hero__grid-noise" aria-hidden="true"></div>

      <div class="hero__container">

        <div class="hero__badge">
          <span class="hero__badge-dot" aria-hidden="true"></span>
          Platform manajemen keuangan event organizer
        </div>

        <h1 class="hero__headline" id="hero-headline">
          Keuangan event yang<br>
          <span class="hero__headline-accent">selalu terkendali.</span>
        </h1>

        <p class="hero__description">
          Catat pemasukan dan pengeluaran setiap event dalam satu platform terpusat.
          Data tiap EO terisolasi penuh, aman, dan siap diakses kapan saja.
        </p>

        <div class="hero__actions">
          <a href="register.php" class="btn btn--primary btn--large">Mulai gratis</a>
          <a href="#cara-kerja" class="btn btn--text btn--large">
            Pelajari lebih lanjut
            <span class="btn__arrow" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
          </a>
        </div>

        <p class="hero__trustline">Dipakai oleh EO independen hingga agensi acara skala nasional</p>

      </div>
    </section>


    <!-- ═══════════════════════════════════════════════════
         TRUST STRIP — logo berjalan
    ═══════════════════════════════════════════════════ -->
    <section class="trust" aria-label="Dipercaya oleh berbagai event organizer">
      <p class="trust__label">Dipercaya oleh tim event organizer seperti</p>
      <div class="trust__track-wrap">
        <div class="trust__track" aria-hidden="true">
          <span class="trust__item">Selaras Event</span>
          <span class="trust__item">Cahaya Nusantara</span>
          <span class="trust__item">Meraki Organizer</span>
          <span class="trust__item">Bintang Acara</span>
          <span class="trust__item">Wedira Wedding</span>
          <span class="trust__item">Kilau Production</span>
          <span class="trust__item">Ruang Karya EO</span>
          <span class="trust__item">Selaras Event</span>
          <span class="trust__item">Cahaya Nusantara</span>
          <span class="trust__item">Meraki Organizer</span>
          <span class="trust__item">Bintang Acara</span>
          <span class="trust__item">Wedira Wedding</span>
          <span class="trust__item">Kilau Production</span>
          <span class="trust__item">Ruang Karya EO</span>
        </div>
      </div>
    </section>


    <!-- ═══════════════════════════════════════════════════
         FITUR UNGGULAN
    ═══════════════════════════════════════════════════ -->
    <section class="features" id="fitur" aria-labelledby="features-title">
      <div class="section-container">

        <div class="section-header reveal">
          <p class="section-label">Fitur Unggulan</p>
          <h2 class="section-title" id="features-title">Semua yang kamu butuhkan,<br>dalam satu platform.</h2>
        </div>

        <div class="features__grid">

          <article class="feature-card">
            <div class="feature-card__icon" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="3" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.8"/>
                <rect x="14" y="3" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.8"/>
                <rect x="3" y="14" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.8"/>
                <rect x="14" y="14" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.8"/>
              </svg>
            </div>
            <h3 class="feature-card__title">Transaksi &amp; Approval</h3>
            <p class="feature-card__desc">Staff mencatat pemasukan dan pengeluaran lengkap dengan bukti, lalu Admin meninjau dan menyetujui sebelum tercatat final di laporan.</p>
          </article>

          <article class="feature-card">
            <div class="feature-card__icon" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 3v18h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M7 16l4-5 4 3 4-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <h3 class="feature-card__title">Laporan &amp; Invoice</h3>
            <p class="feature-card__desc">Laporan keuangan tiap event dihasilkan otomatis, siap dicetak atau diekspor. Invoice dan kalender event membantu tim tetap on-schedule.</p>
          </article>

          <article class="feature-card">
            <div class="feature-card__icon" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/>
                <circle cx="17" cy="9" r="2.5" stroke="currentColor" stroke-width="1.8"/>
                <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M17 14c1.7 0 4 .9 4 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </div>
            <h3 class="feature-card__title">Penugasan Staff</h3>
            <p class="feature-card__desc">Tugaskan staff ke event tertentu saja. Setiap staff hanya bisa mengakses dan mencatat transaksi pada event yang ditugaskan kepadanya.</p>
          </article>

          <article class="feature-card">
            <div class="feature-card__icon" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L4 6v6c0 5 3.6 9.7 8 11 4.4-1.3 8-6 8-11V6l-8-4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M12 8v4l2.5 2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </div>
            <h3 class="feature-card__title">Piutang &amp; Hutang</h3>
            <p class="feature-card__desc">Pantau tagihan klien yang belum terbayar dan kewajiban ke vendor dalam satu halaman, sehingga arus kas EO tetap terkendali.</p>
          </article>

          <article class="feature-card">
            <div class="feature-card__icon" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/>
                <path d="M7 11V7a5 5 0 0110 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <circle cx="12" cy="16" r="1.5" fill="currentColor"/>
              </svg>
            </div>
            <h3 class="feature-card__title">Klien &amp; Vendor</h3>
            <p class="feature-card__desc">Simpan data klien dan vendor dalam satu database, lengkap dengan riwayat kerja sama dan penilaian (review) performa vendor.</p>
          </article>

          <article class="feature-card">
            <div class="feature-card__icon" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="5" y="2" width="14" height="20" rx="3" stroke="currentColor" stroke-width="1.8"/>
                <path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </div>
            <h3 class="feature-card__title">Keamanan Berlapis</h3>
            <p class="feature-card__desc">Login dilindungi verifikasi dua langkah (2FA/OTP), dan setiap aktivitas penting tercatat rapi di audit log untuk transparansi penuh.</p>
          </article>

        </div>
      </div>
    </section>


    <!-- ═══════════════════════════════════════════════════
         ROLE CARDS — Admin EO vs Staff EO
    ═══════════════════════════════════════════════════ -->
    <section class="roles" id="tentang" aria-labelledby="roles-title">
      <div class="section-container">

        <div class="section-header reveal">
          <p class="section-label">Dua Role, Satu Platform</p>
          <h2 class="section-title" id="roles-title">Satu sistem, dua level akses<br>yang jelas batasannya.</h2>
        </div>

        <div class="roles__grid">

          <!-- Admin EO -->
          <article class="role-card role-card--admin">
            <div class="role-card__header">
              <div class="role-card__icon" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="14" cy="9" r="4" stroke="currentColor" stroke-width="1.8"/>
                  <path d="M5 24c0-5 4-9 9-9s9 4 9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <circle cx="22" cy="8" r="3" fill="currentColor" fill-opacity=".15" stroke="currentColor" stroke-width="1.5"/>
                  <path d="M21 8h2M22 7v2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
              </div>
              <div>
                <p class="role-card__badge">Pemilik EO</p>
                <h3 class="role-card__title">Admin EO</h3>
              </div>
            </div>
            <p class="role-card__desc">Daftar sendiri dan kelola seluruh operasional keuangan EO kamu dari satu tempat.</p>
            <ul class="role-card__list" role="list">
              <li class="role-card__item">
                <span class="role-card__check" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" fill="#0071E3"/><path d="M5 8l2.5 2.5L11 5.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                Membuat &amp; mengelola event, kalender, dan invoice
              </li>
              <li class="role-card__item">
                <span class="role-card__check" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" fill="#0071E3"/><path d="M5 8l2.5 2.5L11 5.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                Menyetujui (approval) transaksi yang dicatat staff
              </li>
              <li class="role-card__item">
                <span class="role-card__check" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" fill="#0071E3"/><path d="M5 8l2.5 2.5L11 5.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                Mengelola akun staff dan penugasan ke event
              </li>
              <li class="role-card__item">
                <span class="role-card__check" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" fill="#0071E3"/><path d="M5 8l2.5 2.5L11 5.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                Mengelola data klien, vendor, dan review vendor
              </li>
              <li class="role-card__item">
                <span class="role-card__check" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" fill="#0071E3"/><path d="M5 8l2.5 2.5L11 5.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                Memantau piutang, hutang, laporan, dan audit log
              </li>
            </ul>
            <a href="register.php" class="btn btn--primary role-card__cta">Daftar sebagai Admin</a>
          </article>

          <!-- Staff EO -->
          <article class="role-card role-card--staff">
            <div class="role-card__header">
              <div class="role-card__icon" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="14" cy="9" r="4" stroke="currentColor" stroke-width="1.8"/>
                  <path d="M5 24c0-5 4-9 9-9s9 4 9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </div>
              <div>
                <p class="role-card__badge">Karyawan EO</p>
                <h3 class="role-card__title">Staff EO</h3>
              </div>
            </div>
            <p class="role-card__desc">Akun dibuat oleh Admin. Staff hanya mengakses event tempat mereka ditugaskan.</p>
            <ul class="role-card__list" role="list">
              <li class="role-card__item">
                <span class="role-card__check" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" fill="#6B7280"/><path d="M5 8l2.5 2.5L11 5.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                Melihat detail event yang ditugaskan
              </li>
              <li class="role-card__item">
                <span class="role-card__check" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" fill="#6B7280"/><path d="M5 8l2.5 2.5L11 5.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                Mencatat transaksi &amp; mencetak struk, menunggu approval Admin
              </li>
              <li class="role-card__item">
                <span class="role-card__check" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" fill="#6B7280"/><path d="M5 8l2.5 2.5L11 5.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                Melihat ringkasan keuangan &amp; notifikasi event
              </li>
              <li class="role-card__item">
                <span class="role-card__check role-card__check--muted" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="#D1D5DB" stroke-width="1.2"/><path d="M5.5 8h5" stroke="#D1D5DB" stroke-width="1.5" stroke-linecap="round"/></svg>
                </span>
                <span class="role-card__item--muted">Tidak bisa mengakses event lain</span>
              </li>
              <li class="role-card__item">
                <span class="role-card__check role-card__check--muted" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="#D1D5DB" stroke-width="1.2"/><path d="M5.5 8h5" stroke="#D1D5DB" stroke-width="1.5" stroke-linecap="round"/></svg>
                </span>
                <span class="role-card__item--muted">Tidak bisa menyetujui transaksinya sendiri</span>
              </li>
            </ul>
            <a href="login.php?for=staff" class="btn btn--outline role-card__cta">Masuk sebagai Staff</a>
          </article>

        </div>
      </div>
    </section>


    <!-- ═══════════════════════════════════════════════════
         CARA MULAI — 3 Langkah
    ═══════════════════════════════════════════════════ -->
    <section class="steps" id="cara-kerja" aria-labelledby="steps-title">
      <div class="section-container">

        <div class="section-header reveal">
          <p class="section-label">Cara Kerja</p>
          <h2 class="section-title" id="steps-title">Mulai dalam tiga langkah.</h2>
        </div>

        <div class="steps__grid">

          <article class="step-card">
            <div class="step-card__number" aria-label="Langkah pertama">01</div>
            <div class="step-card__connector" aria-hidden="true"></div>
            <div class="step-card__icon" aria-hidden="true">
              <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="16" cy="12" r="5" stroke="currentColor" stroke-width="1.8"/>
                <path d="M7 28c0-5 4-9 9-9s9 4 9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M22 6l2 2 4-4" stroke="#0071E3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <h3 class="step-card__title">Daftar Akun</h3>
            <p class="step-card__desc">Daftar sebagai Admin EO secara gratis. Isi data EO kamu dan akun siap digunakan dalam hitungan detik.</p>
          </article>

          <article class="step-card">
            <div class="step-card__number" aria-label="Langkah kedua">02</div>
            <div class="step-card__connector" aria-hidden="true"></div>
            <div class="step-card__icon" aria-hidden="true">
              <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="4" y="6" width="24" height="22" rx="3" stroke="currentColor" stroke-width="1.8"/>
                <path d="M4 12h24" stroke="currentColor" stroke-width="1.8"/>
                <path d="M10 4v4M22 4v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M16 18v6M13 21h6" stroke="#0071E3" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </div>
            <h3 class="step-card__title">Buat Event</h3>
            <p class="step-card__desc">Tambahkan event baru, atur tanggal, anggaran, dan tugaskan staff yang akan terlibat di dalamnya.</p>
          </article>

          <article class="step-card">
            <div class="step-card__number" aria-label="Langkah ketiga">03</div>
            <div class="step-card__connector step-card__connector--last" aria-hidden="true"></div>
            <div class="step-card__icon" aria-hidden="true">
              <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 6h20v4H6z" rx="2" stroke="currentColor" stroke-width="1.8"/>
                <path d="M6 16h20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M6 22h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <circle cx="25" cy="24" r="4" fill="#0071E3"/>
                <path d="M23.5 24l1 1 2-2" stroke="white" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <h3 class="step-card__title">Catat Transaksi</h3>
            <p class="step-card__desc">Masukkan setiap pemasukan dan pengeluaran. Laporan keuangan event langsung terbentuk secara otomatis.</p>
          </article>

        </div>

      </div>
    </section>


    <!-- ═══════════════════════════════════════════════════
         FAQ
    ═══════════════════════════════════════════════════ -->
    <section class="faq" id="faq" aria-labelledby="faq-title">
      <div class="section-container">

        <div class="section-header reveal">
          <p class="section-label">Pertanyaan Umum</p>
          <h2 class="section-title" id="faq-title">Masih ada yang ingin ditanyakan?</h2>
        </div>

        <div class="faq__list reveal">

          <div class="faq-item">
            <button class="faq-item__q" type="button" aria-expanded="false">
              Apakah data keuangan tiap EO tercampur dengan EO lain?
              <span class="faq-item__icon" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
            </button>
            <div class="faq-item__a">
              <div class="faq-item__a-inner">Tidak. Setiap akun Admin EO memiliki ruang data yang terisolasi penuh — event, transaksi, klien, vendor, dan staff hanya bisa diakses oleh EO yang bersangkutan.</div>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-item__q" type="button" aria-expanded="false">
              Apakah staff bisa menyetujui transaksinya sendiri?
              <span class="faq-item__icon" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
            </button>
            <div class="faq-item__a">
              <div class="faq-item__a-inner">Tidak bisa. Transaksi yang dicatat staff wajib melalui proses approval Admin sebelum tercatat final di laporan keuangan, sehingga menjaga akurasi dan mencegah kesalahan pencatatan.</div>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-item__q" type="button" aria-expanded="false">
              Berapa jumlah event dan staff yang bisa dikelola?
              <span class="faq-item__icon" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
            </button>
            <div class="faq-item__a">
              <div class="faq-item__a-inner">Tidak ada batas jumlah event maupun staff. Admin bebas membuat event sebanyak yang dibutuhkan dan menugaskan staff sesuai kebutuhan operasional EO.</div>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-item__q" type="button" aria-expanded="false">
              Bagaimana keamanan akun dijaga di platform ini?
              <span class="faq-item__icon" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
            </button>
            <div class="faq-item__a">
              <div class="faq-item__a-inner">Login dilindungi verifikasi dua langkah (2FA/OTP). Semua aktivitas penting — dari perubahan data hingga approval transaksi — tercatat rapi di audit log yang bisa ditinjau kapan saja.</div>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-item__q" type="button" aria-expanded="false">
              Apakah laporan keuangan bisa diekspor atau dicetak?
              <span class="faq-item__icon" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
            </button>
            <div class="faq-item__a">
              <div class="faq-item__a-inner">Ya. Laporan keuangan tiap event, invoice, dan struk transaksi bisa dicetak langsung atau diunduh, sehingga mudah dibagikan ke klien maupun untuk keperluan arsip internal.</div>
            </div>
          </div>

        </div>
      </div>
    </section>


    <!-- ═══════════════════════════════════════════════════
         CTA BANNER — background biru
    ═══════════════════════════════════════════════════ -->
    <section class="cta-banner" aria-labelledby="cta-title">
      <div class="section-container">

        <div class="cta-banner__content reveal">
          <h2 class="cta-banner__title" id="cta-title">
            Siap mengelola keuangan event<br>
            lebih rapi dan profesional?
          </h2>
          <p class="cta-banner__desc">
            Bergabung dengan ratusan event organizer yang sudah mempercayakan<br>
            manajemen keuangan event mereka ke EO Finance.
          </p>
          <div class="cta-banner__actions">
            <a href="register.php" class="btn btn--white btn--large">Mulai gratis sekarang</a>
            <a href="login.php" class="btn btn--white-ghost btn--large">Sudah punya akun? Masuk</a>
          </div>
        </div>

        <!-- Dekorasi visual abstrak di dalam banner -->
        <div class="cta-banner__deco" aria-hidden="true">
          <div class="cta-banner__deco-circle cta-banner__deco-circle--1"></div>
          <div class="cta-banner__deco-circle cta-banner__deco-circle--2"></div>
          <div class="cta-banner__deco-circle cta-banner__deco-circle--3"></div>
        </div>

      </div>
    </section>

  </main>


  <!-- ═══════════════════════════════════════════════════
       FOOTER
  ═══════════════════════════════════════════════════ -->
  <footer class="footer footer--pro" role="contentinfo">
    <div class="footer__container">

      <div class="footer__top">

        <div class="footer__brand">
          <a href="index.php" class="navbar__brand" aria-label="EO Finance — Beranda">
            <span class="navbar__logo-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="28" height="28" rx="8" fill="#0071E3"/>
                <path d="M8 20L12 13L16 16.5L20 8" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="20" cy="8" r="2" fill="white"/>
              </svg>
            </span>
            <span class="navbar__brand-name">EO Finance</span>
          </a>
          <p class="footer__tagline">Platform manajemen keuangan event organizer yang modern, aman, dan mudah digunakan — dari pencatatan transaksi hingga laporan final.</p>
          <div class="footer__social" aria-label="Sosial media EO Finance">
            <a href="#" aria-label="Instagram EO Finance"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor"/></svg></a>
            <a href="#" aria-label="LinkedIn EO Finance"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M8 10.5v6M8 7.8v.2M12.5 16.5v-3.3c0-1.4 1-2.2 2.2-2.2 1.2 0 2 .8 2 2.2v3.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></a>
            <a href="#" aria-label="YouTube EO Finance"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="6" width="19" height="12" rx="4" stroke="currentColor" stroke-width="1.8"/><path d="M10.5 9.5l5 2.5-5 2.5z" fill="currentColor"/></svg></a>
          </div>
        </div>

        <div class="footer__col">
          <p class="footer__col-title">Produk</p>
          <div class="footer__col-list">
            <a href="#fitur">Fitur</a>
            <a href="#cara-kerja">Cara Kerja</a>
            <a href="register.php">Daftar sebagai Admin</a>
            <a href="login.php?for=staff">Masuk sebagai Staff</a>
          </div>
        </div>

        <div class="footer__col">
          <p class="footer__col-title">Perusahaan</p>
          <div class="footer__col-list">
            <a href="#tentang">Tentang Kami</a>
            <a href="#faq">FAQ</a>
            <a href="#">Karier</a>
            <a href="#">Blog</a>
          </div>
        </div>

        <div class="footer__col">
          <p class="footer__col-title">Bantuan</p>
          <div class="footer__col-list">
            <a href="#">Pusat Bantuan</a>
            <a href="#">Hubungi Kami</a>
            <a href="login.php">Masuk ke Akun</a>
            <a href="lupa-password.php">Lupa Password</a>
          </div>
        </div>

      </div>

      <div class="footer__bottom">
        <p class="footer__copy">&copy; 2026 EO Finance. Seluruh hak dilindungi.</p>
        <div class="footer__legal">
          <a href="#">Kebijakan Privasi</a>
          <a href="#">Syarat Layanan</a>
        </div>
      </div>

    </div>
  </footer>

  <script src="assets/js/landing.js" defer></script>
  <script src="assets/js/landing-pro.js" defer></script>

  <!-- FAQ accordion — inline supaya selalu berfungsi meskipun file JS eksternal gagal dimuat -->
  <script>
    (function () {
      function initFaq() {
        var items = document.querySelectorAll('.faq-item');
        items.forEach(function (item) {
          var question = item.querySelector('.faq-item__q');
          var answer = item.querySelector('.faq-item__a');
          if (!question || !answer || question.dataset.faqBound) return;
          question.dataset.faqBound = '1';

          question.addEventListener('click', function () {
            var alreadyOpen = item.classList.contains('is-open');

            items.forEach(function (other) {
              other.classList.remove('is-open');
              var otherAnswer = other.querySelector('.faq-item__a');
              var otherQuestion = other.querySelector('.faq-item__q');
              if (otherAnswer) otherAnswer.style.maxHeight = null;
              if (otherQuestion) otherQuestion.setAttribute('aria-expanded', 'false');
            });

            if (!alreadyOpen) {
              item.classList.add('is-open');
              answer.style.maxHeight = answer.scrollHeight + 'px';
              question.setAttribute('aria-expanded', 'true');
            }
          });
        });

        window.addEventListener('resize', function () {
          var openAnswer = document.querySelector('.faq-item.is-open .faq-item__a');
          if (openAnswer) openAnswer.style.maxHeight = openAnswer.scrollHeight + 'px';
        });
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFaq);
      } else {
        initFaq();
      }
    })();
  </script>
</body>
</html>