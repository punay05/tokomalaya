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
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$nama_user = $_SESSION['nama'] ?? 'Admin';
$pesan = '';
$pesan_type = '';

/* =========================================================
   TAMBAH KATEGORI
========================================================= */
if (isset($_POST['tambah_kategori'])) {
    $nama_kategori = trim($_POST['nama_kategori'] ?? '');

    if ($nama_kategori === '') {
        $pesan = 'Nama kategori wajib diisi.';
        $pesan_type = 'error';
    } elseif (mb_strlen($nama_kategori) > 100) {
        $pesan = 'Nama kategori maksimal 100 karakter.';
        $pesan_type = 'error';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id_kategori FROM kategori WHERE nama_kategori = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $nama_kategori);
        mysqli_stmt_execute($stmt);
        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) > 0) {
            $pesan = 'Kategori tersebut sudah ada.';
            $pesan_type = 'error';
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO kategori (nama_kategori) VALUES (?)");
            mysqli_stmt_bind_param($stmt, "s", $nama_kategori);

            if (mysqli_stmt_execute($stmt)) {
                $pesan = 'Kategori berhasil ditambahkan.';
                $pesan_type = 'success';
            } else {
                $pesan = 'Kategori gagal ditambahkan.';
                $pesan_type = 'error';
            }
        }
        mysqli_stmt_close($stmt);
    }
}

/* =========================================================
   EDIT KATEGORI
========================================================= */
if (isset($_POST['edit_kategori'])) {
    $id_kategori = (int)($_POST['id_kategori'] ?? 0);
    $nama_kategori = trim($_POST['nama_kategori'] ?? '');

    if ($id_kategori <= 0 || $nama_kategori === '') {
        $pesan = 'Data kategori tidak lengkap.';
        $pesan_type = 'error';
    } elseif (mb_strlen($nama_kategori) > 100) {
        $pesan = 'Nama kategori maksimal 100 karakter.';
        $pesan_type = 'error';
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_kategori FROM kategori
             WHERE nama_kategori = ? AND id_kategori <> ?
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "si", $nama_kategori, $id_kategori);
        mysqli_stmt_execute($stmt);
        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) > 0) {
            $pesan = 'Nama kategori sudah digunakan.';
            $pesan_type = 'error';
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE kategori SET nama_kategori = ? WHERE id_kategori = ?"
            );
            mysqli_stmt_bind_param($stmt, "si", $nama_kategori, $id_kategori);

            if (mysqli_stmt_execute($stmt)) {
                $pesan = 'Kategori berhasil diperbarui.';
                $pesan_type = 'success';
            } else {
                $pesan = 'Kategori gagal diperbarui.';
                $pesan_type = 'error';
            }
        }
        mysqli_stmt_close($stmt);
    }
}

/* =========================================================
   HAPUS KATEGORI
========================================================= */
if (isset($_GET['hapus'])) {
    $id_kategori = (int)$_GET['hapus'];

    if ($id_kategori > 0) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total FROM barang WHERE id_kategori = ?"
        );
        mysqli_stmt_bind_param($stmt, "i", $id_kategori);
        mysqli_stmt_execute($stmt);
        $cek = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($cek);
        mysqli_stmt_close($stmt);

        $jumlah_produk = (int)($row['total'] ?? 0);

        if ($jumlah_produk > 0) {
            $pesan = "Kategori tidak dapat dihapus karena masih digunakan oleh {$jumlah_produk} produk.";
            $pesan_type = 'error';
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM kategori WHERE id_kategori = ?"
            );
            mysqli_stmt_bind_param($stmt, "i", $id_kategori);

            if (mysqli_stmt_execute($stmt)) {
                $pesan = 'Kategori berhasil dihapus.';
                $pesan_type = 'success';
            } else {
                $pesan = 'Kategori gagal dihapus.';
                $pesan_type = 'error';
            }
            mysqli_stmt_close($stmt);
        }
    }

    header(
        "Location: kategori.php?" . (isset($_GET['embed']) ? "embed=1&" : "") .
        "status=" . urlencode($pesan_type) .
        "&pesan=" . urlencode($pesan)
    );
    exit;
}

