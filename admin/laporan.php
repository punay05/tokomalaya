
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: ../auth/login.php');
    exit;
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'pemilik'], true)) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$nama = $_SESSION['nama'] ?? 'Admin';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rupiah($v): string {
    return 'Rp ' . number_format((float)$v, 0, ',', '.');
}

function tanggalValid($v): bool {
    $d = DateTime::createFromFormat('!Y-m-d', (string)$v);
    return $d && $d->format('Y-m-d') === $v;
}

function ambilData(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $data = [];
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return $data;
    }

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
    }

    mysqli_stmt_close($stmt);
    return $data;
}

function tabelAda(mysqli $conn, string $tabel): bool {
    $tabel = mysqli_real_escape_string($conn, $tabel);
    $q = mysqli_query($conn, "SHOW TABLES LIKE '$tabel'");
    return $q && mysqli_num_rows($q) > 0;
}

/* =========================
   FILTER PERIODE LAPORAN
========================= */

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

if (!tanggalValid($tanggal)) {
    $tanggal = date('Y-m-d');
}

$mode = $_GET['mode'] ?? 'harian';

if (!in_array($mode, ['harian', 'mingguan', 'bulanan'], true)) {
    $mode = 'harian';
}

$selected = new DateTime($tanggal);

if ($mode === 'mingguan') {
    $start = (clone $selected)->modify('monday this week');
    $end = (clone $start)->modify('+6 days');
} elseif ($mode === 'bulanan') {
    $start = new DateTime($selected->format('Y-m-01'));
    $end = (clone $start)->modify('last day of this month');
} else {
    $start = clone $selected;
    $end = clone $selected;
}

$awal = $start->format('Y-m-d');
$akhir = $end->format('Y-m-d');

/* =========================
   PENJUALAN LANGSUNG
========================= */

$langsung = ambilData(
    $conn,
    "SELECT p.id_penjualan, p.kode_penjualan, p.waktu_penjualan,
            p.total_penjualan, p.bayar, p.kembalian,
            u.nama AS nama_kasir
     FROM penjualan p
     LEFT JOIN users u ON p.id_user = u.id_user
     WHERE p.waktu_penjualan >= ?
       AND p.waktu_penjualan < DATE_ADD(?, INTERVAL 1 DAY)
     ORDER BY p.waktu_penjualan ASC",
    'ss',
    [$awal, $akhir]
);

/* =========================
   PESANAN ONLINE SELESAI
========================= */

$online = [];

if (tabelAda($conn, 'pesanan')) {
    $online = ambilData(
        $conn,
        "SELECT p.id_pesanan, p.tanggal_pesanan, p.total_harga,
                p.metode_pembayaran, u.nama AS nama_pelanggan
         FROM pesanan p
         LEFT JOIN users u ON p.id_user = u.id_user
         WHERE p.status = 'selesai'
           AND p.tanggal_pesanan >= ?
           AND p.tanggal_pesanan < DATE_ADD(?, INTERVAL 1 DAY)
         ORDER BY p.tanggal_pesanan ASC",
        'ss',
        [$awal, $akhir]
    );
}

/* =========================
   PENGELUARAN
========================= */

$pengeluaran = [];

if (tabelAda($conn, 'pengeluaran')) {
    $pengeluaran = ambilData(
        $conn,
        "SELECT id_pengeluaran, tanggal_pengeluaran, keterangan, jumlah
         FROM pengeluaran
         WHERE tanggal_pengeluaran >= ?
           AND tanggal_pengeluaran < DATE_ADD(?, INTERVAL 1 DAY)
         ORDER BY tanggal_pengeluaran ASC",
        'ss',
        [$awal, $akhir]
    );
}

/* =========================
   PERHITUNGAN LAPORAN
========================= */

$totalLangsung = array_sum(array_map(
    fn($r) => (float)$r['total_penjualan'],
    $langsung
));

$totalOnline = array_sum(array_map(
    fn($r) => (float)$r['total_harga'],
    $online
));

$totalPengeluaran = array_sum(array_map(
    fn($r) => (float)$r['jumlah'],
    $pengeluaran
));

