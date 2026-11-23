<?php

session_start();

include "../config/database.php";

// ========================================
// CEK LOGIN
// ========================================

if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION['role'] !== 'pelanggan') {
    header("Location: ../index.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? 'Pelanggan';
$email = $_SESSION['email'] ?? '';


// ========================================
// FORMAT RUPIAH
// ========================================

function rupiah($angka)
{
    return 'Rp ' . number_format(
        (float) $angka,
        0,
        ',',
        '.'
    );
}


// ========================================
// JUMLAH KERANJANG
// ========================================

$total_keranjang = 0;

$cek_keranjang = mysqli_query(
    $conn,
    "SHOW TABLES LIKE 'keranjang'"
);

if (
    $cek_keranjang &&
    mysqli_num_rows($cek_keranjang) > 0
) {

    $query_keranjang = mysqli_query(
        $conn,
        "SELECT COALESCE(SUM(jumlah), 0) AS total
         FROM keranjang
         WHERE id_user = $id_user"
    );

    if ($query_keranjang) {

        $data_keranjang =
            mysqli_fetch_assoc($query_keranjang);

        $total_keranjang =
            (int) ($data_keranjang['total'] ?? 0);
    }
}


// ========================================
// STATISTIK PESANAN
// ========================================

$total_pesanan = 0;
$pesanan_diproses = 0;
$pesanan_selesai = 0;

$cek_tabel = mysqli_query(
    $conn,
    "SHOW TABLES LIKE 'pesanan'"
);

if (
    $cek_tabel &&
    mysqli_num_rows($cek_tabel) > 0
) {

    // TOTAL PESANAN

    $query_total = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM pesanan
         WHERE id_user = $id_user"
    );

    if ($query_total) {

        $data_total =
            mysqli_fetch_assoc($query_total);

        $total_pesanan =
            (int) ($data_total['total'] ?? 0);
    }


    // PESANAN AKTIF

    $query_proses = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM pesanan
         WHERE id_user = $id_user
         AND status IN (
             'menunggu',
             'diproses',
             'dikirim'
         )"
    );

    if ($query_proses) {

        $data_proses =
            mysqli_fetch_assoc($query_proses);

        $pesanan_diproses =
            (int) ($data_proses['total'] ?? 0);
    }


    // PESANAN SELESAI

    $query_selesai = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM pesanan
         WHERE id_user = $id_user
         AND status = 'selesai'"
    );

    if ($query_selesai) {

        $data_selesai =
            mysqli_fetch_assoc($query_selesai);

        $pesanan_selesai =
            (int) ($data_selesai['total'] ?? 0);
    }
}


// ========================================
// PRODUK PILIHAN
// ========================================

$produk = [];

$query_produk = mysqli_query(
    $conn,
    "SELECT
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
     LIMIT 6"
);

