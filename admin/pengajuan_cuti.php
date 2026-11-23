
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

$id_admin = (int)$_SESSION['id_user'];
$nama_admin = $_SESSION['nama'] ?? 'Admin';

/* =========================================================
   HELPER
========================================================= */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tanggal_indo($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    return date('d-m-Y', strtotime($tanggal));
}

function jumlah_format($angka)
{
    return number_format((int)$angka, 0, ',', '.');
}

/* =========================================================
   CSRF TOKEN
========================================================= */
if (empty($_SESSION['csrf_cuti'])) {
    $_SESSION['csrf_cuti'] = bin2hex(random_bytes(32));
}

/* =========================================================
   NOTIFIKASI
========================================================= */
$pesan = '';
$jenis_pesan = '';

/* =========================================================
   PROSES PERSETUJUAN DAN PENOLAKAN
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_cuti'], $token)
    ) {
        $pesan = 'Permintaan tidak valid. Silakan muat ulang halaman.';
        $jenis_pesan = 'error';

    } else {

        $id_pengajuan = (int)($_POST['id_pengajuan'] ?? 0);
        $aksi = $_POST['aksi'] ?? '';
        $catatan_admin = trim($_POST['catatan_admin'] ?? '');

        if (
            $id_pengajuan <= 0 ||
            !in_array($aksi, ['setujui', 'tolak'], true)
        ) {
            $pesan = 'Data pengajuan tidak valid.';
            $jenis_pesan = 'error';

        } else {

            mysqli_begin_transaction($conn);

            try {

                /* Kunci data pengajuan */
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT *
                     FROM pengajuan_cuti_izin
                     WHERE id_pengajuan = ?
                     FOR UPDATE"
                );

                if (!$stmt) {
                    throw new Exception('Gagal membaca data pengajuan.');
                }

                mysqli_stmt_bind_param($stmt, "i", $id_pengajuan);
                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);
                $pengajuan = mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);

                if (!$pengajuan) {
                    throw new Exception('Pengajuan tidak ditemukan.');
                }

                if ($pengajuan['status'] !== 'Menunggu') {
                    throw new Exception(
                        'Pengajuan ini sudah diproses sebelumnya.'
                    );
                }

                $status_baru = ($aksi === 'setujui')
                    ? 'Disetujui'
                    : 'Ditolak';

                /* =========================================
                   JIKA CUTI DISETUJUI
                ========================================= */
                if (
                    $aksi === 'setujui' &&
                    $pengajuan['jenis_pengajuan'] === 'cuti'
                ) {

                    $id_user = (int)$pengajuan['id_user'];
                    $jumlah_hari = (int)$pengajuan['jumlah_hari'];

                    $tahun = (int)date(
                        'Y',
                        strtotime($pengajuan['tanggal_mulai'])
                    );

                    /* Pastikan jatah tahunan tersedia */
                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT IGNORE INTO jatah_cuti_tahunan
                            (id_user, tahun, total_jatah, cuti_terpakai)
                         VALUES (?, ?, 12, 0)"
                    );

                    if (!$stmt) {
                        throw new Exception('Gagal menyiapkan jatah cuti.');
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ii",
                        $id_user,
                        $tahun
                    );

                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    /* Ambil jatah cuti */
                    $stmt = mysqli_prepare(
                        $conn,
                        "SELECT total_jatah, cuti_terpakai
                         FROM jatah_cuti_tahunan
                         WHERE id_user = ?
                           AND tahun = ?
                         FOR UPDATE"
                    );

                    if (!$stmt) {
                        throw new Exception('Gagal membaca jatah cuti.');
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ii",
                        $id_user,
                        $tahun
                    );

                    mysqli_stmt_execute($stmt);

                    $result = mysqli_stmt_get_result($stmt);
                    $jatah = mysqli_fetch_assoc($result);

                    mysqli_stmt_close($stmt);

                    if (!$jatah) {
                        throw new Exception(
                            'Data jatah cuti tidak ditemukan.'
                        );
                    }

                    $sisa_cuti =
                        (int)$jatah['total_jatah'] -
                        (int)$jatah['cuti_terpakai'];

                    if ($jumlah_hari > $sisa_cuti) {
                        throw new Exception(
                            'Jatah cuti tidak mencukupi. Sisa cuti karyawan: ' .
                            $sisa_cuti . ' hari.'
                        );
                    }

                    /* Tambahkan cuti yang digunakan */
                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE jatah_cuti_tahunan
                         SET cuti_terpakai = cuti_terpakai + ?
                         WHERE id_user = ?
                           AND tahun = ?"
                    );

                    if (!$stmt) {
                        throw new Exception('Gagal memperbarui jatah cuti.');
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iii",
                        $jumlah_hari,
                        $id_user,
                        $tahun
                    );

                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }

                /* =========================================
                   UPDATE STATUS PENGAJUAN
                ========================================= */
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE pengajuan_cuti_izin
                     SET status = ?,
                         catatan_admin = ?,
                         disetujui_oleh = ?,
                         tanggal_keputusan = NOW()
                     WHERE id_pengajuan = ?
                       AND status = 'Menunggu'"
                );

                if (!$stmt) {
                    throw new Exception('Gagal menyiapkan keputusan admin.');
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssii",
                    $status_baru,
                    $catatan_admin,
                    $id_admin,
                    $id_pengajuan
                );

                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) !== 1) {
                    throw new Exception('Pengajuan gagal diperbarui.');
                }

                mysqli_stmt_close($stmt);

                mysqli_commit($conn);

                $pesan = ($aksi === 'setujui')
                    ? 'Pengajuan berhasil disetujui.'
                    : 'Pengajuan berhasil ditolak.';

                $jenis_pesan = 'success';

            } catch (Throwable $error) {

                mysqli_rollback($conn);

                $pesan = $error->getMessage();
                $jenis_pesan = 'error';
            }
        }
    }
}

