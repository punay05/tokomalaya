<?php

session_start();
include "config/database.php";

/* =========================
   DATA LOGIN
========================= */
$is_login = isset($_SESSION['id_user']);

$id_user = $_SESSION['id_user'] ?? null;
$nama = $_SESSION['nama'] ?? '';
$email = $_SESSION['email'] ?? '';
$role = $_SESSION['role'] ?? '';

/* =========================
   HELPER
========================= */
function rupiah($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

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

/* =========================
   URL PRODUK
========================= */
if (!$is_login) {
    $produk_url = 'auth/login.php';
} elseif ($role === 'pelanggan') {
    $produk_url = 'pelanggan/produk.php';
} else {
    $produk_url = dashboard_url($role);
}

/* =========================
   PRODUK RANDOM
========================= */
$query_produk = "
    SELECT
        b.id_barang,
        b.kode_barang,
        b.nama_barang,
        b.harga_jual,
        b.stok,
        b.satuan,
        b.gambar,
        k.nama_kategori
    FROM barang b
    LEFT JOIN kategori k
        ON b.id_kategori = k.id_kategori
    WHERE b.stok > 0
    ORDER BY RAND()
    LIMIT 8
";

$result_produk = mysqli_query($conn, $query_produk);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        SembakoKu - Belanja Kebutuhan Sehari-hari
    </title>

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

        /* =========================
           NAVBAR
        ========================= */

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
            gap: 28px;
        }

        .nav-menu a {
            color: #4b5563;
            font-size: 14px;
            font-weight: 500;
            transition: 0.2s;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            color: #1677d2;
        }

        .nav-menu a.active {
            font-weight: 700;
        }

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

        /* =========================
           HERO
        ========================= */

        .hero {
            min-height: 500px;
            background:
                linear-gradient(
                    90deg,
                    rgba(10, 38, 64, 0.92),
                    rgba(10, 38, 64, 0.68),
                    rgba(10, 38, 64, 0.20)
                ),
                url('assets/toko1.jpg') center/cover no-repeat;
            display: flex;
            align-items: center;
        }

        .hero-inner {
            width: 100%;
            max-width: 1200px;
            margin: auto;
            padding: 70px 30px;
        }

        .hero-content {
            max-width: 600px;
            color: #ffffff;
        }

        .hero-label {
            display: inline-block;
            margin-bottom: 16px;
            padding: 7px 13px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            font-size: 13px;
        }

        .hero h1 {
            margin: 0 0 18px;
            font-size: 46px;
            line-height: 1.15;
        }

        .hero h1 span {
            color: #6db5ff;
        }

        .hero p {
            margin: 0 0 28px;
            max-width: 530px;
            font-size: 17px;
            line-height: 1.7;
            color: #e5edf6;
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 13px 22px;
            border-radius: 7px;
            background: #1677d2;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            transition: 0.2s;
        }

        .btn-primary:hover {
            background: #0f64b7;
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 13px 22px;
            border-radius: 7px;
            background: #ffffff;
            color: #153b63;
            font-size: 14px;
            font-weight: 700;
            transition: 0.2s;
        }

        .btn-secondary:hover {
            background: #edf4fb;
        }

        /* =========================
           SECTION
        ========================= */

        .section {
            max-width: 1200px;
            margin: auto;
            padding: 65px 30px;
        }

        .section-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
        }

        .section-title h2 {
            margin: 0 0 8px;
            color: #153b63;
            font-size: 28px;
        }

        .section-title p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .section-link {
            color: #1677d2;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .section-link:hover {
            text-decoration: underline;
        }

        /* =========================
           FEATURE
        ========================= */

        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .feature-card {
            padding: 25px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            border-radius: 9px;
            background: #eaf4ff;
            color: #1677d2;
            font-size: 21px;
        }

        .feature-card h3 {
            margin: 0 0 8px;
            color: #1f2937;
            font-size: 17px;
        }

        .feature-card p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.6;
        }

        /* =========================
           PRODUK
        ========================= */

        .product-section {
            background: #ffffff;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .product-card {
            display: block;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            color: inherit;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;
        }

        .product-card:hover {
            transform: translateY(-4px);
            border-color: #cbdff3;
            box-shadow: 0 10px 25px rgba(15, 61, 100, 0.10);
        }

        .product-image {
            width: 100%;
            height: 190px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f7fa;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-placeholder {
            color: #9ca3af;
            font-size: 13px;
        }

        .product-info {
            padding: 16px;
        }

        .product-category {
            display: inline-block;
            margin-bottom: 8px;
            padding: 4px 8px;
            border-radius: 4px;
            background: #edf5fd;
            color: #1677d2;
            font-size: 11px;
            font-weight: 600;
        }

        .product-name {
            margin: 0 0 10px;
            color: #1f2937;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.4;
            min-height: 42px;
        }

        .product-price {
            margin-bottom: 6px;
            color: #1677d2;
            font-size: 17px;
            font-weight: 700;
        }

        .product-stock {
            color: #6b7280;
            font-size: 12px;
        }

        .empty-product {
            grid-column: 1 / -1;
            padding: 50px 20px;
            text-align: center;
            color: #6b7280;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 10px;
        }

        /* =========================
           CTA
        ========================= */

        .cta {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px 70px;
        }

        .cta-box {
            padding: 45px;
            border-radius: 12px;
            background: linear-gradient(135deg, #153b63, #1677d2);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
        }

        .cta-content h2 {
            margin: 0 0 10px;
            font-size: 27px;
        }

        .cta-content p {
            margin: 0;
            color: #dceafa;
            font-size: 14px;
            line-height: 1.6;
        }

        .cta .btn-secondary {
            flex-shrink: 0;
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

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1000px) {

            .nav-menu {
                gap: 18px;
            }

            .user-name {
                display: none;
            }

            .product-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 900px) {

            .nav-menu {
                display: none;
            }

            .hero h1 {
                font-size: 38px;
            }

            .features {
                grid-template-columns: 1fr;
            }

            .product-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .cta-box {
                flex-direction: column;
                align-items: flex-start;
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
                min-height: 470px;
            }

            .hero-inner {
                padding: 50px 20px;
            }

            .hero h1 {
                font-size: 32px;
            }

            .hero p {
                font-size: 14px;
            }

            .section {
                padding: 45px 15px;
            }

            .section-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .product-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .product-image {
                height: 145px;
            }

            .product-info {
                padding: 12px;
            }

            .product-name {
                font-size: 13px;
            }

            .product-price {
                font-size: 15px;
            }

            .cta {
                padding: 0 15px 50px;
            }

            .cta-box {
                padding: 30px 25px;
            }

            .cta-content h2 {
                font-size: 22px;
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

<!-- =====================================================
     NAVBAR
===================================================== -->

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

            <a href="index.php" class="active" data-nav="beranda">
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
            
            <a href="testimoni.php" data-nav="testimoni">
                Ulasan
            </a>

        </div>

        <!-- BUTTON KANAN -->
        <div class="nav-right">

            <?php if (!$is_login): ?>

                <a
                    href="auth/login.php"
                    class="btn-login"
                >
                    Login
                </a>

                <a
                    href="auth/register.php"
                    class="btn-register"
                >
                    Daftar
                </a>

            <?php else: ?>

                <span
                    class="user-name"
                    title="<?= htmlspecialchars($nama); ?>"
                >
                    Halo, <?= htmlspecialchars($nama); ?>
                </span>

                <a
                    href="auth/login.php"
                    class="btn-login"
                >
                    Login
                </a>

                <a
                    href="<?= htmlspecialchars(dashboard_url($role)); ?>"
                    class="btn-dashboard"
                >
                    Dashboard
                </a>

                <a
                    href="auth/logout.php"
                    class="btn-logout"
                >
                    Logout
                </a>

            <?php endif; ?>

        </div>

    </div>

</nav>

<!-- =====================================================
     HERO
===================================================== -->

<section class="hero">

    <div class="hero-inner">

        <div class="hero-content">

            <div class="hero-label">
                Belanja Kebutuhan Harian dengan Mudah
            </div>

            <h1>
                Semua Kebutuhan
                <span>Sehari-hari</span>
                Ada di Toko Malaya
            </h1>

            <p>
                Temukan berbagai kebutuhan sembako dan
                barang harian dengan mudah. Pilih produk
                yang kamu butuhkan dan nikmati pengalaman
                belanja yang praktis.
            </p>

            <div class="hero-buttons">

                <a
                    href="<?= htmlspecialchars($produk_url); ?>"
                    class="btn-primary"
                >
                    Lihat Produk
                </a>

                <a
                    href="#keunggulan"
                    class="btn-secondary"
                >
                    Kenapa harus di Toko Malaya?
                </a>

            </div>

        </div>

    </div>

</section>

<!-- =====================================================
     KEUNGGULAN
===================================================== -->

<section
    class="section"
    id="keunggulan"
>

    <div class="section-header">

        <div class="section-title">

            <h2>
                Kenapa harus di Toko Malaya?
            </h2>

            <p>
                Belanja kebutuhan sehari-hari dengan lebih praktis.
            </p>

        </div>

    </div>

    <div class="features">

        <!-- CARD 1 -->
        <div class="feature-card">

            <div class="feature-icon">
                🛒
            </div>

            <h3>
                Produk Beragam
            </h3>

            <p>
                Berbagai kebutuhan sembako dan barang
                sehari-hari tersedia dalam satu tempat.
            </p>

        </div>

        <!-- CARD 2 -->
        <div class="feature-card">

            <div class="feature-icon">
                💰
            </div>

            <h3>
                Harga Jelas
            </h3>

            <p>
                Harga produk ditampilkan secara jelas
                sehingga kamu bisa mengetahui total
                belanja dengan mudah.
            </p>

        </div>

        <!-- CARD 3 -->
        <div class="feature-card">

            <div class="feature-icon">
                📦
            </div>

            <h3>
                Belanja Praktis
            </h3>

            <p>
                Pilih produk, masukkan ke keranjang,
                lalu lanjutkan proses pembelian dengan mudah.
            </p>

        </div>

    </div>

</section>

<!-- =====================================================
     PRODUK RANDOM
===================================================== -->

<section
    class="product-section"
    id="produk"
>

    <div class="section">

        <div class="section-header">

            <div class="section-title">

                <h2>
                    Produk Pilihan
                </h2>

                <p>
                    Beberapa produk yang tersedia di Toko Malaya.
                </p>

            </div>

            <a
                href="<?= htmlspecialchars($produk_url); ?>"
                class="section-link"
            >
                Lihat Produk Lainnya →
            </a>

        </div>

        <div class="product-grid">

            <?php if (
                $result_produk &&
                mysqli_num_rows($result_produk) > 0
            ): ?>

                <?php while (
                    $produk = mysqli_fetch_assoc($result_produk)
                ): ?>

                    <?php

                    $gambar = trim($produk['gambar'] ?? '');

                    $gambar_path = 'uploads/' . $gambar;

                    $gambar_tersedia =
                        !empty($gambar)
                        &&
                        file_exists(
                            __DIR__ . '/uploads/' . $gambar
                        );

                    ?>

                    <a
                        href="<?= htmlspecialchars($produk_url); ?>"
                        class="product-card"
                    >

                        <div class="product-image">

                            <?php if ($gambar_tersedia): ?>

                                <img
                                    src="<?= htmlspecialchars($gambar_path); ?>"
                                    alt="<?= htmlspecialchars($produk['nama_barang']); ?>"
                                >

                            <?php else: ?>

                                <div class="product-placeholder">
                                    Tidak ada gambar
                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="product-info">

                            <span class="product-category">
                                <?= htmlspecialchars(
                                    $produk['nama_kategori'] ?: 'Umum'
                                ); ?>
                            </span>

                            <h3 class="product-name">
                                <?= htmlspecialchars($produk['nama_barang']); ?>
                            </h3>

                            <div class="product-price">
                                <?= rupiah($produk['harga_jual']); ?>
                            </div>

                            <div class="product-stock">
                                Stok tersedia:
                                <?= (int)$produk['stok']; ?>
                                <?= htmlspecialchars($produk['satuan']); ?>
                            </div>

                        </div>

                    </a>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="empty-product">
                    Belum ada produk yang tersedia.
                </div>

            <?php endif; ?>

        </div>

    </div>

</section>

<!-- =====================================================
     CTA
===================================================== -->

<section class="cta">

    <div class="cta-box">

        <div class="cta-content">

            <h2>
                Mau melihat semua produk?
            </h2>

            <p>
                Jelajahi katalog Toko Malaya dan temukan
                produk yang kamu butuhkan.
            </p>

        </div>

        <a
            href="<?= htmlspecialchars($produk_url); ?>"
            class="btn-secondary"
        >
            Lihat Semua Produk →
        </a>

    </div>

</section>

<!-- =====================================================
     FOOTER
===================================================== -->

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
                
                <a href="kebijakan_privasi.php">Kebijakan dan Privasi</a>
                
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
<script>
document.addEventListener('DOMContentLoaded', function () {

    const navLinks = document.querySelectorAll('.nav-menu a[data-nav]');

    if (!navLinks.length) return;

    function setActive(name) {
        navLinks.forEach(function (link) {
            link.classList.toggle('active', link.dataset.nav === name);
        });
    }

    function activeFromHash() {
        if (window.location.hash === '#keunggulan') {
            setActive('keunggulan');
        } else if (window.location.hash === '#produk') {
            setActive('produk');
        } else {
            setActive('beranda');
        }
    }

    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {

            if (
                link.dataset.nav !== 'tentang' &&
                link.dataset.nav !== 'kontak'
            ) {
                setActive(link.dataset.nav);
            }

        });
    });

    window.addEventListener('hashchange', activeFromHash);

    activeFromHash();

    const sectionIds = ['keunggulan', 'produk'];

    if ('IntersectionObserver' in window) {

        const observer = new IntersectionObserver(function (entries) {

            const visible = entries
                .filter(function (entry) {
                    return entry.isIntersecting;
                })
                .sort(function (a, b) {
                    return b.intersectionRatio - a.intersectionRatio;
                })[0];

            if (visible) {
                setActive(visible.target.id);
            } else if (window.scrollY < 250) {
                setActive('beranda');
            }

        }, {
            rootMargin: '-20% 0px -55% 0px',
            threshold: [0, 0.15, 0.3, 0.5]
        });

        sectionIds.forEach(function (id) {

            const section = document.getElementById(id);

            if (section) {
                observer.observe(section);
            }

        });

    }

});
</script>

</body>
</html>