/* =========================================================
   PESAN REDIRECT
========================================================= */
if (isset($_GET['pesan'])) {
    $pesan = $_GET['pesan'];
    $pesan_type = $_GET['status'] ?? 'success';
}

/* =========================================================
   PENCARIAN
========================================================= */
$keyword = trim($_GET['q'] ?? '');

if ($keyword !== '') {
    $like = '%' . $keyword . '%';

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            k.id_kategori,
            k.nama_kategori,
            COUNT(b.id_barang) AS jumlah_produk
         FROM kategori k
         LEFT JOIN barang b ON b.id_kategori = k.id_kategori
         WHERE k.nama_kategori LIKE ?
         GROUP BY k.id_kategori, k.nama_kategori
         ORDER BY k.nama_kategori ASC"
    );
    mysqli_stmt_bind_param($stmt, "s", $like);
    mysqli_stmt_execute($stmt);
    $result_kategori = mysqli_stmt_get_result($stmt);
} else {
    $result_kategori = mysqli_query(
        $conn,
        "SELECT
            k.id_kategori,
            k.nama_kategori,
            COUNT(b.id_barang) AS jumlah_produk
         FROM kategori k
         LEFT JOIN barang b ON b.id_kategori = k.id_kategori
         GROUP BY k.id_kategori, k.nama_kategori
         ORDER BY k.nama_kategori ASC"
    );
}

/* =========================================================
   TOTAL
========================================================= */
$total_kategori = 0;
$q_total = mysqli_query($conn, "SELECT COUNT(*) AS total FROM kategori");
if ($q_total) {
    $total_kategori = (int)(mysqli_fetch_assoc($q_total)['total'] ?? 0);
}

$total_produk = 0;
$q_produk = mysqli_query($conn, "SELECT COUNT(*) AS total FROM barang");
if ($q_produk) {
    $total_produk = (int)(mysqli_fetch_assoc($q_produk)['total'] ?? 0);
}

$total_kategori_terisi = 0;
$q_terisi = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT id_kategori) AS total
     FROM barang
     WHERE id_kategori IS NOT NULL"
);
if ($q_terisi) {
    $total_kategori_terisi = (int)(mysqli_fetch_assoc($q_terisi)['total'] ?? 0);
}

$persen_kategori = $total_kategori > 0
    ? round(($total_kategori_terisi / $total_kategori) * 100)
    : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kategori - Toko Malaya</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #1e293b;
}

button,
input {
    font-family: inherit;
}

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
    color: #111827;
}

.brand-logo,
.drawer-logo {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #1677d2;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.brand-logo img,
.drawer-logo img {
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

.top-avatar,
.drawer-avatar {
    border-radius: 50%;
    background: #e0efff;
    color: #1265a7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
}

.top-avatar {
    width: 38px;
    height: 38px;
}

.top-user-info,
.drawer-user-info {
    display: flex;
    flex-direction: column;
}

.top-user-info strong {
    font-size: 14px;
}

.top-user-info span,
.drawer-user-info span {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}

.menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .45);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: .25s;
}

.menu-overlay.show {
    opacity: 1;
    visibility: visible;
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
    font-size: 17px;
}

.drawer-user-info strong {
    font-size: 15px;
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
    min-height: 45px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    margin-bottom: 4px;
    border-radius: 9px;
    color: #475569;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: .2s;
}

.menu-link:hover {
    background: #f1f5f9;
    color: #1677d2;
}

.menu-link.active {
    background: #e8f3ff;
    color: #1677d2;
    font-weight: 800;
    border-left: 3px solid #1677d2;
}

.menu-icon {
    width: 25px;
    min-width: 25px;
    text-align: center;
    font-size: 18px;
}

