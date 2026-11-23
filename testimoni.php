
<?php
session_start();
require_once "config/database.php";

// ========================================
// DATA LOGIN
// ========================================

$is_login = isset($_SESSION['id_user']);
$nama = $_SESSION['nama'] ?? '';
$role = $_SESSION['role'] ?? '';

function dashboard_url($role)
{
    switch ($role) {
        case 'admin':
            return 'admin/dashboard.php';
        case 'kasir':
            return 'kasir/dashboard.php';
        case 'pemilik':
            return 'pemilik/dashboard.php';
        case 'pelanggan':
            return 'pelanggan/dashboard.php';
        default:
            return 'auth/login.php';
    }
}

// ========================================
// HELPER
// ========================================

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tanggalIndonesia($tanggal)
{
    if (!$tanggal) return '-';

    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April',
        'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'
    ];

    $waktu = strtotime($tanggal);

    return date('d', $waktu) . ' ' .
        $bulan[(int)date('n', $waktu)] . ' ' .
        date('Y', $waktu);
}

// ========================================
// CEK TABEL
// ========================================

$cek_tabel = mysqli_query($conn, "SHOW TABLES LIKE 'testimoni'");

if (!$cek_tabel || mysqli_num_rows($cek_tabel) === 0) {
    die("Tabel testimoni belum tersedia.");
}

// ========================================
// STATISTIK
// ========================================

$statistik = [
    'total' => 0,
    'rata_rata' => 0,
    'bintang_5' => 0
];

$query_statistik = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total,
        COALESCE(AVG(rating), 0) AS rata_rata,
        COALESCE(SUM(rating = 5), 0) AS bintang_5
    FROM testimoni
");

if ($query_statistik) {
    $statistik = mysqli_fetch_assoc($query_statistik);
}

// ========================================
// FILTER RATING
// ========================================

$filter_rating = isset($_GET['rating'])
    ? (int)$_GET['rating']
    : 0;

if ($filter_rating < 0 || $filter_rating > 5) {
    $filter_rating = 0;
}

// ========================================
// AMBIL TESTIMONI
// ========================================

$query = "
    SELECT
        t.id_testimoni,
        t.id_pesanan,
        t.rating,
        t.komentar,
        t.tanggal_dibuat,
        u.nama AS nama_pelanggan,
        GROUP_CONCAT(
            DISTINCT b.nama_barang
            ORDER BY b.nama_barang
            SEPARATOR ', '
        ) AS nama_produk

    FROM testimoni t

    INNER JOIN users u
        ON t.id_user = u.id_user

    INNER JOIN pesanan p
        ON t.id_pesanan = p.id_pesanan

    LEFT JOIN detail_pesanan dp
        ON p.id_pesanan = dp.id_pesanan

    LEFT JOIN barang b
        ON dp.id_barang = b.id_barang

    WHERE 1=1
";

if ($filter_rating > 0) {
    $query .= " AND t.rating = " . $filter_rating;
}

$query .= "
    GROUP BY
        t.id_testimoni,
        t.id_pesanan,
        t.rating,
        t.komentar,
        t.tanggal_dibuat,
        u.nama

    ORDER BY t.tanggal_dibuat DESC, t.id_testimoni DESC
";

$result_testimoni = mysqli_query($conn, $query);

if (!$result_testimoni) {
    die("Gagal mengambil data testimoni.");
}

$testimoni = [];

while ($row = mysqli_fetch_assoc($result_testimoni)) {
    $testimoni[] = $row;
}

$total_ulasan = (int)$statistik['total'];
$rata_rata = (float)$statistik['rata_rata'];

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Testimoni Pelanggan - Toko Malaya</title>

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
}

a {
    text-decoration: none;
}

/* ========================================
   NAVBAR - SAMA DENGAN INDEX.PHP
======================================== */

.navbar {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    padding: 0 6%;
}

.navbar-inner {
    max-width: 1200px;
    height: 72px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
}

/* BRAND */

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

/* MENU */

