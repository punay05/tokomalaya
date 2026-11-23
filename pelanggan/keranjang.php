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
// PESAN
// ========================================

$error = "";
$success = "";


// ========================================
// TAMBAH PRODUK KE KERANJANG
// ========================================

if (isset($_GET['tambah'])) {

    $id_barang = (int) $_GET['tambah'];

    if ($id_barang > 0) {

        $query_barang = mysqli_prepare(
            $conn,
            "SELECT id_barang, stok
             FROM barang
             WHERE id_barang = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $query_barang,
            "i",
            $id_barang
        );

        mysqli_stmt_execute($query_barang);

        $result_barang =
            mysqli_stmt_get_result($query_barang);

        $barang =
            mysqli_fetch_assoc($result_barang);

        mysqli_stmt_close($query_barang);


        if (!$barang) {

            $error = "Produk tidak ditemukan.";

        } elseif ((int) $barang['stok'] <= 0) {

            $error = "Stok produk sedang habis.";

        } else {

            $query_cek = mysqli_prepare(
                $conn,
                "SELECT id_keranjang, jumlah
                 FROM keranjang
                 WHERE id_user = ?
                 AND id_barang = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $query_cek,
                "ii",
                $id_user,
                $id_barang
            );

            mysqli_stmt_execute($query_cek);

            $result_cek =
                mysqli_stmt_get_result($query_cek);

            $keranjang_lama =
                mysqli_fetch_assoc($result_cek);

            mysqli_stmt_close($query_cek);


            if ($keranjang_lama) {

                $jumlah_baru =
                    (int) $keranjang_lama['jumlah'] + 1;


                if (
                    $jumlah_baru >
                    (int) $barang['stok']
                ) {

                    $error =
                        "Jumlah melebihi stok yang tersedia.";

                } else {

                    $query_update =
                        mysqli_prepare(
                            $conn,
                            "UPDATE keranjang
                             SET jumlah = ?
                             WHERE id_keranjang = ?
                             AND id_user = ?"
                        );

                    mysqli_stmt_bind_param(
                        $query_update,
                        "iii",
                        $jumlah_baru,
                        $keranjang_lama['id_keranjang'],
                        $id_user
                    );

                    mysqli_stmt_execute(
                        $query_update
                    );

                    mysqli_stmt_close(
                        $query_update
                    );

                    $success =
                        "Produk berhasil ditambahkan ke keranjang.";
                }

            } else {

                $jumlah = 1;

                $query_insert =
                    mysqli_prepare(
                        $conn,
                        "INSERT INTO keranjang
                            (id_user, id_barang, jumlah)
                         VALUES (?, ?, ?)"
                    );

                mysqli_stmt_bind_param(
                    $query_insert,
                    "iii",
                    $id_user,
                    $id_barang,
                    $jumlah
                );

                mysqli_stmt_execute(
                    $query_insert
                );

                mysqli_stmt_close(
                    $query_insert
                );

                $success =
                    "Produk berhasil ditambahkan ke keranjang.";
            }
        }
    }
}


// ========================================
// TAMBAH JUMLAH
// ========================================

if (isset($_GET['plus'])) {

    $id_keranjang =
        (int) $_GET['plus'];


    $query = mysqli_prepare(
        $conn,
        "SELECT
            k.id_keranjang,
            k.jumlah,
            b.stok
         FROM keranjang k
         INNER JOIN barang b
            ON k.id_barang = b.id_barang
         WHERE k.id_keranjang = ?
         AND k.id_user = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $query,
        "ii",
        $id_keranjang,
        $id_user
    );

    mysqli_stmt_execute($query);

    $result =
        mysqli_stmt_get_result($query);

    $data =
        mysqli_fetch_assoc($result);

    mysqli_stmt_close($query);


    if ($data) {

        $jumlah_baru =
            (int) $data['jumlah'] + 1;


        if (
            $jumlah_baru <=
            (int) $data['stok']
        ) {

            $update = mysqli_prepare(
                $conn,
                "UPDATE keranjang
                 SET jumlah = ?
                 WHERE id_keranjang = ?
                 AND id_user = ?"
            );

            mysqli_stmt_bind_param(
                $update,
                "iii",
                $jumlah_baru,
                $id_keranjang,
                $id_user
            );

            mysqli_stmt_execute($update);

            mysqli_stmt_close($update);

        } else {

            $error =
                "Jumlah sudah mencapai stok tersedia.";
        }
    }
}