.drawer-footer {
    border-top: 1px solid #e5e7eb;
    padding: 14px;
}

.logout-link {
    min-height: 45px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    border-radius: 9px;
    color: #ef4444;
    font-size: 14px;
    font-weight: 700;
}

.logout-link:hover {
    background: #fef2f2;
}

.main {
    max-width: 1200px;
    margin: auto;
    padding: 30px 24px 50px;
}

.page-header {
    margin-bottom: 24px;
}

.page-header h1 {
    font-size: 28px;
    color: #102a43;
    margin-bottom: 7px;
}

.page-header p {
    color: #64748b;
    font-size: 14px;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 22px;
}

.stat-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
}

.stat-title {
    display: block;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 26px;
    font-weight: 800;
    color: #155fa0;
}

.stat-desc {
    margin-top: 5px;
    font-size: 12px;
    color: #64748b;
}

.content-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    padding: 22px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
}

.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 20px;
}

.search-form {
    display: flex;
    flex: 1;
    max-width: 420px;
    gap: 8px;
}

.search-form input {
    width: 100%;
    height: 42px;
    border: 1px solid #dbe4ee;
    border-radius: 9px;
    padding: 0 13px;
    outline: none;
    font-size: 13px;
}

.search-form input:focus {
    border-color: #1677d2;
    box-shadow: 0 0 0 3px rgba(22,119,210,.10);
}

.btn {
    min-height: 42px;
    border: none;
    border-radius: 9px;
    padding: 0 16px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
}

.btn-primary {
    background: #1677d2;
    color: #fff;
}

.btn-primary:hover {
    background: #1268ba;
}

.btn-light {
    background: #f1f5f9;
    color: #334155;
}

.btn-edit {
    background: #e8f3ff;
    color: #1677d2;
}

.btn-delete {
    background: #fef2f2;
    color: #dc2626;
}

.alert {
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 13px;
    font-weight: 600;
}

.alert.success {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.alert.error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead th {
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-align: left;
    padding: 13px 14px;
    border-bottom: 1px solid #e5e7eb;
}

tbody td {
    padding: 14px;
    border-bottom: 1px solid #eef2f7;
    font-size: 13px;
    color: #334155;
}

tbody tr:hover {
    background: #fbfdff;
}

.category-name {
    font-weight: 700;
    color: #1e293b;
}

.count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 28px;
    padding: 0 9px;
    background: #e8f3ff;
    color: #1677d2;
    border-radius: 8px;
    font-weight: 800;
}

.actions {
    display: flex;
    gap: 7px;
}

.action-btn {
    min-height: 34px;
    padding: 0 11px;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}

.empty {
    text-align: center;
    padding: 45px 20px;
    color: #94a3b8;
    font-size: 13px;
}

.modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .48);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 1200;
}

.modal.show {
    display: flex;
}