.nav-menu {
    display: flex;
    align-items: center;
    gap: 28px;
}

.nav-menu a {
    color: #4b5563;
    font-size: 14px;
    font-weight: 500;
    transition: 0.2s;
}

.nav-menu a:hover {
    color: #1677d2;
}

.nav-menu a.active {
    color: #1677d2 !important;
    font-weight: 700 !important;
}

/* RIGHT NAV */

.nav-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.user-name {
    color: #374151;
    font-size: 13px;
    font-weight: 600;
    margin-right: 4px;
    max-width: 130px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* LOGIN */

.btn-login {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 16px;
    border: 1px solid #1677d2;
    border-radius: 7px;
    color: #1677d2;
    background: #ffffff;
    font-size: 13px;
    font-weight: 600;
    transition: 0.2s;
}

.btn-login:hover {
    background: #1677d2;
    color: #ffffff;
}

/* DAFTAR */

.btn-register {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 16px;
    border-radius: 7px;
    background: #1677d2;
    color: #ffffff;
    font-size: 13px;
    font-weight: 600;
    transition: 0.2s;
}

.btn-register:hover {
    background: #125fa8;
}

/* DASHBOARD */

.btn-dashboard {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 16px;
    border-radius: 7px;
    background: #1677d2;
    color: #ffffff;
    font-size: 13px;
    font-weight: 600;
    transition: 0.2s;
}

.btn-dashboard:hover {
    background: #125fa8;
}

/* LOGOUT */

.btn-logout {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 15px;
    border: 1px solid #dc3545;
    border-radius: 7px;
    background: #ffffff;
    color: #dc3545;
    font-size: 13px;
    font-weight: 600;
    transition: 0.2s;
}

.btn-logout:hover {
    background: #dc3545;
    color: #ffffff;
}

/* ========================================
   HERO
======================================== */

.hero {
    padding: 55px 20px;
    background: linear-gradient(120deg, #123b65, #1677d2);
    color: white;
    text-align: center;
}

.hero-content {
    max-width: 700px;
    margin: auto;
}

.hero-label {
    display: inline-block;
    margin-bottom: 13px;
    padding: 7px 13px;
    border: 1px solid #ffffff50;
    border-radius: 20px;
    background: #ffffff15;
    font-size: 11px;
    font-weight: bold;
    letter-spacing: .5px;
}

.hero h1 {
    margin: 0 0 12px;
    font-size: 34px;
}

.hero p {
    max-width: 580px;
    margin: auto;
    color: #e0edff;
    font-size: 14px;
    line-height: 1.8;
}

/* ========================================
   MAIN
======================================== */

.main {
    max-width: 1100px;
    margin: auto;
    padding: 35px 24px 50px;
}

/* STATISTIK */

.stats-card {
    display: grid;
    grid-template-columns: 1.1fr 1fr 1fr;
    gap: 15px;
    margin-bottom: 30px;
}

.stat-item {
    padding: 20px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    text-align: center;
}

.stat-label {
    margin-bottom: 9px;
    color: #6b7280;
    font-size: 12px;
}

.stat-value {
    color: #153b63;
    font-size: 26px;
    font-weight: bold;
}

.stat-stars {
    margin-top: 5px;
    color: #f5a623;
    font-size: 18px;
    letter-spacing: 2px;
}

/* HEADING */

.section-heading {
    margin-bottom: 18px;
}

.section-heading h2 {
    margin: 0 0 6px;
    color: #153b63;
    font-size: 21px;
}

.section-heading p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
}

/* FILTER */

.filter-area {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-label {
    color: #4b5563;
    font-size: 13px;
    font-weight: 600;
}

.filter-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.filter-buttons a {
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 7px;
    background: white;
    color: #4b5563;
    font-size: 11px;
    font-weight: 600;
    transition: .2s;
}

.filter-buttons a:hover,
.filter-buttons a.active {
    border-color: #1677d2;
    background: #eaf4ff;
    color: #1677d2;
}

/* REVIEW */

.review-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.review-card {
    padding: 19px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 11px;
    transition: .2s;
}

.review-card:hover {
    border-color: #cbdff3;
    box-shadow: 0 10px 25px rgba(15, 61, 100, .06);
}

.review-header {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 13px;
}

.avatar {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #eaf4ff;
    color: #1677d2;
    font-size: 17px;
    font-weight: bold;
}

.customer-info {
    flex: 1;
    min-width: 0;
}

.customer-name {
    color: #153b63;
    font-size: 13px;
    font-weight: bold;
}

.review-date {
    margin-top: 4px;
    color: #9ca3af;
    font-size: 11px;
}

.review-rating {
    margin-bottom: 11px;
    color: #f5a623;
    font-size: 16px;
    letter-spacing: 2px;
}

.product-tag {
    display: inline-block;
    margin-bottom: 11px;
    padding: 6px 9px;
    border-radius: 5px;
    background: #f1f6fc;
    color: #315a82;
    font-size: 11px;
    line-height: 1.5;
}

.review-comment {
    margin: 0;
    color: #4b5563;
    font-size: 13px;
    line-height: 1.8;
    overflow-wrap: anywhere;
}

/* EMPTY */

.empty-state {
    padding: 55px 20px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 11px;
    text-align: center;
}

.empty-icon {
    margin-bottom: 13px;
    font-size: 45px;
}

.empty-state h3 {
    margin: 0 0 8px;
    color: #153b63;
    font-size: 18px;
}

.empty-state p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.7;
}

/* CTA */

.cta {
    margin-top: 35px;
    padding: 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: #eaf4ff;
    border-radius: 11px;
}

.cta h3 {
    margin: 0 0 6px;
    color: #153b63;
    font-size: 17px;
}

.cta p {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.6;
}

.cta a {
    flex-shrink: 0;
    padding: 11px 16px;
    border-radius: 7px;
    background: #1677d2;
    color: white;
    font-size: 12px;
    font-weight: bold;
}

/* FOOTER */
footer {
    background: #102d4f;
    color: white;
    padding: 45px 7% 20px;
    margin-top: 70px;
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
    font-size: 14px;
    line-height: 1.8;
    margin: 0 0 10px;
}

.footer-links,
.footer-contact {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.footer-links a,
.footer-contact a {
    color: #cbd5e1;
    font-size: 14px;
    text-decoration: none;
}

.footer-links a:hover,
.footer-contact a:hover {
    color: white;
}

.footer-bottom {
    max-width: 1200px;
    margin: auto;
    border-top: 1px solid #36506c;
    padding-top: 20px;
    text-align: center;
    color: #cbd5e1;
    font-size: 13px;
}

@media (max-width: 768px) {
    .footer-content {
        grid-template-columns: 1fr;
        gap: 25px;
    }
}

/* ========================================
   RESPONSIVE
======================================== */

@media (max-width: 1000px) {

    .nav-menu {
        gap: 18px;
    }

    .user-name {
        display: none;
    }

}

@media (max-width: 900px) {

    .nav-menu {
        display: none;
    }

    .review-list {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 600px) {

    .navbar {
        padding: 0 15px;
    }

    .navbar-inner {
        height: 64px;
    }

    .brand-name {
        font-size: 17px;
    }

    .brand-logo {
        width: 36px;
        height: 36px;
    }

    .btn-login,
    .btn-register,
    .btn-dashboard,
    .btn-logout {
        padding: 8px 11px;
        font-size: 11px;
    }

    .hero {
        padding: 42px 18px;
    }

    .hero h1 {
        font-size: 27px;
    }

    .hero p {
        font-size: 12px;
    }

    .main {
        padding: 25px 14px 35px;
    }

    .stats-card {
        grid-template-columns: 1fr 1fr;
        gap: 9px;
    }

    .stat-item:first-child {
        grid-column: 1 / -1;
    }

    .stat-item {
        padding: 15px 10px;
    }

    .stat-value {
        font-size: 23px;
    }

    .filter-area {
        align-items: flex-start;
        flex-direction: column;
    }

    .cta {
        align-items: flex-start;
        flex-direction: column;
    }

    .footer-inner {
        padding: 30px 15px;
        flex-direction: column;
        align-items: flex-start;
    }

}

</style>

</head>

<body>

<!-- ========================================
     NAVBAR SAMA DENGAN INDEX.PHP
======================================== -->

<nav class="navbar">

    <div class="navbar-inner">

        <!-- LOGO -->

        <a href="index.php" class="brand">

            <img
                src="assets/SembakoKu (3).png"
                alt="Logo SembakoKu"
                class="brand-logo"
            >

            <span class="brand-name">
                Toko Malaya
            </span>

        </a>

        <!-- MENU -->

        <div class="nav-menu">

            <a href="index.php" data-nav="beranda">
                Beranda
            </a>

            <a href="index.php#keunggulan" data-nav="keunggulan">
                Keunggulan
            </a>

            <a href="index.php#produk" data-nav="produk">
                Produk
            </a>

            <a href="tentang_kami.php" data-nav="tentang">
                Tentang Kami
            </a>
            
            <a href="testimoni.php" class="active">
                Ulasan
            </a>

        </div>

        <!-- BUTTON KANAN -->

        <div class="nav-right">

            <?php if (!$is_login): ?>

                <a href="auth/login.php" class="btn-login">
                    Login
                </a>

                <a href="auth/register.php" class="btn-register">
                    Daftar
                </a>

            <?php else: ?>

                <span
                    class="user-name"
                    title="<?= e($nama); ?>"
                >
                    Halo, <?= e($nama); ?>
                </span>

                <a href="auth/login.php" class="btn-login">
                    Login
                </a>

                <a
                    href="<?= e(dashboard_url($role)); ?>"
                    class="btn-dashboard"
                >
                    Dashboard
                </a>

                <a href="auth/logout.php" class="btn-logout">
                    Logout
                </a>

            <?php endif; ?>

        </div>

    </div>

</nav>

<!-- ========================================
     HERO
======================================== -->

<section class="hero">

    <div class="hero-content">

        <span class="hero-label">
            CERITA PELANGGAN
        </span>

        <h1>Apa Kata Pelanggan Kami?</h1>

        <p>
            Kepuasan pelanggan menjadi bagian penting bagi Toko Malaya.
            Simak pengalaman dan ulasan pelanggan yang telah berbelanja
            bersama kami.
        </p>

    </div>

</section>

<!-- ========================================
     MAIN
======================================== -->

<main class="main">

    <!-- STATISTIK -->

    <section class="stats-card">

        <div class="stat-item">

            <div class="stat-label">
                Total Ulasan
            </div>

            <div class="stat-value">
                <?= number_format($total_ulasan, 0, ',', '.') ?>
            </div>

            <div class="stat-stars">★★★★★</div>

        </div>

        <div class="stat-item">

            <div class="stat-label">
                Rata-rata Rating
            </div>

            <div class="stat-value">
                <?= number_format($rata_rata, 1, ',', '.') ?>/5
            </div>

            <div class="stat-stars">★★★★★</div>

        </div>

        <div class="stat-item">

            <div class="stat-label">
                Ulasan Bintang 5
            </div>

            <div class="stat-value">
                <?= number_format((int)$statistik['bintang_5'], 0, ',', '.') ?>
            </div>

            <div class="stat-stars">★★★★★</div>

        </div>

    </section>

    <!-- DAFTAR ULASAN -->

    <section>

        <div class="section-heading">

            <h2>Ulasan Pelanggan</h2>

            <p>
                Pengalaman nyata dari pelanggan Toko Malaya.
            </p>

        </div>

        <!-- FILTER -->

        <div class="filter-area">

            <span class="filter-label">
                Filter berdasarkan rating
            </span>

            <div class="filter-buttons">

                <a
                    href="testimoni.php"
                    class="<?= $filter_rating === 0 ? 'active' : '' ?>"
                >
                    Semua
                </a>

                <?php for ($i = 5; $i >= 1; $i--): ?>

                    <a
                        href="testimoni.php?rating=<?= $i ?>"
                        class="<?= $filter_rating === $i ? 'active' : '' ?>"
                    >
                        <?= $i ?> ★
                    </a>

                <?php endfor; ?>

            </div>

        </div>

        <?php if (!empty($testimoni)): ?>

            <div class="review-list">

                <?php foreach ($testimoni as $item):

                    $nama_pelanggan = trim(
                        (string)$item['nama_pelanggan']
                    );

                    $inisial = strtoupper(
                        substr(
                            $nama_pelanggan !== ''
                                ? $nama_pelanggan
                                : 'P',
                            0,
                            1
                        )
                    );

                    $rating = max(
                        1,
                        min(5, (int)$item['rating'])
                    );

                ?>

                    <article class="review-card">

                        <div class="review-header">

                            <div class="avatar">
                                <?= e($inisial) ?>
                            </div>

                            <div class="customer-info">

                                <div class="customer-name">
                                    <?= e($nama_pelanggan) ?>
                                </div>

                                <div class="review-date">
                                    <?= e(
                                        tanggalIndonesia(
                                            $item['tanggal_dibuat']
                                        )
                                    ) ?>
                                </div>

                            </div>

                        </div>

                        <div class="review-rating">

                            <?= str_repeat('★', $rating) ?>

                            <span style="color:#d1d5db">
                                <?= str_repeat('★', 5 - $rating) ?>
                            </span>

                        </div>

                        <?php if (!empty($item['nama_produk'])): ?>

                            <div class="product-tag">
                                📦 <?= e($item['nama_produk']) ?>
                            </div>

                        <?php endif; ?>

                        <p class="review-comment">
                            <?= nl2br(e($item['komentar'])) ?>
                        </p>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <h3>Belum Ada Ulasan</h3>

                <p>

                    <?php if ($filter_rating > 0): ?>

                        Belum ada pelanggan yang memberikan
                        rating <?= $filter_rating ?> bintang.

                    <?php else: ?>

                        Belum ada ulasan pelanggan yang masuk.
                        Jadilah salah satu pelanggan pertama
                        yang memberikan ulasan untuk Toko Malaya.

                    <?php endif; ?>

                </p>

            </div>

        <?php endif; ?>

    </section>

    <!-- CTA -->

    <section class="cta">

        <div>

            <h3>Sudah berbelanja di Toko Malaya?</h3>

            <p>
                Bagikan pengalaman belanja kamu selama berbelanja di Toko Malaya.
            </p>

        </div>

        <?php if (
            $is_login &&
            $role === 'pelanggan'
        ): ?>

            <a href="pelanggan/pesanan.php">
                Beri Ulasan →
            </a>

        <?php else: ?>

            <a href="auth/login.php">
                Login →
            </a>

        <?php endif; ?>

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

                <a href="index.php">Beranda</a>

                <a href="index.php#produk">Produk</a>

                <a href="tentang_kami.php">Tentang Kami</a>

                <a href="testimoni.php">Ulasan</a>
                
                <a href="syarat_ketentuan.php">Syarat dan Ketentuan</a>
                
                <a href="kebijakan_privasi.php">Kebijakan Privasi</a>
                
                <a href="faq.php">FAQ</a>

            </div>

        </div>

        <!-- KOLOM 3 -->
        <div>

            <h3>Kontak</h3>

            <div class="footer-contact">

                <p>📍 Kampung Tengah, DKI Jakarta</p>

                <p>☎ 088590043576</p>

                <p>✉ sembako@tokomalaya</p>

                <p>🕒 07.00 - 19.00 WIB</p>

            </div>

        </div>

    </div>

    <!-- COPYRIGHT -->
    <div class="footer-bottom">

        © <?= date('Y'); ?> Toko Malaya.
        Semua hak dilindungi.

    </div>

</footer>
</body>
</html>