// ========================================
// KURANGI JUMLAH
// ========================================

if (isset($_GET['minus'])) {

    $id_keranjang =
        (int) $_GET['minus'];


    $query = mysqli_prepare(
        $conn,
        "SELECT jumlah
         FROM keranjang
         WHERE id_keranjang = ?
         AND id_user = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $query,
        "ii",
        $id_keranjang,
        $id_user
    );

    mysqli_stmt_execute($query);

    $result =
        mysqli_stmt_get_result($query);

    $data =
        mysqli_fetch_assoc($result);

    mysqli_stmt_close($query);


    if ($data) {

        $jumlah_baru =
            (int) $data['jumlah'] - 1;


        if ($jumlah_baru <= 0) {

            $delete = mysqli_prepare(
                $conn,
                "DELETE FROM keranjang
                 WHERE id_keranjang = ?
                 AND id_user = ?"
            );

            mysqli_stmt_bind_param(
                $delete,
                "ii",
                $id_keranjang,
                $id_user
            );

            mysqli_stmt_execute($delete);

            mysqli_stmt_close($delete);

        } else {

            $update = mysqli_prepare(
                $conn,
                "UPDATE keranjang
                 SET jumlah = ?
                 WHERE id_keranjang = ?
                 AND id_user = ?"
            );

            mysqli_stmt_bind_param(
                $update,
                "iii",
                $jumlah_baru,
                $id_keranjang,
                $id_user
            );

            mysqli_stmt_execute($update);

            mysqli_stmt_close($update);
        }
    }
}


// ========================================
// HAPUS PRODUK
// ========================================

if (isset($_GET['hapus'])) {

    $id_keranjang =
        (int) $_GET['hapus'];


    $delete = mysqli_prepare(
        $conn,
        "DELETE FROM keranjang
         WHERE id_keranjang = ?
         AND id_user = ?"
    );

    mysqli_stmt_bind_param(
        $delete,
        "ii",
        $id_keranjang,
        $id_user
    );

    mysqli_stmt_execute($delete);

    mysqli_stmt_close($delete);


    $success =
        "Produk berhasil dihapus dari keranjang.";
}


// ========================================
// AMBIL KERANJANG
// ========================================

$items = [];

$total = 0;

$total_item = 0;

$total_keranjang = 0;


$query_keranjang = mysqli_prepare(
    $conn,
    "SELECT
        k.id_keranjang,
        k.jumlah,
        k.catatan,
        b.id_barang,
        b.kode_barang,
        b.nama_barang,
        b.harga_jual,
        b.stok,
        b.satuan,
        b.gambar,
        ka.nama_kategori
     FROM keranjang k
     INNER JOIN barang b
        ON k.id_barang = b.id_barang
     LEFT JOIN kategori ka
        ON b.id_kategori = ka.id_kategori
     WHERE k.id_user = ?
     ORDER BY k.id_keranjang DESC"
);

mysqli_stmt_bind_param(
    $query_keranjang,
    "i",
    $id_user
);

mysqli_stmt_execute(
    $query_keranjang
);

$result_keranjang =
    mysqli_stmt_get_result(
        $query_keranjang
    );


while (
    $row =
    mysqli_fetch_assoc(
        $result_keranjang
    )
) {

    $subtotal =
        (float) $row['harga_jual'] *
        (int) $row['jumlah'];


    $row['subtotal'] =
        $subtotal;


    $items[] =
        $row;


    $total +=
        $subtotal;


    $total_item +=
        (int) $row['jumlah'];
}


mysqli_stmt_close(
    $query_keranjang
);


