
<?php
session_start();

$is_login = isset($_SESSION['id_user']);
$nama = $_SESSION['nama'] ?? '';
$role = $_SESSION['role'] ?? '';

function dashboard_url_faq($role)
{
    switch ($role) {
        case 'admin': return 'admin/dashboard.php';
        case 'kasir': return 'kasir/dashboard.php';
        case 'pemilik': return 'pemilik/dashboard.php';
        case 'pelanggan': return 'pelanggan/dashboard.php';
        default: return 'auth/login.php';
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>FAQ - Pertanyaan Umum | Toko Malaya</title>

<style>

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f6f8fb;
    color: #1f2937;
    line-height: 1.7;
}

a {
    text-decoration: none;
}

/* ========================================
   NAVBAR
======================================== */

.navbar {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    padding: 0 6%;
}

.navbar-inner {
    max-width: 1200px;
    min-height: 72px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #153b63;
    flex-shrink: 0;
}

.brand-logo {
    width: 42px;
    height: 42px;
    object-fit: contain;
}

.brand-name {
    font-size: 20px;
    font-weight: 700;
}

.nav-menu {
    display: flex;
    align-items: center;
    gap: 24px;
}

.nav-menu a {
    color: #4b5563;
    font-size: 14px;
    font-weight: 400;
    transition: .2s;
}

.nav-menu a:hover {
    color: #1677d2;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 9px;
}

.user-name {
    max-width: 130px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 17px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    transition: .2s;
}

.btn-login {
    color: #1677d2;
    border: 1px solid #1677d2;
}

.btn-login:hover {
    background: #eff6ff;
}

.btn-primary {
    background: #1677d2;
    color: #fff;
    border: 1px solid #1677d2;
}

.btn-primary:hover {
    background: #105fae;
}

.btn-logout {
    background: #f3f4f6;
    color: #374151;
}

/* ========================================
   HERO
======================================== */

.hero {
    background:
        linear-gradient(
            110deg,
            rgba(13, 48, 83, .96),
            rgba(22, 119, 210, .87)
        ),
        url('assets/toko1.jpg') center/cover no-repeat;

    color: #fff;
    padding: 75px 6%;
}

.hero-inner {
    max-width: 1100px;
    margin: auto;
    text-align: center;
}

.eyebrow {
    display: inline-block;
    padding: 7px 14px;
    border: 1px solid rgba(255,255,255,.35);
    border-radius: 30px;
    background: rgba(255,255,255,.10);
    font-size: 12px;
    font-weight: 700;
}

.hero h1 {
    margin: 18px 0 10px;
    font-size: clamp(32px, 5vw, 45px);
    line-height: 1.2;
}

.hero p {
    max-width: 620px;
    margin: auto;
    color: #e7f1fb;
    font-size: 15px;
}

/* ========================================
   FAQ MAIN
======================================== */

.faq-section {
    max-width: 950px;
    margin: auto;
    padding: 65px 24px 75px;
}

.section-heading {
    text-align: center;
    margin-bottom: 35px;
}

.section-label {
    color: #1677d2;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.section-heading h2 {
    margin: 9px 0;
    color: #153b63;
    font-size: 30px;
}

.section-heading p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}

/* ========================================
   SEARCH
======================================== */

.faq-search {
    position: relative;
    margin-bottom: 28px;
}

.faq-search input {
    width: 100%;
    padding: 15px 18px 15px 45px;
    border: 1px solid #dce4ed;
    border-radius: 10px;
    outline: none;
    background: #fff;
    color: #334155;
    font-size: 14px;
    transition: .2s;
}

.faq-search input:focus {
    border-color: #1677d2;
    box-shadow: 0 0 0 3px rgba(22,119,210,.10);
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 18px;
}

/* ========================================
   CATEGORY
======================================== */

.faq-category {
    margin-bottom: 27px;
}

.category-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 13px;
    color: #153b63;
    font-size: 17px;
    font-weight: 700;
}

.category-icon {
    width: 35px;
    height: 35px;
    display: grid;
    place-items: center;
    background: #e7f2ff;
    color: #1677d2;
    border-radius: 9px;
    font-size: 17px;
}

/* ========================================
   ACCORDION
======================================== */

