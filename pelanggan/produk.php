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

if (($_SESSION['role'] ?? '') !== 'pelanggan') {
    header("Location: ../index.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];

$nama = $_SESSION['nama'] ?? 'Pelanggan';


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
// FILTER
// ========================================

$keyword = trim($_GET['keyword'] ?? '');

$id_kategori = isset($_GET['kategori'])
    ? (int) $_GET['kategori']
    : 0;


// ========================================
// DAFTAR KATEGORI
// ========================================

$kategori = [];

$query_kategori = mysqli_query(
    $conn,
    "SELECT
        id_kategori,
        nama_kategori
     FROM kategori
     ORDER BY nama_kategori ASC"
);

if ($query_kategori) {

    while ($row = mysqli_fetch_assoc($query_kategori)) {
        $kategori[] = $row;
    }
}


// ========================================
// QUERY PRODUK
// ========================================

$produk = [];

$sql = "
    SELECT
        b.id_barang,
        b.kode_barang,
        b.nama_barang,
        b.harga_jual,
        b.stok,
        b.satuan,
        b.gambar,
        b.id_kategori,
        k.nama_kategori
    FROM barang b
    LEFT JOIN kategori k
        ON b.id_kategori = k.id_kategori
    WHERE b.stok > 0
";

$params = [];
$types = '';


// ========================================
// SEARCH
// ========================================

if ($keyword !== '') {

    $sql .= "
        AND (
            b.nama_barang LIKE ?
            OR b.kode_barang LIKE ?
        )
    ";

    $search = '%' . $keyword . '%';

    $params[] = $search;
    $params[] = $search;

    $types .= 'ss';
}


// ========================================
// KATEGORI
// ========================================

if ($id_kategori > 0) {

    $sql .= "
        AND b.id_kategori = ?
    ";

    $params[] = $id_kategori;

    $types .= 'i';
}


$sql .= "
    ORDER BY b.nama_barang ASC
";


$stmt_produk = mysqli_prepare(
    $conn,
    $sql
);

if ($stmt_produk) {

    if (!empty($params)) {

        mysqli_stmt_bind_param(
            $stmt_produk,
            $types,
            ...$params
        );
    }

    mysqli_stmt_execute($stmt_produk);

    $result_produk =
        mysqli_stmt_get_result($stmt_produk);

    while (
        $row = mysqli_fetch_assoc($result_produk)
    ) {

        $produk[] = $row;
    }

    mysqli_stmt_close($stmt_produk);
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

    <title>Produk - Toko Malaya</title>


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
                30px 24px 50px;
        }


        /* ========================================
           PAGE HEADER
        ======================================== */

        .page-header {
            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 25px;
        }


        .page-title {
            margin: 0 0 6px;

            color: #153b63;

            font-size: 28px;
        }


        .page-description {
            margin: 0;

            color: #6b7280;

            font-size: 14px;

            line-height: 1.6;
        }


        /* ========================================
           FILTER
        ======================================== */

        .filter-card {
            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 25px;

            padding: 16px;

            background: #ffffff;

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


        .search-form {
            flex: 1;

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .search-input {
            width: 100%;

            height: 42px;

            padding:
                0 13px;

            border:
                1px solid #dbe3ec;

            border-radius: 8px;

            outline: none;

            color: #374151;

            font-family: inherit;

            font-size: 13px;
        }


        .search-input:focus {
            border-color: #1677d2;

            box-shadow:
                0 0 0 3px
                rgba(
                    22,
                    119,
                    210,
                    0.08
                );
        }


        .category-select {
            height: 42px;

            min-width: 190px;

            padding:
                0 12px;

            border:
                1px solid #dbe3ec;

            border-radius: 8px;

            background: #ffffff;

            color: #374151;

            outline: none;

            font-family: inherit;

            font-size: 13px;
        }


        .category-select:focus {
            border-color: #1677d2;
        }


        .btn-search {
            height: 42px;

            padding:
                0 18px;

            border: none;

            border-radius: 8px;

            background: #1677d2;

            color: #ffffff;

            cursor: pointer;

            font-family: inherit;

            font-size: 13px;

            font-weight: bold;
        }


        .btn-search:hover {
            background: #125ca3;
        }


        .btn-reset {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            height: 42px;

            padding:
                0 15px;

            border:
                1px solid #dbe3ec;

            border-radius: 8px;

            background: #ffffff;

            color: #6b7280;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;
        }


        .btn-reset:hover {
            border-color: #1677d2;

            color: #1677d2;
        }


        /* ========================================
           PRODUCT INFO
        ======================================== */

        .product-result-info {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 15px;
        }


        .result-text {
            color: #6b7280;

            font-size: 13px;
        }


        .result-text strong {
            color: #374151;
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

            background: #ffffff;

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


        /* ========================================
           PRODUCT IMAGE
        ======================================== */

        .product-image {
            width: 100%;

            height: 220px;

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
            display: flex;

            align-items: center;

            justify-content: center;

            width: 100%;
            height: 100%;

            color: #9ca3af;

            font-size: 13px;

            text-align: center;
        }


        /* ========================================
           PRODUCT BODY
        ======================================== */

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
            min-height: 44px;

            margin:
                0 0 7px;

            color: #1f2937;

            font-size: 16px;

            line-height: 1.4;
        }


        .product-code {
            margin-bottom: 10px;

            color: #9ca3af;

            font-size: 11px;
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


        /* ========================================
           TOMBOL PRODUK
        ======================================== */

        .btn-product {
            display: flex;

            align-items: center;

            justify-content: center;

            width: 100%;

            min-height: 41px;

            padding:
                10px;

            border: none;

            border-radius: 8px;

            background: #1677d2;

            color: #ffffff;

            text-align: center;

            text-decoration: none;

            font-family: inherit;

            font-size: 13px;

            font-weight: bold;

            cursor: pointer;

            transition:
                0.2s ease;
        }


        .btn-product:hover {
            background: #125ca3;
        }


        .btn-product:disabled {
            opacity: .65;

            cursor: not-allowed;

            transform: none;
        }


        /* ========================================
           EMPTY
        ======================================== */

        .empty-product {
            grid-column:
                1 / -1;

            padding:
                65px 20px;

            background: #ffffff;

            border:
                1px solid #e5e7eb;

            border-radius: 12px;

            color: #6b7280;

            text-align: center;

            box-shadow:
                0 3px 12px
                rgba(
                    15,
                    23,
                    42,
                    0.04
                );
        }


        .empty-icon {
            margin-bottom: 12px;

            font-size: 42px;
        }


        .empty-product h3 {
            margin:
                0 0 7px;

            color: #374151;

            font-size: 18px;
        }


        .empty-product p {
            margin: 0;

            font-size: 13px;
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

            .filter-card {
                flex-wrap: wrap;
            }

            .search-form {
                flex-basis: 100%;
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
                    22px 14px 35px;
            }


            .page-header {
                align-items: flex-start;

                flex-direction: column;

                margin-bottom: 20px;
            }


            .page-title {
                font-size: 25px;
            }


            .filter-card {
                align-items: stretch;

                flex-direction: column;

                padding: 13px;
            }


            .search-form {
                width: 100%;

                flex-direction: column;

                align-items: stretch;
            }


            .category-select {
                width: 100%;
            }


            .btn-search,
            .btn-reset {
                width: 100%;
            }


            .product-grid {
                grid-template-columns:
                    1fr;
            }


            .product-image {
                height: 230px;
            }

        }


        @media (max-width: 430px) {

            .cart-button {
                width: 38px;
                height: 38px;
            }


            .product-image {
                height: 215px;
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

            <a href="dashboard.php">
                Beranda
            </a>

            <a
                href="produk.php"
                class="active"
            >
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


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div>

            <h1 class="page-title">
                Semua Produk
            </h1>

            <p class="page-description">
                Temukan berbagai kebutuhan sehari-hari
                yang tersedia di Toko Malaya.
            </p>

        </div>

    </div>


    <!-- FILTER -->

    <div class="filter-card">

        <form
            action="produk.php"
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="keyword"
                class="search-input"
                placeholder="Cari nama atau kode produk..."
                value="<?= htmlspecialchars($keyword); ?>"
            >


            <select
                name="kategori"
                class="category-select"
            >

                <option value="0">
                    Semua Kategori
                </option>


                <?php foreach ($kategori as $item): ?>

                    <option
                        value="<?= (int) $item['id_kategori']; ?>"
                        <?= (
                            $id_kategori ===
                            (int) $item['id_kategori']
                        )
                            ? 'selected'
                            : ''; ?>
                    >

                        <?= htmlspecialchars(
                            $item['nama_kategori']
                        ); ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <button
                type="submit"
                class="btn-search"
            >
                🔍 Cari
            </button>


            <?php if (
                $keyword !== '' ||
                $id_kategori > 0
            ): ?>

                <a
                    href="produk.php"
                    class="btn-reset"
                >
                    Reset
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- RESULT INFO -->

    <div class="product-result-info">

        <div class="result-text">

            Menampilkan
            <strong>
                <?= count($produk); ?>
            </strong>
            produk tersedia

        </div>

    </div>


    <!-- PRODUCT GRID -->

    <div class="product-grid">


        <?php if (count($produk) > 0): ?>


            <?php foreach ($produk as $item): ?>

                <?php

                $gambar =
                    trim(
                        $item['gambar'] ?? ''
                    );

                $path_gambar =
                    "../uploads/" .
                    $gambar;

                ?>


                <article class="product-card">


                    <!-- IMAGE -->

                    <div class="product-image">

                        <?php if (
                            $gambar !== '' &&
                            file_exists($path_gambar)
                        ): ?>

                            <img
                                src="<?= htmlspecialchars(
                                    $path_gambar
                                ); ?>"
                                alt="<?= htmlspecialchars(
                                    $item['nama_barang']
                                ); ?>"
                            >

                        <?php else: ?>

                            <div class="product-placeholder">

                                📦
                                <br>
                                Tidak ada gambar

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- BODY -->

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


                        <h2 class="product-name">

                            <?= htmlspecialchars(
                                $item['nama_barang']
                            ); ?>

                        </h2>


                        <?php if (
                            !empty(
                                $item['kode_barang']
                            )
                        ): ?>

                            <div class="product-code">

                                Kode:
                                <?= htmlspecialchars(
                                    $item['kode_barang']
                                ); ?>

                            </div>

                        <?php endif; ?>


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


                        <!-- =====================================
                             TOMBOL TAMBAH KERANJANG
                        ====================================== -->

                        <button
                            type="button"
                            class="btn-product"
                            onclick="tambahKeranjang(
                                <?= (int) $item['id_barang']; ?>,
                                this
                            )"
                        >
                            🛒 Tambah ke Keranjang
                        </button>


                    </div>


                </article>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="empty-product">

                <div class="empty-icon">
                    📦
                </div>

                <h3>
                    Produk Tidak Ditemukan
                </h3>

                <p>
                    Tidak ada produk yang sesuai
                    dengan pencarian kamu.
                </p>

            </div>


        <?php endif; ?>


    </div>


    <!-- FOOTER -->

    <footer class="footer">

        © <?= date('Y'); ?> Toko Malaya.
        Belanja kebutuhan sehari-hari dengan mudah.

    </footer>


</main>


<!-- ==========================================
     JAVASCRIPT TAMBAH KERANJANG
========================================== -->

<script>

async function tambahKeranjang(idBarang, button)
{
    // Cegah tombol diklik berkali-kali
    if (button.disabled) {
        return;
    }


    const teksAwal = button.innerHTML;


    button.disabled = true;

    button.innerHTML =
        '⏳ Menambahkan...';


    try {

        const formData = new FormData();

        formData.append(
            'id_barang',
            idBarang
        );


        const response = await fetch(
            'tambah_keranjang.php',
            {
                method: 'POST',
                body: formData
            }
        );


        // Pastikan response berhasil
        if (!response.ok) {
            throw new Error(
                'HTTP Error: ' +
                response.status
            );
        }


        const data =
            await response.json();


        // ====================================
        // BERHASIL
        // ====================================

        if (data.success) {

            // Update badge keranjang
            updateCartBadge(
                data.total_keranjang
            );


            // Tampilkan pesan
            showCartMessage(
                data.message,
                true
            );


        } else {

            showCartMessage(
                data.message ||
                'Gagal menambahkan produk.',
                false
            );

        }


    } catch (error) {

        console.error(
            'Tambah keranjang error:',
            error
        );


        showCartMessage(
            'Terjadi kesalahan saat menambahkan produk ke keranjang.',
            false
        );

    } finally {

        button.disabled = false;

        button.innerHTML =
            teksAwal;
    }
}


// ==========================================
// UPDATE BADGE KERANJANG
// ==========================================

function updateCartBadge(total)
{
    const cartButton =
        document.querySelector(
            '.cart-button'
        );


    if (!cartButton) {
        return;
    }


    let badge =
        cartButton.querySelector(
            '.cart-badge'
        );


    total = parseInt(total) || 0;


    if (total > 0) {

        if (!badge) {

            badge =
                document.createElement(
                    'span'
                );

            badge.className =
                'cart-badge';

            cartButton.appendChild(
                badge
            );
        }


        badge.textContent =
            total;

    } else {

        if (badge) {
            badge.remove();
        }

    }
}


// ==========================================
// PESAN NOTIFIKASI
// ==========================================

function showCartMessage(
    message,
    success
)
{
    let notification =
        document.getElementById(
            'cartNotification'
        );


    if (!notification) {

        notification =
            document.createElement(
                'div'
            );

        notification.id =
            'cartNotification';


        document.body.appendChild(
            notification
        );
    }


    notification.textContent =
        message;


    notification.className =
        success
            ? 'cart-notification success'
            : 'cart-notification error';


    notification.classList.add(
        'show'
    );


    clearTimeout(
        window.cartNotificationTimer
    );


    window.cartNotificationTimer =
        setTimeout(
            function () {

                notification.classList.remove(
                    'show'
                );

            },
            2500
        );
}

</script>


<style>

/* ==========================================
   NOTIFIKASI KERANJANG
========================================== */

.cart-notification {

    position: fixed;

    top: 88px;

    right: 24px;

    z-index: 2000;

    max-width: 350px;

    padding:
        13px 17px;

    border-radius: 9px;

    background: #ffffff;

    border:
        1px solid #dbe3ec;

    box-shadow:
        0 8px 25px
        rgba(
            15,
            23,
            42,
            0.14
        );

    color: #374151;

    font-size: 13px;

    font-weight: 600;

    opacity: 0;

    visibility: hidden;

    transform:
        translateY(-10px);

    transition:
        0.25s ease;
}


.cart-notification.show {

    opacity: 1;

    visibility: visible;

    transform:
        translateY(0);
}


.cart-notification.success {

    border-left:
        4px solid #1677d2;

    color: #1677d2;
}


.cart-notification.error {

    border-left:
        4px solid #dc3545;

    color: #dc3545;
}


@media (max-width: 600px) {

    .cart-notification {

        left: 14px;

        right: 14px;

        top: 78px;

        max-width: none;

    }

}

</style>


</body>

</html>