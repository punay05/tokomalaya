<?php

session_start();
include "../config/database.php";

/* =========================================================
   AUTH
========================================================= */
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

/* =========================================================
   HELPER
========================================================= */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value)
{
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function short_rupiah($value)
{
    $value = (float)$value;

    if ($value >= 1000000000) {
        return 'Rp ' . number_format($value / 1000000000, 1, ',', '.') . ' M';
    }

    if ($value >= 1000000) {
        return 'Rp ' . number_format($value / 1000000, 1, ',', '.') . ' Jt';
    }

    if ($value >= 1000) {
        return 'Rp ' . number_format($value / 1000, 0, ',', '.') . ' Rb';
    }

    return 'Rp ' . number_format($value, 0, ',', '.');
}

/* =========================================================
   USER
========================================================= */
$nama_user = $_SESSION['nama'] ?? 'Admin';

/* =========================================================
   DEFAULT
========================================================= */
$today = date('Y-m-d');

$total_produk = 0;
$total_stok = 0;
$total_kategori = 0;
$total_pengguna = 0;

$stok_habis = 0;
$stok_kritis = 0;

$omzet_hari_ini = 0;
$transaksi_hari_ini = 0;

$omzet_bulan_ini = 0;
$transaksi_bulan_ini = 0;

$pesanan_menunggu = 0;
$pesanan_diproses = 0;
$pesanan_dikirim = 0;
$pesanan_selesai = 0;
$pesanan_dibatalkan = 0;
$pesanan_aktif = 0;
$total_pesanan = 0;

$direct_today = 0;
$direct_month = 0;
$direct_today_count = 0;
$direct_month_count = 0;

$produkTerlaris = [];
$stokMenipis = [];
$pesananTerbaru = [];

$chart_direct = [];
$chart_online = [];
$chart_labels = [];

/* =========================================================
   MASTER DATA
========================================================= */

/* Produk */
$q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM barang");
if ($q) {
    $row = mysqli_fetch_assoc($q);
    $total_produk = (int)($row['total'] ?? 0);
}

/* Total stok */
$q = mysqli_query($conn, "SELECT COALESCE(SUM(stok), 0) AS total FROM barang");
if ($q) {
    $row = mysqli_fetch_assoc($q);
    $total_stok = (int)($row['total'] ?? 0);
}

/* Kategori */
$q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM kategori");
if ($q) {
    $row = mysqli_fetch_assoc($q);
    $total_kategori = (int)($row['total'] ?? 0);
}

/* Pengguna */
$q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
if ($q) {
    $row = mysqli_fetch_assoc($q);
    $total_pengguna = (int)($row['total'] ?? 0);
}

/* =========================================================
   STOK
========================================================= */

/* Habis */
$q = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM barang
     WHERE stok <= 0"
);

if ($q) {
    $row = mysqli_fetch_assoc($q);
    $stok_habis = (int)($row['total'] ?? 0);
}

/* Kritis = di atas 0 tetapi sudah <= stok minimum */
$q = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM barang
     WHERE stok > 0
       AND stok <= stok_minimum"
);

if ($q) {
    $row = mysqli_fetch_assoc($q);
    $stok_kritis = (int)($row['total'] ?? 0);
}

/* Produk stok menipis */
$q = mysqli_query(
    $conn,
    "SELECT
        id_barang,
        nama_barang,
        stok,
        stok_minimum,
        satuan
     FROM barang
     WHERE stok <= stok_minimum
     ORDER BY
        CASE WHEN stok <= 0 THEN 0 ELSE 1 END,
        stok ASC
     LIMIT 6"
);

if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $stokMenipis[] = $row;
    }
}

/* =========================================================
   PESANAN ONLINE
========================================================= */

/* Total semua pesanan */
$q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM pesanan");
if ($q) {
    $row = mysqli_fetch_assoc($q);
    $total_pesanan = (int)($row['total'] ?? 0);
}

/* Status */
$statusList = [
    'menunggu' => 0,
    'diproses' => 0,
    'dikirim' => 0,
    'selesai' => 0,
    'dibatalkan' => 0
];

$q = mysqli_query(
    $conn,
    "SELECT status, COUNT(*) AS total
     FROM pesanan
     GROUP BY status"
);

if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $status = $row['status'] ?? '';

        if (array_key_exists($status, $statusList)) {
            $statusList[$status] = (int)$row['total'];
        }
    }
}

$pesanan_menunggu = $statusList['menunggu'];
$pesanan_diproses = $statusList['diproses'];
$pesanan_dikirim = $statusList['dikirim'];
$pesanan_selesai = $statusList['selesai'];
$pesanan_dibatalkan = $statusList['dibatalkan'];

$pesanan_aktif =
    $pesanan_menunggu +
    $pesanan_diproses +
    $pesanan_dikirim;