/* =========================================================
   FILTER STATUS
========================================================= */
$filter = $_GET['status'] ?? 'Semua';

$status_valid = [
    'Semua',
    'Menunggu',
    'Disetujui',
    'Ditolak'
];

if (!in_array($filter, $status_valid, true)) {
    $filter = 'Semua';
}

/* =========================================================
   STATISTIK
========================================================= */
$total_menunggu = 0;
$total_disetujui = 0;
$total_ditolak = 0;

$q = mysqli_query(
    $conn,
    "SELECT status, COUNT(*) AS total
     FROM pengajuan_cuti_izin
     GROUP BY status"
);

if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {

        if ($row['status'] === 'Menunggu') {
            $total_menunggu = (int)$row['total'];

        } elseif ($row['status'] === 'Disetujui') {
            $total_disetujui = (int)$row['total'];

        } elseif ($row['status'] === 'Ditolak') {
            $total_ditolak = (int)$row['total'];
        }
    }
}

/* =========================================================
   DATA PENGAJUAN
========================================================= */
$pengajuan_list = [];

if ($filter === 'Semua') {

    $sql = "
        SELECT
            p.*,
            u.nama AS nama_karyawan,
            u.username,
            a.nama AS nama_admin
        FROM pengajuan_cuti_izin p
        INNER JOIN users u
            ON u.id_user = p.id_user
        LEFT JOIN users a
            ON a.id_user = p.disetujui_oleh
        ORDER BY
            CASE WHEN p.status = 'Menunggu' THEN 0 ELSE 1 END,
            p.tanggal_pengajuan DESC
    ";

    $q = mysqli_query($conn, $sql);

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            p.*,
            u.nama AS nama_karyawan,
            u.username,
            a.nama AS nama_admin
         FROM pengajuan_cuti_izin p
         INNER JOIN users u
            ON u.id_user = p.id_user
         LEFT JOIN users a
            ON a.id_user = p.disetujui_oleh
         WHERE p.status = ?
         ORDER BY p.tanggal_pengajuan DESC"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $filter);
        mysqli_stmt_execute($stmt);
        $q = mysqli_stmt_get_result($stmt);
    } else {
        $q = false;
    }
}

if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $pengajuan_list[] = $row;
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

<title>Pengajuan Cuti & Izin - Toko Malaya</title>

<style>

/* =========================================================
   GLOBAL
========================================================= */

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
    text-decoration: none;
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
   STATISTIK
========================================================= */

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.stat {
    background: #fff;
    border: 1px solid #e3e9f1;
    border-radius: 14px;
    padding: 19px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
}

.stat span {
    display: block;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
}

.stat strong {
    display: block;
    font-size: 27px;
    margin-top: 10px;
}

.stat.waiting strong {
    color: #b77900;
}

.stat.approved strong {
    color: #059669;
}

.stat.rejected strong {
    color: #dc2626;
}

/* =========================================================
   ALERT
========================================================= */

.alert {
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 18px;
    font-size: 13px;
}

.alert.success {
    background: #e9f9f2;
    color: #047857;
}

.alert.error {
    background: #fff0f0;
    color: #dc2626;
}

