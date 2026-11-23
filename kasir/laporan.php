<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['id_user']) || ($_SESSION['role'] ?? '') !== 'kasir') {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? 'Kasir';

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function valid_date($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/*
|--------------------------------------------------------------------------
| TANGGAL & MODE LAPORAN
|--------------------------------------------------------------------------
*/

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

if (!valid_date($tanggal)) {
    $tanggal = date('Y-m-d');
}

$mode = $_GET['mode'] ?? 'harian';

if (!in_array($mode, ['harian', 'mingguan', 'bulanan'], true)) {
    $mode = 'harian';
}

$selected = new DateTime($tanggal);

/*
|--------------------------------------------------------------------------
| PERIODE LAPORAN
|--------------------------------------------------------------------------
*/

if ($mode === 'mingguan') {

    // Senin - Minggu
    $start = (clone $selected)->modify('monday this week');
    $end = (clone $start)->modify('+6 days');

} elseif ($mode === 'bulanan') {

    // Tanggal 1 - akhir bulan
    $start = new DateTime($selected->format('Y-m-01'));
    $end = (clone $start)->modify('last day of this month');

} else {

    // 1 hari
    $start = clone $selected;
    $end = clone $selected;
}

$start_date = $start->format('Y-m-d');
$end_date = $end->format('Y-m-d');

/*
|--------------------------------------------------------------------------
| PENJUALAN LANGSUNG
|--------------------------------------------------------------------------
| Hanya transaksi yang dilakukan kasir yang sedang login.
|--------------------------------------------------------------------------
*/

$langsung = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        id_penjualan,
        kode_penjualan,
        waktu_penjualan,
        total_penjualan,
        bayar,
        kembalian
     FROM penjualan
     WHERE id_user = ?
       AND DATE(waktu_penjualan) BETWEEN ? AND ?
     ORDER BY waktu_penjualan ASC"
);

mysqli_stmt_bind_param(
    $stmt,
    'iss',
    $id_user,
    $start_date,
    $end_date
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $langsung[] = $row;
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| PESANAN ONLINE
|--------------------------------------------------------------------------
| Hanya pesanan online yang sudah selesai.
|--------------------------------------------------------------------------
*/

$online = [];

$cek_tabel = mysqli_query($conn, "SHOW TABLES LIKE 'pesanan'");

if ($cek_tabel && mysqli_num_rows($cek_tabel) > 0) {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            id_pesanan,
            tanggal_pesanan,
            total_harga,
            metode_pembayaran
         FROM pesanan
         WHERE status = 'selesai'
           AND DATE(tanggal_pesanan) BETWEEN ? AND ?
         ORDER BY tanggal_pesanan ASC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        'ss',
        $start_date,
        $end_date
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $online[] = $row;
    }

    mysqli_stmt_close($stmt);
}

/*
|--------------------------------------------------------------------------
| TOTAL LAPORAN
|--------------------------------------------------------------------------
*/

$jumlah_langsung = count($langsung);

$total_langsung = array_sum(
    array_map(
        fn($r) => (float)$r['total_penjualan'],
        $langsung
    )
);

$jumlah_online = count($online);

$total_online = array_sum(
    array_map(
        fn($r) => (float)$r['total_harga'],
        $online
    )
);

$total_transaksi = $jumlah_langsung + $jumlah_online;

$total_penjualan = $total_langsung + $total_online;

/*
|--------------------------------------------------------------------------
| REKAP PER TANGGAL
|--------------------------------------------------------------------------
*/

$rekap_harian = [];

$cursor = clone $start;

while ($cursor <= $end) {

    $key = $cursor->format('Y-m-d');

    $rekap_harian[$key] = [
        'langsung_jumlah' => 0,
        'langsung_total' => 0,
        'online_jumlah' => 0,
        'online_total' => 0,
    ];

    $cursor->modify('+1 day');
}

/*
|--------------------------------------------------------------------------
| MASUKKAN PENJUALAN LANGSUNG KE REKAP
|--------------------------------------------------------------------------
*/

foreach ($langsung as $row) {

    $key = date(
        'Y-m-d',
        strtotime($row['waktu_penjualan'])
    );

    if (isset($rekap_harian[$key])) {

        $rekap_harian[$key]['langsung_jumlah']++;

        $rekap_harian[$key]['langsung_total'] +=
            (float)$row['total_penjualan'];
    }
}

/*
|--------------------------------------------------------------------------
| MASUKKAN PESANAN ONLINE KE REKAP
|--------------------------------------------------------------------------
*/

foreach ($online as $row) {

    $key = date(
        'Y-m-d',
        strtotime($row['tanggal_pesanan'])
    );

    if (isset($rekap_harian[$key])) {

        $rekap_harian[$key]['online_jumlah']++;

        $rekap_harian[$key]['online_total'] +=
            (float)$row['total_harga'];
    }
}

/*
|--------------------------------------------------------------------------
| NAMA PERIODE
|--------------------------------------------------------------------------
*/

$bulan_indonesia = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

function tanggal_indonesia($date) {

    global $bulan_indonesia;

    $time = strtotime($date);

    $hari = date('d', $time);
    $bulan = $bulan_indonesia[(int)date('n', $time)];
    $tahun = date('Y', $time);

    return $hari . ' ' . $bulan . ' ' . $tahun;
}

function tanggal_singkat($date) {

    global $bulan_indonesia;

    $time = strtotime($date);

    $hari = date('d', $time);
    $bulan = $bulan_indonesia[(int)date('n', $time)];
    $tahun = date('Y', $time);

    return $hari . ' ' . substr($bulan, 0, 3) . ' ' . $tahun;
}

if ($mode === 'harian') {

    $label_periode = tanggal_indonesia(
        $selected->format('Y-m-d')
    );

} else {

    $label_periode =
        tanggal_indonesia($start->format('Y-m-d')) .
        ' – ' .
        tanggal_indonesia($end->format('Y-m-d'));
}

/*
|--------------------------------------------------------------------------
| NAVIGASI PERIODE
|--------------------------------------------------------------------------
*/

$prev = clone $selected;
$next = clone $selected;

if ($mode === 'mingguan') {

    $prev->modify('-7 days');
    $next->modify('+7 days');

} elseif ($mode === 'bulanan') {

    $prev->modify('first day of last month');
    $next->modify('first day of next month');

} else {

    $prev->modify('-1 day');
    $next->modify('+1 day');
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

<title>Laporan - Toko Malaya</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #172033;
}

body.sidebar-open {
    overflow: hidden;
}


/* =========================================================
   SIDEBAR OVERLAY
========================================================= */

.sidebar-overlay {
    position: fixed;
    inset: 0;

    background: rgba(15, 23, 42, 0.42);

    z-index: 1200;

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


/* =========================================================
   SIDEBAR
========================================================= */

.kasir-sidebar {
    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    width: 307px;

    background: #ffffff;

    border-right: 1px solid #e5eaf2;

    box-shadow:
        8px 0 25px rgba(15, 23, 42, 0.08);

    z-index: 1300;

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


/* =========================================================
   SIDEBAR BRAND
   ========================================================= */

.sidebar-brand {

    /*
     * INI YANG DIPERBAIKI.
     * Sebelumnya 165px sehingga muncul
     * jarak kosong besar.
     */
    height: 80px;
    min-height: 80px;

    padding: 0 22px;

    display: flex;
    align-items: center;

    gap: 12px;

    border-bottom: 1px solid #eef1f5;

    box-sizing: border-box;
}

.sidebar-logo {

    width: 44px;
    height: 44px;

    border-radius: 12px;

    background: #eaf4ff;

    display: flex;
    align-items: center;
    justify-content: center;

    overflow: hidden;

    flex: 0 0 auto;
}

.sidebar-logo img {

    width: 38px;
    height: 38px;

    object-fit: contain;
}

.sidebar-brand-text {

    display: flex;
    flex-direction: column;

    gap: 3px;

    line-height: 1.1;
}

.sidebar-brand-text strong {

    font-size: 20px;

    color: #1f2937;

    font-weight: 700;
}

.sidebar-brand-text span {

    font-size: 13px;

    color: #7b8798;
}


/* =========================================================
   SIDEBAR USER
========================================================= */

.sidebar-user {

    margin: 18px 18px 8px;

    padding: 14px;

    display: flex;
    align-items: center;

    gap: 12px;

    border: 1px solid #e5ebf3;

    background: #f7faff;

    border-radius: 12px;

    box-sizing: border-box;
}

.sidebar-user-avatar {

    width: 42px;
    height: 42px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #e6f2ff;

    color: #1677d2;

    font-weight: 700;

    font-size: 18px;

    flex: 0 0 auto;
}

.sidebar-user-info {

    display: flex;

    flex-direction: column;

    gap: 3px;

    min-width: 0;
}

.sidebar-user-info strong {

    font-size: 14px;

    color: #263244;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.sidebar-user-info span {

    font-size: 12px;

    color: #7b8798;
}


/* =========================================================
   SIDEBAR MENU
========================================================= */

.sidebar-menu {

    padding: 0 12px 16px;

    overflow-y: auto;
}

.sidebar-menu-label {

    font-size: 11px;

    font-weight: 700;

    letter-spacing: .04em;

    color: #98a2b3;

    padding: 10px 12px 8px;
}

.sidebar-menu-label-second {

    margin-top: 8px;
}

.sidebar-link {

    position: relative;

    display: flex;
    align-items: center;

    gap: 12px;

    text-decoration: none;

    color: #566276;

    font-size: 14px;

    font-weight: 500;

    padding: 11px 14px;

    border-radius: 8px;

    margin: 3px 0;

    transition:
        background .2s ease,
        color .2s ease;
}

.sidebar-link:hover {

    background: #f4f8fd;

    color: #1677d2;
}

.sidebar-link.active {

    background: #e8f3ff;

    color: #1677d2;

    font-weight: 700;
}

.sidebar-link.active::before {

    content: "";

    position: absolute;

    left: 0;

    top: 7px;

    bottom: 7px;

    width: 3px;

    border-radius:
        0 3px 3px 0;

    background: #1677d2;
}


/* =========================================================
   SIDEBAR BOTTOM
========================================================= */

.sidebar-bottom {

    margin-top: auto;

    padding: 14px 12px;

    border-top: 1px solid #eef1f5;
}

.sidebar-logout {

    display: flex;
    align-items: center;

    gap: 12px;

    padding: 11px 14px;

    border-radius: 8px;

    text-decoration: none;

    color: #ef4444;

    font-size: 14px;

    font-weight: 500;
}

.sidebar-logout:hover {

    background: #fff1f2;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height: 78px;

    background: #ffffff;

    border-bottom: 1px solid #e8edf4;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 24px;

    position: sticky;

    top: 0;

    z-index: 1000;
}

.topbar-left {

    display: flex;
    align-items: center;

    gap: 14px;
}

.hamburger {

    width: 46px;
    height: 46px;

    border: 0;

    border-radius: 10px;

    background: #edf6ff;

    color: #1677d2;

    font-size: 26px;

    line-height: 1;

    cursor: pointer;
}

.hamburger:hover {

    background: #e3f1ff;
}

.topbar-brand {

    display: flex;
    align-items: center;

    gap: 10px;

    text-decoration: none;
}

.topbar-logo {

    width: 44px;
    height: 44px;

    border-radius: 12px;

    background: #edf6ff;

    display: flex;
    align-items: center;
    justify-content: center;

    overflow: hidden;
}

.topbar-logo img {

    width: 38px;
    height: 38px;

    object-fit: contain;
}

.topbar-brand-text {

    display: flex;

    flex-direction: column;

    gap: 2px;
}

.topbar-brand-text strong {

    font-size: 18px;

    color: #1f2937;
}

.topbar-brand-text span {

    font-size: 12px;

    color: #7b8798;
}

.topbar-user {

    display: flex;
    align-items: center;

    gap: 10px;
}

.topbar-user-info {

    display: flex;

    flex-direction: column;

    gap: 2px;

    text-align: right;
}

.topbar-user-info strong {

    font-size: 14px;

    color: #263244;
}

.topbar-user-info span {

    font-size: 12px;

    color: #7b8798;
}

.topbar-avatar {

    width: 42px;
    height: 42px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #e6f2ff;

    color: #1677d2;

    font-weight: 700;

    font-size: 18px;
}


/* =========================================================
   CONTENT
========================================================= */

.content {

    max-width: 1200px;

    margin: 0 auto;

    padding: 34px 28px 50px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;
}

.page-header h1 {

    margin: 0 0 6px;

    font-size: 28px;

    color: #172033;
}

.page-header p {

    margin: 0;

    color: #718096;

    font-size: 14px;
}

.page-icon {

    width: 52px;
    height: 52px;

    border-radius: 14px;

    background: #e8f3ff;

    color: #1677d2;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 25px;
}


/* =========================================================
   REPORT TABS
========================================================= */

.report-tabs {

    display: flex;

    gap: 8px;

    margin-bottom: 18px;

    flex-wrap: wrap;
}

.report-tab {

    padding: 10px 18px;

    border-radius: 9px;

    background: #ffffff;

    border: 1px solid #dfe6ef;

    color: #526074;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;

    transition: .2s;
}

.report-tab:hover {

    border-color: #1677d2;

    color: #1677d2;
}

.report-tab.active {

    background: #1677d2;

    color: #ffffff;

    border-color: #1677d2;
}


/* =========================================================
   PERIOD BAR
========================================================= */

.period-bar {

    background: #ffffff;

    border: 1px solid #e1e7ef;

    border-radius: 12px;

    padding: 16px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 8px rgba(15, 23, 42, .04);
}

.period-center {

    text-align: center;

    flex: 1;
}

.period-label {

    font-size: 12px;

    color: #8490a3;

    margin-bottom: 3px;
}

.period-value {

    font-size: 17px;

    font-weight: 700;

    color: #172033;
}

.date-btn {

    width: 42px;
    height: 40px;

    border: 1px solid #dce4ee;

    border-radius: 8px;

    background: #ffffff;

    color: #1677d2;

    font-size: 20px;

    text-decoration: none;

    display: flex;

    align-items: center;
    justify-content: center;
}

.date-btn:hover {

    background: #eaf4ff;

    border-color: #1677d2;
}

.today-btn {

    padding: 10px 14px;

    border-radius: 8px;

    background: #eaf4ff;

    color: #1677d2;

    text-decoration: none;

    font-size: 13px;

    font-weight: 700;

    white-space: nowrap;
}

.today-btn:hover {

    background: #dceeff;
}


/* =========================================================
   SUMMARY
========================================================= */

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 16px;

    margin-bottom: 20px;
}

.summary-card {

    background: #ffffff;

    border: 1px solid #e3e9f1;

    border-radius: 12px;

    padding: 20px;

    border-top: 4px solid #1677d2;

    box-shadow:
        0 2px 8px rgba(15, 23, 42, .04);
}

.summary-card h3 {

    margin: 0 0 10px;

    font-size: 13px;

    font-weight: 600;

    color: #68758a;
}

.summary-value {

    font-size: 25px;

    font-weight: 700;

    color: #1677d2;
}

.summary-note {

    font-size: 12px;

    color: #7b8798;

    margin-top: 6px;
}


/* =========================================================
   REPORT CARD
========================================================= */

.report-card {

    background: #ffffff;

    border: 1px solid #e3e9f1;

    border-radius: 12px;

    overflow: hidden;

    box-shadow:
        0 2px 8px rgba(15, 23, 42, .04);

    margin-bottom: 20px;
}

.report-card-header {

    padding: 18px 20px;

    border-bottom: 1px solid #edf1f5;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.report-card-header h2 {

    margin: 0;

    font-size: 17px;

    color: #172033;
}

.report-card-header span {

    font-size: 12px;

    color: #7b8798;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrap {

    overflow-x: auto;
}

.report-table {

    width: 100%;

    border-collapse: collapse;

    min-width: 760px;
}

.report-table th,
.report-table td {

    padding: 13px 16px;

    border-bottom: 1px solid #edf1f5;

    text-align: left;

    font-size: 13px;
}

.report-table th {

    background: #f8fbff;

    color: #5f6d82;

    font-size: 12px;

    font-weight: 700;
}

.report-table td {

    color: #39465a;
}

.report-table td.money {

    text-align: right;

    font-weight: 600;
}

.report-table th.money {

    text-align: right;
}

.report-table tr:last-child td {

    border-bottom: 0;
}


/* =========================================================
   SOURCE COLORS
========================================================= */

.source-direct {

    color: #1677d2;

    font-weight: 700;
}

.source-online {

    color: #2563eb;

    font-weight: 700;
}

.empty {

    text-align: center;

    color: #7b8798;

    padding: 35px !important;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 800px) {

    .summary-grid {

        grid-template-columns: 1fr;
    }

    .content {

        padding:
            24px
            18px
            40px;
    }

    .topbar {

        padding:
            0 14px;
    }

    .topbar-user-info {

        display: none;
    }

    .page-header {

        align-items: center;
    }

    .period-bar {

        flex-wrap: wrap;
    }

    .period-center {

        order: 1;

        min-width: 100%;
    }

    .date-btn {

        order: 2;
    }

    .today-btn {

        order: 3;
    }

    .kasir-sidebar {

        width: 285px;
    }
}

@media (max-width: 480px) {

    .topbar-brand-text {

        display: none;
    }

    .page-header h1 {

        font-size: 23px;
    }

    .report-tabs {

        display: grid;

        grid-template-columns:
            repeat(3, 1fr);
    }

    .report-tab {

        text-align: center;

        padding:
            9px 6px;

        font-size: 12px;
    }

    .page-icon {

        width: 44px;
        height: 44px;

        font-size: 21px;
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
    onclick="closeSidebar()">
</div>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside
    class="kasir-sidebar"
    id="kasirSidebar"
>

    <!-- BRAND -->

    <div class="sidebar-brand">

        <div class="sidebar-logo">

            <img
                src="../assets/SembakoKu (1).png"
                alt="Logo Toko Malaya"
            >

        </div>

        <div class="sidebar-brand-text">

            <strong>Toko Malaya</strong>

            <span>Panel Kasir</span>

        </div>

    </div>


    <!-- USER -->

    <div class="sidebar-user">

        <div class="sidebar-user-avatar">

            <?= htmlspecialchars(
                strtoupper(
                    substr($nama, 0, 1)
                )
            ); ?>

        </div>

        <div class="sidebar-user-info">

            <strong>
                <?= htmlspecialchars($nama); ?>
            </strong>

            <span>Kasir</span>

        </div>

    </div>


    <!-- MENU -->

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
            class="sidebar-link"
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
            class="sidebar-link active"
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


    <!-- LOGOUT -->

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

                <strong>Toko Malaya</strong>

                <span>Panel Kasir</span>

            </div>

        </a>

    </div>


    <div class="topbar-user">

        <div class="topbar-user-info">

            <strong>
                <?= htmlspecialchars($nama); ?>
            </strong>

            <span>Kasir</span>

        </div>


        <div class="topbar-avatar">

            <?= htmlspecialchars(
                strtoupper(
                    substr($nama, 0, 1)
                )
            ); ?>

        </div>

    </div>

</header>


<!-- =====================================================
     CONTENT
===================================================== -->

<main class="content">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div>

            <h1>Laporan</h1>

            <p>
                Rekap penjualan berdasarkan periode.
            </p>

        </div>


        <div class="page-icon">
            📊
        </div>

    </div>


    <!-- =================================================
         TAB PERIODE
    ================================================= -->

    <div class="report-tabs">

        <a
            class="report-tab <?= $mode === 'harian' ? 'active' : ''; ?>"
            href="?mode=harian&tanggal=<?= $selected->format('Y-m-d'); ?>"
        >
            Harian
        </a>


        <a
            class="report-tab <?= $mode === 'mingguan' ? 'active' : ''; ?>"
            href="?mode=mingguan&tanggal=<?= $selected->format('Y-m-d'); ?>"
        >
            Mingguan
        </a>


        <a
            class="report-tab <?= $mode === 'bulanan' ? 'active' : ''; ?>"
            href="?mode=bulanan&tanggal=<?= $selected->format('Y-m-d'); ?>"
        >
            Bulanan
        </a>

    </div>


    <!-- =================================================
         PERIODE
    ================================================= -->

    <div class="period-bar">


        <a
            class="date-btn"
            href="?mode=<?= $mode; ?>&tanggal=<?= $prev->format('Y-m-d'); ?>"
            title="Periode sebelumnya"
        >
            ‹
        </a>


        <div class="period-center">

            <div class="period-label">
                Periode Laporan
            </div>

            <div class="period-value">
                <?= htmlspecialchars($label_periode); ?>
            </div>

        </div>


        <a
            class="today-btn"
            href="?mode=<?= $mode; ?>&tanggal=<?= date('Y-m-d'); ?>"
        >
            Hari Ini
        </a>


        <a
            class="date-btn"
            href="?mode=<?= $mode; ?>&tanggal=<?= $next->format('Y-m-d'); ?>"
            title="Periode berikutnya"
        >
            ›
        </a>

    </div>


    <!-- =================================================
         SUMMARY
    ================================================= -->

    <div class="summary-grid">


        <!-- TOTAL -->

        <div class="summary-card">

            <h3>
                Total Penjualan
            </h3>

            <div class="summary-value">
                <?= rupiah($total_penjualan); ?>
            </div>

            <div class="summary-note">
                <?= $total_transaksi; ?>
                transaksi keseluruhan
            </div>

        </div>


        <!-- LANGSUNG -->

        <div class="summary-card">

            <h3>
                Penjualan Langsung
            </h3>

            <div class="summary-value">
                <?= rupiah($total_langsung); ?>
            </div>

            <div class="summary-note">
                <?= $jumlah_langsung; ?>
                transaksi langsung
            </div>

        </div>


        <!-- ONLINE -->

        <div class="summary-card">

            <h3>
                Pesanan Online
            </h3>

            <div class="summary-value">
                <?= rupiah($total_online); ?>
            </div>

            <div class="summary-note">
                <?= $jumlah_online; ?>
                pesanan selesai
            </div>

        </div>

    </div>


    <!-- =================================================
         REKAP PENJUALAN
    ================================================= -->

    <div class="report-card">

        <div class="report-card-header">

            <h2>
                Rekap Penjualan
            </h2>

            <span>
                <?= htmlspecialchars($label_periode); ?>
            </span>

        </div>


        <div class="table-wrap">

            <table class="report-table">

                <thead>

                    <tr>

                        <th>
                            Tanggal
                        </th>

                        <th>
                            Penjualan Langsung
                        </th>

                        <th class="money">
                            Total Langsung
                        </th>

                        <th>
                            Pesanan Online
                        </th>

                        <th class="money">
                            Total Online
                        </th>

                        <th class="money">
                            Total Hari
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($rekap_harian as $tgl => $r): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                tanggal_singkat($tgl)
                            ); ?>
                        </td>


                        <td>

                            <span class="source-direct">

                                <?= $r['langsung_jumlah']; ?>

                                transaksi

                            </span>

                        </td>


                        <td class="money">

                            <?= rupiah(
                                $r['langsung_total']
                            ); ?>

                        </td>


                        <td>

                            <span class="source-online">

                                <?= $r['online_jumlah']; ?>

                                pesanan

                            </span>

                        </td>


                        <td class="money">

                            <?= rupiah(
                                $r['online_total']
                            ); ?>

                        </td>


                        <td class="money">

                            <?= rupiah(
                                $r['langsung_total']
                                +
                                $r['online_total']
                            ); ?>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (!$rekap_harian): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="empty"
                        >
                            Tidak ada data pada periode ini.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =================================================
         DETAIL TRANSAKSI
    ================================================= -->

    <div class="report-card">

        <div class="report-card-header">

            <h2>
                Detail Transaksi
            </h2>

            <span>
                Penjualan langsung dan pesanan online selesai
            </span>

        </div>


        <div class="table-wrap">

            <table class="report-table">

                <thead>

                    <tr>

                        <th>
                            Waktu
                        </th>

                        <th>
                            Sumber
                        </th>

                        <th>
                            Kode / ID
                        </th>

                        <th>
                            Metode
                        </th>

                        <th class="money">
                            Total
                        </th>

                    </tr>

                </thead>


                <tbody>


                <!-- PENJUALAN LANGSUNG -->

                <?php foreach ($langsung as $row): ?>

                    <tr>

                        <td>

                            <?= htmlspecialchars(
                                date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $row['waktu_penjualan']
                                    )
                                )
                            ); ?>

                        </td>


                        <td class="source-direct">

                            Penjualan Langsung

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $row['kode_penjualan']
                            ); ?>

                        </td>


                        <td>
                            Tunai
                        </td>


                        <td class="money">

                            <?= rupiah(
                                $row['total_penjualan']
                            ); ?>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <!-- PESANAN ONLINE -->

                <?php foreach ($online as $row): ?>

                    <tr>

                        <td>

                            <?= htmlspecialchars(
                                date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $row['tanggal_pesanan']
                                    )
                                )
                            ); ?>

                        </td>


                        <td class="source-online">

                            Pesanan Online

                        </td>


                        <td>

                            #<?= htmlspecialchars(
                                $row['id_pesanan']
                            ); ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $row['metode_pembayaran'] ?? '-'
                            ); ?>

                        </td>


                        <td class="money">

                            <?= rupiah(
                                $row['total_harga']
                            ); ?>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (!$langsung && !$online): ?>

                    <tr>

                        <td
                            colspan="5"
                            class="empty"
                        >
                            Belum ada transaksi pada periode ini.
                        </td>

                    </tr>

                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>

</main>


<!-- =====================================================
     JAVASCRIPT SIDEBAR
===================================================== -->

<script>

function openSidebar() {

    const sidebar =
        document.getElementById('kasirSidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    sidebar.classList.add('show');

    overlay.classList.add('show');

    document.body.classList.add('sidebar-open');
}


function closeSidebar() {

    const sidebar =
        document.getElementById('kasirSidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    sidebar.classList.remove('show');

    overlay.classList.remove('show');

    document.body.classList.remove('sidebar-open');
}


function toggleSidebar() {

    const sidebar =
        document.getElementById('kasirSidebar');

    if (sidebar.classList.contains('show')) {

        closeSidebar();

    } else {

        openSidebar();

    }
}


document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {

            closeSidebar();

        }

    }
);

</script>

</body>
</html>