/* Pesanan terbaru */
$q = mysqli_query(
    $conn,
    "SELECT
        p.id_pesanan,
        p.tanggal_pesanan,
        p.total_harga,
        p.status,
        u.nama AS nama_pelanggan
     FROM pesanan p
     LEFT JOIN users u
        ON u.id_user = p.id_user
     ORDER BY p.tanggal_pesanan DESC
     LIMIT 6"
);

if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $pesananTerbaru[] = $row;
    }
}

/* =========================================================
   OMZET ONLINE
   Hanya pesanan selesai yang dihitung sebagai omzet.
========================================================= */

$q = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS jumlah,
        COALESCE(SUM(total_harga), 0) AS omzet
     FROM pesanan
     WHERE status = 'selesai'
       AND DATE(tanggal_pesanan) = '$today'"
);

if ($q) {
    $row = mysqli_fetch_assoc($q);

    $online_today_count = (int)($row['jumlah'] ?? 0);
    $online_today = (float)($row['omzet'] ?? 0);
} else {
    $online_today_count = 0;
    $online_today = 0;
}

$q = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS jumlah,
        COALESCE(SUM(total_harga), 0) AS omzet
     FROM pesanan
     WHERE status = 'selesai'
       AND YEAR(tanggal_pesanan) = YEAR(CURDATE())
       AND MONTH(tanggal_pesanan) = MONTH(CURDATE())"
);

if ($q) {
    $row = mysqli_fetch_assoc($q);

    $online_month_count = (int)($row['jumlah'] ?? 0);
    $online_month = (float)($row['omzet'] ?? 0);
} else {
    $online_month_count = 0;
    $online_month = 0;
}

/* =========================================================
   PENJUALAN LANGSUNG
   Opsional: jika tabel penjualan tersedia.
========================================================= */

$ada_penjualan_langsung = false;

$cek_penjualan = mysqli_query(
    $conn,
    "SHOW TABLES LIKE 'penjualan'"
);

if ($cek_penjualan && mysqli_num_rows($cek_penjualan) > 0) {
    $ada_penjualan_langsung = true;

    $q = mysqli_query(
        $conn,
        "SELECT
            COUNT(*) AS jumlah,
            COALESCE(SUM(total_penjualan), 0) AS omzet
         FROM penjualan
         WHERE DATE(waktu_penjualan) = '$today'"
    );

    if ($q) {
        $row = mysqli_fetch_assoc($q);
        $direct_today_count = (int)($row['jumlah'] ?? 0);
        $direct_today = (float)($row['omzet'] ?? 0);
    }

    $q = mysqli_query(
        $conn,
        "SELECT
            COUNT(*) AS jumlah,
            COALESCE(SUM(total_penjualan), 0) AS omzet
         FROM penjualan
         WHERE YEAR(waktu_penjualan) = YEAR(CURDATE())
           AND MONTH(waktu_penjualan) = MONTH(CURDATE())"
    );

    if ($q) {
        $row = mysqli_fetch_assoc($q);
        $direct_month_count = (int)($row['jumlah'] ?? 0);
        $direct_month = (float)($row['omzet'] ?? 0);
    }
}

/* =========================================================
   KPI PENJUALAN
========================================================= */

$omzet_hari_ini =
    $direct_today +
    $online_today;

$transaksi_hari_ini =
    $direct_today_count +
    $online_today_count;

$omzet_bulan_ini =
    $direct_month +
    $online_month;

$transaksi_bulan_ini =
    $direct_month_count +
    $online_month_count;

$rata_transaksi_hari_ini =
    $transaksi_hari_ini > 0
        ? $omzet_hari_ini / $transaksi_hari_ini
        : 0;

/* =========================================================
   PRODUK TERLARIS
   Berdasarkan pesanan online yang selesai.
========================================================= */

$q = mysqli_query(
    $conn,
    "SELECT
        b.id_barang,
        b.nama_barang,
        COALESCE(SUM(dp.jumlah), 0) AS jumlah_terjual,
        COALESCE(SUM(dp.subtotal), 0) AS omzet
     FROM detail_pesanan dp
     INNER JOIN pesanan p
        ON p.id_pesanan = dp.id_pesanan
     INNER JOIN barang b
        ON b.id_barang = dp.id_barang
     WHERE p.status = 'selesai'
     GROUP BY
        b.id_barang,
        b.nama_barang
     ORDER BY
        jumlah_terjual DESC
     LIMIT 5"
);

if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $produkTerlaris[] = $row;
    }
}

/* =========================================================
   GRAFIK 7 HARI
   Dua sumber: penjualan langsung + online selesai.
========================================================= */

