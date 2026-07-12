/**
 * validation.js — EO Finance
 * Validasi form sisi klien: cek field wajib,
 * format email, kesesuaian password, dan
 * feedback error inline sebelum submit.
 *
 * CATATAN PENTING TENTANG FORM ID:
 * Tidak ada satu pun form di proyek ini yang punya atribut id pada tag <form>.
 * Strategi identifikasi form:
 *   - Halaman auth   → selector class "form.auth-form" (satu per halaman)
 *   - Halaman admin  → identifikasi via field unik di dalam form
 *                      (misal form yang punya #nama_event = form event)
 *
 * CSS class yang dipakai (sesuai style.css):
 *   .form-input--error  → border merah pada input bermasalah
 *   .form-error-msg     → teks error di bawah input
 *
 * CSS class password strength (sesuai auth.css):
 *   .auth-password-strength  → wrapper indikator
 *   .auth-strength-bars      → container bar-bar kecil
 *   .auth-strength-bar       → satu segmen bar
 *   .auth-strength-bar--{weak|fair|good|strong}
 *   .auth-strength-label     → teks keterangan
 *   .auth-strength-label--{weak|fair|good|strong}
 */

/* ============================================================
   A. FUNGSI HELPER DASAR
============================================================ */

/**
 * Tampilkan pesan error inline di bawah field.
 * Menambahkan class .form-input--error ke input dan
 * menyisipkan <span class="form-error-msg"> setelah elemen input.
 * Jika span sudah ada, teks-nya diperbarui (tidak duplikat).
 *
 * @param {string} fieldId  ID elemen input
 * @param {string} message  Teks pesan error
 */
function showError(fieldId, message) {
    var field = document.getElementById(fieldId);
    if (!field) return;

    field.classList.add('form-input--error');

    // Cari span error yang sudah ada (hindari duplikat)
    var existing = field.parentElement.querySelector('.form-error-msg[data-for="' + fieldId + '"]');
    if (existing) {
        existing.textContent = message;
        return;
    }

    var span = document.createElement('span');
    span.className   = 'form-error-msg';
    span.dataset.for = fieldId;   // penanda agar tidak duplikat
    span.setAttribute('role', 'alert');
    span.setAttribute('aria-live', 'polite');
    span.textContent = message;

    // Sisipkan tepat setelah elemen field dalam parent yang sama
    field.insertAdjacentElement('afterend', span);
}

/**
 * Hapus pesan error dari sebuah field.
 * Menghapus class .form-input--error dan span .form-error-msg terkait.
 *
 * @param {string} fieldId  ID elemen input
 */
function clearError(fieldId) {
    var field = document.getElementById(fieldId);
    if (!field) return;

    field.classList.remove('form-input--error');

    var span = field.parentElement.querySelector('.form-error-msg[data-for="' + fieldId + '"]');
    if (span) span.remove();
}

/**
 * Hapus semua pesan error di dalam sebuah form.
 * Berguna sebelum re-validasi saat submit ulang.
 *
 * @param {HTMLFormElement} form  Elemen form (bukan ID — lihat catatan di atas)
 */
function clearAllErrors(form) {
    if (!form) return;

    // Hapus class error dari semua input
    form.querySelectorAll('.form-input--error').forEach(function (el) {
        el.classList.remove('form-input--error');
    });

    // Hapus semua span pesan error
    form.querySelectorAll('.form-error-msg').forEach(function (el) {
        el.remove();
    });
}

/**
 * Validasi format email dengan regex sederhana.
 * Cukup untuk feedback UX — validasi ketat tetap di server.
 *
 * @param  {string}  email
 * @return {boolean}
 */
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
}

/**
 * Validasi format nomor telepon Indonesia.
 * Menerima: awalan +62 atau 0, total 10–15 digit, boleh ada tanda -
 *
 * @param  {string}  phone
 * @return {boolean}
 */
function isValidPhone(phone) {
    var stripped = phone.replace(/[\s\-]/g, '');   // hapus spasi dan tanda hubung
    return /^(\+62|62|0)[0-9]{8,13}$/.test(stripped);
}

/**
 * Ukur kekuatan password dan kembalikan skor + pesan.
 * Skor: 0 = terlalu pendek, 1 = lemah, 2 = sedang, 3 = kuat
 *
 * Kriteria:
 *   - Minimal 8 karakter
 *   - Ada huruf kapital
 *   - Ada angka
 *   - Ada karakter khusus (bonus → skor 3)
 *
 * @param  {string} password
 * @return {{ score: number, level: string, message: string }}
 */