/* =========================================================
   PANEL
========================================================= */

.panel {
    background: #fff;
    border: 1px solid #e3e9f1;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 18px;
}

.panel-header h2 {
    font-size: 18px;
    color: #1e293b;
}

.panel-header p {
    color: #64748b;
    font-size: 12px;
    margin-top: 5px;
}

/* =========================================================
   FILTER
========================================================= */

.filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.filter {
    padding: 9px 14px;
    border: 1px solid #dbe3ed;
    border-radius: 8px;
    color: #475569;
    font-size: 12px;
    font-weight: bold;
    transition: .2s;
}

.filter:hover {
    background: #f1f5f9;
}

.filter.active {
    background: #e5f1ff;
    border-color: #1677d2;
    color: #1677d2;
}

/* =========================================================
   TABLE
========================================================= */

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

th {
    background: #f8fafc;
    color: #64748b;
    text-align: left;
    padding: 13px 11px;
    font-size: 11px;
    border-bottom: 1px solid #e5e7eb;
}

td {
    padding: 14px 11px;
    border-bottom: 1px solid #eef2f7;
    font-size: 12px;
    vertical-align: top;
    color: #334155;
}

tr:last-child td {
    border-bottom: none;
}

.employee-name {
    font-weight: bold;
    color: #1e293b;
}

.employee-username {
    color: #94a3b8;
    font-size: 11px;
    margin-top: 4px;
}

.type {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 7px;
    font-size: 10px;
    font-weight: bold;
}

.type.cuti {
    background: #e8f3ff;
    color: #1677d2;
}

.type.izin {
    background: #fff7df;
    color: #a16207;
}

.status {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 7px;
    font-size: 10px;
    font-weight: bold;
}

.status.menunggu {
    background: #fff7df;
    color: #a16207;
}

.status.disetujui {
    background: #e9f9f2;
    color: #047857;
}

.status.ditolak {
    background: #fff0f0;
    color: #dc2626;
}

.reason {
    max-width: 220px;
    line-height: 1.5;
    color: #475569;
    white-space: normal;
    overflow-wrap: anywhere;
}

/* =========================================================
   ACTION FORM
========================================================= */

.action-form {
    min-width: 190px;
}

.action-form textarea {
    width: 100%;
    min-height: 58px;
    resize: vertical;
    border: 1px solid #dbe3ed;
    border-radius: 7px;
    padding: 8px;
    font-family: Arial, sans-serif;
    font-size: 11px;
    outline: none;
    margin-bottom: 7px;
}

.action-form textarea:focus {
    border-color: #1677d2;
}

.action-buttons {
    display: flex;
    gap: 6px;
}

.btn {
    border: none;
    padding: 8px 10px;
    border-radius: 7px;
    color: white;
    font-size: 11px;
    font-weight: bold;
    cursor: pointer;
}

.btn-approve {
    background: #059669;
}

.btn-reject {
    background: #dc2626;
}

.btn:hover {
    opacity: .85;
}

.processed-info {
    color: #64748b;
    font-size: 11px;
    line-height: 1.6;
}

/* =========================================================
   EMPTY
========================================================= */