for ($i = 6; $i >= 0; $i--) {

    $tanggal_obj = new DateTime();
    $tanggal_obj->modify("-{$i} days");

    $tanggal_chart = $tanggal_obj->format('Y-m-d');

    $chart_labels[] = $tanggal_obj->format('d M');
    $chart_direct[] = 0;
    $chart_online[] = 0;

    if ($ada_penjualan_langsung) {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT COALESCE(SUM(total_penjualan), 0) AS total
             FROM penjualan
             WHERE DATE(waktu_penjualan) = ?"
        );

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $tanggal_chart
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);

            $chart_direct[count($chart_direct) - 1] =
                (float)($row['total'] ?? 0);

            mysqli_stmt_close($stmt);
        }
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT COALESCE(SUM(total_harga), 0) AS total
         FROM pesanan
         WHERE status = 'selesai'
           AND DATE(tanggal_pesanan) = ?"
    );

    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $tanggal_chart
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        $chart_online[count($chart_online) - 1] =
            (float)($row['total'] ?? 0);

        mysqli_stmt_close($stmt);
    }
}

/* =========================================================
   LABEL STATUS
========================================================= */

function status_label($status)
{
    switch ($status) {
        case 'menunggu':
            return 'Menunggu';

        case 'diproses':
            return 'Diproses';

        case 'dikirim':
            return 'Dikirim';

        case 'selesai':
            return 'Selesai';

        case 'dibatalkan':
            return 'Dibatalkan';

        default:
            return ucfirst($status);
    }
}

function status_class($status)
{
    switch ($status) {
        case 'menunggu':
            return 'waiting';

        case 'diproses':
            return 'processing';

        case 'dikirim':
            return 'shipping';

        case 'selesai':
            return 'done';

        case 'dibatalkan':
            return 'cancelled';

        default:
            return 'waiting';
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

<title>Dashboard Admin - Toko Malaya</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #172033;
}

body.menu-open {
    overflow: hidden;
}

a {
    color: inherit;
}

/* =========================================================
   TOPBAR
========================================================= */

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
    border: none;
    border-radius: 10px;
    background: #f1f5f9;
    color: #1e293b;
    font-size: 23px;
    cursor: pointer;
}

.hamburger:hover {
    background: #e2e8f0;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.brand-logo {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #1677d2;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.brand-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.brand-text strong {
    font-size: 17px;
    font-weight: 800;
    color: #111827;
}

.brand-text span {
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}

.top-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.top-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #e0efff;
    color: #1265a7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
}

.top-user-info {
    display: flex;
    flex-direction: column;
}

.top-user-info strong {
    font-size: 14px;
}

.top-user-info span {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}

/* =========================================================
   DRAWER
========================================================= */

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
    transition: transform .28s ease;
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
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
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
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.drawer-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.drawer-brand-text {
    display: flex;
    flex-direction: column;
}

.drawer-brand-text strong {
    font-size: 18px;
    font-weight: 800;
}

.drawer-brand-text span {
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}

.drawer-close {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 10px;
    background: #f1f5f9;
    font-size: 25px;
    cursor: pointer;
}

.drawer-user {
    margin: 20px;
    padding: 14px;
    border: 1px solid #dbe4ee;
    background: #f8fafc;
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
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    font-weight: 800;
}

.drawer-user-info {
    display: flex;
    flex-direction: column;
}

.drawer-user-info strong {
    font-size: 15px;
}

.drawer-user-info span {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
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
    font-weight: 800;
    color: #94a3b8;
    letter-spacing: .5px;
}

.menu-link {
    min-height: 48px;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 0 14px;
    margin-bottom: 5px;
    border-radius: 10px;
    color: #475569;
    text-decoration: none;
    font-size: 15px;
    font-weight: 700;
    transition: .2s;
}

.menu-link:hover {
    background: #f1f5f9;
    color: #0879e8;
}

.menu-link.active {
    background: #e5f1ff;
    color: #0879e8;
    font-weight: 800;
    position: relative;
}

.menu-link.active::before {
    content: "";
    position: absolute;
    left: 0;
    top: 8px;
    bottom: 8px;
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
    padding: 0 14px;
    display: flex;
    align-items: center;
    gap: 14px;
    text-decoration: none;
    border-radius: 10px;
    color: #ef4444;
    font-size: 15px;
    font-weight: 700;
}

.logout-link:hover {
    background: #fef2f2;
}

/* =========================================================
   MAIN
========================================================= */

.main {
    max-width: 1250px;
    margin: auto;
    padding: 30px 24px 55px;
}

.page-header {
    margin-bottom: 22px;
}

.page-header h1 {
    font-size: 29px;
    color: #102a43;
    margin-bottom: 7px;
}

.page-header p {
    color: #64748b;
    font-size: 14px;
}

/* =========================================================
   WELCOME / CONTROL HEADER
========================================================= */

