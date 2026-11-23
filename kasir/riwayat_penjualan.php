<?php

session_start();

require_once "../config/database.php";

// =====================================================
// CEK LOGIN
// =====================================================

if (
    !isset($_SESSION['id_user']) ||
    ($_SESSION['role'] ?? '') !== 'kasir'
) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$nama_user = $_SESSION['nama'] ?? 'Kasir';


// =====================================================
// FUNCTION
// =====================================================

function rupiah($angka)
{
    return "Rp " . number_format(
        (float) $angka,
        0,
        ',',
        '.'
    );
}


// =====================================================
// TANGGAL HARI INI
// =====================================================

$tanggal_hari_ini = date('Y-m-d');


// =====================================================
// TRANSAKSI PENJUALAN LANGSUNG HARI INI
// HANYA TRANSAKSI KASIR YANG LOGIN
// =====================================================

$transaksi_langsung = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        p.id_penjualan,
        p.kode_penjualan,
        p.tanggal_penjualan,
        p.waktu_penjualan,
        p.total_penjualan,
        p.bayar,
        p.kembalian,
        u.nama AS nama_kasir
     FROM penjualan p
     JOIN users u
        ON p.id_user = u.id_user
     WHERE p.id_user = ?
       AND DATE(p.waktu_penjualan) = ?
     ORDER BY p.waktu_penjualan DESC"
);