$jumlahTransaksi = count($langsung) + count($online);
$totalPenjualan = $totalLangsung + $totalOnline;
$totalBersih = $totalPenjualan - $totalPengeluaran;

$rataRata = $jumlahTransaksi
    ? $totalPenjualan / $jumlahTransaksi
    : 0;

$labelPeriode = $mode === 'harian'
    ? $start->format('d/m/Y')
    : $start->format('d/m/Y') . ' – ' . $end->format('d/m/Y');

/* =========================
   NAVIGASI PERIODE
========================= */

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

/* =========================
   REKAP HARIAN
========================= */

$rekap = [];

$cursor = clone $start;

while ($cursor <= $end) {
    $key = $cursor->format('Y-m-d');

    $rekap[$key] = [
        'penjualan' => 0,
        'pengeluaran' => 0
    ];

    $cursor->modify('+1 day');
}

foreach ($langsung as $r) {
    $key = date('Y-m-d', strtotime($r['waktu_penjualan']));

    if (isset($rekap[$key])) {
        $rekap[$key]['penjualan'] += (float)$r['total_penjualan'];
    }
}

foreach ($online as $r) {
    $key = date('Y-m-d', strtotime($r['tanggal_pesanan']));

    if (isset($rekap[$key])) {
        $rekap[$key]['penjualan'] += (float)$r['total_harga'];
    }
}

foreach ($pengeluaran as $r) {
    $key = date('Y-m-d', strtotime($r['tanggal_pengeluaran']));

    if (isset($rekap[$key])) {
        $rekap[$key]['pengeluaran'] += (float)$r['jumlah'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laporan - Toko Malaya</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #f5f7fb;
    color: #172033;
}

body.menu-open {
    overflow: hidden;
}

a {
    text-decoration: none;
}

/* =========================
   TOPBAR - SAMA DENGAN DASHBOARD
========================= */

.topbar {
    height: 72px;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    position: sticky;
    top: 0;
    z-index: 900;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.hamburger {
    width: 42px;
    height: 42px;
    border: 0;
    border-radius: 10px;
    background: #f1f5f9;
    font-size: 23px;
    color: #334155;
    cursor: pointer;
}

.hamburger:hover {
    background: #e2e8f0;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #111827;
    text-decoration: none;
}

.brand-logo {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #1677d2;
    overflow: hidden;
    flex-shrink: 0;
}

.brand-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.brand-text {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.brand-text strong {
    font-size: 17px;
    font-weight: 800;
    color: #111827;
}

.brand-text span {
    font-size: 12px;
    color: #64748b;
}

.top-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.top-user-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    text-align: right;
}

.top-user-info strong {
    font-size: 14px;
    color: #111827;
}

.top-user-info span {
    font-size: 12px;
    color: #64748b;
}

.top-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #e0efff;
    color: #1265a7;
    display: grid;
    place-items: center;
    font-weight: bold;
    flex-shrink: 0;
}

/* =========================
   OVERLAY DAN DRAWER
   SAMA DENGAN DASHBOARD
========================= */

.menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .45);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: .25s;
}

.menu-overlay.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.drawer {
    position: fixed;
    top: 0;
    left: 0;
    width: 335px;
    height: 100vh;
    background: #fff;
    z-index: 1100;
    transform: translateX(-100%);
    transition: .28s ease;
    display: flex;
    flex-direction: column;
    box-shadow: 8px 0 30px rgba(15, 23, 42, .12);
}

.drawer.show {
    transform: translateX(0);
}

.drawer-header {
    min-height: 88px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e5e7eb;
}

.drawer-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.drawer-logo {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    background: #1677d2;
    overflow: hidden;
    flex-shrink: 0;
}