$total_keranjang =
    $total_item;

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
        Keranjang - Toko Malaya
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
                rgba(
                    15,
                    23,
                    42,
                    0.05
                );
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


        .nav-menu a:hover {
            color: #1677d2;
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

            background: #e3effb;

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

            padding:
                0 5px;

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
                35px 24px 50px;
        }


        /* ========================================
           PAGE HEADER
        ======================================== */

        .page-header {
            margin-bottom: 25px;
        }


        .page-header h1 {
            margin:
                0 0 7px;

            color: #153b63;

            font-size: 28px;
        }


        .page-header p {
            margin: 0;

            color: #6b7280;

            font-size: 14px;
        }


        /* ========================================
           ALERT
        ======================================== */

        .alert {
            padding:
                12px 15px;

            margin-bottom: 18px;

            border-radius: 8px;

            font-size: 13px;
        }


        .alert-success {
            background: #eaf7ef;

            color: #146c43;

            border:
                1px solid #b9e2c8;
        }


        .alert-error {
            background: #fff1f2;

            color: #b42318;

            border:
                1px solid #f3c1c7;
        }


        /* ========================================
           CART LAYOUT
        ======================================== */

        .cart-layout {
            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                330px;

            gap: 22px;

            align-items: start;
        }


        .cart-list {
            display: flex;

            flex-direction: column;

            gap: 14px;
        }


        .cart-item {
            display: grid;

            grid-template-columns:
                90px
                minmax(0, 1fr)
                auto
                auto;

            align-items: start;

            gap: 18px;

            padding: 18px;

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
                    0.05
                );
        }


        .cart-image {
            width: 90px;
            height: 90px;

            display: flex;

            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            overflow: hidden;

            border-radius: 9px;

            background: #f8fafc;
        }


        .cart-image img {
            width: 100%;
            height: 100%;

            object-fit: cover;
        }


        .cart-placeholder {
            padding: 8px;

            color: #9ca3af;

            font-size: 10px;

            text-align: center;
        }


        .cart-info {
            min-width: 0;
        }


        .cart-category {
            display: inline-block;

            margin-bottom: 6px;

            padding:
                4px 7px;

            border-radius: 5px;

            background: #e8f2fc;

            color: #1677d2;

            font-size: 10px;

            font-weight: bold;
        }


        .cart-name {
            margin:
                0 0 6px;

            color: #1f2937;

            font-size: 16px;
        }


        .cart-code {
            margin-bottom: 7px;

            color: #9ca3af;

            font-size: 11px;
        }


        .cart-price {
            color: #1677d2;

            font-size: 14px;

            font-weight: bold;
        }


        /* ========================================
           CATATAN PRODUK
        ======================================== */

        .catatan-produk {
            margin-top: 14px;

            max-width: 520px;
        }


        .catatan-label {
            display: block;

            margin-bottom: 6px;

            color: #4b5563;

            font-size: 12px;

            font-weight: bold;
        }


        .catatan-textarea {
            width: 100%;

            min-height: 58px;

            padding:
                9px 11px;

            border:
                1px solid #dbe2ea;

            border-radius: 8px;

            background: #ffffff;

            color: #374151;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 12px;

            line-height: 1.5;

            resize: vertical;

            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }


        .catatan-textarea::placeholder {
            color: #a1aab5;
        }


        .catatan-textarea:focus {
            border-color: #1677d2;

            box-shadow:
                0 0 0 3px
                rgba(
                    22,
                    119,
                    210,
                    0.10
                );
        }


        .catatan-footer {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            margin-top: 6px;
        }


        .catatan-hint {
            color: #9ca3af;

            font-size: 10px;
        }


        .btn-simpan-catatan {
            padding:
                7px 11px;

            border: none;

            border-radius: 6px;

            background: #e8f2fc;

            color: #1677d2;

            font-size: 11px;

            font-weight: bold;

            cursor: pointer;

            transition:
                0.2s ease;
        }


        .btn-simpan-catatan:hover {
            background: #d8eafa;
        }


        .btn-simpan-catatan:disabled {
            opacity: 0.6;

            cursor: not-allowed;
        }


        .catatan-status {
            min-height: 15px;

            margin-top: 4px;

            color: #198754;

            font-size: 10px;
        }


        .catatan-status.error {
            color: #dc3545;
        }


        /* ========================================
           QUANTITY
        ======================================== */

        .cart-quantity {
            display: flex;

            align-items: center;

            gap: 7px;
        }


        .quantity-btn {
            width: 31px;
            height: 31px;

            display: flex;

            align-items: center;
            justify-content: center;

            border:
                1px solid #dbe2ea;

            border-radius: 6px;

            background: #f1f5f9;

            color: #374151;

            text-decoration: none;

            font-size: 17px;

            font-weight: bold;
        }


        .quantity-btn:hover {
            background: #e8f2fc;

            border-color: #1677d2;

            color: #1677d2;
        }


        .quantity-number {
            min-width: 32px;

            color: #1f2937;

            font-size: 14px;

            font-weight: bold;

            text-align: center;
        }


        /* ========================================
           SUBTOTAL
        ======================================== */

        .cart-subtotal {
            min-width: 120px;

            text-align: right;
        }


        .cart-subtotal span {
            display: block;

            margin-bottom: 5px;

            color: #9ca3af;

            font-size: 11px;
        }


        .cart-subtotal strong {
            color: #153b63;

            font-size: 15px;
        }


        .delete-cart {
            display: inline-block;

            margin-top: 7px;

            color: #dc3545;

            text-decoration: none;

            font-size: 11px;
        }


        .delete-cart:hover {
            text-decoration: underline;
        }


        /* ========================================
           SUMMARY
        ======================================== */

        .summary-card {
            position: sticky;

            top: 92px;

            padding: 22px;

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
                    0.05
                );
        }


        .summary-card h2 {
            margin:
                0 0 20px;

            color: #153b63;

            font-size: 19px;
        }


        .summary-row {
            display: flex;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 13px;

            color: #6b7280;

            font-size: 13px;
        }


        .summary-row strong {
            color: #1f2937;
        }


        .summary-divider {
            height: 1px;

            margin:
                17px 0;

            background: #e5e7eb;
        }


        .summary-total {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 18px;
        }


        .summary-total span {
            color: #1f2937;

            font-size: 14px;

            font-weight: bold;
        }


        .summary-total strong {
            color: #1677d2;

            font-size: 20px;
        }


        .btn-checkout {
            display: block;

            width: 100%;

            padding: 12px;

            border-radius: 8px;

            background: #1677d2;

            color: #ffffff;

            text-decoration: none;

            text-align: center;

            font-size: 13px;

            font-weight: bold;
        }


        .btn-checkout:hover {
            background: #125ca3;
        }


        .btn-continue {
            display: block;

            width: 100%;

            margin-top: 10px;

            padding: 11px;

            border-radius: 8px;

            border:
                1px solid #dbe2ea;

            color: #374151;

            text-align: center;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;
        }


        .btn-continue:hover {
            background: #f8fafc;
        }


        /* ========================================
           EMPTY CART
        ======================================== */

        .empty-cart {
            padding:
                65px 25px;

            background: #ffffff;

            border:
                1px solid #e5e7eb;

            border-radius: 12px;

            text-align: center;

            box-shadow:
                0 3px 12px
                rgba(
                    15,
                    23,
                    42,
                    0.05
                );
        }


        .empty-cart-icon {
            margin-bottom: 15px;

            font-size: 45px;
        }


        .empty-cart h2 {
            margin:
                0 0 8px;

            color: #153b63;

            font-size: 20px;
        }


        .empty-cart p {
            margin:
                0 0 20px;

            color: #6b7280;

            font-size: 13px;
        }


        .btn-start-shopping {
            display: inline-block;

            padding:
                11px 18px;

            border-radius: 8px;

            background: #1677d2;

            color: #ffffff;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;
        }


        .btn-start-shopping:hover {
            background: #125ca3;
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


            .cart-layout {
                grid-template-columns: 1fr;
            }


            .summary-card {
                position: static;
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
                    25px 14px 35px;
            }


            .cart-item {
                grid-template-columns:
                    80px
                    minmax(0, 1fr);
            }


            .cart-image {
                width: 80px;
                height: 80px;
            }


            .cart-quantity {
                grid-column: 2;
            }


            .cart-subtotal {
                grid-column: 2;

                min-width: auto;

                text-align: left;
            }


            .catatan-produk {
                grid-column: 2;

                max-width: none;
            }

        }


        @media (max-width: 430px) {

            .page-header h1 {
                font-size: 24px;
            }


            .cart-button {
                width: 38px;
                height: 38px;
            }


            .cart-item {
                grid-template-columns:
                    70px
                    minmax(0, 1fr);

                padding: 14px;

                gap: 13px;
            }


            .cart-image {
                width: 70px;
                height: 70px;
            }


            .cart-name {
                font-size: 14px;
            }


            .cart-price {
                font-size: 13px;
            }


            .catatan-footer {
                align-items: flex-start;

                flex-direction: column;
            }


            .btn-simpan-catatan {
                width: 100%;
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


    <!-- HEADER -->

    <div class="page-header">

        <h1>
            Keranjang Belanja
        </h1>

        <p>
            Atur produk yang ingin kamu pesan
            sebelum checkout.
        </p>

    </div>


    <!-- ALERT -->

    <?php if ($success !== ""): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($success); ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">

            <?= htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>


    <!-- EMPTY -->

    <?php if (count($items) === 0): ?>


        <div class="empty-cart">

            <div class="empty-cart-icon">
                🛒
            </div>


            <h2>
                Keranjang masih kosong
            </h2>


            <p>
                Yuk pilih produk kebutuhanmu
                terlebih dahulu.
            </p>


            <a
                href="produk.php"
                class="btn-start-shopping"
            >
                Mulai Belanja
            </a>

        </div>


    <?php else: ?>


        <!-- CART -->

        <div class="cart-layout">


            <!-- LIST PRODUK -->

            <div class="cart-list">


                <?php foreach ($items as $item): ?>


                    <div class="cart-item">


                        <!-- GAMBAR -->

                        <div class="cart-image">

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

                                <div class="cart-placeholder">

                                    Tidak ada gambar

                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- INFO -->

                        <div class="cart-info">


                            <?php if (
                                !empty(
                                    $item['nama_kategori']
                                )
                            ): ?>

                                <span class="cart-category">

                                    <?= htmlspecialchars(
                                        $item['nama_kategori']
                                    ); ?>

                                </span>

                            <?php endif; ?>


                            <h3 class="cart-name">

                                <?= htmlspecialchars(
                                    $item['nama_barang']
                                ); ?>

                            </h3>


                            <div class="cart-code">

                                Kode:
                                <?= htmlspecialchars(
                                    $item['kode_barang']
                                ); ?>

                            </div>


                            <div class="cart-price">

                                <?= rupiah(
                                    $item['harga_jual']
                                ); ?>

                                /

                                <?= htmlspecialchars(
                                    $item['satuan']
                                ); ?>

                            </div>


                            <!-- =================================
                                 CATATAN PRODUK
                            ================================== -->

                            <div
                                class="catatan-produk"
                                data-id="<?= (int) $item['id_keranjang']; ?>"
                            >

                                <label class="catatan-label">

                                    📝 Catatan untuk produk

                                </label>


                                <textarea
                                    class="catatan-textarea"
                                    id="catatan_<?= (int) $item['id_keranjang']; ?>"
                                    maxlength="500"
                                    placeholder=
                                ><?= htmlspecialchars($item['catatan'] ?? ''); ?></textarea>


                                <div class="catatan-footer">

                                    <span class="catatan-hint">

                                        Maksimal 500 karakter

                                    </span>


                                    <button
                                        type="button"
                                        class="btn-simpan-catatan"
                                        data-id="<?= (int) $item['id_keranjang']; ?>"
                                    >

                                        Simpan Catatan

                                    </button>

                                </div>


                                <div
                                    class="catatan-status"
                                    id="status_<?= (int) $item['id_keranjang']; ?>"
                                ></div>

                            </div>

                        </div>


                        <!-- JUMLAH -->

                        <div class="cart-quantity">


                            <a
                                href="keranjang.php?minus=<?= (int) $item['id_keranjang']; ?>"
                                class="quantity-btn"
                                title="Kurangi"
                            >
                                −
                            </a>


                            <span class="quantity-number">

                                <?= (int) $item['jumlah']; ?>

                            </span>


                            <a
                                href="keranjang.php?plus=<?= (int) $item['id_keranjang']; ?>"
                                class="quantity-btn"
                                title="Tambah"
                            >
                                +
                            </a>


                        </div>


                        <!-- SUBTOTAL -->

                        <div class="cart-subtotal">


                            <span>
                                Subtotal
                            </span>


                            <strong>

                                <?= rupiah(
                                    $item['subtotal']
                                ); ?>

                            </strong>


                            <br>


                            <a
                                href="keranjang.php?hapus=<?= (int) $item['id_keranjang']; ?>"
                                class="delete-cart"
                                onclick="return confirm('Hapus produk ini dari keranjang?');"
                            >

                                Hapus

                            </a>


                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


            <!-- RINGKASAN -->

            <aside class="summary-card">


                <h2>
                    Ringkasan Belanja
                </h2>


                <div class="summary-row">

                    <span>
                        Jumlah item
                    </span>

                    <strong>
                        <?= $total_item; ?>
                    </strong>

                </div>


                <div class="summary-row">

                    <span>
                        Produk
                    </span>

                    <strong>
                        <?= count($items); ?>
                    </strong>

                </div>


                <div class="summary-divider"></div>


                <div class="summary-total">

                    <span>
                        Total
                    </span>

                    <strong>
                        <?= rupiah($total); ?>
                    </strong>

                </div>


                <a
                    href="checkout.php"
                    class="btn-checkout"
                >
                    Lanjut Checkout
                </a>


                <a
                    href="produk.php"
                    class="btn-continue"
                >
                    ← Lanjut Belanja
                </a>


            </aside>


        </div>


    <?php endif; ?>


    <!-- FOOTER -->

    <footer class="footer">

        © <?= date('Y'); ?> Toko Malaya.
        Belanja kebutuhan sehari-hari dengan mudah.

    </footer>


</main>


<!-- ==========================================
     JAVASCRIPT CATATAN
========================================== -->

<script>

document
    .querySelectorAll('.btn-simpan-catatan')
    .forEach(function(button) {

        button.addEventListener('click', async function() {

            const idKeranjang =
                this.dataset.id;

            const textarea =
                document.getElementById(
                    'catatan_' + idKeranjang
                );

            const status =
                document.getElementById(
                    'status_' + idKeranjang
                );


            if (!textarea || !status) {
                return;
            }


            const catatan =
                textarea.value.trim();


            const formData =
                new FormData();

            formData.append(
                'id_keranjang',
                idKeranjang
            );

            formData.append(
                'catatan',
                catatan
            );


            const teksAwal =
                this.textContent;


            this.disabled = true;

            this.textContent =
                'Menyimpan...';


            status.textContent = '';

            status.classList.remove(
                'error'
            );


            try {

                const response =
                    await fetch(
                        'tambah_catatan.php',
                        {
                            method: 'POST',

                            body: formData,

                            credentials:
                                'same-origin'
                        }
                    );


                const data =
                    await response.json();


                if (data.success) {

                    status.textContent =
                        '✓ Catatan berhasil disimpan.';

                    status.classList.remove(
                        'error'
                    );

                } else {

                    status.textContent =
                        data.message ||
                        'Catatan gagal disimpan.';

                    status.classList.add(
                        'error'
                    );
                }


            } catch (error) {

                status.textContent =
                    'Terjadi kesalahan saat menyimpan catatan.';

                status.classList.add(
                    'error'
                );

            } finally {

                this.disabled = false;

                this.textContent =
                    teksAwal;

            }

        });

    });

</script>


</body>

</html>