mysqli_stmt_bind_param(
    $stmt,
    "is",
    $id_user,
    $tanggal_hari_ini
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {

    $transaksi_langsung[] = [
        'jenis'             => 'langsung',
        'id'                => (int) $row['id_penjualan'],
        'kode'              => $row['kode_penjualan'],
        'tanggal'           => $row['waktu_penjualan'],
        'total'             => (float) $row['total_penjualan'],
        'bayar'             => (float) $row['bayar'],
        'kembalian'         => (float) $row['kembalian'],
        'nama_kasir'        => $row['nama_kasir'],
        'nama_pelanggan'    => '',
        'metode_pembayaran' => '',
        'status'            => 'selesai',
        'alamat'            => ''
    ];
}

mysqli_stmt_close($stmt);


// =====================================================
// DETAIL PENJUALAN LANGSUNG
// =====================================================

foreach ($transaksi_langsung as &$trx) {

    $id_penjualan = $trx['id'];

    $stmtDetail = mysqli_prepare(
        $conn,
        "SELECT
            d.id_detail_penjualan,
            d.id_barang,
            d.jumlah,
            d.harga_jual,
            d.subtotal,
            b.nama_barang,
            b.satuan
         FROM detail_penjualan d
         JOIN barang b
            ON d.id_barang = b.id_barang
         WHERE d.id_penjualan = ?
         ORDER BY d.id_detail_penjualan ASC"
    );

    mysqli_stmt_bind_param(
        $stmtDetail,
        "i",
        $id_penjualan
    );

    mysqli_stmt_execute($stmtDetail);

    $resultDetail = mysqli_stmt_get_result(
        $stmtDetail
    );

    $detail = [];

    while ($rowDetail = mysqli_fetch_assoc($resultDetail)) {

        $detail[] = [
            'nama_barang' => $rowDetail['nama_barang'],
            'satuan'      => $rowDetail['satuan'],
            'harga'       => (float) $rowDetail['harga_jual'],
            'jumlah'      => (int) $rowDetail['jumlah'],
            'subtotal'    => (float) $rowDetail['subtotal']
        ];
    }

    $trx['detail'] = $detail;

    mysqli_stmt_close($stmtDetail);
}

unset($trx);


// =====================================================
// PESANAN ONLINE SELESAI HARI INI
// =====================================================

$pesanan_online = [];

$cek_tabel = mysqli_query(
    $conn,
    "SHOW TABLES LIKE 'pesanan'"
);

if (
    $cek_tabel &&
    mysqli_num_rows($cek_tabel) > 0
) {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            p.id_pesanan,
            p.tanggal_pesanan,
            p.total_harga,
            p.alamat_pengiriman,
            p.metode_pembayaran,
            p.status,
            u.nama AS nama_pelanggan
         FROM pesanan p
         JOIN users u
            ON p.id_user = u.id_user
         WHERE p.status = 'selesai'
           AND DATE(p.tanggal_pesanan) = ?
         ORDER BY p.tanggal_pesanan DESC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $tanggal_hari_ini
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {

        $pesanan_online[] = [
            'jenis'             => 'online',
            'id'                => (int) $row['id_pesanan'],
            'kode'              => 'PESAN #' . $row['id_pesanan'],
            'tanggal'           => $row['tanggal_pesanan'],
            'total'             => (float) $row['total_harga'],
            'bayar'             => 0,
            'kembalian'         => 0,
            'nama_kasir'        => '',
            'nama_pelanggan'    => $row['nama_pelanggan'],
            'metode_pembayaran' => $row['metode_pembayaran'],
            'status'            => $row['status'],
            'alamat'            => $row['alamat_pengiriman']
        ];
    }

    mysqli_stmt_close($stmt);
}


// =====================================================
// DETAIL PESANAN ONLINE
// =====================================================

foreach ($pesanan_online as &$trx) {

    $id_pesanan = $trx['id'];

    $stmtDetail = mysqli_prepare(
        $conn,
        "SELECT
            d.id_detail,
            d.id_barang,
            d.jumlah,
            d.harga,
            d.subtotal,
            b.nama_barang,
            b.satuan
         FROM detail_pesanan d
         JOIN barang b
            ON d.id_barang = b.id_barang
         WHERE d.id_pesanan = ?
         ORDER BY d.id_detail ASC"
    );

    mysqli_stmt_bind_param(
        $stmtDetail,
        "i",
        $id_pesanan
    );

    mysqli_stmt_execute($stmtDetail);

    $resultDetail = mysqli_stmt_get_result(
        $stmtDetail
    );

    $detail = [];

    while ($rowDetail = mysqli_fetch_assoc($resultDetail)) {

        $detail[] = [
            'nama_barang' => $rowDetail['nama_barang'],
            'satuan'      => $rowDetail['satuan'],
            'harga'       => (float) $rowDetail['harga'],
            'jumlah'      => (int) $rowDetail['jumlah'],
            'subtotal'    => (float) $rowDetail['subtotal']
        ];
    }

    $trx['detail'] = $detail;

    mysqli_stmt_close($stmtDetail);
}

unset($trx);


// =====================================================
// GABUNG TRANSAKSI
// =====================================================

$semua_transaksi = array_merge(
    $transaksi_langsung,
    $pesanan_online
);


// =====================================================
// URUTKAN BERDASARKAN WAKTU
// =====================================================

usort(
    $semua_transaksi,
    function ($a, $b) {

        return strtotime($b['tanggal'])
            <=> strtotime($a['tanggal']);
    }
);


// =====================================================
// RINGKASAN
// =====================================================

$jumlah_langsung = count(
    $transaksi_langsung
);

$jumlah_online = count(
    $pesanan_online
);

$jumlah_semua = count(
    $semua_transaksi
);


$total_langsung = 0;

foreach ($transaksi_langsung as $trx) {

    $total_langsung +=
        (float) $trx['total'];
}


$total_online = 0;

foreach ($pesanan_online as $trx) {

    $total_online +=
        (float) $trx['total'];
}


$total_semua =
    $total_langsung +
    $total_online;


// =====================================================
// FORMAT TANGGAL
// =====================================================

$nama_hari = [
    'Sunday'    => 'Minggu',
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu'
];

$nama_bulan = [
    'January'   => 'Januari',
    'February'  => 'Februari',
    'March'     => 'Maret',
    'April'     => 'April',
    'May'       => 'Mei',
    'June'      => 'Juni',
    'July'      => 'Juli',
    'August'    => 'Agustus',
    'September' => 'September',
    'October'    => 'Oktober',
    'November'  => 'November',
    'December'  => 'Desember'
];

$hari = $nama_hari[
    date('l')
];

$bulan = $nama_bulan[
    date('F')
];

$tanggal_label =
    $hari . ', ' .
    date('d') . ' ' .
    $bulan . ' ' .
    date('Y');

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
    Riwayat Transaksi - Toko Malaya
</title>

<style>

/* =====================================================
   RESET
===================================================== */

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

    background: #f4f7fb;

    color: #1f2937;
}