if ($query_produk) {

    while ($row = mysqli_fetch_assoc($query_produk)) {

        $produk[] = $row;
    }
}

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
        Toko Malaya - Belanja Kebutuhan Sehari-hari
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

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f8fafc;

            color: #1f2937;
        }


        /* ========================================
           NAVBAR
        ======================================== */

        .navbar {
            position: sticky;

            top: 0;

            z-index: 1000;

            background: #ffffff;

            border-bottom:
                1px solid #e5e7eb;

            box-shadow:
                0 2px 10px
                rgba(15, 23, 42, 0.05);
        }


        .navbar-inner {
            max-width: 1200px;

            min-height: 72px;

            margin: 0 auto;

            padding:
                0 24px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 25px;
        }


        /* ========================================
           LOGO
        ======================================== */

        .logo {
            display: flex;

            align-items: center;

            gap: 10px;

            color: #153b63;

            text-decoration: none;

            flex-shrink: 0;
        }


        .logo-icon {
            width: 42px;
            height: 42px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #1677d2;

            border-radius: 11px;

            overflow: hidden;
        }


        .logo-icon img {
            width: 36px;
            height: 36px;

            object-fit: contain;
        }


        .logo-text {
            font-size: 20px;

            font-weight: bold;
        }


        /* ========================================
           NAV MENU
        ======================================== */

        .nav-menu {
            display: flex;

            align-items: center;

            gap: 28px;

            margin-left: auto;
        }


        .nav-menu a {
            position: relative;

            padding:
                26px 0;

            color: #4b5563;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

            transition:
                color 0.2s ease;
        }


        .nav-menu a:hover,
        .nav-menu a.active {
            color: #1677d2;
        }


        .nav-menu a.active::after {
            content: "";

            position: absolute;

            left: 0;
            right: 0;

            bottom: 17px;

            height: 2px;

            background: #1677d2;

            border-radius: 2px;
        }


        /* ========================================
           NAV RIGHT
        ======================================== */

        .nav-right {
            display: flex;

            align-items: center;

            gap: 10px;
        }


        .cart-button {
            position: relative;

            display: flex;

            align-items: center;

            justify-content: center;

            width: 42px;
            height: 42px;

            border-radius: 9px;

            background: #f1f6fc;

            color: #1677d2;

            text-decoration: none;

            font-size: 20px;

            transition:
                0.2s ease;
        }


        .cart-button:hover {
            background: #e3effb;

            transform:
                translateY(-1px);
        }


        .cart-badge {
            position: absolute;

            top: -5px;
            right: -5px;

            min-width: 19px;
            height: 19px;

            padding: 0 5px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #dc3545;

            color: white;

            border-radius: 20px;

            font-size: 10px;

            font-weight: bold;
        }


        .profile-button {
            display: flex;

            align-items: center;

            gap: 8px;

            padding:
                9px 13px;

            background: #f8fafc;

            border:
                1px solid #e5e7eb;

            border-radius: 9px;

            color: #374151;

            text-decoration: none;

            font-size: 13px;

            font-weight: 600;
        }


        .profile-button:hover {
            border-color: #1677d2;

            color: #1677d2;
        }


        .logout-button {
            display: flex;

            align-items: center;

            justify-content: center;

            padding:
                10px 14px;

            border-radius: 8px;

            background: #ffffff;

            border:
                1px solid #dbe3ec;

            color: #6b7280;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;
        }


        .logout-button:hover {
            background: #f8fafc;

            color: #dc3545;

            border-color: #f1b5bc;
        }


        /* ========================================
           MAIN
        ======================================== */

        .main {
            max-width: 1200px;

            margin: 0 auto;

            padding:
                28px 24px 50px;
        }


        /* ========================================
           HERO
        ======================================== */

        .hero {
            position: relative;

            min-height: 310px;

            display: flex;

            align-items: center;

            padding:
                45px 50px;

            margin-bottom: 28px;

            border-radius: 18px;

            overflow: hidden;

            background:
                linear-gradient(
                    120deg,
                    #1677d2,
                    #125ca3
                );

            box-shadow:
                0 12px 30px
                rgba(
                    21,
                    59,
                    99,
                    0.15
                );
        }


        .hero::before {
            content: "";

            position: absolute;

            width: 330px;
            height: 330px;

            right: -90px;
            top: -130px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.08
                );

            border-radius: 50%;
        }


        .hero::after {
            content: "";

            position: absolute;

            width: 220px;
            height: 220px;

            right: 130px;
            bottom: -150px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.06
                );

            border-radius: 50%;
        }


        .hero-content {
            position: relative;

            z-index: 2;

            max-width: 650px;

            color: white;
        }


        .hero-small {
            display: inline-block;

            margin-bottom: 12px;

            padding:
                6px 11px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.14
                );

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;
        }


        .hero h1 {
            margin: 0 0 12px;

            font-size: 35px;

            line-height: 1.2;
        }


        .hero p {
            margin: 0 0 22px;

            max-width: 570px;

            color: #e8f3ff;

            font-size: 15px;

            line-height: 1.7;
        }


        .hero-buttons {
            display: flex;

            align-items: center;

            gap: 10px;
        }


        .btn-primary {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding:
                12px 20px;

            border-radius: 8px;

            background: white;

            color: #125ca3;

            text-decoration: none;

            font-size: 14px;

            font-weight: bold;

            transition:
                0.2s ease;
        }


        .btn-primary:hover {
            background: #f1f5f9;

            transform:
                translateY(-1px);
        }


        .btn-secondary {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding:
                11px 19px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.35
                );

            border-radius: 8px;

            color: white;

            text-decoration: none;

            font-size: 14px;

            font-weight: bold;
        }


        .btn-secondary:hover {
            background:
                rgba(
                    255,
                    255,
                    255,
                    0.10
                );
        }


        /* ========================================
           STATISTIK
        ======================================== */

        .stat-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;

            margin-bottom: 35px;
        }


        .stat-card {
            display: flex;

            align-items: center;

            gap: 15px;

            padding: 20px;

            background: white;

            border:
                1px solid #e5e7eb;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(
                    15,
                    23,
                    42,
                    0.04
                );
        }


        .stat-icon {
            width: 48px;
            height: 48px;

            display: flex;

            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            border-radius: 11px;

            background: #e8f2fc;

            font-size: 21px;
        }


        .stat-card:nth-child(2)
        .stat-icon {
            background: #eef6ff;
        }


        .stat-card:nth-child(3)
        .stat-icon {
            background: #eef8f1;
        }


        .stat-label {
            display: block;

            margin-bottom: 5px;

            color: #6b7280;

            font-size: 13px;
        }


        .stat-value {
            display: block;

            color: #153b63;

            font-size: 23px;

            font-weight: bold;
        }


        /* ========================================
           SECTION HEADER
        ======================================== */

        .section-header {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 16px;
        }


        .section-header h2 {
            margin: 0;

            color: #153b63;

            font-size: 22px;
        }


        .section-description {
            margin:
                5px 0 0;

            color: #6b7280;

            font-size: 13px;
        }


        .section-link {
            color: #1677d2;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;
        }


        .section-link:hover {
            text-decoration: underline;
        }


        /* ========================================
           PRODUCT GRID
        ======================================== */

        .product-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;
        }


        .product-card {
            overflow: hidden;

            background: white;

            border:
                1px solid #e5e7eb;

            border-radius: 13px;

            box-shadow:
                0 3px 12px
                rgba(
                    15,
                    23,
                    42,
                    0.04
                );

            transition:
                0.2s ease;
        }


        .product-card:hover {
            transform:
                translateY(-4px);

            box-shadow:
                0 10px 25px
                rgba(
                    15,
                    23,
                    42,
                    0.08
                );
        }


        .product-image {
            width: 100%;

            height: 210px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #f8fafc;

            overflow: hidden;
        }


        .product-image img {
            width: 100%;
            height: 100%;

            object-fit: cover;

            transition:
                transform 0.3s ease;
        }


        .product-card:hover
        .product-image img {
            transform:
                scale(1.04);
        }


        .product-placeholder {
            color: #9ca3af;

            font-size: 13px;
        }


        .product-body {
            padding: 17px;
        }


        .product-category {
            display: inline-block;

            margin-bottom: 8px;

            padding:
                5px 8px;

            border-radius: 5px;

            background: #e8f2fc;

            color: #1677d2;

            font-size: 10px;

            font-weight: bold;
        }


        .product-name {
            min-height: 43px;

            margin:
                0 0 7px;

            color: #1f2937;

            font-size: 16px;

            line-height: 1.4;
        }


        .product-price {
            margin-bottom: 6px;

            color: #1677d2;

            font-size: 18px;

            font-weight: bold;
        }


        .product-stock {
            margin-bottom: 15px;

            color: #6b7280;

            font-size: 12px;
        }


        .btn-product {
            display: block;

            width: 100%;

            padding:
                11px;

            border-radius: 8px;

            background: #1677d2;

            color: white;

            text-align: center;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;

            transition:
                0.2s ease;
        }


        .btn-product:hover {
            background: #125ca3;
        }


        /* ========================================
           QUICK MENU
        ======================================== */

        .quick-section {
            margin-top: 38px;
        }


        .quick-grid {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;
        }


        .quick-item {
            display: flex;

            align-items: center;

            gap: 12px;

            padding: 16px;

            background: white;

            border:
                1px solid #e5e7eb;

            border-radius: 11px;

            color: #374151;

            text-decoration: none;

            transition:
                0.2s ease;
        }


        .quick-item:hover {
            border-color: #1677d2;

            color: #1677d2;

            transform:
                translateY(-2px);
        }


        .quick-icon {
            width: 40px;
            height: 40px;

            display: flex;

            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            border-radius: 9px;

            background: #eef5fc;

            font-size: 18px;
        }


        .quick-text strong {
            display: block;

            margin-bottom: 3px;

            font-size: 13px;
        }


        .quick-text span {
            color: #9ca3af;

            font-size: 11px;
        }


        /* ========================================
           EMPTY PRODUCT
        ======================================== */

        .empty-product {
            grid-column:
                1 / -1;

            padding:
                55px 20px;

            background: white;

            border:
                1px solid #e5e7eb;

            border-radius: 12px;

            color: #6b7280;

            text-align: center;
        }


        /* ========================================
           FOOTER
        ======================================== */

        .footer {
            margin-top: 50px;

            padding:
                25px 0;

            border-top:
                1px solid #e5e7eb;

            color: #6b7280;

            font-size: 12px;

            text-align: center;
        }


        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 950px) {

            .nav-menu {
                gap: 16px;
            }

            .nav-menu a {
                font-size: 13px;
            }

            .profile-button {
                display: none;
            }

            .product-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .quick-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }
        }


        @media (max-width: 700px) {

            .navbar-inner {
                min-height: 64px;

                padding:
                    0 15px;

                gap: 10px;
            }


            .logo-text {
                font-size: 17px;
            }


            .logo-icon {
                width: 38px;
                height: 38px;
            }


            .logo-icon img {
                width: 32px;
                height: 32px;
            }


            .nav-menu {
                display: none;
            }


            .logout-button {
                padding:
                    9px 10px;

                font-size: 0;
            }


            .logout-button::before {
                content: "↪";

                font-size: 18px;
            }


            .main {
                padding:
                    18px 14px 35px;
            }


            .hero {
                min-height: 300px;

                padding:
                    30px 25px;

                border-radius: 14px;
            }


            .hero h1 {
                font-size: 28px;
            }


            .hero p {
                font-size: 14px;
            }


            .hero-buttons {
                flex-wrap: wrap;
            }


            .stat-grid {
                grid-template-columns:
                    1fr;

                gap: 12px;
            }


            .product-grid {
                grid-template-columns:
                    1fr;
            }


            .quick-grid {
                grid-template-columns:
                    1fr;
            }


            .section-header {
                align-items: flex-start;

                flex-direction: column;
            }
        }


        @media (max-width: 430px) {

            .hero {
                padding:
                    27px 20px;
            }


            .hero h1 {
                font-size: 25px;
            }


            .hero-small {
                font-size: 10px;
            }


            .hero-buttons {
                width: 100%;

                flex-direction: column;

                align-items: stretch;
            }


            .btn-primary,
            .btn-secondary {
                width: 100%;
            }


            .cart-button {
                width: 38px;
                height: 38px;
            }

        }

    </style>