.welcome {
    background: linear-gradient(135deg, #1667aa, #195b96);
    color: #fff;
    border-radius: 16px;
    padding: 24px 26px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.welcome h2 {
    font-size: 21px;
    margin-bottom: 7px;
}

.welcome p {
    color: #dbeafe;
    font-size: 13px;
    line-height: 1.6;
}

.today-box {
    min-width: 170px;
    padding: 13px 15px;
    border: 1px solid rgba(255,255,255,.25);
    background: rgba(255,255,255,.10);
    border-radius: 12px;
    text-align: right;
}

.today-box span {
    display: block;
    color: #dbeafe;
    font-size: 11px;
    margin-bottom: 4px;
}

.today-box strong {
    font-size: 14px;
}

/* =========================================================
   KPI
========================================================= */

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.kpi {
    background: #fff;
    border: 1px solid #e3e9f1;
    border-radius: 14px;
    padding: 18px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
    min-height: 145px;
}

.kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.kpi-title {
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.kpi-icon {
    width: 39px;
    height: 39px;
    border-radius: 10px;
    background: #eaf4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.kpi-value {
    margin-top: 15px;
    color: #155fa0;
    font-size: 23px;
    font-weight: 800;
}

.kpi-note {
    margin-top: 5px;
    color: #7b8798;
    font-size: 11px;
}

.kpi.warning .kpi-icon {
    background: #fff7df;
}

.kpi.warning .kpi-value {
    color: #b77900;
}

.kpi.danger .kpi-icon {
    background: #fff0f0;
}

.kpi.danger .kpi-value {
    color: #dc2626;
}

/* =========================================================
   ERP OPERASIONAL
========================================================= */

.section-title {
    margin: 24px 0 12px;
}

.section-title h2 {
    font-size: 18px;
    color: #1e293b;
}

.section-title p {
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}

.ops-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.ops-card {
    text-decoration: none;
    background: #fff;
    border: 1px solid #e3e9f1;
    border-radius: 12px;
    padding: 16px;
    transition: .2s;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .03);
}

.ops-card:hover {
    transform: translateY(-2px);
    border-color: #bcdcff;
}

.ops-label {
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.ops-number {
    margin-top: 9px;
    color: #1e293b;
    font-size: 24px;
    font-weight: 800;
}

.ops-card.waiting .ops-number {
    color: #b77900;
}

.ops-card.processing .ops-number {
    color: #1677d2;
}

.ops-card.shipping .ops-number {
    color: #7c3aed;
}

.ops-card.done .ops-number {
    color: #059669;
}

.ops-card.cancelled .ops-number {
    color: #dc2626;
}

/* =========================================================
   TWO COLUMN
========================================================= */

.two-col {
    display: grid;
    grid-template-columns: 1.55fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.card {
    background: #fff;
    border: 1px solid #e3e9f1;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
}

.card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 17px;
}

.card-header h2 {
    font-size: 17px;
    color: #1e293b;
}

.card-header p {
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
}

.card-link {
    color: #1677d2;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
}

.card-link:hover {
    text-decoration: underline;
}

/* =========================================================
   SALES CHART
========================================================= */

.chart-wrap {
    width: 100%;
    height: 295px;
    position: relative;
}

#salesChart {
    width: 100%;
    height: 100%;
}

.chart-legend {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-top: 7px;
    font-size: 11px;
    color: #64748b;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.legend-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
}

.legend-direct {
    background: #1677d2;
}

.legend-online {
    background: #7c3aed;
}

/* =========================================================
   TOP PRODUCTS
========================================================= */

.product-list {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.product-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 11px;
    background: #f8fafc;
    border-radius: 10px;
}

.product-left {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.product-rank {
    width: 30px;
    height: 30px;
    min-width: 30px;
    border-radius: 8px;
    background: #e8f3ff;
    color: #1677d2;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
}

.product-name {
    color: #334155;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-sales {
    text-align: right;
    white-space: nowrap;
}

.product-sales strong {
    display: block;
    font-size: 12px;
}

.product-sales span {
    display: block;
    margin-top: 2px;
    color: #64748b;
    font-size: 10px;
}

/* =========================================================
   LOWER GRID
========================================================= */

.lower-grid {
    display: grid;
    grid-template-columns: 1fr 1.35fr;
    gap: 20px;
    margin-bottom: 20px;
}

/* =========================================================
   STOCK MONITOR
========================================================= */

.stock-summary {
    display: flex;
    gap: 8px;
    margin-bottom: 14px;
}

.stock-chip {
    padding: 7px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 800;
}

.stock-chip.empty {
    background: #fff0f0;
    color: #dc2626;
}

.stock-chip.critical {
    background: #fff7df;
    color: #b77900;
}

.stock-list {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.stock-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 12px;
    border: 1px solid #edf1f5;
    border-radius: 10px;
}

.stock-name {
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

.stock-unit {
    color: #64748b;
    font-size: 10px;
    margin-top: 3px;
}

.stock-value {
    text-align: right;
}

.stock-number {
    font-size: 14px;
    font-weight: 800;
}

.stock-number.empty-stock {
    color: #dc2626;
}

.stock-number.critical-stock {
    color: #b77900;
}

.stock-min {
    color: #64748b;
    font-size: 10px;
    margin-top: 2px;
}

/* =========================================================
   LATEST ORDERS
========================================================= */

.order-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.order-table {
    width: 100%;
    min-width: 620px;
    border-collapse: collapse;
}

.order-table th {
    padding: 11px 10px;
    background: #f8fafc;
    color: #64748b;
    border-bottom: 1px solid #e5e7eb;
    font-size: 11px;
    text-align: left;
}

.order-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #eef2f7;
    color: #334155;
    font-size: 12px;
}

.order-table tr:last-child td {
    border-bottom: none;
}

.order-id {
    color: #1677d2;
    font-weight: 800;
}

.order-total {
    font-weight: 800;
    white-space: nowrap;
}

.status {
    display: inline-flex;
    align-items: center;
    padding: 5px 8px;
    border-radius: 7px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.status.waiting {
    background: #fff7df;
    color: #a16207;
}

.status.processing {
    background: #e8f3ff;
    color: #1677d2;
}

.status.shipping {
    background: #f1eaff;
    color: #7c3aed;
}

.status.done {
    background: #e9f9f2;
    color: #047857;
}

.status.cancelled {
    background: #fff0f0;
    color: #dc2626;
}

/* =========================================================
   BUSINESS SUMMARY
========================================================= */

.business-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.business-box {
    padding: 15px;
    background: #f8fafc;
    border-radius: 10px;
}

.business-box span {
    display: block;
    color: #64748b;
    font-size: 11px;
    margin-bottom: 7px;
}

.business-box strong {
    color: #155fa0;
    font-size: 18px;
}

/* =========================================================
   EMPTY
========================================================= */

.empty-state {
    padding: 28px 10px;
    text-align: center;
    color: #94a3b8;
    font-size: 12px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .ops-grid {
        grid-template-columns: repeat(3, 1fr);
    }

    .two-col,
    .lower-grid {
        grid-template-columns: 1fr;
    }

    .business-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 700px) {

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

    .page-header h1 {
        font-size: 25px;
    }

    .welcome {
        flex-direction: column;
        align-items: flex-start;
    }

    .today-box {
        width: 100%;
        text-align: left;
    }

    .kpi-grid {
        grid-template-columns: 1fr 1fr;
    }

    .ops-grid {
        grid-template-columns: 1fr 1fr;
    }

    .business-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {

    .drawer {
        width: 300px;
    }

    .kpi-grid,
    .ops-grid,
    .business-grid {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 16px;
    }

    .card-header {
        flex-direction: column;
    }
}

</style>

</head>

<body>

<!-- =========================================================
     OVERLAY
========================================================= -->

<div
    class="menu-overlay"
    id="menuOverlay"
    onclick="closeMenu()"
></div>

<!-- =========================================================
     DRAWER
========================================================= -->

<aside
    class="drawer"
    id="drawer"
>

    <div class="drawer-header">

        <div class="drawer-brand">

            <div class="drawer-logo">
                <img
                    src="../assets/SembakoKu (3).png"
                    alt="Toko Malaya"
                >
            </div>

            <div class="drawer-brand-text">
                <strong>Toko Malaya</strong>
                <span>Panel Admin / Pemilik</span>
            </div>

        </div>

        <button
            type="button"
            class="drawer-close"
            onclick="closeMenu()"
        >
            ×
        </button>

    </div>

    <div class="drawer-user">

        <div class="drawer-avatar">
            <?= e(strtoupper(substr($nama_user, 0, 1))) ?>
        </div>

        <div class="drawer-user-info">
            <strong><?= e($nama_user) ?></strong>
            <span>Admin / Pemilik</span>
        </div>

    </div>

    <nav class="drawer-menu">

        <div class="menu-label">MENU UTAMA</div>

        <a href="dashboard.php" class="menu-link active">
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

        <a href="laporan.php" class="menu-link">
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

        <a
            href="../auth/logout.php"
            class="logout-link"
            onclick="return confirm('Apakah kamu yakin ingin keluar?');"
        >
            <span class="menu-icon">🚪</span>
            Keluar
        </a>

    </div>

</aside>

<!-- =========================================================
     TOPBAR
========================================================= -->

<header class="topbar">

    <div class="topbar-left">

        <button
            type="button"
            class="hamburger"
            onclick="toggleMenu()"
            aria-label="Buka menu"
        >
            ☰
        </button>

        <a href="dashboard.php" class="brand">

            <div class="brand-logo">
                <img
                    src="../assets/SembakoKu (3).png"
                    alt="Toko Malaya"
                >
            </div>

            <div class="brand-text">
                <strong>Toko Malaya</strong>
                <span>Panel Admin</span>
            </div>

        </a>

    </div>

    <div class="top-user">

        <div class="top-avatar">
            <?= e(strtoupper(substr($nama_user, 0, 1))) ?>
        </div>

        <div class="top-user-info">
            <strong><?= e($nama_user) ?></strong>
            <span>Admin / Pemilik</span>
        </div>

    </div>

</header>

<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">

    <div class="page-header">

        <h1>Dashboard Admin</h1>

        <p>
            Pusat kendali operasional dan performa bisnis Toko Malaya.
        </p>

    </div>

    <!-- WELCOME -->

    <section class="welcome">

        <div>
            <h2>
                Selamat datang, <?= e($nama_user) ?> 👋
            </h2>

            <p>
                Pantau omzet, transaksi, pesanan online, dan kondisi stok
                dari satu halaman.
            </p>
        </div>

        <div class="today-box">

            <span>Hari ini</span>

            <strong>
                <?= e(date('d F Y')) ?>
            </strong>

        </div>

    </section>

    <!-- KPI UTAMA -->

    <section class="kpi-grid">

        <div class="kpi">

            <div class="kpi-top">

                <span class="kpi-title">
                    Omzet Hari Ini
                </span>

                <div class="kpi-icon">
                    💰
                </div>

            </div>

            <div class="kpi-value">
                <?= rupiah($omzet_hari_ini) ?>
            </div>

            <div class="kpi-note">
                <?= number_format($transaksi_hari_ini) ?>
                transaksi hari ini
            </div>

        </div>

        <div class="kpi">

            <div class="kpi-top">

                <span class="kpi-title">
                    Omzet Bulan Ini
                </span>

                <div class="kpi-icon">
                    📈
                </div>

            </div>

            <div class="kpi-value">
                <?= rupiah($omzet_bulan_ini) ?>
            </div>

            <div class="kpi-note">
                <?= number_format($transaksi_bulan_ini) ?>
                transaksi bulan berjalan
            </div>

        </div>

        <div class="kpi <?= $pesanan_aktif > 0 ? 'warning' : '' ?>">

            <div class="kpi-top">

                <span class="kpi-title">
                    Pesanan Aktif
                </span>

                <div class="kpi-icon">
                    🛒
                </div>

            </div>

            <div class="kpi-value">
                <?= number_format($pesanan_aktif) ?>
            </div>

            <div class="kpi-note">
                Menunggu, diproses, dan dikirim
            </div>

        </div>

        <div class="kpi <?= ($stok_habis + $stok_kritis) > 0 ? 'danger' : '' ?>">

            <div class="kpi-top">

                <span class="kpi-title">
                    Perhatian Stok
                </span>

                <div class="kpi-icon">
                    ⚠️
                </div>

            </div>

            <div class="kpi-value">
                <?= number_format($stok_habis + $stok_kritis) ?>
            </div>

            <div class="kpi-note">
                <?= number_format($stok_habis) ?> habis ·
                <?= number_format($stok_kritis) ?> kritis
            </div>

        </div>

    </section>

    <!-- OPERASIONAL PESANAN -->

    <div class="section-title">

        <h2>Kontrol Operasional</h2>

        <p>
            Posisi pesanan online berdasarkan alur kerja toko.
        </p>

    </div>

    <section class="ops-grid">

        <a
            href="pesanan.php?status=menunggu"
            class="ops-card waiting"
        >
            <div class="ops-label">Menunggu</div>
            <div class="ops-number">
                <?= number_format($pesanan_menunggu) ?>
            </div>
        </a>

        <a
            href="pesanan.php?status=diproses"
            class="ops-card processing"
        >
            <div class="ops-label">Diproses</div>
            <div class="ops-number">
                <?= number_format($pesanan_diproses) ?>
            </div>
        </a>

        <a
            href="pesanan.php?status=dikirim"
            class="ops-card shipping"
        >
            <div class="ops-label">Dikirim</div>
            <div class="ops-number">
                <?= number_format($pesanan_dikirim) ?>
            </div>
        </a>

        <a
            href="pesanan.php?status=selesai"
            class="ops-card done"
        >
            <div class="ops-label">Selesai</div>
            <div class="ops-number">
                <?= number_format($pesanan_selesai) ?>
            </div>
        </a>

        <a
            href="pesanan.php?status=dibatalkan"
            class="ops-card cancelled"
        >
            <div class="ops-label">Dibatalkan</div>
            <div class="ops-number">
                <?= number_format($pesanan_dibatalkan) ?>
            </div>
        </a>

    </section>

    <!-- GRAFIK + PRODUK TERLARIS -->

    <section class="two-col">

        <div class="card">

            <div class="card-header">

                <div>
                    <h2>Performa Penjualan</h2>

                    <p>
                        Omzet 7 hari terakhir berdasarkan sumber transaksi.
                    </p>
                </div>

                <a
                    href="laporan.php"
                    class="card-link"
                >
                    Lihat laporan →
                </a>

            </div>

            <div class="chart-wrap">

                <canvas id="salesChart"></canvas>

            </div>

            <div class="chart-legend">

                <div class="legend-item">
                    <span class="legend-dot legend-direct"></span>
                    Penjualan langsung
                </div>

                <div class="legend-item">
                    <span class="legend-dot legend-online"></span>
                    Pesanan online
                </div>

            </div>

        </div>

        <div class="card">

            <div class="card-header">

                <div>
                    <h2>Produk Terlaris</h2>

                    <p>
                        Produk dengan penjualan online selesai tertinggi.
                    </p>
                </div>

                <a
                    href="produk.php"
                    class="card-link"
                >
                    Produk →
                </a>

            </div>

            <div class="product-list">

                <?php if (count($produkTerlaris) > 0): ?>

                    <?php $rank = 1; ?>

                    <?php foreach ($produkTerlaris as $produk): ?>

                        <div class="product-row">

                            <div class="product-left">

                                <div class="product-rank">
                                    <?= $rank++ ?>
                                </div>

                                <div class="product-name">
                                    <?= e($produk['nama_barang']) ?>
                                </div>

                            </div>

                            <div class="product-sales">

                                <strong>
                                    <?= number_format((int)$produk['jumlah_terjual']) ?>
                                    terjual
                                </strong>

                                <span>
                                    <?= rupiah($produk['omzet']) ?>
                                </span>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="empty-state">
                        Belum ada data produk terjual.
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>

    <!-- STOK + PESANAN TERBARU -->

    <section class="lower-grid">

        <div class="card">

            <div class="card-header">

                <div>
                    <h2>Monitoring Stok</h2>

                    <p>
                        Produk yang sudah mencapai batas minimum.
                    </p>
                </div>

                <a
                    href="stok.php"
                    class="card-link"
                >
                    Kelola stok →
                </a>

            </div>

            <div class="stock-summary">

                <span class="stock-chip empty">
                    <?= number_format($stok_habis) ?> Habis
                </span>

                <span class="stock-chip critical">
                    <?= number_format($stok_kritis) ?> Kritis
                </span>

            </div>

            <div class="stock-list">

                <?php if (count($stokMenipis) > 0): ?>

                    <?php foreach ($stokMenipis as $stok): ?>

                        <?php
                        $stok_now = (int)$stok['stok'];
                        $is_habis = $stok_now <= 0;
                        ?>

                        <div class="stock-row">

                            <div>

                                <div class="stock-name">
                                    <?= e($stok['nama_barang']) ?>
                                </div>

                                <div class="stock-unit">
                                    Satuan:
                                    <?= e($stok['satuan']) ?>
                                </div>

                            </div>

                            <div class="stock-value">

                                <div
                                    class="stock-number <?= $is_habis ? 'empty-stock' : 'critical-stock' ?>"
                                >
                                    <?= number_format($stok_now) ?>
                                </div>

                                <div class="stock-min">
                                    Min:
                                    <?= number_format((int)$stok['stok_minimum']) ?>
                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="empty-state">
                        Semua stok masih aman.
                    </div>

                <?php endif; ?>

            </div>

        </div>

        <div class="card">

            <div class="card-header">

                <div>
                    <h2>Pesanan Terbaru</h2>

                    <p>
                        Aktivitas pesanan online terbaru.
                    </p>
                </div>

                <a
                    href="pesanan.php"
                    class="card-link"
                >
                    Kelola →
                </a>

            </div>

            <div class="order-table-wrap">

                <table class="order-table">

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Pelanggan</th>
                            <th>Waktu</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($pesananTerbaru) > 0): ?>

                        <?php foreach ($pesananTerbaru as $order): ?>

                            <tr>

                                <td class="order-id">
                                    #<?= (int)$order['id_pesanan'] ?>
                                </td>

                                <td>
                                    <?= e($order['nama_pelanggan'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= e(
                                        date(
                                            'd/m H:i',
                                            strtotime($order['tanggal_pesanan'])
                                        )
                                    ) ?>
                                </td>

                                <td class="order-total">
                                    <?= rupiah($order['total_harga']) ?>
                                </td>

                                <td>
                                    <span
                                        class="status <?= e(status_class($order['status'])) ?>"
                                    >
                                        <?= e(status_label($order['status'])) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td
                                colspan="5"
                                class="empty-state"
                            >
                                Belum ada pesanan.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </section>

    <!-- RINGKASAN BISNIS -->

    <section class="card">

        <div class="card-header">

            <div>
                <h2>Ringkasan Bisnis</h2>

                <p>
                    Angka utama untuk membantu pemilik melihat kondisi toko.
                </p>
            </div>

            <a
                href="laporan.php"
                class="card-link"
            >
                Buka laporan →
            </a>

        </div>

        <div class="business-grid">

            <div class="business-box">
                <span>Total Produk</span>
                <strong>
                    <?= number_format($total_produk) ?>
                </strong>
            </div>

            <div class="business-box">
                <span>Total Kategori</span>
                <strong>
                    <?= number_format($total_kategori) ?>
                </strong>
            </div>

            <div class="business-box">
                <span>Total Pengguna</span>
                <strong>
                    <?= number_format($total_pengguna) ?>
                </strong>
            </div>

            <div class="business-box">
                <span>Rata-rata Transaksi Hari Ini</span>
                <strong>
                    <?= rupiah($rata_transaksi_hari_ini) ?>
                </strong>
            </div>

        </div>

    </section>

</main>

<script>

/* =========================================================
   CHART DATA
========================================================= */

const chartLabels = <?= json_encode($chart_labels) ?>;
const chartDirect = <?= json_encode($chart_direct) ?>;
const chartOnline = <?= json_encode($chart_online) ?>;

/* =========================================================
   CHART
========================================================= */

const canvas = document.getElementById('salesChart');

if (canvas) {

    const ctx = canvas.getContext('2d');

    function formatShort(value) {

        value = Number(value) || 0;

        if (value >= 1000000000) {
            return 'Rp ' + (value / 1000000000).toFixed(1) + ' M';
        }

        if (value >= 1000000) {
            return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
        }

        if (value >= 1000) {
            return 'Rp ' + Math.round(value / 1000) + ' Rb';
        }

        return 'Rp ' + Math.round(value);
    }

    function drawLine(
        data,
        chartWidth,
        chartHeight,
        left,
        top,
        maxValue,
        width,
        height,
        stroke
    ) {

        if (!data.length) {
            return;
        }

        ctx.beginPath();

        data.forEach(function(value, index) {

            const x =
                left +
                (
                    chartWidth *
                    index /
                    Math.max(data.length - 1, 1)
                );

            const y =
                top +
                chartHeight -
                (
                    (Number(value) / maxValue) *
                    chartHeight
                );

            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }

        });

        ctx.strokeStyle = stroke;
        ctx.lineWidth = 2.5;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.stroke();

        data.forEach(function(value, index) {

            const x =
                left +
                (
                    chartWidth *
                    index /
                    Math.max(data.length - 1, 1)
                );

            const y =
                top +
                chartHeight -
                (
                    (Number(value) / maxValue) *
                    chartHeight
                );

            ctx.beginPath();
            ctx.arc(x, y, 3.5, 0, Math.PI * 2);

            ctx.fillStyle = stroke;
            ctx.fill();

            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.stroke();

        });
    }

    function drawChart() {

        const rect = canvas.getBoundingClientRect();

        const width = rect.width;
        const height = rect.height;

        const dpr = window.devicePixelRatio || 1;

        canvas.width = width * dpr;
        canvas.height = height * dpr;

        ctx.setTransform(
            dpr,
            0,
            0,
            dpr,
            0,
            0
        );

        ctx.clearRect(0, 0, width, height);

        const left = 58;
        const right = 20;
        const top = 20;
        const bottom = 38;

        const chartWidth =
            Math.max(width - left - right, 1);

        const chartHeight =
            Math.max(height - top - bottom, 1);

        const allValues =
            chartDirect.concat(chartOnline);

        const maxData =
            Math.max(
                ...(allValues.length ? allValues : [0]),
                1
            );

        const maxValue =
            maxData === 0
                ? 1
                : maxData;

        /* GRID */

        ctx.font = '10px Arial';
        ctx.textAlign = 'right';

        for (let i = 0; i <= 4; i++) {

            const y =
                top +
                chartHeight -
                (
                    chartHeight *
                    i /
                    4
                );

            ctx.beginPath();

            ctx.moveTo(left, y);
            ctx.lineTo(width - right, y);

            ctx.strokeStyle = '#e5e7eb';
            ctx.lineWidth = 1;
            ctx.stroke();

            const value =
                maxValue *
                i /
                4;

            ctx.fillStyle = '#64748b';

            ctx.fillText(
                formatShort(value),
                left - 8,
                y + 3
            );
        }

        /* LABEL */

        ctx.textAlign = 'center';
        ctx.fillStyle = '#64748b';
        ctx.font = '10px Arial';

        chartLabels.forEach(function(label, index) {

            const x =
                left +
                (
                    chartWidth *
                    index /
                    Math.max(chartLabels.length - 1, 1)
                );

            ctx.fillText(
                label,
                x,
                height - 13
            );
        });

        /* LINES */

        drawLine(
            chartDirect,
            chartWidth,
            chartHeight,
            left,
            top,
            maxValue,
            width,
            height,
            '#1677d2'
        );

        drawLine(
            chartOnline,
            chartWidth,
            chartHeight,
            left,
            top,
            maxValue,
            width,
            height,
            '#7c3aed'
        );
    }

    drawChart();

    window.addEventListener(
        'resize',
        drawChart
    );
}

/* =========================================================
   DRAWER
========================================================= */

function openMenu() {

    const drawer =
        document.getElementById('drawer');

    const overlay =
        document.getElementById('menuOverlay');

    drawer.classList.add('show');
    overlay.classList.add('show');

    document.body.classList.add('menu-open');
}

function closeMenu() {

    const drawer =
        document.getElementById('drawer');

    const overlay =
        document.getElementById('menuOverlay');

    drawer.classList.remove('show');
    overlay.classList.remove('show');

    document.body.classList.remove('menu-open');
}

function toggleMenu() {

    const drawer =
        document.getElementById('drawer');

    if (drawer.classList.contains('show')) {
        closeMenu();
    } else {
        openMenu();
    }
}

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {
            closeMenu();
        }

    }
);

</script>

</body>
</html>