body.sidebar-open {
    overflow: hidden;
}


/* =====================================================
   TOPBAR
===================================================== */

.topbar {
    position: sticky;
    top: 0;
    z-index: 900;

    height: 72px;

    background: #ffffff;

    border-bottom:
        1px solid #e5eaf0;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 24px;
}

.topbar-left {
    display: flex;

    align-items: center;

    gap: 14px;
}

.hamburger {
    width: 42px;
    height: 42px;

    border: none;

    border-radius: 9px;

    background: #eef6ff;

    color: #1677d2;

    font-size: 23px;

    cursor: pointer;
}

.hamburger:hover {
    background: #1677d2;
    color: #ffffff;
}

.topbar-brand {
    display: flex;

    align-items: center;

    gap: 10px;

    text-decoration: none;
}

.topbar-logo {
    width: 40px;
    height: 40px;

    border-radius: 10px;

    background: #eef6ff;

    display: flex;

    align-items: center;

    justify-content: center;

    overflow: hidden;
}

.topbar-logo img {
    width: 34px;
    height: 34px;

    object-fit: contain;
}

.topbar-brand-text {
    display: flex;

    flex-direction: column;
}

.topbar-brand-text strong {
    font-size: 16px;

    color: #1f2937;
}

.topbar-brand-text span {
    margin-top: 3px;

    font-size: 11px;

    color: #6b7280;
}

.topbar-user {
    display: flex;

    align-items: center;

    gap: 10px;
}

.topbar-user-info {
    display: flex;

    flex-direction: column;

    text-align: right;
}

.topbar-user-info strong {
    font-size: 14px;
}

.topbar-user-info span {
    margin-top: 3px;

    font-size: 12px;

    color: #6b7280;
}

.topbar-avatar {
    width: 40px;
    height: 40px;

    border-radius: 50%;

    background: #e7f2ff;

    color: #1677d2;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: bold;
}


/* =====================================================
   SIDEBAR
===================================================== */

.sidebar-overlay {
    position: fixed;

    inset: 0;

    background:
        rgba(15, 23, 42, .48);

    z-index: 1200;

    opacity: 0;

    visibility: hidden;

    pointer-events: none;

    transition: .25s;
}

.sidebar-overlay.show {
    opacity: 1;

    visibility: visible;

    pointer-events: auto;
}

.kasir-sidebar {
    position: fixed;

    top: 0;
    left: 0;
    bottom: 0;

    width: 285px;

    background: #ffffff;

    border-right:
        1px solid #e5eaf0;

    box-shadow:
        8px 0 25px
        rgba(15,23,42,.12);

    z-index: 1300;

    display: flex;

    flex-direction: column;

    transform:
        translateX(-105%);

    transition:
        transform .28s ease;

    overflow-y: auto;
}

.kasir-sidebar.show {
    transform:
        translateX(0);
}

.sidebar-brand {
    min-height: 78px;

    padding: 18px 20px;

    display: flex;

    align-items: center;

    gap: 12px;

    border-bottom:
        1px solid #edf0f3;
}

.sidebar-logo {
    width: 42px;
    height: 42px;

    border-radius: 10px;

    background: #eef6ff;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    overflow: hidden;
}

.sidebar-logo img {
    width: 36px;
    height: 36px;

    object-fit: contain;
}

.sidebar-brand-text {
    display: flex;

    flex-direction: column;

    line-height: 1.2;
}

.sidebar-brand-text strong {
    color: #1f2937;

    font-size: 16px;
}

.sidebar-brand-text span {
    margin-top: 4px;

    color: #6b7280;

    font-size: 11px;
}

.sidebar-user {
    display: flex;

    align-items: center;

    gap: 11px;

    margin: 16px 14px;

    padding: 13px;

    background: #f7faff;

    border:
        1px solid #e3edf8;

    border-radius: 10px;
}

