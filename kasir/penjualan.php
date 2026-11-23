<?php

session_start();

require_once "../config/database.php";

// ========================================
// CEK LOGIN
// ========================================

if (
    !isset($_SESSION['id_user']) ||
    $_SESSION['role'] !== 'kasir'
) {
    header("Location: ../auth/login.php");
    exit;
}

$nama_user = $_SESSION['nama'] ?? 'Kasir';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Penjualan - Toko Malaya</title>

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
            background: #f4f7fb;
            color: #1f2937;
        }

        body.sidebar-open {
            overflow: hidden;
        }

        /* ========================================
           TOPBAR
           ======================================== */

        .topbar {
            position: sticky;
            top: 0;
            z-index: 900;

            height: 72px;

            display: flex;
            align-items: center;

            background: #ffffff;

            border-bottom: 1px solid #e8edf3;

            padding: 0 24px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;

            min-width: 0;
        }

        .hamburger {
            width: 42px;
            height: 42px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border: 1px solid #e0e6ed;
            border-radius: 9px;

            background: #ffffff;

            color: #1f2937;

            font-size: 22px;

            cursor: pointer;

            transition:
                background 0.2s ease,
                border-color 0.2s ease;
        }

        .hamburger:hover {
            background: #f4f8fc;
            border-color: #cfd8e3;
        }

        .topbar-brand {
            display: flex;
            align-items: center;

            gap: 10px;

            text-decoration: none;

            color: #1f2937;
        }

        .topbar-logo {
            width: 38px;
            height: 38px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #1677d2;

            border-radius: 9px;

            overflow: hidden;

            flex-shrink: 0;
        }

        .topbar-logo img {
            width: 32px;
            height: 32px;

            object-fit: contain;
        }

        .topbar-brand-text {
            display: flex;
            flex-direction: column;

            line-height: 1.1;
        }

        .topbar-brand-text strong {
            font-size: 17px;
            color: #1f2937;
        }

        .topbar-brand-text span {
            margin-top: 3px;

            font-size: 11px;

            color: #7b8794;
        }

        .topbar-right {
            margin-left: auto;

            display: flex;
            align-items: center;

            gap: 10px;
        }

        .topbar-user {
            display: flex;
            align-items: center;

            gap: 9px;
        }

        .topbar-avatar {
            width: 36px;
            height: 36px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #e8f3ff;

            color: #1677d2;

            font-size: 14px;
            font-weight: bold;
        }

        .topbar-user-info {
            display: flex;
            flex-direction: column;
        }

        .topbar-user-info strong {
            font-size: 13px;
            color: #263238;
        }

        .topbar-user-info span {
            margin-top: 2px;

            font-size: 11px;

            color: #7b8794;
        }

        /* ========================================
           SIDEBAR OVERLAY
           ======================================== */

        .sidebar-overlay {
            position: fixed;

            inset: 0;

            background: rgba(15, 23, 42, 0.42);

            z-index: 999;

            opacity: 0;

            visibility: hidden;

            pointer-events: none;

            transition:
                opacity 0.25s ease,
                visibility 0.25s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;

            visibility: visible;

            pointer-events: auto;
        }

        /* ========================================
           SIDEBAR
           ======================================== */

        .kasir-sidebar {
            position: fixed;

            top: 0;
            left: 0;
            bottom: 0;

            width: 280px;

            background: #ffffff;

            border-right: 1px solid #e5eaf0;

            box-shadow:
                8px 0 30px rgba(15, 23, 42, 0.10);

            z-index: 1000;

            display: flex;
            flex-direction: column;

            overflow-y: auto;

            transform: translateX(-105%);

            transition:
                transform 0.28s ease;
        }

        .kasir-sidebar.show {
            transform: translateX(0);
        }

        /* ========================================
           CONTENT
           ======================================== */

        .content {
            width: 100%;

            min-height:
                calc(100vh - 72px);

            padding: 24px;

            max-width: 1500px;

            margin: 0 auto;
        }

        /* ========================================
           PAGE HEADER
           ======================================== */

        .page-header {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            background: #ffffff;

            border: 1px solid #e5eaf0;

            border-radius: 12px;

            padding: 20px 22px;

            margin-bottom: 20px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .page-header h1 {
            margin: 0 0 5px;

            font-size: 25px;

            color: #1f2937;
        }

        .page-header p {
            margin: 0;

            color: #6b7280;

            font-size: 14px;
        }

        .page-header-right {
            display: flex;
            align-items: center;

            gap: 10px;
        }

        .dashboard-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 40px;

            padding: 0 15px;

            border-radius: 8px;

            background: #f1f7fd;

            color: #1677d2;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;

            transition:
                background 0.2s ease;
        }

        .dashboard-link:hover {
            background: #e5f1fc;
        }

        /* ========================================
           MAIN LAYOUT
           ======================================== */

        .kasir-layout {
            display: grid;

            grid-template-columns:
                minmax(0, 1.6fr)
                minmax(320px, 0.8fr);

            gap: 20px;

            align-items: start;
        }

        .left-column {
            display: flex;

            flex-direction: column;

            gap: 20px;

            min-width: 0;
        }

        /* ========================================
           CARD
           ======================================== */

        .card {
            background: #ffffff;

            border: 1px solid #e5eaf0;

            border-radius: 12px;

            padding: 20px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.04);

            min-width: 0;
        }

        .card h3 {
            margin: 0 0 18px;

            color: #1f2937;

            font-size: 17px;
        }

        /* ========================================
           SEARCH
           ======================================== */

        .search-box {
            position: relative;
        }

        .search-input {
            width: 100%;

            height: 48px;

            padding: 0 15px;

            border: 1px solid #d5dde6;

            border-radius: 8px;

            outline: none;

            background: #ffffff;

            color: #1f2937;

            font-size: 15px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        .search-input::placeholder {
            color: #9aa5b1;
        }

        .search-input:focus {
            border-color: #1677d2;

            box-shadow:
                0 0 0 3px
                rgba(22, 119, 210, 0.10);
        }

        .hasil-pencarian {
            position: absolute;

            top: calc(100% + 4px);

            left: 0;
            right: 0;

            background: #ffffff;

            border: 1px solid #dfe5ec;

            border-radius: 8px;

            z-index: 200;

            max-height: 350px;

            overflow-y: auto;

            display: none;

            box-shadow:
                0 10px 25px rgba(15, 23, 42, 0.10);
        }

        .hasil-item {
            padding: 13px 15px;

            border-bottom:
                1px solid #edf0f3;

            cursor: pointer;

            transition:
                background 0.15s ease;
        }

        .hasil-item:hover {
            background: #f4f8fc;
        }

        .hasil-item:last-child {
            border-bottom: none;
        }

        .nama-barang {
            margin-bottom: 4px;

            font-weight: bold;

            color: #263238;

            font-size: 14px;
        }

        .detail-barang {
            color: #6b7280;

            font-size: 13px;
        }

        .harga-barang {
            color: #1677d2;

            font-weight: bold;
        }

        .tidak-ditemukan {
            padding: 15px;

            text-align: center;

            color: #777;

            font-size: 13px;
        }

        /* ========================================
           TABLE
           ======================================== */

        .table-wrapper {
            width: 100%;

            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 650px;
        }

        th {
            background: #f5f7fa;

            color: #526173;

            font-size: 12px;

            font-weight: bold;

            padding: 12px;

            text-align: left;

            white-space: nowrap;
        }

        td {
            padding: 12px;

            border-bottom:
                1px solid #edf0f3;

            font-size: 13px;

            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #fafbfd;
        }

        .qty-input {
            width: 65px;

            padding: 8px;

            border:
                1px solid #cfd8e3;

            border-radius: 6px;

            outline: none;

            font-size: 13px;
        }

        .qty-input:focus {
            border-color: #1677d2;

            box-shadow:
                0 0 0 2px
                rgba(22, 119, 210, 0.08);
        }

        .btn-hapus {
            width: 32px;
            height: 32px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border: none;

            border-radius: 6px;

            background: #fee2e2;

            color: #dc3545;

            font-size: 18px;

            cursor: pointer;

            transition:
                background 0.2s ease;
        }

        .btn-hapus:hover {
            background: #fecaca;
        }

        /* ========================================
           EMPTY TABLE
           ======================================== */

        .empty-cart {
            padding: 30px 15px !important;

            text-align: center;

            color: #8a96a3;
        }

        /* ========================================
           TOTAL
           ======================================== */

        .total-box {
            margin-top: 18px;

            padding-top: 18px;

            border-top:
                2px solid #edf0f3;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            font-size: 21px;

            font-weight: bold;

            color: #1f2937;
        }

        .total-box span:last-child {
            color: #1677d2;

            white-space: nowrap;
        }

        /* ========================================
           PAYMENT
           ======================================== */

        .payment-group {
            margin-bottom: 16px;
        }

        .payment-group:last-of-type {
            margin-bottom: 0;
        }

        .payment label {
            display: block;

            margin-bottom: 7px;

            color: #374151;

            font-size: 13px;

            font-weight: bold;
        }

        .payment input {
            width: 100%;

            height: 46px;

            padding: 0 13px;

            border:
                1px solid #d5dde6;

            border-radius: 8px;

            background: #ffffff;

            color: #1f2937;

            font-size: 16px;

            outline: none;
        }

        .payment input:focus {
            border-color: #1677d2;

            box-shadow:
                0 0 0 3px
                rgba(22, 119, 210, 0.10);
        }

        .payment input[readonly] {
            background: #f7f9fb;

            color: #526173;
        }

        .payment .kembalian {
            background: #f0fdf4;

            border-color: #bbf7d0;

            color: #15803d;
        }

        .btn-cetak {
            width: 100%;

            min-height: 48px;

            margin-top: 17px;

            border: none;

            border-radius: 8px;

            background: #1677d2;

            color: #ffffff;

            font-size: 15px;

            font-weight: bold;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.1s ease;
        }

        .btn-cetak:hover {
            background: #1267b8;
        }

        .btn-cetak:active {
            transform: translateY(1px);
        }

        /* ========================================
           STRUK PREVIEW
           ======================================== */

        .struk-preview {
            width: 100%;

            max-width: 350px;

            margin: 10px auto 0;

            background: #ffffff;

            border:
                1px dashed #b8c1ca;

            border-radius: 6px;

            padding: 20px;

            font-family:
                "Courier New",
                monospace;

            font-size: 13px;

            color: #1f2937;
        }

        .struk-header {
            text-align: center;

            margin-bottom: 15px;
        }

        .struk-header h2 {
            margin: 0;

            font-size: 20px;
        }

        .struk-header p {
            margin: 3px 0;
        }

        .garis {
            border-top:
                1px dashed #333;

            margin: 10px 0;
        }

        .struk-item {
            margin-bottom: 8px;
        }

        .struk-item-name {
            font-weight: bold;

            word-break: break-word;
        }

        .struk-item-detail {
            display: flex;

            justify-content: space-between;

            gap: 10px;
        }

        .struk-total {
            display: flex;

            justify-content: space-between;

            gap: 10px;

            font-weight: bold;

            margin: 5px 0;
        }

        .struk-footer {
            text-align: center;

            margin-top: 15px;
        }

        .struk-footer p {
            margin: 3px 0;
        }

        /* ========================================
           RESPONSIVE
           ======================================== */

        @media (max-width: 1050px) {

            .kasir-layout {
                grid-template-columns: 1fr;
            }

            .struk-preview {
                max-width: 400px;
            }

        }

        @media (max-width: 700px) {

            .topbar {
                padding: 0 15px;
            }

            .topbar-brand-text span {
                display: none;
            }

            .topbar-user-info {
                display: none;
            }

            .content {
                padding: 16px 12px;
            }

            .page-header {
                align-items: flex-start;

                padding: 17px;

                flex-direction: column;
            }

            .page-header-right {
                width: 100%;
            }

            .dashboard-link {
                width: 100%;
            }

            .card {
                padding: 16px;
            }

        }

        @media (max-width: 480px) {

            .topbar {
                height: 64px;
            }

            .topbar-logo {
                width: 35px;
                height: 35px;
            }

            .topbar-logo img {
                width: 29px;
                height: 29px;
            }

            .topbar-brand-text strong {
                font-size: 15px;
            }

            .hamburger {
                width: 39px;
                height: 39px;

                font-size: 20px;
            }

            .content {
                min-height:
                    calc(100vh - 64px);

                padding: 12px 10px;
            }

            .page-header h1 {
                font-size: 21px;
            }

            .page-header p {
                font-size: 13px;
            }

            .card h3 {
                font-size: 16px;
            }

            .total-box {
                font-size: 18px;
            }

            .struk-preview {
                padding: 15px;

                font-size: 12px;
            }

            .kasir-sidebar {
                width: min(280px, 86vw);
            }

        }

    </style>

</head>

<body>

<?php include "sidebar.php"; ?>


<!-- ==========================================
     TOPBAR
     ========================================== -->

<header class="topbar">

    <div class="topbar-left">

        <button
            type="button"
            class="hamburger"
            onclick="toggleSidebar()"
            aria-label="Buka menu"
            title="Menu"
        >
            ☰
        </button>


        <a
            href="dashboard.php"
            class="topbar-brand"
        >

            <div class="topbar-logo">

                <img
                    src="../assets/SembakoKu (1).png"
                    alt="Logo Toko Malaya"
                >

            </div>


            <div class="topbar-brand-text">

                <strong>
                    Toko Malaya
                </strong>

                <span>
                    Panel Kasir
                </span>

            </div>

        </a>

    </div>


    <div class="topbar-right">

        <div class="topbar-user">

            <div class="topbar-avatar">

                <?= htmlspecialchars(
                    strtoupper(
                        substr(
                            $nama_user,
                            0,
                            1
                        )
                    )
                ) ?>

            </div>


            <div class="topbar-user-info">

                <strong>
                    <?= htmlspecialchars($nama_user) ?>
                </strong>

                <span>
                    Kasir
                </span>

            </div>

        </div>

    </div>

</header>


<!-- ==========================================
     CONTENT
     ========================================== -->

<main class="content">


    <!-- ======================================
         PAGE HEADER
         ====================================== -->

    <div class="page-header">

        <div>

            <h1>
                Transaksi Penjualan
            </h1>

            <p>
                Kelola transaksi penjualan pelanggan melalui kasir.
            </p>

        </div>


        <div class="page-header-right">

            <a
                href="dashboard.php"
                class="dashboard-link"
            >
                ← Dashboard
            </a>

        </div>

    </div>


    <!-- ======================================
         TRANSACTION LAYOUT
         ====================================== -->

    <div class="kasir-layout">


        <!-- ==================================
             LEFT COLUMN
             ================================== -->

        <div class="left-column">


            <!-- ==================================
                 SEARCH BARANG
                 ================================== -->

            <div class="card">

                <h3>
                    Cari Barang
                </h3>


                <div class="search-box">

                    <input
                        type="text"
                        id="cariBarang"
                        class="search-input"
                        placeholder="Ketik nama barang..."
                        autocomplete="off"
                        autofocus
                    >


                    <div
                        id="hasilPencarian"
                        class="hasil-pencarian"
                    ></div>

                </div>

            </div>


            <!-- ==================================
                 DAFTAR BELANJA
                 ================================== -->

            <div class="card">

                <h3>
                    Daftar Belanja
                </h3>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    No
                                </th>

                                <th>
                                    Barang
                                </th>

                                <th>
                                    Harga
                                </th>

                                <th>
                                    Qty
                                </th>

                                <th>
                                    Subtotal
                                </th>

                                <th>
                                </th>

                            </tr>

                        </thead>


                        <tbody id="tabelBarang">

                            <tr>

                                <td
                                    colspan="6"
                                    class="empty-cart"
                                >
                                    Belum ada barang
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>


                <div class="total-box">

                    <span>
                        TOTAL
                    </span>

                    <span id="total">
                        Rp 0
                    </span>

                </div>

            </div>


            <!-- ==================================
                 PEMBAYARAN
                 ================================== -->

            <div class="card payment">

                <h3>
                    Pembayaran
                </h3>


                <div class="payment-group">

                    <label>
                        Total Belanja
                    </label>

                    <input
                        type="text"
                        id="totalBayar"
                        value="Rp 0"
                        readonly
                    >

                </div>


                <div class="payment-group">

                    <label>
                        Uang Dibayar
                    </label>

                    <input
                        type="number"
                        id="bayar"
                        placeholder="Masukkan uang pelanggan"
                        min="0"
                        oninput="hitungKembalian()"
                    >

                </div>


                <div class="payment-group">

                    <label>
                        Kembalian
                    </label>

                    <input
                        type="text"
                        id="kembalian"
                        value="Rp 0"
                        readonly
                        class="kembalian"
                    >

                </div>


                <button
                    type="button"
                    class="btn-cetak"
                    onclick="cetakStruk()"
                >
                    🖨 Cetak Struk
                </button>

            </div>

        </div>


        <!-- ==================================
             RIGHT COLUMN
             ================================== -->

        <div class="card">

            <h3>
                Preview Struk
            </h3>


            <div
                id="strukPreview"
                class="struk-preview"
            >

                <div class="struk-header">

                    <h2>
                        TOKO MALAYA
                    </h2>

                    <p>
                        TOKO SEMBAKO
                    </p>

                    <p>
                        Selamat Berbelanja
                    </p>

                </div>


                <div class="garis"></div>


                <div>
                    Kasir:
                    <?= htmlspecialchars($nama_user) ?>
                </div>


                <div>
                    <?= date('d/m/Y H:i') ?>
                </div>


                <div class="garis"></div>


                <div id="strukItems">

                    <div
                        style="
                            text-align:center;
                        "
                    >
                        Belum ada barang
                    </div>

                </div>


                <div class="garis"></div>


                <div class="struk-total">

                    <span>
                        TOTAL
                    </span>

                    <span id="strukTotal">
                        Rp 0
                    </span>

                </div>


                <div class="struk-total">

                    <span>
                        BAYAR
                    </span>

                    <span id="strukBayar">
                        Rp 0
                    </span>

                </div>


                <div class="struk-total">

                    <span>
                        KEMBALI
                    </span>

                    <span id="strukKembalian">
                        Rp 0
                    </span>

                </div>


                <div class="garis"></div>


                <div class="struk-footer">

                    <p>
                        TERIMA KASIH
                    </p>

                    <p>
                        Selamat Berbelanja
                    </p>

                </div>

            </div>

        </div>

    </div>

</main>


<script>

/* ========================================
   VARIABEL
   ======================================== */

let keranjang = [];

let timerPencarian = null;


/* ========================================
   FORMAT RUPIAH
   ======================================== */

function formatRupiah(angka) {

    return new Intl.NumberFormat(
        'id-ID',
        {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }
    ).format(angka);

}


/* ========================================
   INPUT PENCARIAN
   ======================================== */

const inputCari =
    document.getElementById(
        'cariBarang'
    );


inputCari.addEventListener(
    'input',
    function() {

        const keyword =
            this.value.trim();


        clearTimeout(
            timerPencarian
        );


        if (
            keyword.length === 0
        ) {

            tutupHasil();

            return;

        }


        timerPencarian =
            setTimeout(
                function() {

                    cariBarang(
                        keyword
                    );

                },
                250
            );

    }
);


/* ========================================
   CARI BARANG
   ======================================== */

async function cariBarang(keyword) {

    try {

        const response =
            await fetch(
                'cari_barang.php?q=' +
                encodeURIComponent(
                    keyword
                )
            );


        const data =
            await response.json();


        const hasil =
            document.getElementById(
                'hasilPencarian'
            );


        hasil.innerHTML = '';


        if (
            !data.success ||
            !data.barang ||
            data.barang.length === 0
        ) {

            hasil.innerHTML = `

                <div class="tidak-ditemukan">
                    Barang tidak ditemukan
                </div>

            `;

            hasil.style.display =
                'block';

            return;

        }


        data.barang.forEach(
            barang => {

                const div =
                    document.createElement(
                        'div'
                    );


                div.className =
                    'hasil-item';


                div.innerHTML = `

                    <div class="nama-barang">

                        ${escapeHtml(
                            barang.nama_barang
                        )}

                    </div>

                    <div class="detail-barang">

                        <span class="harga-barang">

                            ${formatRupiah(
                                parseFloat(
                                    barang.harga_jual
                                )
                            )}

                        </span>

                        /
                        ${escapeHtml(
                            barang.satuan || ''
                        )}

                    </div>

                `;


                div.addEventListener(
                    'click',
                    function() {

                        tambahKeKeranjang(
                            barang
                        );

                    }
                );


                hasil.appendChild(
                    div
                );

            }
        );


        hasil.style.display =
            'block';


    } catch (error) {

        console.error(
            'Error pencarian:',
            error
        );

    }

}


/* ========================================
   TAMBAH KE KERANJANG
   ======================================== */

function tambahKeKeranjang(barang) {

    const index =
        keranjang.findIndex(
            item =>
                item.id_barang ==
                barang.id_barang
        );


    if (index >= 0) {

        keranjang[index].jumlah++;

    } else {

        keranjang.push({

            id_barang:
                barang.id_barang,

            kode_barang:
                barang.kode_barang,

            nama_barang:
                barang.nama_barang,

            harga_jual:
                parseFloat(
                    barang.harga_jual
                ),

            jumlah: 1

        });

    }


    renderKeranjang();

    inputCari.value = '';

    tutupHasil();

    inputCari.focus();

}


/* ========================================
   RENDER KERANJANG
   ======================================== */

function renderKeranjang() {

    const tbody =
        document.getElementById(
            'tabelBarang'
        );


    tbody.innerHTML = '';


    if (
        keranjang.length === 0
    ) {

        tbody.innerHTML = `

            <tr>

                <td
                    colspan="6"
                    class="empty-cart"
                >
                    Belum ada barang
                </td>

            </tr>

        `;


        updateTotal();

        return;

    }


    keranjang.forEach(
        (item, index) => {

            const subtotal =
                item.harga_jual *
                item.jumlah;


            const row =
                document.createElement(
                    'tr'
                );


            row.innerHTML = `

                <td>
                    ${index + 1}
                </td>

                <td>

                    <strong>
                        ${escapeHtml(
                            item.nama_barang
                        )}
                    </strong>

                </td>

                <td>

                    ${formatRupiah(
                        item.harga_jual
                    )}

                </td>

                <td>

                    <input
                        type="number"
                        min="1"
                        class="qty-input"
                        value="${item.jumlah}"
                    >

                </td>

                <td>

                    ${formatRupiah(
                        subtotal
                    )}

                </td>

                <td>

                    <button
                        type="button"
                        class="btn-hapus"
                        aria-label="Hapus barang"
                    >
                        ×
                    </button>

                </td>

            `;


            const qtyInput =
                row.querySelector(
                    '.qty-input'
                );


            qtyInput.addEventListener(
                'change',
                function() {

                    ubahQty(
                        index,
                        this.value
                    );

                }
            );


            const btnHapus =
                row.querySelector(
                    '.btn-hapus'
                );


            btnHapus.addEventListener(
                'click',
                function() {

                    hapusBarang(
                        index
                    );

                }
            );


            tbody.appendChild(
                row
            );

        }
    );


    updateTotal();

}


/* ========================================
   UBAH QTY
   ======================================== */

function ubahQty(index, jumlah) {

    jumlah =
        parseInt(
            jumlah
        );


    if (
        isNaN(jumlah) ||
        jumlah < 1
    ) {

        jumlah = 1;

    }


    if (
        !keranjang[index]
    ) {

        return;

    }


    keranjang[index].jumlah =
        jumlah;


    renderKeranjang();

}


/* ========================================
   HAPUS BARANG
   ======================================== */

function hapusBarang(index) {

    keranjang.splice(
        index,
        1
    );


    renderKeranjang();

    inputCari.focus();

}


/* ========================================
   HITUNG TOTAL
   ======================================== */

function hitungTotal() {

    let total = 0;


    keranjang.forEach(
        item => {

            total +=
                item.harga_jual *
                item.jumlah;

        }
    );


    return total;

}


/* ========================================
   UPDATE TOTAL
   ======================================== */

function updateTotal() {

    const total =
        hitungTotal();


    document
        .getElementById(
            'total'
        )
        .innerText =
        formatRupiah(
            total
        );


    document
        .getElementById(
            'totalBayar'
        )
        .value =
        formatRupiah(
            total
        );


    hitungKembalian();

    updateStruk();

}


/* ========================================
   HITUNG KEMBALIAN
   ======================================== */

function hitungKembalian() {

    const total =
        hitungTotal();


    const bayar =
        parseFloat(
            document
                .getElementById(
                    'bayar'
                )
                .value
        ) || 0;


    const kembalian =
        Math.max(
            bayar - total,
            0
        );


    document
        .getElementById(
            'kembalian'
        )
        .value =
        formatRupiah(
            kembalian
        );


    updateStruk();

}


/* ========================================
   UPDATE PREVIEW STRUK
   ======================================== */

function updateStruk() {

    const container =
        document.getElementById(
            'strukItems'
        );


    container.innerHTML = '';


    if (
        keranjang.length === 0
    ) {

        container.innerHTML = `

            <div
                style="
                    text-align:center;
                "
            >
                Belum ada barang
            </div>

        `;

    } else {

        keranjang.forEach(
            item => {

                const subtotal =
                    item.harga_jual *
                    item.jumlah;


                const div =
                    document.createElement(
                        'div'
                    );


                div.className =
                    'struk-item';


                div.innerHTML = `

                    <div class="struk-item-name">

                        ${escapeHtml(
                            item.nama_barang
                        )}

                    </div>

                    <div
                        class="struk-item-detail"
                    >

                        <span>

                            ${item.jumlah}
                            x
                            ${formatRupiah(
                                item.harga_jual
                            )}

                        </span>

                        <span>

                            ${formatRupiah(
                                subtotal
                            )}

                        </span>

                    </div>

                `;


                container.appendChild(
                    div
                );

            }
        );

    }


    const total =
        hitungTotal();


    const bayar =
        parseFloat(
            document
                .getElementById(
                    'bayar'
                )
                .value
        ) || 0;


    const kembalian =
        Math.max(
            bayar - total,
            0
        );


    document
        .getElementById(
            'strukTotal'
        )
        .innerText =
        formatRupiah(
            total
        );


    document
        .getElementById(
            'strukBayar'
        )
        .innerText =
        formatRupiah(
            bayar
        );


    document
        .getElementById(
            'strukKembalian'
        )
        .innerText =
        formatRupiah(
            kembalian
        );

}


/* ========================================
   CETAK STRUK
   ======================================== */

async function cetakStruk() {

    if (
        keranjang.length === 0
    ) {

        alert(
            'Belum ada barang.'
        );

        inputCari.focus();

        return;

    }


    const total =
        hitungTotal();


    const bayar =
        parseFloat(
            document
                .getElementById(
                    'bayar'
                )
                .value
        ) || 0;


    if (
        bayar < total
    ) {

        alert(
            'Uang pembayaran kurang.'
        );

        document
            .getElementById(
                'bayar'
            )
            .focus();

        return;

    }


    const printWindow =
        window.open(
            '',
            '_blank',
            'width=450,height=700'
        );


    if (!printWindow) {

        alert(
            'Popup diblokir browser. ' +
            'Silakan izinkan popup untuk halaman ini.'
        );

        return;

    }


    try {

        const response =
            await fetch(
                'simpan_penjualan.php',
                {

                    method: 'POST',

                    headers: {

                        'Content-Type':
                            'application/json'

                    },

                    body:
                        JSON.stringify({

                            items:
                                keranjang,

                            bayar:
                                bayar

                        })

                }
            );


        const result =
            await response.json();


        if (
            !result.success
        ) {

            printWindow.close();

            alert(
                result.message
            );

            return;

        }


        printWindow.location.href =
            'cetak_struk.php?id=' +
            result.id_penjualan;


        keranjang = [];


        document
            .getElementById(
                'bayar'
            )
            .value = '';


        renderKeranjang();

        inputCari.focus();


    } catch (error) {

        printWindow.close();

        console.error(
            error
        );

        alert(
            'Terjadi kesalahan saat ' +
            'menyimpan transaksi.'
        );

    }

}


/* ========================================
   TUTUP HASIL PENCARIAN
   ======================================== */

function tutupHasil() {

    document
        .getElementById(
            'hasilPencarian'
        )
        .style.display =
        'none';

}


/* ========================================
   ESCAPE HTML
   ======================================== */

function escapeHtml(text) {

    const div =
        document.createElement(
            'div'
        );


    div.textContent =
        text ?? '';


    return div.innerHTML;

}


/* ========================================
   KLIK DI LUAR PENCARIAN
   ======================================== */

document.addEventListener(
    'click',
    function(event) {

        const searchBox =
            document.querySelector(
                '.search-box'
            );


        if (
            searchBox &&
            !searchBox.contains(
                event.target
            )
        ) {

            tutupHasil();

        }

    }
);


/* ========================================
   ENTER PADA SEARCH
   ======================================== */

inputCari.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            tutupHasil();

            this.value = '';

        }

    }
);

</script>

</body>

</html>