function isStrongPassword(password) {
    if (password.length < 8) {
        return { score: 0, level: 'weak', message: 'Terlalu pendek (min. 8 karakter)' };
    }

    var hasUpper   = /[A-Z]/.test(password);
    var hasNumber  = /[0-9]/.test(password);
    var hasSpecial = /[^A-Za-z0-9]/.test(password);

    var score = 0;
    if (hasUpper)   score++;
    if (hasNumber)  score++;
    if (hasSpecial) score++;

    // Normalkan: 0 skor terpenuhi = lemah (1), dst.
    if (score === 0) return { score: 1, level: 'weak',   message: 'Lemah — tambahkan huruf kapital dan angka' };
    if (score === 1) return { score: 1, level: 'weak',   message: 'Lemah — tambahkan huruf kapital atau angka' };
    if (score === 2) return { score: 2, level: 'fair',   message: 'Sedang — tambahkan karakter khusus (!@#$…)' };
    return                 { score: 3, level: 'strong', message: 'Kuat' };
}


/* ============================================================
   B. INDIKATOR KEKUATAN PASSWORD
   Dibuat secara dinamis via JS — tidak perlu ubah HTML PHP.
============================================================ */

/**
 * Buat elemen HTML indikator kekuatan password dan sisipkan
 * setelah field yang diberikan. Dipanggil sekali saat setup.
 * Kembalikan referensi ke elemen bars dan label agar bisa diupdate.
 *
 * @param  {HTMLElement} passwordField  Elemen input password
 * @return {{ bars: NodeList-like, label: HTMLElement } | null}
 */
function createStrengthIndicator(passwordField) {
    if (!passwordField) return null;

    // Hindari duplikat jika fungsi dipanggil ulang
    var existing = document.getElementById('strength-' + passwordField.id);
    if (existing) existing.remove();

    var wrapper = document.createElement('div');
    wrapper.className = 'auth-password-strength';
    wrapper.id = 'strength-' + passwordField.id;

    // Tiga segmen bar
    var barsDiv = document.createElement('div');
    barsDiv.className = 'auth-strength-bars';
    for (var i = 0; i < 3; i++) {
        var bar = document.createElement('div');
        bar.className = 'auth-strength-bar';
        barsDiv.appendChild(bar);
    }

    var label = document.createElement('span');
    label.className = 'auth-strength-label';

    wrapper.appendChild(barsDiv);
    wrapper.appendChild(label);

    // Sisipkan setelah input (sebelum .form-hint jika ada)
    passwordField.insertAdjacentElement('afterend', wrapper);

    return {
        bars:  barsDiv.querySelectorAll('.auth-strength-bar'),
        label: label
    };
}

/**
 * Perbarui tampilan indikator kekuatan berdasarkan nilai password.
 * Mewarnai bar dan label sesuai level: weak / fair / good / strong.
 *
 * @param {HTMLElement} passwordField
 * @param {{ bars, label }} indicator  Hasil createStrengthIndicator()
 */
function updateStrengthIndicator(passwordField, indicator) {
    if (!passwordField || !indicator) return;

    var val    = passwordField.value;
    var result = isStrongPassword(val);
    var bars   = indicator.bars;
    var label  = indicator.label;

    // Sembunyikan indikator jika password masih kosong
    var wrapper = document.getElementById('strength-' + passwordField.id);
    if (wrapper) {
        wrapper.style.display = val.length > 0 ? '' : 'none';
    }

    // Reset semua bar ke warna default
    var levels = ['weak', 'fair', 'good', 'strong'];
    bars.forEach(function (bar) {
        levels.forEach(function (lv) { bar.classList.remove('auth-strength-bar--' + lv); });
    });
    levels.forEach(function (lv) { label.classList.remove('auth-strength-label--' + lv); });

    if (val.length === 0) {
        label.textContent = '';
        return;
    }

    // Warnai bar sesuai skor: skor 1 → 1 bar, 2 → 2 bar, 3 → 3 bar
    var levelClass = result.score === 3 ? 'strong' : (result.score === 2 ? 'fair' : 'weak');
    for (var i = 0; i < result.score; i++) {
        if (bars[i]) bars[i].classList.add('auth-strength-bar--' + levelClass);
    }

    label.className   = 'auth-strength-label auth-strength-label--' + levelClass;
    label.textContent = result.message;
}


/* ============================================================
   C. HELPER TOGGLE FIELD DESKRIPSI (KATEGORI LAIN-LAIN)
   Dipakai oleh validasi transaksi admin DAN staff.
============================================================ */

/**
 * Tampilkan atau sembunyikan field deskripsi berdasarkan
 * nilai input kategori. Jika nilai mengandung "lain",
 * deskripsi menjadi wajib; jika tidak, menjadi opsional.
 *
 * @param {HTMLElement} kategoriEl   Input #kategori
 * @param {HTMLElement} deskripsiEl  Textarea #deskripsi
 */