.sidebar-user-avatar {
    width: 38px;
    height: 38px;

    border-radius: 50%;

    background: #e7f2ff;

    color: #1677d2;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: bold;

    flex-shrink: 0;
}

.sidebar-user-info {
    display: flex;

    flex-direction: column;

    min-width: 0;
}

.sidebar-user-info strong {
    font-size: 13px;

    color: #1f2937;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.sidebar-user-info span {
    margin-top: 3px;

    font-size: 11px;

    color: #6b7280;
}

.sidebar-menu {
    padding: 0 14px 15px;
}

.sidebar-menu-label {
    padding: 10px 9px 7px;

    color: #9aa5b1;

    font-size: 10px;

    font-weight: bold;

    letter-spacing: .7px;
}

.sidebar-menu-label-second {
    margin-top: 10px;
}

.sidebar-link {
    display: flex;

    align-items: center;

    gap: 11px;

    min-height: 43px;

    padding: 10px 12px;

    margin-bottom: 4px;

    border-radius: 8px;

    color: #526173;

    text-decoration: none;

    font-size: 13px;

    font-weight: 500;
}

.sidebar-link:hover {
    background: #f0f6fd;

    color: #1677d2;
}

.sidebar-link.active {
    background: #eaf4ff;

    color: #1677d2;

    font-weight: bold;
}

.sidebar-bottom {
    margin-top: auto;

    padding: 14px;

    border-top:
        1px solid #edf0f3;
}

.sidebar-logout {
    display: flex;

    align-items: center;

    gap: 11px;

    min-height: 43px;

    padding: 10px 12px;

    border-radius: 8px;

    color: #dc3545;

    text-decoration: none;

    font-size: 13px;
}

.sidebar-logout:hover {
    background: #fff1f2;
}


/* =====================================================
   CONTENT
===================================================== */

.content {
    min-height:
        calc(100vh - 72px);

    padding:
        28px 30px 50px;

    max-width: 1400px;

    margin: 0 auto;
}


/* =====================================================
   PAGE HEADER
===================================================== */

.page-header {
    background: #ffffff;

    border:
        1px solid #e5eaf0;

    border-radius: 12px;

    padding: 22px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.05);
}

.page-header-top {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;
}

.page-header h1 {
    margin:
        0 0 6px;

    font-size: 26px;

    color: #1f2937;
}

.page-header p {
    margin: 0;

    color: #6b7280;

    font-size: 14px;
}

.btn-laporan {
    text-decoration: none;

    background: #eef6ff;

    color: #1677d2;

    border:
        1px solid #cfe3f8;

    padding:
        10px 15px;

    border-radius: 8px;

    font-weight: bold;

    white-space: nowrap;
}

.btn-laporan:hover {
    background: #1677d2;

    color: #ffffff;
}


/* =====================================================
   TODAY INFO
===================================================== */

.today-info {
    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 20px;

    padding:
        14px 18px;

    background: #eaf4ff;

    border:
        1px solid #cfe3f8;

    border-radius: 10px;

    color: #315b82;

    font-size: 14px;
}

.today-info strong {
    color: #1677d2;
}


/* =====================================================
   SUMMARY
===================================================== */

.summary {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 20px;
}

.summary-card {
    background: #ffffff;

    border:
        1px solid #e5eaf0;

    border-left:
        4px solid #1677d2;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.05);
}

.summary-title {
    color: #6b7280;

    font-size: 14px;

    margin-bottom: 8px;
}

.summary-value {
    font-size: 23px;

    font-weight: bold;

    color: #1677d2;
}


/* =====================================================
   TRANSAKSI
===================================================== */

.transaksi {
    background: #ffffff;

    border:
        1px solid #e5eaf0;

    border-radius: 12px;

    margin-bottom: 15px;

    overflow: hidden;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.05);
}

.transaksi-header {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    padding:
        18px 20px;

    border-bottom:
        1px solid #edf0f3;
}

.kode {
    font-weight: bold;

    font-size: 17px;

    color: #1f2937;
}

.tanggal {
    margin-top: 5px;

    color: #6b7280;

    font-size: 13px;
}