.drawer-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.drawer-brand-text {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.drawer-brand-text strong {
    font-size: 18px;
    font-weight: 800;
    color: #111827;
}

.drawer-brand-text span {
    font-size: 12px;
    color: #64748b;
}

.drawer-close {
    width: 40px;
    height: 40px;
    border: 0;
    border-radius: 10px;
    background: #f1f5f9;
    font-size: 25px;
    color: #334155;
    cursor: pointer;
}

.drawer-close:hover {
    background: #e2e8f0;
}

.drawer-user {
    margin: 20px;
    padding: 14px;
    background: #f8fafc;
    border: 1px solid #dbe4ee;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.drawer-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #e0efff;
    color: #1265a7;
    display: grid;
    place-items: center;
    font-size: 17px;
    font-weight: bold;
    flex-shrink: 0;
}

.drawer-user-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.drawer-user-info strong {
    font-size: 15px;
    color: #111827;
}

.drawer-user-info span {
    font-size: 12px;
    color: #64748b;
}

.drawer-menu {
    padding: 0 14px 20px;
    overflow-y: auto;
    flex: 1;
}

.menu-label {
    padding: 10px 12px;
    margin-bottom: 7px;
    font-size: 11px;
    font-weight: bold;
    color: #94a3b8;
    letter-spacing: .5px;
}

.menu-link {
    min-height: 48px;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 0 14px;
    border-radius: 10px;
    margin-bottom: 5px;
    color: #475569;
    text-decoration: none;
    font-size: 15px;
    font-weight: bold;
    transition: .2s;
}

.menu-link:hover {
    background: #f1f5f9;
    color: #0879e8;
}

.menu-link.active {
    background: #e5f1ff;
    color: #0879e8;
    font-weight: bold;
    position: relative;
}

.menu-link.active::before {
    content: "";
    position: absolute;
    left: 0;
    top: 10px;
    bottom: 10px;
    width: 3px;
    background: #0879e8;
    border-radius: 0 4px 4px 0;
}

.menu-icon {
    width: 25px;
    min-width: 25px;
    text-align: center;
    font-size: 19px;
}

.drawer-footer {
    border-top: 1px solid #e5e7eb;
    padding: 14px;
}

.logout-link {
    min-height: 48px;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 0 14px;
    border-radius: 10px;
    color: #ef4444;
    text-decoration: none;
    font-size: 15px;
    font-weight: bold;
}

.logout-link:hover {
    background: #fef2f2;
}

/* =========================
   KONTEN LAPORAN
========================= */

.main {
    max-width: 1200px;
    margin: auto;
    padding: 30px 24px 50px;
}

.page-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    gap: 15px;
}

h1 {
    font-size: 28px;
    color: #102a43;
    margin-bottom: 7px;
}

.subtitle {
    font-size: 14px;
    color: #64748b;
}

.page-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: #e8f3ff;
    display: grid;
    place-items: center;
    font-size: 25px;
}

.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.tab {
    padding: 10px 18px;
    border: 1px solid #dbe4ee;
    border-radius: 9px;
    background: #fff;
    color: #526074;
    font-size: 13px;
    font-weight: bold;
}

.tab.active {
    background: #1677d2;
    color: white;
    border-color: #1677d2;
}

.period {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    border: 1px solid #e1e7ef;
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 20px;
}

.period-center {
    text-align: center;
    flex: 1;
}

.period-center small {
    display: block;
    color: #8490a3;
    margin-bottom: 5px;
}

.period-center strong {
    font-size: 17px;
}

.date-btn,
.today {
    padding: 10px 13px;
    border: 1px solid #dce4ee;
    border-radius: 8px;
    color: #1677d2;
    background: #fff;
    font-weight: bold;
}

.today {
    background: #eaf4ff;
    border: 0;
}

.cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.card {
    background: #fff;
    border: 1px solid #e3e9f1;
    border-radius: 13px;
    padding: 19px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .03);
}

.card-label {
    font-size: 13px;
    color: #68758a;
    font-weight: 600;
    margin-bottom: 10px;
}

.card-value {
    font-size: clamp(18px, 2vw, 24px);
    font-weight: 800;
    color: #1677d2;
    overflow-wrap: anywhere;
}

.card-note {
    font-size: 12px;
    color: #7b8798;
    margin-top: 7px;
}

.card.expense .card-value {
    color: #dc6b26;
}

.card.net .card-value {
    color: <?= $totalBersih < 0 ? '#dc2626' : '#16835d' ?>;
}