</head>


<body>


<!-- ==========================================
     NAVBAR
========================================== -->

<header class="navbar">

    <div class="navbar-inner">


        <!-- LOGO -->

        <a
            href="dashboard.php"
            class="logo"
        >

            <div class="logo-icon">

                <img
                    src="../assets/SembakoKu (3).png"
                    alt="Toko Malaya"
                >

            </div>

            <div class="logo-text">
                Toko Malaya
            </div>

        </a>


        <!-- MENU -->

        <nav class="nav-menu">

            <a
                href="dashboard.php"
                class="active"
            >
                Beranda
            </a>

            <a href="produk.php">
                Produk
            </a>

            <a href="pesanan.php">
                Pesanan
            </a>

        </nav>


        <!-- RIGHT -->

        <div class="nav-right">


            <a
                href="keranjang.php"
                class="cart-button"
                title="Keranjang"
            >

                🛒

                <?php if ($total_keranjang > 0): ?>

                    <span class="cart-badge">
                        <?= $total_keranjang; ?>
                    </span>

                <?php endif; ?>

            </a>


            <a
                href="profil.php"
                class="profile-button"
            >

                👤

                <?= htmlspecialchars($nama); ?>

            </a>


            <a
                href="../auth/logout.php"
                class="logout-button"
            >
                Logout
            </a>

        </div>

    </div>