.total {
    color: #1677d2;

    font-size: 18px;

    font-weight: bold;

    white-space: nowrap;
}


/* =====================================================
   BADGE
===================================================== */

.badge {
    display: inline-block;

    margin-left: 7px;

    padding:
        5px 9px;

    border-radius: 6px;

    font-size: 11px;

    font-weight: bold;
}

.badge-online {
    background: #e7f2ff;

    color: #1677d2;
}

.badge-langsung {
    background: #eef6ff;

    color: #1677d2;
}

.badge-selesai {
    background: #eaf8f0;

    color: #198754;
}


/* =====================================================
   AKSI
===================================================== */

.aksi {
    padding:
        13px 20px;

    background: #fafbfc;

    border-bottom:
        1px solid #edf0f3;
}

.btn {
    border: none;

    padding:
        9px 14px;

    border-radius: 7px;

    cursor: pointer;

    text-decoration: none;

    display: inline-block;

    margin-right: 5px;

    font-size: 14px;
}

.btn-detail {
    background: #eef6ff;

    color: #1677d2;
}

.btn-detail:hover {
    background: #1677d2;

    color: #ffffff;
}

.btn-cetak {
    background: #1677d2;

    color: #ffffff;
}

.btn-cetak:hover {
    background: #1264b3;
}


/* =====================================================
   DETAIL
===================================================== */

.detail {
    display: none;

    padding: 20px;

    background: #f8fafc;
}

.detail table {
    width: 100%;

    border-collapse: collapse;

    background: #ffffff;

    border:
        1px solid #e5e7eb;
}

.detail th,
.detail td {
    padding:
        11px 12px;

    border-bottom:
        1px solid #edf0f3;

    text-align: left;
}

.detail th {
    background: #f3f7fb;

    color: #526173;

    font-size: 13px;
}

.detail td {
    font-size: 14px;
}

.detail small {
    color: #6b7280;
}


/* =====================================================
   INFO ONLINE
===================================================== */

.info-online {
    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;

    margin-bottom: 18px;

    padding: 15px;

    background: #ffffff;

    border:
        1px solid #e5e7eb;

    border-radius: 8px;
}

.info-item {
    font-size: 13px;

    color: #6b7280;
}

.info-item strong {
    display: block;

    margin-top: 4px;

    color: #1f2937;

    font-size: 14px;
}

.alamat {
    grid-column:
        1 / -1;
}


/* =====================================================
   PAYMENT
===================================================== */

.payment-summary {
    margin-top: 18px;

    background: #ffffff;

    border:
        1px solid #e5e7eb;

    border-radius: 8px;

    padding:
        15px 18px;
}

.payment-row {
    display: flex;

    justify-content: space-between;

    gap: 15px;

    padding: 6px 0;
}

.payment-row.total-row {
    border-top:
        1px solid #edf0f3;

    margin-top: 5px;

    padding-top: 12px;

    font-weight: bold;

    color: #1677d2;
}


/* =====================================================
   EMPTY
===================================================== */

.kosong {
    background: #ffffff;

    padding:
        55px 30px;

    text-align: center;

    border-radius: 12px;

    border:
        1px solid #e5eaf0;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.05);

    color: #6b7280;
}

.kosong-icon {
    font-size: 42px;

    margin-bottom: 12px;
}

