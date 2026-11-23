
<?php
session_start();
require_once '../config/database.php';

/* =========================================================
   AUTENTIKASI
========================================================= */
if (!isset($_SESSION['id_user'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit('Koneksi database tidak ditemukan.');
}

mysqli_set_charset($conn, 'utf8mb4');

/* =========================================================
   HELPER
========================================================= */
function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value)
{
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function tanggalIndonesia($tanggal, $denganJam = false)
{
    $timestamp = strtotime($tanggal);
    if (!$timestamp) {
        return '-';
    }

    return date($denganJam ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
}

$nama_user = $_SESSION['nama'] ?? 'Admin';
$pesan = '';
$error = '';

/* =========================================================
   CSRF TOKEN
========================================================= */
if (empty($_SESSION['csrf_pengeluaran'])) {
    $_SESSION['csrf_pengeluaran'] = bin2hex(random_bytes(32));
}

/* =========================================================
   PROSES TAMBAH, EDIT, HAPUS
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_pengeluaran'], $token)
    ) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $aksi = $_POST['aksi'] ?? '';

        /* TAMBAH / EDIT */
        if ($aksi === 'simpan') {
            $id = (int)($_POST['id_pengeluaran'] ?? 0);
            $tanggal = trim($_POST['tanggal_pengeluaran'] ?? '');
            $keterangan = trim($_POST['keterangan'] ?? '');
            $jumlahInput = trim($_POST['jumlah'] ?? '');

            $tanggalObj = DateTime::createFromFormat('!Y-m-d\TH:i', $tanggal);
            $tanggalValid = $tanggalObj
                && $tanggalObj->format('Y-m-d\TH:i') === $tanggal;

            $jumlahNormal = str_replace(',', '.', $jumlahInput);

            if (
                !$tanggalValid ||
                $keterangan === '' ||
                mb_strlen($keterangan) > 255 ||
                !is_numeric($jumlahNormal) ||
                (float)$jumlahNormal <= 0 ||
                (float)$jumlahNormal > 999999999999999
            ) {
                $error = 'Mohon periksa tanggal, keterangan, dan jumlah pengeluaran.';
            } else {
                $tanggalSql = $tanggalObj->format('Y-m-d H:i:s');
                $jumlah = (float)$jumlahNormal;

                if ($id > 0) {
                    $stmt = $conn->prepare(
                        "UPDATE pengeluaran
                         SET tanggal_pengeluaran = ?,
                             keterangan = ?,
                             jumlah = ?
                         WHERE id_pengeluaran = ?"
                    );

                    if ($stmt) {
                        $stmt->bind_param(
                            'ssdi',
                            $tanggalSql,
                            $keterangan,
                            $jumlah,
                            $id
                        );
                    }
                } else {
                    $idUser = (int)$_SESSION['id_user'];

                    $stmt = $conn->prepare(
                        "INSERT INTO pengeluaran
                         (tanggal_pengeluaran, keterangan, jumlah, id_user)
                         VALUES (?, ?, ?, ?)"
                    );

                    if ($stmt) {
                        $stmt->bind_param(
                            'ssdi',
                            $tanggalSql,
                            $keterangan,
                            $jumlah,
                            $idUser
                        );
                    }
                }

                if (isset($stmt) && $stmt) {
                    if ($stmt->execute()) {
                        $stmt->close();

                        header('Location: pengeluaran.php?status=tersimpan');
                        exit;
                    }

                    $stmt->close();
                    $error = 'Pengeluaran gagal disimpan. Periksa tabel database.';
                } else {
                    $error = 'Tidak dapat menyiapkan perintah database.';
                }
            }
        }

        /* HAPUS */
        elseif ($aksi === 'hapus') {
            $id = (int)($_POST['id_pengeluaran'] ?? 0);

            if ($id <= 0) {
                $error = 'Data pengeluaran tidak valid.';
            } else {
                $stmt = $conn->prepare(
                    "DELETE FROM pengeluaran WHERE id_pengeluaran = ?"
                );

                if ($stmt) {
                    $stmt->bind_param('i', $id);

                    if ($stmt->execute()) {
                        $stmt->close();

                        header('Location: pengeluaran.php?status=terhapus');
                        exit;
                    }

                    $stmt->close();
                    $error = 'Pengeluaran gagal dihapus.';
                } else {
                    $error = 'Tidak dapat menyiapkan perintah database.';
                }
            }
        }
    }
}

if (($_GET['status'] ?? '') === 'tersimpan') {
    $pesan = 'Data pengeluaran berhasil disimpan.';
} elseif (($_GET['status'] ?? '') === 'terhapus') {
    $pesan = 'Data pengeluaran berhasil dihapus.';
}

/* =========================================================
   FILTER TANGGAL
========================================================= */
$hariIni = date('Y-m-d');

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? $hariIni;

$objDari = DateTime::createFromFormat('!Y-m-d', $dari);
$objSampai = DateTime::createFromFormat('!Y-m-d', $sampai);

if (
    !$objDari ||
    $objDari->format('Y-m-d') !== $dari ||
    !$objSampai ||
    $objSampai->format('Y-m-d') !== $sampai ||
    $dari > $sampai
) {
    $dari = date('Y-m-01');
    $sampai = $hariIni;
}

$awal = $dari . ' 00:00:00';
$akhir = date('Y-m-d', strtotime($sampai . ' +1 day')) . ' 00:00:00';

/* =========================================================
   TOTAL PENGELUARAN PERIODE
========================================================= */
$totalPengeluaran = 0;

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(jumlah), 0) AS total
     FROM pengeluaran
     WHERE tanggal_pengeluaran >= ?
       AND tanggal_pengeluaran < ?"
);