</header>


<!-- ==========================================
     MAIN
========================================== -->

<main class="main">


    <!-- ======================================
         HERO
    ======================================= -->

    <section class="hero">

        <div class="hero-content">

            <span class="hero-small">
                🛒 BELANJA MUDAH DI TOKO MALAYA
            </span>


            <h1>
                Mau belanja apa hari ini,
                <?= htmlspecialchars($nama); ?>?
            </h1>


            <p>
                Temukan berbagai kebutuhan sembako,
                makanan, minuman, dan kebutuhan rumah
                tangga dengan mudah di Toko Malaya.
            </p>


            <div class="hero-buttons">

                <a
                    href="produk.php"
                    class="btn-primary"
                >
                    🛒 Mulai Belanja
                </a>


                <a
                    href="pesanan.php"
                    class="btn-secondary"
                >
                    📦 Lihat Pesanan
                </a>

            </div>

        </div>

    </section>


    <!-- ======================================
         STATISTIK
    ======================================= -->

    <section class="stat-grid">


        <div class="stat-card">

            <div class="stat-icon">
                📦
            </div>

            <div>

                <span class="stat-label">
                    Jumlah Pesanan
                </span>

                <strong class="stat-value">
                    <?= $total_pesanan; ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🔄
            </div>

            <div>

                <span class="stat-label">
                    Pesanan Diproses
                </span>

                <strong class="stat-value">
                    <?= $pesanan_diproses; ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                ✅
            </div>

            <div>

                <span class="stat-label">
                    Pesanan Selesai
                </span>

                <strong class="stat-value">
                    <?= $pesanan_selesai; ?>
                </strong>

            </div>

        </div>


    </section>


    <!-- ======================================
         PRODUK
    ======================================= -->

    <section>

        <div class="section-header">

            <div>

                <h2>
                    Produk Pilihan
                </h2>

                <p class="section-description">
                    Pilihan produk yang tersedia untuk kamu.
                </p>

            </div>


            <a
                href="produk.php"
                class="section-link"
            >
                Lihat Semua Produk →
            </a>

        </div>


        <div class="product-grid">


            <?php if (count($produk) > 0): ?>


                <?php foreach ($produk as $item): ?>


                    <article class="product-card">


                        <!-- GAMBAR -->

                        <div class="product-image">

                            <?php

                            $gambar =
                                trim(
                                    $item['gambar'] ?? ''
                                );

                            $path_gambar =
                                "../uploads/" .
                                $gambar;

                            ?>


                            <?php if (
                                $gambar !== '' &&
                                file_exists($path_gambar)
                            ): ?>

                                <img
                                    src="<?= htmlspecialchars($path_gambar); ?>"
                                    alt="<?= htmlspecialchars($item['nama_barang']); ?>"
                                >

                            <?php else: ?>

                                <div class="product-placeholder">
                                    Tidak ada gambar
                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- DETAIL -->

                        <div class="product-body">


                            <?php if (
                                !empty(
                                    $item['nama_kategori']
                                )
                            ): ?>

                                <span class="product-category">

                                    <?= htmlspecialchars(
                                        $item['nama_kategori']
                                    ); ?>

                                </span>

                            <?php endif; ?>


                            <h3 class="product-name">

                                <?= htmlspecialchars(
                                    $item['nama_barang']
                                ); ?>

                            </h3>


                            <div class="product-price">

                                <?= rupiah(
                                    $item['harga_jual']
                                ); ?>

                            </div>


                            <div class="product-stock">

                                Stok tersedia:
                                <?= (int) $item['stok']; ?>

                                <?= htmlspecialchars(
                                    $item['satuan']
                                ); ?>

                            </div>


                            <a
                                href="produk.php"
                                class="btn-product"
                            >
                                🛒 Belanja Produk
                            </a>


                        </div>

                    </article>


                <?php endforeach; ?>


            <?php else: ?>


                <div class="empty-product">

                    Belum ada produk yang tersedia
                    untuk dibeli.

                </div>


            <?php endif; ?>


        </div>

    </section>


    <!-- ======================================
         MENU CEPAT
    ======================================= -->

    <section class="quick-section">


        <div class="section-header">

            <div>

                <h2>
                    Menu Cepat
                </h2>

                <p class="section-description">
                    Akses fitur Toko Sembako dengan cepat.
                </p>

            </div>

        </div>


        <div class="quick-grid">


            <a
                href="produk.php"
                class="quick-item"
            >

                <div class="quick-icon">
                    🛒
                </div>

                <div class="quick-text">

                    <strong>
                        Semua Produk
                    </strong>

                    <span>
                        Lihat produk
                    </span>

                </div>

            </a>


            <a
                href="keranjang.php"
                class="quick-item"
            >

                <div class="quick-icon">
                    🛍️
                </div>

                <div class="quick-text">

                    <strong>
                        Keranjang
                    </strong>

                    <span>
                        Barang belanjaan
                    </span>

                </div>

            </a>


            <a
                href="pesanan.php"
                class="quick-item"
            >

                <div class="quick-icon">
                    📦
                </div>

                <div class="quick-text">

                    <strong>
                        Pesanan Saya
                    </strong>

                    <span>
                        Lihat status pesanan
                    </span>

                </div>

            </a>


            <a
                href="profil.php"
                class="quick-item"
            >

                <div class="quick-icon">
                    👤
                </div>

                <div class="quick-text">

                    <strong>
                        Profil Saya
                    </strong>

                    <span>
                        Kelola akun
                    </span>

                </div>

            </a>


        </div>

    </section>


    <!-- ======================================
         FOOTER
    ======================================= -->

    <footer class="footer">

        © <?= date('Y'); ?> Toko Malaya.
        Belanja kebutuhan sehari-hari dengan mudah.

    </footer>


</main>


</body>

</html>