function toggleDeskripsiKategori(kategoriEl, deskripsiEl) {
    if (!kategoriEl || !deskripsiEl) return;

    var val        = kategoriEl.value.trim().toLowerCase();
    var isLainLain = val === 'lain-lain' || val === 'lainnya' || val === 'lain lain';
    var group      = deskripsiEl.closest('.form-group');

    if (isLainLain) {
        if (group) group.style.display = '';
        deskripsiEl.required    = true;
        deskripsiEl.placeholder = 'Jelaskan kategori pengeluaran ini (wajib diisi)';
        // Tambahkan tanda * ke label jika belum ada
        var lbl = group ? group.querySelector('label') : null;
        if (lbl && !lbl.querySelector('.form-label__required')) {
            var star = document.createElement('span');
            star.className   = 'form-label__required';
            star.setAttribute('aria-hidden', 'true');
            star.textContent = ' *';
            lbl.appendChild(star);
        }
    } else {
        if (group) group.style.display = '';  // tetap tampil, tapi tidak wajib
        deskripsiEl.required    = false;
        deskripsiEl.placeholder = 'Keterangan tambahan (opsional)';
        var lbl2 = group ? group.querySelector('.form-label__required') : null;
        if (lbl2) lbl2.remove();
    }
}


/* ============================================================
   D. HELPER DISABLE SUBMIT BUTTON
============================================================ */

/**
 * Nonaktifkan / aktifkan tombol submit dalam sebuah form.
 * Dipakai untuk mencegah double-submit saat ada error.
 *
 * @param {HTMLFormElement} form
 * @param {boolean}         disabled
 */
function setSubmitDisabled(form, disabled) {
    if (!form) return;
    var btn = form.querySelector('[type="submit"]');
    if (btn) {
        btn.disabled = disabled;
        btn.style.opacity = disabled ? '0.6' : '';
        btn.style.cursor  = disabled ? 'not-allowed' : '';
    }
}


/* ============================================================
   E. VALIDASI FORM AUTENTIKASI
============================================================ */

/**
 * Validasi form login (form.auth-form di login.php).
 * Field: #email, #password
 *
 * @param  {Event} e  Submit event
 * @return {boolean}
 */
function validateLogin(e) {
    var form = e.target;
    clearAllErrors(form);
    var valid = true;

    var email    = document.getElementById('email');
    var password = document.getElementById('password');

    if (!email || !email.value.trim()) {
        showError('email', 'Email tidak boleh kosong.');
        valid = false;
    } else if (!isValidEmail(email.value)) {
        showError('email', 'Format email tidak valid.');
        valid = false;
    }

    if (!password || !password.value) {
        showError('password', 'Password tidak boleh kosong.');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        setSubmitDisabled(form, false);  // biarkan user coba lagi
    }
    return valid;
}

/**
 * Validasi form registrasi (form.auth-form di register.php).
 * Field: #nama, #email, #password, #konfirmasi_password,
 *        #nama_eo, #telepon, #alamat_eo
 *
 * @param  {Event} e
 * @return {boolean}
 */
function validateRegister(e) {
    var form = e.target;
    clearAllErrors(form);
    var valid = true;

    var nama      = document.getElementById('nama');
    var email     = document.getElementById('email');
    var password  = document.getElementById('password');
    var konfirmasi = document.getElementById('konfirmasi_password');
    var namaEo    = document.getElementById('nama_eo');
    var telepon   = document.getElementById('telepon');
    var alamatEo  = document.getElementById('alamat_eo');

    // Nama lengkap
    if (!nama || nama.value.trim().length < 3) {
        showError('nama', 'Nama lengkap minimal 3 karakter.');
        valid = false;
    }

    // Email
    if (!email || !email.value.trim()) {
        showError('email', 'Email tidak boleh kosong.');
        valid = false;
    } else if (!isValidEmail(email.value)) {
        showError('email', 'Format email tidak valid.');
        valid = false;
    }

    // Password
    if (!password || !password.value) {
        showError('password', 'Password tidak boleh kosong.');
        valid = false;
    } else if (password.value.length < 8) {
        showError('password', 'Password minimal 8 karakter.');
        valid = false;
    }

    // Konfirmasi password
    if (!konfirmasi || !konfirmasi.value) {
        showError('konfirmasi_password', 'Konfirmasi password tidak boleh kosong.');
        valid = false;
    } else if (password && konfirmasi.value !== password.value) {
        showError('konfirmasi_password', 'Konfirmasi password tidak cocok.');
        valid = false;
    }

    // Nama EO
    if (!namaEo || !namaEo.value.trim()) {
        showError('nama_eo', 'Nama EO tidak boleh kosong.');
        valid = false;
    }

    // Alamat EO
    if (!alamatEo || !alamatEo.value.trim()) {
        showError('alamat_eo', 'Alamat EO tidak boleh kosong.');
        valid = false;
    }

    // Telepon (opsional — hanya validasi jika diisi)
    if (telepon && telepon.value.trim() && !isValidPhone(telepon.value)) {
        showError('telepon', 'Format telepon tidak valid. Contoh: 0812345678 atau +628123456789');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        setSubmitDisabled(form, false);
    }
    return valid;
}