.empty {
    padding: 45px 15px;
    text-align: center;
    color: #94a3b8;
    font-size: 13px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {
    .stats {
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

    .stats {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 16px;
    }

    .panel-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {

    .drawer {
        width: 300px;
    }

    .panel {
        padding: 14px;
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

<aside class="drawer" id="drawer">

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
            <?= e(strtoupper(substr($nama_admin, 0, 1))) ?>
        </div>

        <div class="drawer-user-info">
            <strong><?= e($nama_admin) ?></strong>
            <span>Admin / Pemilik</span>
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

        <a href="pengajuan_cuti.php" class="menu-link active">
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
            <?= e(strtoupper(substr($nama_admin, 0, 1))) ?>
        </div>

        <div class="top-user-info">
            <strong><?= e($nama_admin) ?></strong>
            <span>Admin / Pemilik</span>
        </div>

    </div>

</header>

<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">

    <div class="page-header">

        <h1>Pengajuan Cuti & Izin</h1>

        <p>
            Kelola dan berikan keputusan atas pengajuan cuti
            dan izin karyawan Toko Malaya.
        </p>

    </div>

    <?php if ($pesan !== ''): ?>

        <div class="alert <?= e($jenis_pesan) ?>">
            <?= e($pesan) ?>
        </div>

    <?php endif; ?>

    <!-- STATISTIK -->

    <section class="stats">

        <div class="stat waiting">

            <span>Menunggu Persetujuan</span>

            <strong>
                <?= jumlah_format($total_menunggu) ?>
            </strong>

        </div>

        <div class="stat approved">

            <span>Disetujui</span>

            <strong>
                <?= jumlah_format($total_disetujui) ?>
            </strong>

        </div>

        <div class="stat rejected">

            <span>Ditolak</span>

            <strong>
                <?= jumlah_format($total_ditolak) ?>
            </strong>

        </div>

    </section>

    <!-- DAFTAR PENGAJUAN -->

    <section class="panel">

        <div class="panel-header">

            <div>
                <h2>Daftar Pengajuan Karyawan</h2>

                <p>
                    Periksa jenis pengajuan, tanggal, dan alasan
                    sebelum memberikan keputusan.
                </p>
            </div>

        </div>

        <!-- FILTER -->

        <div class="filters">

            <?php foreach ($status_valid as $status): ?>

                <a
                    href="?status=<?= urlencode($status) ?>"
                    class="filter <?= $filter === $status ? 'active' : '' ?>"
                >
                    <?= e($status) ?>
                </a>

            <?php endforeach; ?>

        </div>

        <!-- TABEL -->

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>Karyawan</th>
                        <th>Jenis</th>
                        <th>Tanggal</th>
                        <th>Jumlah</th>
                        <th>Alasan</th>
                        <th>Status</th>
                        <th>Keputusan Admin</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (count($pengajuan_list) > 0): ?>

                    <?php foreach ($pengajuan_list as $item): ?>

                        <tr>

                            <!-- KARYAWAN -->

                            <td>

                                <div class="employee-name">
                                    <?= e($item['nama_karyawan']) ?>
                                </div>

                                <div class="employee-username">
                                    @<?= e($item['username']) ?>
                                </div>

                            </td>

                            <!-- JENIS -->

                            <td>

                                <span class="type <?= e($item['jenis_pengajuan']) ?>">
                                    <?= strtoupper(e($item['jenis_pengajuan'])) ?>
                                </span>

                            </td>

                            <!-- TANGGAL -->

                            <td>

                                <?= tanggal_indo($item['tanggal_mulai']) ?>

                                <br>

                                s.d.

                                <br>

                                <?= tanggal_indo($item['tanggal_selesai']) ?>

                            </td>

                            <!-- JUMLAH -->

                            <td>

                                <strong>
                                    <?= jumlah_format($item['jumlah_hari']) ?>
                                    hari
                                </strong>

                            </td>

                            <!-- ALASAN -->

                            <td>

                                <div class="reason">
                                    <?= nl2br(e($item['alasan'])) ?>
                                </div>

                            </td>

                            <!-- STATUS -->

                            <td>

                                <span class="status <?= strtolower($item['status']) ?>">
                                    <?= e($item['status']) ?>
                                </span>

                            </td>

                            <!-- KEPUTUSAN ADMIN -->

                            <td>

                                <?php if ($item['status'] === 'Menunggu'): ?>

                                    <form
                                        method="POST"
                                        class="action-form"
                                        onsubmit="return confirm('Apakah kamu yakin dengan keputusan ini?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION['csrf_cuti']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id_pengajuan"
                                            value="<?= (int)$item['id_pengajuan'] ?>"
                                        >

                                        <textarea
                                            name="catatan_admin"
                                            placeholder="Catatan admin (opsional)"
                                        ></textarea>

                                        <div class="action-buttons">

                                            <button
                                                type="submit"
                                                name="aksi"
                                                value="setujui"
                                                class="btn btn-approve"
                                            >
                                                Setujui
                                            </button>

                                            <button
                                                type="submit"
                                                name="aksi"
                                                value="tolak"
                                                class="btn btn-reject"
                                            >
                                                Tolak
                                            </button>

                                        </div>

                                    </form>

                                <?php else: ?>

                                    <div class="processed-info">

                                        <strong>
                                            <?= e($item['nama_admin'] ?? 'Admin') ?>
                                        </strong>

                                        <br>

                                        <?= e($item['tanggal_keputusan'] ?? '-') ?>

                                        <?php if (!empty($item['catatan_admin'])): ?>

                                            <br>

                                            Catatan:
                                            <?= e($item['catatan_admin']) ?>

                                        <?php endif; ?>

                                    </div>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="7" class="empty">
                            Belum ada pengajuan pada kategori ini.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

<!-- =========================================================
     JAVASCRIPT DRAWER
========================================================= -->

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