.faq-item {
    margin-bottom: 10px;
    background: #fff;
    border: 1px solid #e5eaf0;
    border-radius: 10px;
    overflow: hidden;
    transition: .2s;
}

.faq-item:hover {
    border-color: #bfd8f2;
}

.faq-question {
    width: 100%;
    padding: 17px 19px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    border: none;
    background: transparent;
    color: #153b63;
    text-align: left;
    font-family: inherit;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.faq-question:hover {
    color: #1677d2;
}

.faq-arrow {
    flex-shrink: 0;
    width: 25px;
    height: 25px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #f1f6fc;
    color: #1677d2;
    font-size: 16px;
    transition: transform .25s;
}

.faq-item.open .faq-arrow {
    transform: rotate(180deg);
    background: #e7f2ff;
}

.faq-answer {
    display: none;
    padding: 0 19px 17px;
    color: #64748b;
    font-size: 13px;
    line-height: 1.8;
}

.faq-item.open .faq-answer {
    display: block;
}

.faq-answer p {
    margin: 0;
}

.faq-answer a {
    color: #1677d2;
    font-weight: 600;
}

.faq-answer a:hover {
    text-decoration: underline;
}

/* ========================================
   EMPTY SEARCH
======================================== */

.no-result {
    display: none;
    padding: 35px 20px;
    background: #fff;
    border: 1px solid #e5eaf0;
    border-radius: 10px;
    text-align: center;
    color: #64748b;
    font-size: 14px;
}

.no-result.show {
    display: block;
}

/* ========================================
   CONTACT CTA
======================================== */

.contact-section {
    max-width: 950px;
    margin: 0 auto 70px;
    padding: 0 24px;
}

.contact-box {
    padding: 35px;
    border-radius: 15px;
    background: linear-gradient(110deg, #153b63, #1677d2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
}

.contact-box h2 {
    margin: 0 0 7px;
    color: white;
    font-size: 23px;
}

.contact-box p {
    margin: 0;
    color: #e5f1ff;
    font-size: 13px;
}

.contact-button {
    flex-shrink: 0;
    padding: 12px 18px;
    border-radius: 8px;
    background: white;
    color: #145b9e;
    font-size: 13px;
    font-weight: 700;
}

.contact-button:hover {
    background: #eaf4ff;
}

/* ========================================
   FOOTER
======================================== */

footer {
    background: #102f4e;
    color: #dbe8f5;
    padding: 42px 6% 20px;
}

.footer-content {
    max-width: 1200px;
    margin: auto;
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 50px;
    margin-bottom: 30px;
}

.footer-content h3 {
    font-size: 16px;
    margin: 0 0 15px;
    color: white;
}

.footer-content p {
    color: #cbd5e1;
    font-size: 13px;
    line-height: 1.8;
    margin: 0 0 10px;
}

.footer-links,
.footer-contact {
    display: flex;
    flex-direction: column;
    gap: 11px;
}

.footer-links a {
    color: #cbd5e1;
    font-size: 13px;
    transition: .2s;
}

.footer-links a:hover {
    color: white;
}

.footer-bottom {
    max-width: 1200px;
    margin: auto;
    border-top: 1px solid #36506c;
    padding-top: 20px;
    text-align: center;
    color: #cbd5e1;
    font-size: 12px;
}

/* ========================================
   RESPONSIVE
======================================== */

@media (max-width: 800px) {

    .navbar-inner {
        flex-wrap: wrap;
        padding: 12px 0;
        gap: 12px;
    }

    .nav-menu {
        order: 3;
        width: 100%;
        justify-content: center;
        gap: 22px;
        padding: 8px 0 3px;
    }

    .nav-right {
        margin-left: auto;
    }

    .footer-content {
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }

    .footer-about {
        grid-column: 1 / -1;
    }

}

@media (max-width: 520px) {

    .navbar {
        padding: 0 4%;
    }

    .brand-name {
        font-size: 18px;
    }

    .nav-menu {
        gap: 15px;
    }

    .nav-menu a {
        font-size: 12px;
    }

    .user-name {
        display: none;
    }

    .btn {
        padding: 9px 12px;
        font-size: 12px;
    }

    .hero {
        padding: 55px 6%;
    }

    .hero p {
        font-size: 13px;
    }

    .faq-section {
        padding: 48px 16px;
    }

    .section-heading h2 {
        font-size: 25px;
    }

    .faq-question {
        padding: 15px;
        font-size: 13px;
    }

    .faq-answer {
        padding: 0 15px 15px;
    }

    .contact-section {
        padding: 0 16px;
        margin-bottom: 50px;
    }

    .contact-box {
        padding: 25px 20px;
        flex-direction: column;
        align-items: flex-start;
    }

    .contact-box h2 {
        font-size: 20px;
    }

    .footer-content {
        grid-template-columns: 1fr;
        gap: 25px;
    }

    .footer-about {
        grid-column: auto;
    }

}

</style>

</head>

<body>

<!-- ========================================
     NAVBAR
======================================== -->

<nav class="navbar">

    <div class="navbar-inner">

        <a href="index.php" class="brand">

            <img
                src="assets/SembakoKu (3).png"
                alt="Logo Toko Malaya"
                class="brand-logo"
            >

            <span class="brand-name">
                Toko Malaya
            </span>

        </a>

        <div class="nav-menu">

            <a href="index.php">
                Beranda
            </a>

            <a href="index.php#keunggulan">
                Keunggulan
            </a>

            <a href="index.php#produk">
                Produk
            </a>

            <a href="tentang_kami.php">
                Tentang Kami
            </a>
            
            <a href="testimoni.php">
                Ulasan
            </a>

        </div>

        <div class="nav-right">

            <?php if (!$is_login): ?>

                <a href="auth/login.php" class="btn btn-login">
                    Login
                </a>

                <a href="auth/register.php" class="btn btn-primary">
                    Daftar
                </a>

            <?php else: ?>

                <span class="user-name">
                    Halo, <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>
                </span>

                <a
                    href="<?= htmlspecialchars(dashboard_url_faq($role), ENT_QUOTES, 'UTF-8'); ?>"
                    class="btn btn-primary"
                >
                    Dashboard
                </a>

                <a href="auth/logout.php" class="btn btn-logout">
                    Logout
                </a>

            <?php endif; ?>

        </div>

    </div>

</nav>

<!-- ========================================
     HERO
======================================== -->

<header class="hero">

    <div class="hero-inner">

        <span class="eyebrow">
            PUSAT BANTUAN TOKO MALAYA
        </span>

        <h1>Frequently Asked Questions</h1>

        <p>
            Temukan jawaban dari pertanyaan yang sering diajukan
            mengenai layanan dan proses belanja di Toko Malaya.
        </p>

    </div>

</header>

<!-- ========================================
     FAQ
======================================== -->

<main>

<section class="faq-section">

    <div class="section-heading">

        <span class="section-label">
            Bantuan Pelanggan
        </span>

        <h2>Ada yang ingin kamu tanyakan?</h2>

        <p>
            Cari pertanyaan atau pilih kategori di bawah
            untuk menemukan informasi yang kamu butuhkan.
        </p>

    </div>

    <!-- SEARCH -->

    <div class="faq-search">

        <span class="search-icon">⌕</span>

        <input
            type="text"
            id="faqSearch"
            placeholder="Cari pertanyaan di sini..."
            autocomplete="off"
        >

    </div>

    <!-- ========================================
         KATEGORI 1
    ======================================== -->

    <div class="faq-category">

        <div class="category-title">

            <span class="category-icon">👤</span>

            Akun Pelanggan

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Bagaimana cara membuat akun di Toko Malaya?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Kamu dapat membuat akun melalui halaman Daftar.
                    Isi informasi yang diminta pada formulir pendaftaran,
                    kemudian ikuti proses yang tersedia sampai akun berhasil dibuat.
                </p>

            </div>

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Bagaimana cara login ke akun saya?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Klik tombol Login pada bagian atas website.
                    Masukkan informasi akun yang sudah didaftarkan,
                    kemudian tekan tombol Login untuk masuk.
                </p>

            </div>

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Apakah saya harus memiliki akun untuk berbelanja?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Untuk melakukan pemesanan dan melihat riwayat transaksi,
                    pelanggan perlu masuk menggunakan akun yang terdaftar
                    di Toko Malaya.
                </p>

            </div>

        </div>

    </div>

    <!-- ========================================
         KATEGORI 2
    ======================================== -->

    <div class="faq-category">

        <div class="category-title">

            <span class="category-icon">🛒</span>

            Produk dan Pemesanan

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Bagaimana cara melihat produk yang tersedia?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Kamu dapat membuka bagian Produk pada halaman Beranda
                    untuk melihat berbagai kebutuhan yang tersedia di
                    Toko Malaya. Pilih produk untuk melihat informasi
                    lebih lanjut.
                </p>

            </div>

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Bagaimana cara melakukan pemesanan?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Login terlebih dahulu ke akun pelanggan.
                    Pilih produk yang diinginkan, masukkan ke keranjang,
                    kemudian lanjutkan ke proses checkout.
                    Lengkapi informasi pesanan dan ikuti instruksi
                    yang tersedia sampai pesanan berhasil dibuat.
                </p>

            </div>

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Bagaimana cara mengetahui status pesanan?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Kamu dapat melihat perkembangan pesanan melalui
                    menu Pesanan pada dashboard pelanggan.
                    Di sana tersedia informasi status dan riwayat
                    pesanan yang pernah dilakukan.
                </p>

            </div>

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Apakah saya bisa melihat riwayat pesanan?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Bisa. Masuk ke akun pelanggan, kemudian buka
                    menu Pesanan. Kamu dapat melihat daftar transaksi
                    dan detail pesanan yang sudah dilakukan.
                </p>

            </div>

        </div>

    </div>

    <!-- ========================================
         KATEGORI 3
    ======================================== -->

    <div class="faq-category">

        <div class="category-title">

            <span class="category-icon">💳</span>

            Pembayaran

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Metode pembayaran apa saja yang tersedia?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Metode pembayaran yang tersedia dapat dilihat
                    pada halaman checkout. Pilihan pembayaran
                    mengikuti metode yang sedang disediakan oleh
                    Toko Malaya.
                </p>

            </div>

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Bagaimana jika pembayaran saya mengalami kendala?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Periksa kembali informasi pembayaran dan status
                    pesanan kamu. Jika kendala masih terjadi,
                    silakan hubungi Toko Malaya melalui informasi
                    kontak yang tersedia.
                </p>

            </div>

        </div>

    </div>

    <!-- ========================================
         KATEGORI 4
    ======================================== -->

    <div class="faq-category">

        <div class="category-title">

            <span class="category-icon">🚚</span>

            Pengiriman Pesanan

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Bagaimana cara mengetahui alamat pengiriman?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Alamat pengiriman dapat dilihat dan diperiksa
                    pada halaman checkout sebelum pesanan dibuat.
                    Pastikan alamat yang dimasukkan sudah benar.
                </p>

            </div>

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Apa yang harus dilakukan jika pesanan belum diterima?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Periksa terlebih dahulu status pesanan melalui
                    menu Pesanan. Jika terdapat kendala atau pesanan
                    belum diterima, hubungi pihak Toko Malaya untuk
                    mendapatkan informasi lebih lanjut.
                </p>

            </div>

        </div>

    </div>

    <!-- ========================================
         KATEGORI 5
    ======================================== -->

    <div class="faq-category">

        <div class="category-title">

            <span class="category-icon">⭐</span>

            Rating dan Ulasan

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Bagaimana cara memberikan ulasan produk?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Setelah pesanan berstatus Selesai, buka menu
                    Pesanan pada dashboard pelanggan.
                    Pilih pesanan yang ingin diberikan ulasan,
                    kemudian klik tombol Beri Ulasan.
                    Selanjutnya kamu dapat memberikan rating
                    bintang dan menuliskan komentar.
                </p>

            </div>

        </div>

        <div class="faq-item">

            <button class="faq-question" type="button">

                Di mana saya bisa melihat ulasan pelanggan?

                <span class="faq-arrow">⌄</span>

            </button>

            <div class="faq-answer">

                <p>
                    Kamu dapat melihat kumpulan ulasan pelanggan
                    melalui halaman Ulasan atau Testimoni yang
                    tersedia pada website Toko Malaya.
                </p>

                <p>
                    Halaman tersebut menampilkan pengalaman
                    pelanggan yang telah memberikan rating
                    dan komentar.
                </p>

            </div>

        </div>

    </div>

    <!-- EMPTY SEARCH -->

    <div class="no-result" id="noResult">

        <div style="font-size:35px;margin-bottom:10px;">
            🔎
        </div>

        <strong>Pertanyaan tidak ditemukan</strong>

        <p>
            Coba gunakan kata kunci yang berbeda.
        </p>

    </div>

</section>

<!-- ========================================
     CONTACT CTA
======================================== -->

<section class="contact-section">

    <div class="contact-box">

        <div>

            <h2>Masih punya pertanyaan?</h2>

            <p>
                Jika belum menemukan jawaban yang kamu cari,
                silakan hubungi kami melalui Kontak Kami.
            </p>

        </div>

    </div>

</section>

</main>

<!-- ========================================
     FOOTER
======================================== -->

<footer>

    <div class="footer-content">

        <!-- KOLOM 1 -->

        <div class="footer-about">

            <h3>Toko Malaya</h3>

            <p>
                Toko Malaya menyediakan berbagai kebutuhan
                sehari-hari untuk membantu memenuhi kebutuhan
                pelanggan dengan pengalaman belanja yang
                praktis, mudah, dan nyaman.
            </p>

        </div>

        <!-- KOLOM 2 -->

        <div>

            <h3>Tautan</h3>

            <div class="footer-links">

                <a href="index.php">
                    Beranda
                </a>

                <a href="index.php#produk">
                    Produk
                </a>

                <a href="tentang_kami.php">
                    Tentang Kami
                </a>

                <a href="testimoni.php">
                    Ulasan
                </a>

                <a href="syarat_ketentuan.php">
                    Syarat dan Ketentuan
                </a>

                <a href="kebijakan_privasi.php">
                    Kebijakan Privasi
                </a>
                
                <a href="faq.php">
                    FAQ
                </a>

            </div>

        </div>

        <!-- KOLOM 3 -->

        <div>

            <h3>Kontak</h3>

            <div class="footer-contact">

                <p>📍 Bekasi, Jawa Barat</p>

                <p>☎ 081234567890</p>

                <p>✉ halo@tokomalaya.test</p>

                <p>🕒 08.00 - 21.00 WIB</p>

            </div>

        </div>

    </div>

    <div class="footer-bottom">

        © <?= date('Y'); ?> Toko Malaya.
        Semua hak dilindungi.

    </div>

</footer>

<!-- ========================================
     JAVASCRIPT FAQ
======================================== -->

<script>

// ========================================
// ACCORDION
// ========================================

const faqItems = document.querySelectorAll('.faq-item');

faqItems.forEach(function(item) {

    const button = item.querySelector('.faq-question');

    button.addEventListener('click', function() {

        const isOpen = item.classList.contains('open');

        // Tutup semua pertanyaan lainnya
        faqItems.forEach(function(otherItem) {

            otherItem.classList.remove('open');

            otherItem.querySelector('.faq-question')
                .setAttribute('aria-expanded', 'false');

        });

        // Buka pertanyaan yang dipilih
        if (!isOpen) {

            item.classList.add('open');

            button.setAttribute('aria-expanded', 'true');

        }

    });

});

// ========================================
// SEARCH FAQ
// ========================================

const searchInput = document.getElementById('faqSearch');
const noResult = document.getElementById('noResult');
const categories = document.querySelectorAll('.faq-category');

searchInput.addEventListener('input', function() {

    const keyword = this.value.toLowerCase().trim();

    let totalFound = 0;

    categories.forEach(function(category) {

        const items = category.querySelectorAll('.faq-item');

        let categoryFound = 0;

        items.forEach(function(item) {

            const question = item.querySelector('.faq-question')
                .innerText.toLowerCase();

            const answer = item.querySelector('.faq-answer')
                .innerText.toLowerCase();

            const match =
                question.includes(keyword) ||
                answer.includes(keyword);

            item.style.display = match ? 'block' : 'none';

            if (match) {

                categoryFound++;
                totalFound++;

            } else {

                item.classList.remove('open');

            }

        });

        category.style.display =
            categoryFound > 0 ? 'block' : 'none';

    });

    noResult.classList.toggle(
        'show',
        totalFound === 0
    );

});

</script>

</body>
</html>