/**
 * Validasi form lupa password (form.auth-form di lupa-password.php).
 * Field: #email
 *
 * @param  {Event} e
 * @return {boolean}
 */
function validateLupaPassword(e) {
    var form = e.target;
    clearAllErrors(form);
    var valid = true;

    var email = document.getElementById('email');

    if (!email || !email.value.trim()) {
        showError('email', 'Email tidak boleh kosong.');
        valid = false;
    } else if (!isValidEmail(email.value)) {
        showError('email', 'Format email tidak valid.');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        setSubmitDisabled(form, false);
    }
    return valid;
}

/**
 * Validasi form verifikasi OTP (form.auth-form di verifikasi-otp.php).
 * Field: #kode_otp — harus tepat 6 digit angka
 *
 * @param  {Event} e
 * @return {boolean}
 */
function validateVerifikasiOtp(e) {
    var form = e.target;
    clearAllErrors(form);
    var valid = true;

    var kode = document.getElementById('kode_otp');

    if (!kode || !kode.value.trim()) {
        showError('kode_otp', 'Kode OTP tidak boleh kosong.');
        valid = false;
    } else if (!/^\d{6}$/.test(kode.value.trim())) {
        showError('kode_otp', 'Kode OTP harus tepat 6 digit angka.');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        setSubmitDisabled(form, false);
    }
    return valid;
}

/**
 * Validasi form reset password (form.auth-form di reset-password.php).
 * Field: #password_baru, #konfirmasi_password
 *
 * @param  {Event} e
 * @return {boolean}
 */
function validateResetPassword(e) {
    var form = e.target;
    clearAllErrors(form);
    var valid = true;

    var passwordBaru  = document.getElementById('password_baru');
    var konfirmasi    = document.getElementById('konfirmasi_password');

    if (!passwordBaru || !passwordBaru.value) {
        showError('password_baru', 'Password baru tidak boleh kosong.');
        valid = false;
    } else if (passwordBaru.value.length < 8) {
        showError('password_baru', 'Password minimal 8 karakter.');
        valid = false;
    }

    if (!konfirmasi || !konfirmasi.value) {
        showError('konfirmasi_password', 'Konfirmasi password tidak boleh kosong.');
        valid = false;
    } else if (passwordBaru && konfirmasi.value !== passwordBaru.value) {
        showError('konfirmasi_password', 'Konfirmasi password tidak cocok.');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        setSubmitDisabled(form, false);
    }
    return valid;
}

/**
 * Validasi form ganti password staff (di staff/ganti-password.php).
 * Field: #password_lama, #password_baru, #konfirmasi_password
 * Identik dengan reset password, ditambah cek password lama.
 *
 * @param  {Event} e
 * @return {boolean}
 */
function validateGantiPassword(e) {
    var form = e.target;
    clearAllErrors(form);
    var valid = true;

    var passwordLama  = document.getElementById('password_lama');
    var passwordBaru  = document.getElementById('password_baru');
    var konfirmasi    = document.getElementById('konfirmasi_password');

    if (passwordLama && !passwordLama.value) {
        showError('password_lama', 'Password lama tidak boleh kosong.');
        valid = false;
    }

    if (!passwordBaru || !passwordBaru.value) {
        showError('password_baru', 'Password baru tidak boleh kosong.');
        valid = false;
    } else if (passwordBaru.value.length < 8) {
        showError('password_baru', 'Password minimal 8 karakter.');
        valid = false;
    }

    if (!konfirmasi || !konfirmasi.value) {
        showError('konfirmasi_password', 'Konfirmasi password tidak boleh kosong.');
        valid = false;
    } else if (passwordBaru && konfirmasi.value !== passwordBaru.value) {
        showError('konfirmasi_password', 'Konfirmasi password tidak cocok.');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        setSubmitDisabled(form, false);
    }
    return valid;
}


/* ============================================================
   F. VALIDASI FORM ADMIN
============================================================ */