if ($stmt) {
    $stmt->bind_param('ss', $awal, $akhir);
    $stmt->execute();

    $hasilTotal = $stmt->get_result()->fetch_assoc();
    $totalPengeluaran = (float)($hasilTotal['total'] ?? 0);

    $stmt->close();
}

/* =========================================================
   JUMLAH CATATAN PERIODE
========================================================= */
$jumlahCatatan = 0;

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM pengeluaran
     WHERE tanggal_pengeluaran >= ?
       AND tanggal_pengeluaran < ?"
);

if ($stmt) {
    $stmt->bind_param('ss', $awal, $akhir);
    $stmt->execute();

    $jumlahCatatan = (int)$stmt->get_result()->fetch_assoc()['total'];

    $stmt->close();
}

/* =========================================================
   DATA PENGELUARAN
========================================================= */
$dataPengeluaran = [];

$stmt = $conn->prepare(
    "SELECT id_pengeluaran, tanggal_pengeluaran, keterangan, jumlah
     FROM pengeluaran
     WHERE tanggal_pengeluaran >= ?
       AND tanggal_pengeluaran < ?
     ORDER BY tanggal_pengeluaran DESC, id_pengeluaran DESC"
);

if ($stmt) {
    $stmt->bind_param('ss', $awal, $akhir);
    $stmt->execute();

    $hasil = $stmt->get_result();

    while ($row = $hasil->fetch_assoc()) {
        $dataPengeluaran[] = $row;
    }

    $stmt->close();
}

/* =========================================================
   DATA EDIT
========================================================= */
$editData = null;