.panel {
    background: #fff;
    border: 1px solid #e3e9f1;
    border-radius: 13px;
    overflow: hidden;
    margin-bottom: 20px;
}

.panel-head {
    padding: 17px 20px;
    border-bottom: 1px solid #edf1f5;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
}

.panel-head h2 {
    font-size: 17px;
}

.panel-head span {
    font-size: 12px;
    color: #7b8798;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 650px;
    border-collapse: collapse;
}

th {
    background: #f8fbff;
    color: #5f6d82;
    font-size: 12px;
    text-align: left;
    padding: 13px 15px;
    border-bottom: 1px solid #edf1f5;
}

td {
    padding: 13px 15px;
    border-bottom: 1px solid #edf1f5;
    font-size: 13px;
    color: #334155;
}

tr:last-child td {
    border-bottom: 0;
}

.money {
    text-align: right;
    font-weight: 700;
    white-space: nowrap;
}

.empty {
    text-align: center;
    color: #94a3b8;
    padding: 30px;
}

.positive {
    color: #16835d;
}

.negative {
    color: #dc2626;
}

/* =========================
   RESPONSIVE
========================= */

@media(max-width: 950px) {
    .cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 700px) {
    .topbar {
        padding: 0 15px;
    }

    .brand-text,
    .top-user-info {
        display: none;
    }

    .top-user {
        border: none;
        padding: 4px;
    }

    .main {
        padding: 24px 15px 40px;
    }
}