/**
 * Validasi form event (admin/events/tambah.php & edit.php).
 * Diidentifikasi dari keberadaan field #nama_event di halaman.
 * Field: #nama_event, #tanggal_mulai, #tanggal_selesai, #anggaran
 *
 * @param  {Event} e
 * @return {boolean}
 */
function validateTambahEvent(e) {
    var form = e.target;
    clearAllErrors(form);
    var valid = true;

    var namaEvent      = document.getElementById('nama_event');
    var tanggalMulai   = document.getElementById('tanggal_mulai');
    var tanggalSelesai = document.getElementById('tanggal_selesai');
    var anggaran       = document.getElementById('anggaran');

    if (!namaEvent || !namaEvent.value.trim()) {
        showError('nama_event', 'Nama event tidak boleh kosong.');
        valid = false;
    }

    if (!tanggalMulai || !tanggalMulai.value) {
        showError('tanggal_mulai', 'Tanggal mulai tidak boleh kosong.');
        valid = false;
    }

    if (!tanggalSelesai || !tanggalSelesai.value) {
        showError('tanggal_selesai', 'Tanggal selesai tidak boleh kosong.');
        valid = false;
    } else if (tanggalMulai && tanggalMulai.value &&
               tanggalSelesai.value < tanggalMulai.value) {
        showError('tanggal_selesai', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        valid = false;
    }

    var nilaiAnggaran = parseFloat(anggaran ? anggaran.value : '');
    if (!anggaran || !anggaran.value || isNaN(nilaiAnggaran) || nilaiAnggaran <= 0) {
        showError('anggaran', 'Anggaran harus berupa angka lebih dari 0.');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        setSubmitDisabled(form, false);
    }
    return valid;
}

/**
 * Validasi form transaksi admin (admin/transaksi/tambah.php & edit.php).
 * Diidentifikasi dari keberadaan field #event_id DAN #tanggal_transaksi.
 * Field: #event_id, tipe (radio), #kategori, #jumlah, #tanggal_transaksi
 *
 * @param  {Event} e
 * @return {boolean}
 */
function validateTambahTransaksi(e) {
    var form = e.target;
    clearAllErrors(form);
    var valid = true;

    var eventId           = document.getElementById('event_id');
    var kategori          = document.getElementById('kategori');
    var jumlah            = document.getElementById('jumlah');
    var tanggalTransaksi  = document.getElementById('tanggal_transaksi');
    var deskripsi         = document.getElementById('deskripsi');

    // Event harus dipilih (nilai kosong = opsi default)
    if (!eventId || !eventId.value) {
        showError('event_id', 'Event harus dipilih.');
        valid = false;
    }

    // Tipe transaksi — radio button, tidak punya ID tunggal
    // Gunakan name="tipe" dan cek apakah ada yang checked
    var tipeChecked = form.querySelector('input[name="tipe"]:checked');
    if (!tipeChecked) {
        // Tipe tidak punya ID, tampilkan error di elemen terdekat
        var tipeContainer = form.querySelector('.tipe-toggle, [name="tipe"]');
        if (tipeContainer) {
            var tipeParent = tipeContainer.closest('.form-group');
            if (tipeParent && !tipeParent.querySelector('.form-error-msg')) {
                var errSpan = document.createElement('span');
                errSpan.className = 'form-error-msg';
                errSpan.setAttribute('role', 'alert');
                errSpan.textContent = 'Tipe transaksi harus dipilih.';
                tipeParent.appendChild(errSpan);
            }
        }
        valid = false;
    }

    // Kategori
    if (!kategori || !kategori.value.trim()) {
        showError('kategori', 'Kategori tidak boleh kosong.');
        valid = false;
    } else {
        // Toggle deskripsi wajib jika kategori Lain-lain
        toggleDeskripsiKategori(kategori, deskripsi);

        // Validasi deskripsi jika sedang wajib
        var valKat = kategori.value.trim().toLowerCase();
        var isLain = valKat === 'lain-lain' || valKat === 'lainnya' || valKat === 'lain lain';
        if (isLain && deskripsi && !deskripsi.value.trim()) {
            showError('deskripsi', 'Deskripsi wajib diisi untuk kategori Lain-lain.');
            valid = false;
        }
    }

    // Jumlah
    var nilaiJumlah = parseFloat(jumlah ? jumlah.value : '');
    if (!jumlah || !jumlah.value || isNaN(nilaiJumlah) || nilaiJumlah <= 0) {
        showError('jumlah', 'Jumlah harus berupa angka lebih dari 0.');
        valid = false;
    }

    // Tanggal transaksi
    if (!tanggalTransaksi || !tanggalTransaksi.value) {
        showError('tanggal_transaksi', 'Tanggal transaksi tidak boleh kosong.');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        setSubmitDisabled(form, false);
    }
    return valid;
}

/**
 * Validasi form staff (admin/staff/tambah.php & edit.php).
 * Diidentifikasi dari keberadaan field #password_sementara
 * (tambah.php) atau hanya #nama + #email (edit.php).
 * Field: #nama, #email, #password_sementara (hanya tambah)
 *
 * @param  {Event} e
 * @return {boolean}
 */
function validateTambahStaff(e) {
    var form = e.target;
    clearAllErrors(form);
    var valid = true;

    var nama              = document.getElementById('nama');
    var email             = document.getElementById('email');
    var passwordSementara = document.getElementById('password_sementara');  // null di edit.php

    // Nama
    if (!nama || nama.value.trim().length < 3) {
        showError('nama', 'Nama minimal 3 karakter.');
        valid = false;
    }

    // Email
    if (!email || !email.value.trim()) {
        showError('email', 'Email tidak boleh kosong.');
        valid = false;
    } else if (!isValidEmail(email.value)) {
        showError('email', 'Format email tidak valid.');
        valid = false;
    }

    // Password sementara (hanya ada di tambah.php — tidak ada di edit.php)
    if (passwordSementara) {
        if (!passwordSementara.value.trim()) {
            showError('password_sementara', 'Password sementara tidak boleh kosong.');
            valid = false;
        } else if (passwordSementara.value.trim().length < 8) {
            showError('password_sementara', 'Password minimal 8 karakter.');
            valid = false;
        }
    }

    if (!valid) {
        e.preventDefault();
        setSubmitDisabled(form, false);
    }
    return valid;
}

/**
 * Validasi form vendor (admin/vendor/tambah.php & edit.php).
 * Field wajib: #nama. Opsional: #email, #telepon
 *
 * @param  {Event} e
 * @return {boolean}
 */
function validateTambahVendor(e) {
    var form = e.target;
    clearAllErrors(form);
    var valid = true;

    var nama    = document.getElementById('nama');
    var email   = document.getElementById('email');
    var telepon = document.getElementById('telepon');

    if (!nama || !nama.value.trim()) {
        showError('nama', 'Nama vendor tidak boleh kosong.');
        valid = false;
    }

    if (email && email.value.trim() && !isValidEmail(email.value)) {
        showError('email', 'Format email tidak valid.');
        valid = false;
    }

    if (telepon && telepon.value.trim() && !isValidPhone(telepon.value)) {
        showError('telepon', 'Format telepon tidak valid. Contoh: 0812345678');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        setSubmitDisabled(form, false);
    }
    return valid;
}

/**
 * Validasi form klien (admin/klien/tambah.php & edit.php).
 * Struktur identik dengan vendor.
 * Field wajib: #nama. Opsional: #email, #telepon
 *
 * @param  {Event} e
 * @return {boolean}
 */
function validateTambahKlien(e) {
    var form = e.target;
    clearAllErrors(form);
    var valid = true;

    var nama    = document.getElementById('nama');
    var email   = document.getElementById('email');
    var telepon = document.getElementById('telepon');

    if (!nama || !nama.value.trim()) {
        showError('nama', 'Nama klien tidak boleh kosong.');
        valid = false;
    }

    if (email && email.value.trim() && !isValidEmail(email.value)) {
        showError('email', 'Format email tidak valid.');
        valid = false;
    }

    if (telepon && telepon.value.trim() && !isValidPhone(telepon.value)) {
        showError('telepon', 'Format telepon tidak valid. Contoh: 0812345678');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        setSubmitDisabled(form, false);
    }
    return valid;
}


/* ============================================================
   G. VALIDASI FORM STAFF
============================================================ */

/**
 * Validasi form input transaksi staff (staff/transaksi/tambah.php).
 * Diidentifikasi dari keberadaan field #event_id di halaman staff.
 * Logika identik dengan validateTambahTransaksi (admin).
 *
 * @param  {Event} e
 * @return {boolean}
 */
function validateInputTransaksi(e) {
    // Delegasikan ke fungsi admin — struktur form identik
    return validateTambahTransaksi(e);
}


/* ============================================================
   H. HELPER PREVIEW RUPIAH
   Membuat dan mengupdate elemen preview di bawah input #jumlah.
   Dipakai oleh form transaksi admin dan staff.
============================================================ */

/**
 * Buat elemen preview format Rupiah di bawah input #jumlah.
 * Hanya dibuat sekali — tidak duplikat jika dipanggil ulang.
 */
function initPreviewJumlah() {
    var inputJumlah = document.getElementById('jumlah');
    if (!inputJumlah) return;

    // Jangan buat ulang jika sudah ada (misal dari staff.js)
    if (document.getElementById('jumlah-preview')) return;

    var preview = document.createElement('span');
    preview.id  = 'jumlah-preview';
    preview.setAttribute('aria-live', 'polite');
    preview.setAttribute('aria-atomic', 'true');
    preview.style.cssText = [
        'display:block',
        'margin-top:4px',
        'font-size:13px',
        'font-weight:600',
        'color:var(--primary, #6366f1)',
        'min-height:18px',
        'transition:opacity 0.15s',
    ].join(';');

    inputJumlah.insertAdjacentElement('afterend', preview);

    function updatePreview() {
        var raw = parseFloat(inputJumlah.value);
        if (!inputJumlah.value || isNaN(raw) || raw <= 0) {
            preview.textContent = '';
            preview.style.opacity = '0';
            return;
        }
        var fmt = (typeof window.formatRupiah === 'function')
                      ? window.formatRupiah(raw)
                      : 'Rp ' + raw.toLocaleString('id-ID');
        preview.textContent   = fmt;
        preview.style.opacity = '1';
    }

    inputJumlah.addEventListener('input', updatePreview);
    updatePreview();  // jalankan saat load untuk halaman edit
}


/* ============================================================
   I. INISIALISASI EVENT LISTENER
============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    /* ----------------------------------------------------------
       STRATEGI IDENTIFIKASI FORM
       Karena tidak ada id pada tag <form>, kita identifikasi
       dari field unik yang hanya ada di halaman tersebut.
    ---------------------------------------------------------- */

    /**
     * Kembalikan form yang mengandung field dengan id tertentu,
     * atau null jika field/form tidak ditemukan.
     *
     * @param  {string} fieldId
     * @return {HTMLFormElement|null}
     */
    function getFormByField(fieldId) {
        var field = document.getElementById(fieldId);
        return field ? field.closest('form') : null;
    }

    /**
     * Attach submit handler ke form jika form ditemukan.
     *
     * @param {HTMLFormElement|null} form
     * @param {Function}             handler
     */
    function attachSubmit(form, handler) {
        if (!form) return;
        form.addEventListener('submit', handler);
    }

    /* ----------------------------------------------------------
       AUTH FORMS — semua pakai class .auth-form, satu per halaman
    ---------------------------------------------------------- */

    // Deteksi halaman auth via field unik
    var authForm = document.querySelector('form.auth-form');

    if (authForm) {
        // login.php         → ada #email + #password, tidak ada #nama
        var isLogin    = !!document.getElementById('password')
                         && !document.getElementById('nama')
                         && !document.getElementById('kode_otp')
                         && !document.getElementById('password_baru')
                         && !document.getElementById('password_lama');

        // register.php      → ada #nama_eo
        var isRegister = !!document.getElementById('nama_eo');

        // lupa-password.php → ada #email, tidak ada #password dan #kode_otp
        var isLupa     = !!document.getElementById('email')
                         && !document.getElementById('password')
                         && !document.getElementById('kode_otp');

        // verifikasi-otp.php → ada #kode_otp
        var isOtp      = !!document.getElementById('kode_otp');

        // reset-password.php → ada #password_baru, tidak ada #password_lama
        var isReset    = !!document.getElementById('password_baru')
                         && !document.getElementById('password_lama');

        // ganti-password.php → ada #password_lama
        var isGanti    = !!document.getElementById('password_lama');

        if (isRegister)    attachSubmit(authForm, validateRegister);
        else if (isOtp)    attachSubmit(authForm, validateVerifikasiOtp);
        else if (isGanti)  attachSubmit(authForm, validateGantiPassword);
        else if (isReset)  attachSubmit(authForm, validateResetPassword);
        else if (isLupa)   attachSubmit(authForm, validateLupaPassword);
        else if (isLogin)  attachSubmit(authForm, validateLogin);
    }

    /* ----------------------------------------------------------
       ADMIN FORMS — identifikasi via field unik dalam form
    ---------------------------------------------------------- */

    // Form event: field unik #nama_event
    attachSubmit(getFormByField('nama_event'),        validateTambahEvent);

    // Form transaksi admin: field unik #tanggal_transaksi (juga ada di staff,
    // tapi admin form punya action ke proses/admin/..., cukup cek presence-nya)
    // Karena bisa ada di admin DAN staff, kita pakai action URL untuk membedakan
    var formTrxAdmin = getFormByField('tanggal_transaksi');
    if (formTrxAdmin) {
        var actionTrx = (formTrxAdmin.action || '').toLowerCase();
        if (actionTrx.indexOf('/admin/') !== -1) {
            attachSubmit(formTrxAdmin, validateTambahTransaksi);
        }
    }

    // Form staff (admin): field unik #password_sementara (tambah) atau
    // form yang punya #nama + action proses-staff.php
    var formStaff = getFormByField('password_sementara');
    if (!formStaff) {
        // edit.php: tidak ada #password_sementara, tapi action ke proses-staff
        var namaField = document.getElementById('nama');
        if (namaField) {
            var candidate = namaField.closest('form');
            if (candidate && (candidate.action || '').indexOf('proses-staff') !== -1) {
                formStaff = candidate;
            }
        }
    }
    attachSubmit(formStaff, validateTambahStaff);

    // Form vendor: field unik #kategori_layanan
    attachSubmit(getFormByField('kategori_layanan'), validateTambahVendor);

    // Form klien: tidak punya field seseunik vendor, pakai action URL
    var namaField2 = document.getElementById('nama');
    if (namaField2) {
        var formKlienCandidate = namaField2.closest('form');
        if (formKlienCandidate && (formKlienCandidate.action || '').indexOf('proses-klien') !== -1) {
            attachSubmit(formKlienCandidate, validateTambahKlien);
        }
    }

    /* ----------------------------------------------------------
       STAFF FORMS — form transaksi staff via action URL
    ---------------------------------------------------------- */
    var formTrxStaff = getFormByField('tanggal_transaksi');
    if (formTrxStaff) {
        var actionStaff = (formTrxStaff.action || '').toLowerCase();
        if (actionStaff.indexOf('/staff/') !== -1) {
            attachSubmit(formTrxStaff, validateInputTransaksi);
        }
    }

    /* ----------------------------------------------------------
       REAL-TIME LISTENERS
    ---------------------------------------------------------- */

    // Password strength indicator — register.php
    var pwdRegister = document.getElementById('password');
    if (pwdRegister && document.getElementById('nama_eo')) {
        var indRegister = createStrengthIndicator(pwdRegister);
        if (indRegister) {
            // Sembunyikan saat pertama load
            var wrapReg = document.getElementById('strength-password');
            if (wrapReg) wrapReg.style.display = 'none';
        }
        pwdRegister.addEventListener('input', function () {
            updateStrengthIndicator(pwdRegister, indRegister);
        });
    }

    // Password strength indicator — reset-password.php dan ganti-password.php
    var pwdBaru = document.getElementById('password_baru');
    if (pwdBaru) {
        var indBaru = createStrengthIndicator(pwdBaru);
        if (indBaru) {
            var wrapBaru = document.getElementById('strength-password_baru');
            if (wrapBaru) wrapBaru.style.display = 'none';
        }
        pwdBaru.addEventListener('input', function () {
            updateStrengthIndicator(pwdBaru, indBaru);
        });
    }

    // Konfirmasi password real-time — hapus error begitu user mengetik lagi
    var konfirmasiInput = document.getElementById('konfirmasi_password');
    if (konfirmasiInput) {
        konfirmasiInput.addEventListener('input', function () {
            // Tentukan field password referensi (bisa #password atau #password_baru)
            var pwdRef = document.getElementById('password_baru')
                         || document.getElementById('password');
            if (!pwdRef) return;

            if (this.value === pwdRef.value) {
                clearError('konfirmasi_password');
            } else if (this.value.length >= pwdRef.value.length) {
                // Tampilkan error hanya setelah user selesai mengetik panjang yang sama
                showError('konfirmasi_password', 'Password tidak cocok.');
            }
        });
    }

    // Hapus error real-time saat user mulai mengetik ulang di field manapun
    // (menggunakan event delegation agar mencakup semua input)
    document.addEventListener('input', function (e) {
        var target = e.target;
        if (!target.id) return;
        // Hanya hapus error jika field sedang dalam state error
        if (target.classList.contains('form-input--error') && target.value.trim()) {
            clearError(target.id);
        }
    });

    // Toggle deskripsi kategori Lain-lain — form transaksi (admin & staff)
    var kategoriInput = document.getElementById('kategori');
    var deskripsiInput = document.getElementById('deskripsi');
    if (kategoriInput && deskripsiInput) {
        // Cek kondisi awal (penting untuk edit.php)
        toggleDeskripsiKategori(kategoriInput, deskripsiInput);

        kategoriInput.addEventListener('input', function () {
            toggleDeskripsiKategori(kategoriInput, deskripsiInput);
        });
    }

    // Preview format Rupiah — form transaksi (admin & staff)
    // initPreviewJumlah() sudah cek internal apakah sudah dibuat oleh staff.js
    initPreviewJumlah();

}); // end DOMContentLoaded1