.modal-box {
    width: 100%;
    max-width: 440px;
    background: #fff;
    border-radius: 15px;
    padding: 24px;
    box-shadow: 0 15px 45px rgba(15,23,42,.20);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h2 {
    font-size: 19px;
}

.modal-close {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 8px;
    background: #f1f5f9;
    font-size: 22px;
    cursor: pointer;
}

.form-group {
    margin-bottom: 17px;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.form-group input {
    width: 100%;
    height: 44px;
    border: 1px solid #dbe4ee;
    border-radius: 9px;
    padding: 0 12px;
    outline: none;
    font-size: 14px;
}

.form-group input:focus {
    border-color: #1677d2;
    box-shadow: 0 0 0 3px rgba(22,119,210,.10);
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 9px;
    margin-top: 20px;
}

@media (max-width: 850px) {
    .stats {
        grid-template-columns: 1fr;
    }

    .toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .search-form {
        max-width: none;
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
        font-size: 24px;
    }
}

@media (max-width: 430px) {
    .drawer {
        width: 300px;
    }

    .content-card {
        padding: 15px;
    }

    .search-form {
        flex-direction: column;
    }

    .search-form .btn {
        width: 100%;
    }
}

.embedded-mode .topbar,.embedded-mode .drawer,.embedded-mode .menu-overlay{display:none!important}
.embedded-mode .main{max-width:none;margin:0;padding:8px 8px 24px}
.embedded-mode .page-header{display:none}
.embedded-mode .stats{grid-template-columns:repeat(3,minmax(0,1fr))}
.embedded-mode .category-frame{height:auto}
@media(max-width:700px){.embedded-mode .stats{grid-template-columns:1fr}}

</style>
</head>

<body class="<?= isset($_GET['embed']) ? 'embedded-mode' : '' ?>">

<div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>

<aside class="drawer" id="drawer">
    <div class="drawer-header">
        <div class="drawer-brand">
            <div class="drawer-logo">
                <img src="../assets/SembakoKu (1).png" alt="Toko Malaya">
            </div>
            <div class="drawer-brand-text">
                <strong>Toko Malaya</strong>
                <span>Panel Admin / Pemilik</span>
            </div>
        </div>

        <button type="button" class="drawer-close" onclick="closeMenu()">×</button>
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
            Produk
        </a>

        <a href="kategori.php" class="menu-link active">
            <span class="menu-icon">🏷️</span>
            Kategori
        </a>

        <a href="pengguna.php" class="menu-link">
            <span class="menu-icon">👥</span>
            Pengguna
        </a>

        <a href="pesanan.php" class="menu-link">
            <span class="menu-icon">🛒</span>
            Pesanan Online
        </a>

        <a href="stok.php" class="menu-link">
            <span class="menu-icon">📦</span>
            Stok
        </a>

        <a href="laporan.php" class="menu-link">
            <span class="menu-icon">📊</span>
            Laporan
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

<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="hamburger" onclick="toggleMenu()">☰</button>

        <a href="dashboard.php" class="brand">
            <div class="brand-logo">
                <img src="../assets/SembakoKu (1).png" alt="Toko Malaya">
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
            <span>Admin</span>
        </div>
    </div>
</header>

<main class="main">

    <div class="page-header">
        <h1>Manajemen Kategori</h1>
        <p>Kelola kategori produk Toko Malaya.</p>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= $pesan_type === 'error' ? 'error' : 'success' ?>">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat-card">
            <span class="stat-title">Total Kategori</span>
            <div class="stat-number"><?= number_format($total_kategori) ?></div>
            <div class="stat-desc">Kategori yang tersedia</div>
        </div>

        <div class="stat-card">
            <span class="stat-title">Total Produk</span>
            <div class="stat-number"><?= number_format($total_produk) ?></div>
            <div class="stat-desc">Produk yang terdaftar</div>
        </div>

        <div class="stat-card">
            <span class="stat-title">Kategori Terisi</span>
            <div class="stat-number"><?= number_format($total_kategori_terisi) ?></div>
            <div class="stat-desc"><?= $persen_kategori ?>% kategori memiliki produk</div>
        </div>
    </section>

    <section class="content-card">

        <div class="toolbar">
            <form method="GET" class="search-form">
                <input
                    type="text"
                    name="q"
                    value="<?= e($keyword) ?>"
                    placeholder="Cari kategori..."
                >
                <button type="submit" class="btn btn-light">Cari</button>

                <?php if ($keyword !== ''): ?>
                    <a
                        href="kategori.php"
                        class="btn btn-light"
                        style="display:flex;align-items:center;text-decoration:none;"
                    >
                        Reset
                    </a>
                <?php endif; ?>
            </form>

            <button type="button" class="btn btn-primary" onclick="openTambah()">
                + Tambah Kategori
            </button>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th width="70">No</th>
                        <th>Nama Kategori</th>
                        <th width="160">Jumlah Produk</th>
                        <th width="190">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                $no = 1;
                if ($result_kategori && mysqli_num_rows($result_kategori) > 0):
                    while ($row = mysqli_fetch_assoc($result_kategori)):
                ?>
                    <tr>
                        <td><?= $no++ ?></td>

                        <td>
                            <span class="category-name">
                                <?= e($row['nama_kategori']) ?>
                            </span>
                        </td>

                        <td>
                            <span class="count-badge">
                                <?= number_format((int)$row['jumlah_produk']) ?>
                            </span>
                        </td>

                        <td>
                            <div class="actions">
                                <button
                                    type="button"
                                    class="action-btn btn-edit"
                                    onclick='openEdit(
                                        <?= (int)$row["id_kategori"] ?>,
                                        <?= json_encode($row["nama_kategori"]) ?>
                                    )'
                                >
                                    Edit
                                </button>

                                <a
                                    href="kategori.php?hapus=<?= (int)$row['id_kategori'] ?>"
                                    class="action-btn btn-delete"
                                    style="display:flex;align-items:center;text-decoration:none;"
                                    onclick="return confirm('Yakin ingin menghapus kategori ini?');"
                                >
                                    Hapus
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php
                    endwhile;
                else:
                ?>
                    <tr>
                        <td colspan="4">
                            <div class="empty">
                                <?php if ($keyword !== ''): ?>
                                    Kategori yang dicari tidak ditemukan.
                                <?php else: ?>
                                    Belum ada kategori.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

</main>

<!-- MODAL TAMBAH -->
<div class="modal" id="modalTambah" onclick="closeModalOutside(event, 'modalTambah')">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Tambah Kategori</h2>
            <button type="button" class="modal-close" onclick="closeModal('modalTambah')">×</button>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="nama_kategori_tambah">Nama Kategori</label>
                <input
                    type="text"
                    id="nama_kategori_tambah"
                    name="nama_kategori"
                    maxlength="100"
                    placeholder="Contoh: Minuman"
                    required
                >
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    onclick="closeModal('modalTambah')"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    name="tambah_kategori"
                    class="btn btn-primary"
                >
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal" id="modalEdit" onclick="closeModalOutside(event, 'modalEdit')">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Edit Kategori</h2>
            <button type="button" class="modal-close" onclick="closeModal('modalEdit')">×</button>
        </div>

        <form method="POST">
            <input type="hidden" name="id_kategori" id="edit_id_kategori">

            <div class="form-group">
                <label for="edit_nama_kategori">Nama Kategori</label>
                <input
                    type="text"
                    id="edit_nama_kategori"
                    name="nama_kategori"
                    maxlength="100"
                    required
                >
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    onclick="closeModal('modalEdit')"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    name="edit_kategori"
                    class="btn btn-primary"
                >
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openMenu() {
    document.getElementById('drawer').classList.add('show');
    document.getElementById('menuOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeMenu() {
    document.getElementById('drawer').classList.remove('show');
    document.getElementById('menuOverlay').classList.remove('show');
    document.body.style.overflow = '';
}

function toggleMenu() {
    const drawer = document.getElementById('drawer');

    if (drawer.classList.contains('show')) {
        closeMenu();
    } else {
        openMenu();
    }
}

function openTambah() {
    document.getElementById('modalTambah').classList.add('show');

    setTimeout(function () {
        document.getElementById('nama_kategori_tambah').focus();
    }, 100);
}

function openEdit(id, nama) {
    document.getElementById('edit_id_kategori').value = id;
    document.getElementById('edit_nama_kategori').value = nama;
    document.getElementById('modalEdit').classList.add('show');

    setTimeout(function () {
        document.getElementById('edit_nama_kategori').focus();
    }, 100);
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function closeModalOutside(event, id) {
    if (event.target.id === id) {
        closeModal(id);
    }
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeMenu();
        closeModal('modalTambah');
        closeModal('modalEdit');
    }
});
</script>

</body>
</html>