@media(max-width: 480px) {
    .drawer {
        width: 300px;
    }

    h1 {
        font-size: 23px;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .period {
        flex-wrap: wrap;
    }

    .period-center {
        order: -1;
        flex-basis: 100%;
        padding: 6px;
    }

    .today {
        flex: 1;
        text-align: center;
    }

    .panel-head {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
</head>

<body>

<!-- OVERLAY -->
<div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>

<!-- DRAWER -->
<aside class="drawer" id="drawer">

    <div class="drawer-header">
        <div class="drawer-brand">
            <div class="drawer-logo">
                <img src="../assets/SembakoKu (3).png" alt="Logo Toko Malaya">
            </div>

            <div class="drawer-brand-text">
                <strong>Toko Malaya</strong>
                <span>Panel Admin / Pemilik</span>
            </div>
        </div>

        <button class="drawer-close" onclick="closeMenu()" aria-label="Tutup menu">
            &times;
        </button>
    </div>

    <div class="drawer-user">
        <div class="drawer-avatar">
            <?= e(strtoupper(substr($nama, 0, 1))) ?>
        </div>

        <div class="drawer-user-info">
            <strong><?= e($nama) ?></strong>
            <span><?= e(ucfirst($role)) ?></span>
        </div>
    </div>

    <nav class="drawer-menu">

        <div class="menu-label">MENU UTAMA</div>

        <a href="dashboard.php" class="menu-link">
            <span class="menu-icon">🏠</span>
            Dashboard
        </a>

        <a href="produk.php" class="menu-link">
            <span class="menu-icon">📦</span>
            Produk dan Kategori
        </a>

        <a href="pengguna.php" class="menu-link">
            <span class="menu-icon">👥</span>
            Pengguna
        </a>

        <a href="stok.php" class="menu-link">
            <span class="menu-icon">📦</span>
            Stok & Supplier
        </a>

        <a href="pengeluaran.php" class="menu-link">
            <span class="menu-icon">🧾</span>
            Pengeluaran
        </a>

        <a href="laporan.php" class="menu-link active">
            <span class="menu-icon">📊</span>
            Laporan
        </a>

        <div class="menu-label">KEPEGAWAIAN</div>

        <a href="jadwal_karyawan.php" class="menu-link">
            <span class="menu-icon">🗓️</span>
            Jadwal Shift Karyawan
        </a>

        <a href="penggajian.php" class="menu-link">
            <span class="menu-icon">💰</span>
            Penggajian Karyawan
        </a>

        <a href="pengajuan_cuti.php" class="menu-link">
            <span class="menu-icon">📝</span>
            Pengajuan Cuti & Izin
        </a>

        <div class="menu-label">AKUN</div>

        <a href="profil.php" class="menu-link">
            <span class="menu-icon">👤</span>
            Profil
        </a>

    </nav>

    <div class="drawer-footer">
        <a href="../auth/logout.php"
           class="logout-link"
           onclick="return confirm('Yakin ingin keluar?')">
            <span class="menu-icon">🚪</span>
            Keluar
        </a>
    </div>

</aside>

<!-- TOPBAR -->
<header class="topbar">

    <div class="topbar-left">

        <button class="hamburger"
                onclick="toggleMenu()"
                aria-label="Buka menu">
            ☰
        </button>

        <a href="dashboard.php" class="brand">
            <div class="brand-logo">
                <img src="../assets/SembakoKu (3).png" alt="Logo Toko Malaya">
            </div>

            <div class="brand-text">
                <strong>Toko Malaya</strong>
                <span>Panel Admin</span>
            </div>
        </a>

    </div>

    <div class="top-user">
        <div class="top-user-info">
            <strong><?= e($nama) ?></strong>
            <span><?= e(ucfirst($role)) ?></span>
        </div>

        <div class="top-avatar">
            <?= e(strtoupper(substr($nama, 0, 1))) ?>
        </div>
    </div>

</header>

<!-- KONTEN UTAMA -->
<main class="main">

    <div class="page-head">
        <div>
            <h1>Laporan Keuangan</h1>
            <p class="subtitle">
                Ringkasan penjualan, pengeluaran, dan total bersih.
            </p>
        </div>

        <div class="page-icon">📊</div>
    </div>

    <!-- FILTER MODE -->
    <div class="tabs">

        <?php foreach ([
            'harian' => 'Harian',
            'mingguan' => 'Mingguan',
            'bulanan' => 'Bulanan'
        ] as $key => $label): ?>

            <a class="tab <?= $mode === $key ? 'active' : '' ?>"
               href="?mode=<?= e($key) ?>&tanggal=<?= e($selected->format('Y-m-d')) ?>">
                <?= e($label) ?>
            </a>

        <?php endforeach; ?>

    </div>

    <!-- NAVIGASI PERIODE -->
    <div class="period">

        <a class="date-btn"
           href="?mode=<?= e($mode) ?>&tanggal=<?= e($prev->format('Y-m-d')) ?>"
           aria-label="Periode sebelumnya">
            ‹
        </a>

        <div class="period-center">
            <small>Periode laporan</small>
            <strong><?= e($labelPeriode) ?></strong>
        </div>

        <a class="today"
           href="?mode=<?= e($mode) ?>&tanggal=<?= date('Y-m-d') ?>">
            Hari Ini
        </a>

        <a class="date-btn"
           href="?mode=<?= e($mode) ?>&tanggal=<?= e($next->format('Y-m-d')) ?>"
           aria-label="Periode berikutnya">
            ›
        </a>

    </div>

    <!-- KARTU RINGKASAN -->
    <section class="cards">

        <div class="card">
            <div class="card-label">Total Penjualan Gabungan</div>
            <div class="card-value"><?= rupiah($totalPenjualan) ?></div>
            <div class="card-note">
                <?= number_format($jumlahTransaksi) ?>
                transaksi (langsung + online selesai)
            </div>
        </div>

        <div class="card expense">
            <div class="card-label">Total Pengeluaran</div>
            <div class="card-value"><?= rupiah($totalPengeluaran) ?></div>
            <div class="card-note">
                <?= number_format(count($pengeluaran)) ?>
                catatan pengeluaran
            </div>
        </div>

        <div class="card net">
            <div class="card-label">Total Bersih</div>
            <div class="card-value"><?= rupiah($totalBersih) ?></div>
            <div class="card-note">
                Penjualan dikurangi pengeluaran
            </div>
        </div>

        <div class="card">
            <div class="card-label">Rata-rata Penjualan</div>
            <div class="card-value"><?= rupiah($rataRata) ?></div>
            <div class="card-note">
                Rata-rata per transaksi
            </div>
        </div>

    </section>

    <!-- REKAP HARIAN -->
    <section class="panel">

        <div class="panel-head">
            <h2>Rekap Harian</h2>
            <span><?= e($labelPeriode) ?></span>
        </div>

        <div class="table-wrap">

            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th class="money">Total Penjualan</th>
                        <th class="money">Pengeluaran</th>
                        <th class="money">Total Bersih</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($rekap as $tgl => $r):
                    $bersihHari = $r['penjualan'] - $r['pengeluaran'];
                ?>

                    <tr>
                        <td><?= e(date('d/m/Y', strtotime($tgl))) ?></td>

                        <td class="money">
                            <?= rupiah($r['penjualan']) ?>
                        </td>

                        <td class="money">
                            <?= rupiah($r['pengeluaran']) ?>
                        </td>

                        <td class="money <?= $bersihHari < 0 ? 'negative' : 'positive' ?>">
                            <?= rupiah($bersihHari) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>

                <tfoot>
                    <tr>
                        <th>TOTAL PERIODE</th>
                        <th class="money"><?= rupiah($totalPenjualan) ?></th>
                        <th class="money"><?= rupiah($totalPengeluaran) ?></th>
                        <th class="money"><?= rupiah($totalBersih) ?></th>
                    </tr>
                </tfoot>

            </table>

        </div>

    </section>

    <!-- DETAIL PENJUALAN -->
    <section class="panel">

        <div class="panel-head">
            <h2>Detail Penjualan</h2>
            <span>Penjualan langsung dan pesanan online selesai</span>
        </div>

        <div class="table-wrap">

            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Sumber</th>
                        <th>Kode / ID</th>
                        <th>Kasir / Pelanggan</th>
                        <th class="money">Total</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($langsung as $r): ?>

                    <tr>
                        <td>
                            <?= e(date('d/m/Y H:i', strtotime($r['waktu_penjualan']))) ?>
                        </td>

                        <td>Penjualan langsung</td>

                        <td><?= e($r['kode_penjualan']) ?></td>

                        <td><?= e($r['nama_kasir'] ?? '-') ?></td>

                        <td class="money">
                            <?= rupiah($r['total_penjualan']) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php foreach ($online as $r): ?>

                    <tr>
                        <td>
                            <?= e(date('d/m/Y H:i', strtotime($r['tanggal_pesanan']))) ?>
                        </td>

                        <td>Pesanan online</td>

                        <td>#<?= e($r['id_pesanan']) ?></td>

                        <td><?= e($r['nama_pelanggan'] ?? '-') ?></td>

                        <td class="money">
                            <?= rupiah($r['total_harga']) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (!$langsung && !$online): ?>

                    <tr>
                        <td colspan="5" class="empty">
                            Belum ada penjualan pada periode ini.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </section>

    <!-- DETAIL PENGELUARAN -->
    <section class="panel">

        <div class="panel-head">
            <h2>Detail Pengeluaran</h2>
            <span>
                <?= number_format(count($pengeluaran)) ?> catatan
            </span>
        </div>

        <div class="table-wrap">

            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th class="money">Jumlah</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($pengeluaran as $r): ?>

                    <tr>
                        <td>
                            <?= e(date('d/m/Y H:i', strtotime($r['tanggal_pengeluaran']))) ?>
                        </td>

                        <td><?= e($r['keterangan']) ?></td>

                        <td class="money">
                            <?= rupiah($r['jumlah']) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (!$pengeluaran): ?>

                    <tr>
                        <td colspan="3" class="empty">
                            Belum ada pengeluaran pada periode ini.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </section>

</main>

<script>
function openMenu() {
    const drawer = document.getElementById('drawer');
    const overlay = document.getElementById('menuOverlay');

    drawer.classList.add('show');
    overlay.classList.add('show');
    document.body.classList.add('menu-open');
}

function closeMenu() {
    const drawer = document.getElementById('drawer');
    const overlay = document.getElementById('menuOverlay');

    drawer.classList.remove('show');
    overlay.classList.remove('show');
    document.body.classList.remove('menu-open');
}

function toggleMenu() {
    const drawer = document.getElementById('drawer');

    if (drawer.classList.contains('show')) {
        closeMenu();
    } else {
        openMenu();
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeMenu();
    }
});
</script>

</body>
</html>