.kosong-title {
    font-size: 19px;

    font-weight: bold;

    color: #374151;

    margin-bottom: 6px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1000px) {

    .summary {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 700px) {

    .topbar {
        padding: 0 15px;
    }

    .topbar-brand-text {
        display: none;
    }

    .topbar-user-info {
        display: none;
    }

    .content {
        padding:
            18px 15px;
    }

    .page-header-top {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .summary {
        grid-template-columns:
            1fr;
    }

    .transaksi-header {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .detail {
        overflow-x: auto;
    }

    .detail table {
        min-width: 600px;
    }

    .info-online {
        grid-template-columns:
            1fr;
    }

    .alamat {
        grid-column:
            auto;
    }

}

@media (max-width: 480px) {

    .kasir-sidebar {
        width: 280px;
    }

    .summary-card {
        padding: 17px;
    }

    .summary-value {
        font-size: 21px;
    }

    .today-info {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     OVERLAY
===================================================== -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
    onclick="closeSidebar()"
></div>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside
    class="kasir-sidebar"
    id="kasirSidebar"
>

    <div class="sidebar-brand">

        <div class="sidebar-logo">

            <img
                src="../assets/SembakoKu (1).png"
                alt="Logo Toko Malaya"
            >

        </div>

        <div class="sidebar-brand-text">

            <strong>
                Toko Malaya
            </strong>

            <span>
                Panel Kasir
            </span>

        </div>

    </div>


    <div class="sidebar-user">

        <div class="sidebar-user-avatar">

            <?= htmlspecialchars(
                strtoupper(
                    substr(
                        $nama_user,
                        0,
                        1
                    )
                )
            ); ?>

        </div>

        <div class="sidebar-user-info">

            <strong>
                <?= htmlspecialchars(
                    $nama_user
                ); ?>
            </strong>

            <span>
                Kasir
            </span>

        </div>

    </div>


    <nav class="sidebar-menu">

        <div class="sidebar-menu-label">
            MENU UTAMA
        </div>


        <a
            href="dashboard.php"
            class="sidebar-link"
        >
            🏠
            <span>Dashboard</span>
        </a>


        <a
            href="penjualan.php"
            class="sidebar-link"
        >
            🧾
            <span>Penjualan</span>
        </a>


        <a
            href="riwayat_penjualan.php"
            class="sidebar-link active"
        >
            📋
            <span>Riwayat Transaksi</span>
        </a>


        <a
            href="pesanan.php"
            class="sidebar-link"
        >
            🛒
            <span>Pesanan Online</span>
        </a>


        <a
            href="stok.php"
            class="sidebar-link"
        >
            📦
            <span>Stok</span>
        </a>


        <a
            href="laporan.php"
            class="sidebar-link"
        >
            📊
            <span>Laporan</span>
        </a>


        <div class="sidebar-menu-label sidebar-menu-label-second">
            AKUN
        </div>


        <a
            href="profil.php"
            class="sidebar-link"
        >
            👤
            <span>Profil</span>
        </a>

    </nav>


    <div class="sidebar-bottom">

        <a
            href="../auth/logout.php"
            class="sidebar-logout"
            onclick="return confirm('Apakah kamu yakin ingin keluar?');"
        >
            🚪
            <span>Keluar</span>
        </a>

    </div>

</aside>


<!-- =====================================================
     TOPBAR
===================================================== -->

<header class="topbar">

    <div class="topbar-left">

        <button
            type="button"
            class="hamburger"
            onclick="toggleSidebar()"
            aria-label="Buka menu"
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
                    alt="Toko Malaya"
                >

            </div>


            <div class="topbar-brand-text">

                <strong>
                    Toko Malaya
                </strong>

                <span>
                    Riwayat Transaksi
                </span>

            </div>

        </a>

    </div>


    <div class="topbar-user">

        <div class="topbar-user-info">

            <strong>
                <?= htmlspecialchars($nama_user); ?>
            </strong>

            <span>
                Kasir
            </span>

        </div>


        <div class="topbar-avatar">

            <?= htmlspecialchars(
                strtoupper(
                    substr(
                        $nama_user,
                        0,
                        1
                    )
                )
            ); ?>

        </div>

    </div>

</header>


<!-- =====================================================
     CONTENT
===================================================== -->

<main class="content">


    <!-- HEADER -->

    <div class="page-header">

        <div class="page-header-top">

            <div>

                <h1>
                    Riwayat Transaksi
                </h1>

                <p>
                    Menampilkan transaksi yang terjadi hari ini.
                </p>

            </div>


            <a
                href="laporan.php"
                class="btn-laporan"
            >
                📊 Lihat Laporan
            </a>

        </div>

    </div>


    <!-- TANGGAL HARI INI -->

    <div class="today-info">

        📅

        <span>
            Riwayat transaksi:
            <strong>
                <?= htmlspecialchars($tanggal_label); ?>
            </strong>
        </span>

    </div>


    <!-- SUMMARY -->

    <div class="summary">


        <div class="summary-card">

            <div class="summary-title">
                Total Transaksi
            </div>

            <div class="summary-value">
                <?= $jumlah_semua; ?>
                transaksi
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Penjualan Langsung
            </div>

            <div class="summary-value">
                <?= $jumlah_langsung; ?>
                transaksi
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Pesanan Online
            </div>

            <div class="summary-value">
                <?= $jumlah_online; ?>
                pesanan
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Total Penjualan
            </div>

            <div class="summary-value">
                <?= rupiah($total_semua); ?>
            </div>

        </div>

    </div>


    <!-- DAFTAR TRANSAKSI -->

    <div id="daftarTransaksi">


        <?php if (
            count($semua_transaksi) === 0
        ): ?>


            <div class="kosong">

                <div class="kosong-icon">
                    📋
                </div>

                <div class="kosong-title">
                    Belum Ada Transaksi Hari Ini
                </div>

                <div>
                    Belum ada transaksi penjualan
                    atau pesanan online selesai
                    pada hari ini.
                </div>

            </div>


        <?php else: ?>


            <?php foreach (
                $semua_transaksi
                as $trx
            ): ?>


                <?php

                $isOnline =
                    $trx['jenis'] === 'online';

                $idDetail =
                    $isOnline
                        ? 'online-' . $trx['id']
                        : 'langsung-' . $trx['id'];

                ?>


                <div class="transaksi">


                    <!-- HEADER TRANSAKSI -->

                    <div class="transaksi-header">

                        <div>

                            <div class="kode">

                                <?= htmlspecialchars(
                                    $trx['kode']
                                ); ?>


                                <?php if ($isOnline): ?>

                                    <span class="badge badge-online">
                                        Pesanan Online
                                    </span>

                                    <span class="badge badge-selesai">
                                        Selesai
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-langsung">
                                        Penjualan Langsung
                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="tanggal">

                                🕒

                                <?= date(
                                    'H:i',
                                    strtotime(
                                        $trx['tanggal']
                                    )
                                ); ?>

                            </div>


                            <?php if ($isOnline): ?>

                                <div class="tanggal">

                                    Pelanggan:

                                    <strong>

                                        <?= htmlspecialchars(
                                            $trx['nama_pelanggan']
                                        ); ?>

                                    </strong>

                                </div>

                            <?php else: ?>

                                <div class="tanggal">

                                    Kasir:

                                    <strong>

                                        <?= htmlspecialchars(
                                            $trx['nama_kasir']
                                        ); ?>

                                    </strong>

                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="total">

                            <?= rupiah(
                                $trx['total']
                            ); ?>

                        </div>

                    </div>


                    <!-- AKSI -->

                    <div class="aksi">

                        <button
                            type="button"
                            class="btn btn-detail"
                            onclick="toggleDetail(
                                '<?= $idDetail; ?>',
                                this
                            )"
                        >
                            Detail Barang
                        </button>


                        <?php if ($isOnline): ?>

                            <a
                                href="struk.php?id=<?= (int) $trx['id']; ?>"
                                target="_blank"
                                class="btn btn-cetak"
                            >
                                🖨 Lihat Struk
                            </a>

                        <?php else: ?>

                            <a
                                href="cetak_struk.php?id=<?= (int) $trx['id']; ?>"
                                target="_blank"
                                class="btn btn-cetak"
                            >
                                🖨 Cetak Ulang Struk
                            </a>

                        <?php endif; ?>

                    </div>


                    <!-- DETAIL -->

                    <div
                        class="detail"
                        id="<?= $idDetail; ?>"
                    >


                        <?php if ($isOnline): ?>

                            <div class="info-online">

                                <div class="info-item">

                                    Pelanggan

                                    <strong>

                                        <?= htmlspecialchars(
                                            $trx['nama_pelanggan']
                                        ); ?>

                                    </strong>

                                </div>


                                <div class="info-item">

                                    Metode Pembayaran

                                    <strong>

                                        <?= htmlspecialchars(
                                            $trx['metode_pembayaran']
                                        ); ?>

                                    </strong>

                                </div>


                                <div class="info-item alamat">

                                    Alamat Pengiriman

                                    <strong>

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $trx['alamat']
                                            )
                                        ); ?>

                                    </strong>

                                </div>

                            </div>

                        <?php endif; ?>


                        <table>

                            <thead>

                                <tr>

                                    <th>No</th>

                                    <th>Barang</th>

                                    <th>Harga</th>

                                    <th>Qty</th>

                                    <th>Subtotal</th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php $no = 1; ?>


                                <?php if (
                                    empty($trx['detail'])
                                ): ?>

                                    <tr>

                                        <td
                                            colspan="5"
                                            style="
                                                text-align:center;
                                                color:#6b7280;
                                            "
                                        >
                                            Detail barang
                                            tidak tersedia.
                                        </td>

                                    </tr>

                                <?php else: ?>


                                    <?php foreach (
                                        $trx['detail']
                                        as $item
                                    ): ?>

                                        <tr>

                                            <td>
                                                <?= $no++; ?>
                                            </td>


                                            <td>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $item['nama_barang']
                                                    ); ?>

                                                </strong>


                                                <?php if (
                                                    !empty(
                                                        $item['satuan']
                                                    )
                                                ): ?>

                                                    <small>

                                                        (
                                                        <?= htmlspecialchars(
                                                            $item['satuan']
                                                        ); ?>
                                                        )

                                                    </small>

                                                <?php endif; ?>

                                            </td>


                                            <td>
                                                <?= rupiah(
                                                    $item['harga']
                                                ); ?>
                                            </td>


                                            <td>
                                                <?= (int)
                                                    $item['jumlah']; ?>
                                            </td>


                                            <td>
                                                <?= rupiah(
                                                    $item['subtotal']
                                                ); ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>


                        <!-- PAYMENT -->

                        <div class="payment-summary">


                            <div class="payment-row">

                                <span>
                                    Total
                                </span>

                                <strong>
                                    <?= rupiah(
                                        $trx['total']
                                    ); ?>
                                </strong>

                            </div>


                            <?php if (!$isOnline): ?>

                                <div class="payment-row">

                                    <span>
                                        Bayar
                                    </span>

                                    <strong>
                                        <?= rupiah(
                                            $trx['bayar']
                                        ); ?>
                                    </strong>

                                </div>


                                <div class="payment-row total-row">

                                    <span>
                                        Kembalian
                                    </span>

                                    <strong>
                                        <?= rupiah(
                                            $trx['kembalian']
                                        ); ?>
                                    </strong>

                                </div>

                            <?php else: ?>

                                <div class="payment-row total-row">

                                    <span>
                                        Status
                                    </span>

                                    <strong>
                                        Selesai
                                    </strong>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</main>


<script>

// =====================================================
// SIDEBAR
// =====================================================

function openSidebar()
{
    document
        .getElementById('kasirSidebar')
        .classList.add('show');

    document
        .getElementById('sidebarOverlay')
        .classList.add('show');

    document.body.classList.add(
        'sidebar-open'
    );
}


function closeSidebar()
{
    document
        .getElementById('kasirSidebar')
        .classList.remove('show');

    document
        .getElementById('sidebarOverlay')
        .classList.remove('show');

    document.body.classList.remove(
        'sidebar-open'
    );
}


function toggleSidebar()
{
    const sidebar =
        document.getElementById(
            'kasirSidebar'
        );

    if (
        sidebar.classList.contains('show')
    ) {
        closeSidebar();
    } else {
        openSidebar();
    }
}


document.addEventListener(
    'keydown',
    function(event)
    {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    }
);


// =====================================================
// DETAIL TRANSAKSI
// =====================================================

function toggleDetail(
    id,
    tombol
)
{
    const detail =
        document.getElementById(id);

    if (!detail) {
        return;
    }

    const terbuka =
        detail.style.display === 'block';

    if (terbuka) {

        detail.style.display = 'none';

        tombol.textContent =
            'Detail Barang';

    } else {

        detail.style.display = 'block';

        tombol.textContent =
            'Tutup Detail';
    }
}

</script>

</body>
</html>