if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];

    if ($idEdit > 0) {
        $stmt = $conn->prepare(
            "SELECT id_pengeluaran, tanggal_pengeluaran, keterangan, jumlah
             FROM pengeluaran
             WHERE id_pengeluaran = ?"
        );

        if ($stmt) {
            $stmt->bind_param('i', $idEdit);
            $stmt->execute();

            $editData = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

$tanggalForm = $editData
    ? date('Y-m-d\TH:i', strtotime($editData['tanggal_pengeluaran']))
    : date('Y-m-d\TH:i');

$keteranganForm = $editData['keterangan'] ?? '';
$jumlahForm = $editData['jumlah'] ?? '';

/* Pertahankan filter ketika kembali dari edit */
$queryFilter = http_build_query([
    'dari' => $dari,
    'sampai' => $sampai
]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengeluaran - Toko Malaya</title>

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

button, input {
    font: inherit;
}

a {
    color: inherit;
}

/* =========================================================
   TOPBAR — DASHBOARD STYLE
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
   DRAWER / SIDEBAR — DASHBOARD STYLE
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
    gap: 10px;
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
    flex-shrink: 0;
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
    flex-shrink: 0;
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
    min-width: 0;
}

.drawer-user-info strong {
    font-size: 15px;
    overflow-wrap: anywhere;
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
   KONTEN UTAMA
========================================================= */
.main {
    max-width: 1250px;
    margin: auto;
    padding: 30px 24px 55px;
}

.page-header {
    margin-bottom: 22px;
}

.page-header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.page-header h1 {
    font-size: 29px;
    color: #102a43;
    margin-bottom: 7px;
}

.page-header p {
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
}

.date-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #e5f1ff;
    color: #0879e8;
    border-radius: 10px;
    padding: 10px 13px;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

/* =========================================================
   KARTU RINGKASAN
========================================================= */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.summary-card {
    background: #fff;
    border: 1px solid #e3e9f1;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
    min-width: 0;
}

.summary-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.summary-label {
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
}

.summary-icon {
    width: 43px;
    height: 43px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #e5f1ff;
    font-size: 21px;
}

.summary-card.total .summary-icon {
    background: #fff0dc;
}

.summary-value {
    margin-top: 15px;
    font-size: clamp(20px, 2.5vw, 28px);
    font-weight: 800;
    color: #102a43;
    overflow-wrap: anywhere;
}

.summary-card.total .summary-value {
    color: #d97706;
}

.summary-note {
    color: #94a3b8;
    font-size: 12px;
    margin-top: 8px;
}

/* =========================================================
   PANEL
========================================================= */
.panel {
    background: #fff;
    border: 1px solid #e3e9f1;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
    min-width: 0;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    margin-bottom: 20px;
}

.panel-header h2 {
    color: #102a43;
    font-size: 18px;
}

.panel-header p {
    color: #64748b;
    font-size: 12px;
    margin-top: 5px;
    line-height: 1.5;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.form-group {
    min-width: 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #374151;
    font-size: 13px;
    font-weight: 700;
}

.form-group input {
    width: 100%;
    min-width: 0;
    height: 43px;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #fff;
    color: #172033;
    outline: none;
    font-size: 13px;
    transition: .2s;
}

.form-group input:focus {
    border-color: #1677d2;
    box-shadow: 0 0 0 3px rgba(22, 119, 210, .1);
}

.form-help {
    display: block;
    margin-top: 6px;
    color: #94a3b8;
    font-size: 11px;
    line-height: 1.5;
}

/* =========================================================
   TOMBOL
========================================================= */
.actions {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    min-height: 40px;
    padding: 10px 15px;
    border: 0;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}

.btn-primary {
    color: #fff;
    background: #1677d2;
}

.btn-primary:hover {
    background: #1265b3;
}

.btn-light {
    color: #475569;
    background: #f1f5f9;
}

.btn-light:hover {
    background: #e2e8f0;
}

.btn-warning {
    color: #92400e;
    background: #fef3c7;
}

.btn-warning:hover {
    background: #fde68a;
}

.btn-danger {
    color: #b91c1c;
    background: #fee2e2;
}

.btn-danger:hover {
    background: #fecaca;
}

.btn-small {
    min-height: 33px;
    padding: 7px 10px;
    font-size: 12px;
}

/* =========================================================
   FILTER
========================================================= */
.filter-form {
    display: flex;
    align-items: end;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filter-form .form-group {
    flex: 1;
    min-width: 155px;
    max-width: 230px;
}

/* =========================================================
   TABEL
========================================================= */
.table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
}

table {
    width: 100%;
    min-width: 720px;
    border-collapse: collapse;
}

thead th {
    padding: 14px 13px;
    text-align: left;
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
    color: #475569;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

tbody td {
    padding: 14px 13px;
    border-bottom: 1px solid #edf0f4;
    color: #334155;
    font-size: 13px;
    vertical-align: middle;
}

tbody tr:last-child td {
    border-bottom: none;
}

tbody tr:hover {
    background: #fafcff;
}

.cell-number {
    font-weight: 800;
    color: #102a43;
    white-space: nowrap;
}

.cell-description {
    min-width: 190px;
    max-width: 360px;
    overflow-wrap: anywhere;
}

.table-actions {
    display: flex;
    align-items: center;
    gap: 7px;
}

.table-actions form {
    display: inline;
}

.empty {
    text-align: center;
    padding: 38px 15px;
    color: #94a3b8;
    line-height: 1.7;
}

.empty-icon {
    display: block;
    font-size: 30px;
    margin-bottom: 8px;
}

/* =========================================================
   NOTIFIKASI
========================================================= */
.notice {
    padding: 13px 16px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 13px;
    line-height: 1.6;
}

.notice.success {
    color: #166534;
    background: #dcfce7;
    border: 1px solid #bbf7d0;
}

.notice.error {
    color: #991b1b;
    background: #fee2e2;
    border: 1px solid #fecaca;
}

/* =========================================================
   RESPONSIVE
========================================================= */
@media (max-width: 900px) {
    .form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
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

    .page-header-top {
        align-items: flex-start;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 17px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .filter-form .form-group {
        max-width: none;
        flex-basis: 100%;
    }
}

@media (max-width: 480px) {
    .drawer {
        width: min(320px, 88vw);
    }

    .page-header-top {
        flex-direction: column;
    }

    .date-badge {
        white-space: normal;
    }

    .panel-header {
        align-items: flex-start;
    }

    .summary-card {
        padding: 17px;
    }

    .summary-value {
        font-size: 22px;
    }

    .actions .btn {
        flex: 1;
    }
}
</style>
</head>

<body>

<!-- OVERLAY -->
<div
    class="menu-overlay"
    id="menuOverlay"
    onclick="closeMenu()"
    aria-hidden="true"
></div>

<!-- =========================================================
     DRAWER / SIDEBAR
========================================================= -->
<aside class="drawer" id="drawer" aria-label="Menu navigasi">

    <div class="drawer-header">
        <div class="drawer-brand">
            <div class="drawer-logo">
                <img src="../assets/SembakoKu (3).png" alt="Toko Malaya">
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
            aria-label="Tutup menu"
        >×</button>
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

        <a href="pengeluaran.php" class="menu-link active">
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
            id="menuButton"
            aria-label="Buka menu"
            aria-controls="drawer"
            aria-expanded="false"
        >☰</button>

        <a href="dashboard.php" class="brand">
            <div class="brand-logo">
                <img src="../assets/SembakoKu (3).png" alt="Toko Malaya">
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
     KONTEN
========================================================= -->
<main class="main">

    <section class="page-header">
        <div class="page-header-top">
            <div>
                <h1>Pengeluaran</h1>
                <p>Kelola dan catat pengeluaran operasional Toko Malaya.</p>
            </div>

            <div class="date-badge">
                📅 <?= e(tanggalIndonesia($hariIni)) ?>
            </div>
        </div>
    </section>

    <?php if ($pesan !== ''): ?>
        <div class="notice success">
            ✓ <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error">
            ⚠ <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- RINGKASAN -->
    <section class="summary-grid">

        <div class="summary-card total">
            <div class="summary-top">
                <div class="summary-label">Total Pengeluaran</div>
            </div>

            <div class="summary-value">
                <?= rupiah($totalPengeluaran) ?>
            </div>

            <div class="summary-note">
                Total pengeluaran sesuai periode yang dipilih
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-top">
                <div class="summary-label">Jumlah Catatan</div>
            </div>

            <div class="summary-value">
                <?= number_format($jumlahCatatan, 0, ',', '.') ?> catatan
            </div>

            <div class="summary-note">
                Jumlah transaksi pengeluaran pada periode terpilih
            </div>
        </div>

    </section>

    <!-- FORM TAMBAH / EDIT -->
    <section class="panel">

        <div class="panel-header">
            <div>
                <h2><?= $editData ? 'Edit Pengeluaran' : 'Tambah Pengeluaran' ?></h2>
                <p>
                    <?= $editData
                        ? 'Perbarui informasi pengeluaran yang dipilih.'
                        : 'Masukkan pengeluaran agar tercatat di laporan keuangan.' ?>
                </p>
            </div>
        </div>

        <form method="post" action="pengeluaran.php<?= $editData ? '?edit=' . (int)$editData['id_pengeluaran'] : '' ?>">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_pengeluaran']) ?>"
            >

            <input type="hidden" name="aksi" value="simpan">

            <input
                type="hidden"
                name="id_pengeluaran"
                value="<?= (int)($editData['id_pengeluaran'] ?? 0) ?>"
            >

            <div class="form-grid">

                <div class="form-group">
                    <label for="tanggal_pengeluaran">Tanggal dan Waktu</label>
                    <input
                        type="datetime-local"
                        id="tanggal_pengeluaran"
                        name="tanggal_pengeluaran"
                        value="<?= e($tanggalForm) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="keterangan">Keterangan Pengeluaran</label>
                    <input
                        type="text"
                        id="keterangan"
                        name="keterangan"
                        maxlength="255"
                        placeholder="Contoh: listrik, transportasi, sewa toko"
                        value="<?= e($keteranganForm) ?>"
                        required
                    >
                    <span class="form-help">Jelaskan biaya yang dikeluarkan.</span>
                </div>

                <div class="form-group">
                    <label for="jumlah">Jumlah Pengeluaran (Rp)</label>
                    <input
                        type="number"
                        id="jumlah"
                        name="jumlah"
                        min="0.01"
                        max="999999999999999"
                        step="0.01"
                        placeholder="Contoh: 150000"
                        value="<?= e($jumlahForm) ?>"
                        required
                    >
                    <span class="form-help">Masukkan nominal tanpa simbol Rp.</span>
                </div>

            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editData ? '💾 Simpan Perubahan' : '＋ Simpan Pengeluaran' ?>
                </button>

                <?php if ($editData): ?>
                    <a class="btn btn-light" href="pengeluaran.php?<?= e($queryFilter) ?>">
                        Batal Edit
                    </a>
                <?php endif; ?>
            </div>
        </form>

    </section>

    <!-- RIWAYAT -->
    <section class="panel">

        <div class="panel-header">
            <div>
                <h2>Riwayat Pengeluaran</h2>
                <p>Gunakan filter tanggal untuk melihat catatan pada periode tertentu.</p>
            </div>
        </div>

        <form method="get" action="pengeluaran.php" class="filter-form">

            <div class="form-group">
                <label for="dari">Dari Tanggal</label>
                <input
                    type="date"
                    id="dari"
                    name="dari"
                    value="<?= e($dari) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="sampai">Sampai Tanggal</label>
                <input
                    type="date"
                    id="sampai"
                    name="sampai"
                    value="<?= e($sampai) ?>"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary">
                Tampilkan
            </button>

            <a href="pengeluaran.php" class="btn btn-light">
                Reset
            </a>

        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px">No.</th>
                        <th style="width:160px">Tanggal</th>
                        <th>Keterangan</th>
                        <th style="width:180px">Jumlah</th>
                        <th style="width:155px">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (count($dataPengeluaran) > 0): ?>

                    <?php foreach ($dataPengeluaran as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>

                            <td>
                                <?= e(tanggalIndonesia($row['tanggal_pengeluaran'], true)) ?>
                            </td>

                            <td class="cell-description">
                                <?= e($row['keterangan']) ?>
                            </td>

                            <td class="cell-number">
                                <?= rupiah($row['jumlah']) ?>
                            </td>

                            <td>
                                <div class="table-actions">

                                    <a
                                        class="btn btn-warning btn-small"
                                        href="pengeluaran.php?edit=<?= (int)$row['id_pengeluaran'] ?>&amp;<?= e($queryFilter) ?>"
                                    >Edit</a>

                                    <form
                                        method="post"
                                        action="pengeluaran.php?<?= e($queryFilter) ?>"
                                        onsubmit="return confirm('Yakin ingin menghapus catatan pengeluaran ini?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION['csrf_pengeluaran']) ?>"
                                        >

                                        <input type="hidden" name="aksi" value="hapus">

                                        <input
                                            type="hidden"
                                            name="id_pengeluaran"
                                            value="<?= (int)$row['id_pengeluaran'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger btn-small"
                                        >Hapus</button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">
                            <div class="empty">
                                <strong>Belum ada pengeluaran</strong>
                                <p>Tidak ada catatan pada periode tanggal yang dipilih.</p>
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>
                </tbody>

                <?php if (count($dataPengeluaran) > 0): ?>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align:right;font-weight:800;padding:16px 13px;background:#f8fafc">
                                Total Periode
                            </td>
                            <td colspan="2" class="cell-number" style="background:#f8fafc;padding:16px 13px">
                                <?= rupiah($totalPengeluaran) ?>
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>

            </table>
        </div>

    </section>

</main>

<script>
function toggleMenu() {
    const drawer = document.getElementById('drawer');
    const overlay = document.getElementById('menuOverlay');
    const button = document.getElementById('menuButton');

    drawer.classList.toggle('show');
    overlay.classList.toggle('show');

    const isOpen = drawer.classList.contains('show');

    document.body.classList.toggle('menu-open', isOpen);
    overlay.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function closeMenu() {
    const drawer = document.getElementById('drawer');
    const overlay = document.getElementById('menuOverlay');
    const button = document.getElementById('menuButton');

    drawer.classList.remove('show');
    overlay.classList.remove('show');
    document.body.classList.remove('menu-open');

    overlay.setAttribute('aria-hidden', 'true');
    button.setAttribute('aria-expanded', 'false');
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeMenu();
    }
});
</script>

</body>
</html>
