<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['id_user']) || ($_SESSION['role'] ?? '') !== 'kasir') {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];

$nama = $_SESSION['nama'] ?? 'Kasir';
$email = $_SESSION['email'] ?? '';

$pesan = '';
$error = '';

/* =========================
   AMBIL DATA USER
========================= */
$stmt = mysqli_prepare(
    $conn,
    "SELECT id_user, nama, email, foto_profil, role
     FROM users
     WHERE id_user = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$nama = $user['nama'] ?? 'Kasir';
$email = $user['email'] ?? '';
$foto_profil = $user['foto_profil'] ?? '';

/* =========================
   UPDATE PROFIL
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama_baru = trim($_POST['nama'] ?? '');
    $email_baru = trim($_POST['email'] ?? '');

    if ($nama_baru === '') {
        $error = "Nama tidak boleh kosong.";
    } elseif ($email_baru === '') {
        $error = "Email tidak boleh kosong.";
    } elseif (!filter_var($email_baru, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else {

        /* Cek email milik user lain */
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_user
             FROM users
             WHERE email = ?
             AND id_user != ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "si", $email_baru, $id_user);
        mysqli_stmt_execute($stmt);

        $cek_email = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek_email) > 0) {
            $error = "Email sudah digunakan oleh pengguna lain.";
        }

        mysqli_stmt_close($stmt);
    }

    /* =========================
       UPLOAD FOTO
    ========================= */
    $nama_foto_baru = $foto_profil;

    if ($error === '' && isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['foto_profil']['error'] !== UPLOAD_ERR_OK) {
            $error = "Foto gagal diupload.";
        } else {

            $tmp_file = $_FILES['foto_profil']['tmp_name'];
            $nama_file_asli = $_FILES['foto_profil']['name'];
            $ukuran_file = $_FILES['foto_profil']['size'];

            $ext = strtolower(pathinfo($nama_file_asli, PATHINFO_EXTENSION));

            $ext_diperbolehkan = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $ext_diperbolehkan, true)) {
                $error = "Format foto harus JPG, JPEG, PNG, atau WEBP.";
            } elseif ($ukuran_file > 2 * 1024 * 1024) {
                $error = "Ukuran foto maksimal 2 MB.";
            } else {

                $folder_upload = "../uploads/profil/";

                if (!is_dir($folder_upload)) {
                    mkdir($folder_upload, 0777, true);
                }

                $nama_foto_baru = 'profil_' . $id_user . '_' . time() . '.' . $ext;

                $tujuan = $folder_upload . $nama_foto_baru;

                if (!move_uploaded_file($tmp_file, $tujuan)) {
                    $error = "Foto gagal disimpan.";
                } else {

                    /* Hapus foto lama jika ada */
                    if (
                        !empty($foto_profil) &&
                        file_exists($folder_upload . $foto_profil)
                    ) {
                        @unlink($folder_upload . $foto_profil);
                    }
                }
            }
        }
    }

    /* =========================
       SIMPAN DATA
    ========================= */
    if ($error === '') {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET nama = ?, email = ?, foto_profil = ?
             WHERE id_user = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "sssi",
            $nama_baru,
            $email_baru,
            $nama_foto_baru,
            $id_user
        );

        if (mysqli_stmt_execute($stmt)) {

            $_SESSION['nama'] = $nama_baru;
            $_SESSION['email'] = $email_baru;

            $nama = $nama_baru;
            $email = $email_baru;
            $foto_profil = $nama_foto_baru;

            $pesan = "Profil berhasil diperbarui.";
        } else {

            $error = "Profil gagal diperbarui.";
        }

        mysqli_stmt_close($stmt);
    }
}

$initial = strtoupper(substr($nama, 0, 1));

$halaman_aktif = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Profil - Toko Malaya</title>

<style>
* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #172033;
}

body.sidebar-open {
    overflow: hidden;
}

/* =====================================================
   SIDEBAR OVERLAY
===================================================== */

.sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .42);
    z-index: 1200;

    opacity: 0;
    visibility: hidden;
    pointer-events: none;

    transition: .2s ease;
}

.sidebar-overlay.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

/* =====================================================
   SIDEBAR
===================================================== */

.kasir-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;

    width: 307px;

    background: #ffffff;

    border-right: 1px solid #e5eaf2;

    box-shadow: 8px 0 25px rgba(15, 23, 42, .08);

    z-index: 1300;

    transform: translateX(-105%);
    transition: transform .25s ease;

    display: flex;
    flex-direction: column;
}

.kasir-sidebar.show {
    transform: translateX(0);
}

/* =====================================================
   SIDEBAR BRAND
   Tidak menggunakan height besar agar tidak ada space
===================================================== */

.sidebar-brand {
    min-height: 82px;

    padding: 18px 22px;

    display: flex;
    align-items: center;

    gap: 12px;

    border-bottom: 1px solid #eef1f5;

    flex-shrink: 0;
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
}

.sidebar-brand-text strong {
    font-size: 20px;
    color: #1f2937;
}

.sidebar-brand-text span {
    font-size: 13px;
    color: #7b8798;
}

/* =====================================================
   SIDEBAR USER
===================================================== */

.sidebar-user {
    margin: 18px 18px 0;

    padding: 14px;

    display: flex;
    align-items: center;

    gap: 12px;

    border: 1px solid #e5ebf3;

    background: #f7faff;

    border-radius: 12px;

    flex-shrink: 0;
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

/* =====================================================
   MENU
===================================================== */

.sidebar-menu {
    padding: 16px 12px;

    overflow-y: auto;

    flex: 1;
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

    transition: .2s ease;
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

    border-radius: 0 3px 3px 0;

    background: #1677d2;
}

/* =====================================================
   SIDEBAR BOTTOM
===================================================== */

.sidebar-bottom {
    margin-top: auto;

    padding: 14px 12px;

    border-top: 1px solid #eef1f5;

    flex-shrink: 0;
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

/* =====================================================
   TOPBAR
===================================================== */

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

/* =====================================================
   TOPBAR BRAND
===================================================== */

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

/* =====================================================
   TOPBAR USER
===================================================== */

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
    color: #1f2937;
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

/* =====================================================
   CONTENT
===================================================== */

.content {
    max-width: 1200px;

    margin: 0 auto;

    padding: 34px 28px 50px;
}

/* =====================================================
   PAGE HEADER
===================================================== */

.page-header {
    display: flex;
    align-items: center;
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

/* =====================================================
   ALERT
===================================================== */

.alert {
    padding: 13px 16px;

    border-radius: 10px;

    margin-bottom: 18px;

    font-size: 14px;
}

.alert-success {
    background: #ecfdf3;
    border: 1px solid #bbf7d0;
    color: #15803d;
}

.alert-error {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    color: #dc2626;
}

/* =====================================================
   PROFILE GRID
===================================================== */

.profile-grid {
    display: grid;

    grid-template-columns: 320px 1fr;

    gap: 20px;
}

/* =====================================================
   PROFILE CARD
===================================================== */

.profile-card {
    background: #ffffff;

    border: 1px solid #e3e9f1;

    border-radius: 12px;

    box-shadow: 0 2px 8px rgba(15, 23, 42, .04);

    overflow: hidden;
}

.profile-card-header {
    padding: 18px 20px;

    border-bottom: 1px solid #edf1f5;
}

.profile-card-header h2 {
    margin: 0;

    font-size: 17px;

    color: #172033;
}

.profile-card-body {
    padding: 24px;
}

/* =====================================================
   FOTO PROFIL
===================================================== */

.profile-photo-section {
    text-align: center;
}

.profile-photo {
    width: 130px;
    height: 130px;

    margin: 5px auto 18px;

    border-radius: 50%;

    background: #eaf4ff;

    border: 4px solid #edf6ff;

    display: flex;
    align-items: center;
    justify-content: center;

    overflow: hidden;

    color: #1677d2;

    font-size: 42px;

    font-weight: 700;
}

.profile-photo img {
    width: 100%;
    height: 100%;

    object-fit: cover;
}

.profile-name {
    margin: 0 0 5px;

    font-size: 20px;

    font-weight: 700;

    color: #172033;
}

.profile-role {
    margin: 0;

    font-size: 13px;

    color: #7b8798;
}

.photo-upload {
    margin-top: 22px;
}

.photo-upload label {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 10px 15px;

    border-radius: 8px;

    background: #edf6ff;

    color: #1677d2;

    font-size: 13px;

    font-weight: 700;

    cursor: pointer;

    transition: .2s ease;
}

.photo-upload label:hover {
    background: #dfefff;
}

.photo-upload input {
    display: none;
}

.photo-note {
    margin: 10px 0 0;

    font-size: 11px;

    line-height: 1.5;

    color: #8a94a6;
}

/* =====================================================
   FORM
===================================================== */

.form-group {
    margin-bottom: 18px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-label {
    display: block;

    margin-bottom: 7px;

    font-size: 13px;

    font-weight: 700;

    color: #445166;
}

.form-input {
    width: 100%;

    height: 44px;

    padding: 0 13px;

    border: 1px solid #dce4ee;

    border-radius: 8px;

    background: #ffffff;

    color: #172033;

    font-size: 14px;

    outline: none;

    transition: .2s ease;
}

.form-input:focus {
    border-color: #1677d2;

    box-shadow: 0 0 0 3px rgba(22, 119, 210, .10);
}

.form-help {
    margin-top: 6px;

    font-size: 11px;

    color: #8a94a6;
}

/* =====================================================
   BUTTON
===================================================== */

.form-actions {
    display: flex;

    justify-content: flex-end;

    margin-top: 24px;

    padding-top: 18px;

    border-top: 1px solid #edf1f5;
}

.btn-save {
    border: 0;

    border-radius: 8px;

    background: #1677d2;

    color: #ffffff;

    padding: 11px 18px;

    font-size: 14px;

    font-weight: 700;

    cursor: pointer;

    transition: .2s ease;
}

.btn-save:hover {
    background: #1268b9;
}

/* =====================================================
   INFO
===================================================== */

.info-list {
    display: flex;
    flex-direction: column;

    gap: 14px;

    margin-top: 22px;

    text-align: left;
}

.info-item {
    padding: 12px;

    border-radius: 8px;

    background: #f8fbff;

    border: 1px solid #e8eef6;
}

.info-item-label {
    font-size: 11px;

    color: #8a94a6;

    margin-bottom: 4px;
}

.info-item-value {
    font-size: 13px;

    font-weight: 600;

    color: #445166;

    word-break: break-word;
}

/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 900px) {

    .profile-grid {
        grid-template-columns: 1fr;
    }

    .profile-photo-section {
        max-width: 500px;

        margin: 0 auto;
    }
}

@media (max-width: 800px) {

    .kasir-sidebar {
        width: 285px;
    }

    .content {
        padding: 24px 18px 40px;
    }

    .topbar {
        padding: 0 14px;
    }

    .topbar-user-info {
        display: none;
    }
}

@media (max-width: 480px) {

    .topbar-brand-text {
        display: none;
    }

    .page-header h1 {
        font-size: 23px;
    }

    .profile-card-body {
        padding: 18px;
    }

    .form-actions {
        justify-content: stretch;
    }

    .btn-save {
        width: 100%;
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

<aside class="kasir-sidebar" id="kasirSidebar">

    <!-- BRAND -->
    <div class="sidebar-brand">

        <div class="sidebar-logo">
            <img
                src="../assets/SembakoKu (1).png"
                alt="Logo Toko Malaya">
        </div>

        <div class="sidebar-brand-text">
            <strong>Toko Malaya</strong>
            <span>Panel Kasir</span>
        </div>

    </div>


    <!-- USER -->
    <div class="sidebar-user">

        <div class="sidebar-user-avatar">
            <?= htmlspecialchars($initial); ?>
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
            class="sidebar-link">

            🏠
            <span>Dashboard</span>

        </a>


        <a
            href="penjualan.php"
            class="sidebar-link">

            🧾
            <span>Penjualan</span>

        </a>


        <a
            href="riwayat_penjualan.php"
            class="sidebar-link">

            📋
            <span>Riwayat Transaksi</span>

        </a>


        <a
            href="pesanan.php"
            class="sidebar-link">

            🛒
            <span>Pesanan Online</span>

        </a>


        <a
            href="stok.php"
            class="sidebar-link">

            📦
            <span>Stok</span>

        </a>


        <a
            href="laporan.php"
            class="sidebar-link">

            📊
            <span>Laporan</span>

        </a>


        <div class="sidebar-menu-label sidebar-menu-label-second">
            AKUN
        </div>


        <a
            href="profil.php"
            class="sidebar-link active">

            👤
            <span>Profil</span>

        </a>

    </nav>


    <!-- LOGOUT -->
    <div class="sidebar-bottom">

        <a
            href="../auth/logout.php"
            class="sidebar-logout"
            onclick="return confirm('Apakah kamu yakin ingin keluar?');">

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
            aria-label="Buka menu">

            ☰

        </button>


        <a
            href="dashboard.php"
            class="topbar-brand">

            <div class="topbar-logo">

                <img
                    src="../assets/SembakoKu (1).png"
                    alt="Toko Malaya">

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

            <?= htmlspecialchars($initial); ?>

        </div>

    </div>

</header>


<!-- =====================================================
     CONTENT
===================================================== -->

<main class="content">

    <!-- HEADER -->
    <div class="page-header">

        <div>

            <h1>Profil</h1>

            <p>
                Kelola informasi profil akun kasir.
            </p>

        </div>


        <div class="page-icon">
            👤
        </div>

    </div>


    <!-- ALERT SUCCESS -->
    <?php if ($pesan !== ''): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars($pesan); ?>
        </div>

    <?php endif; ?>


    <!-- ALERT ERROR -->
    <?php if ($error !== ''): ?>

        <div class="alert alert-error">
            <?= htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <!-- PROFILE -->
    <div class="profile-grid">


        <!-- =================================================
             LEFT PROFILE CARD
        ================================================== -->

        <div class="profile-card">

            <div class="profile-card-header">

                <h2>Profil Kasir</h2>

            </div>


            <div class="profile-card-body profile-photo-section">


                <!-- FOTO -->
                <div class="profile-photo">

                    <?php if (!empty($foto_profil) && file_exists("../uploads/profil/" . $foto_profil)): ?>

                        <img
                            src="../uploads/profil/<?= htmlspecialchars($foto_profil); ?>"
                            alt="Foto Profil">

                    <?php else: ?>

                        <?= htmlspecialchars($initial); ?>

                    <?php endif; ?>

                </div>


                <h3 class="profile-name">
                    <?= htmlspecialchars($nama); ?>
                </h3>


                <p class="profile-role">
                    Kasir
                </p>


                <div class="info-list">

                    <div class="info-item">

                        <div class="info-item-label">
                            Nama
                        </div>

                        <div class="info-item-value">
                            <?= htmlspecialchars($nama); ?>
                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-item-label">
                            Email
                        </div>

                        <div class="info-item-value">
                            <?= htmlspecialchars($email); ?>
                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-item-label">
                            Role
                        </div>

                        <div class="info-item-value">
                            Kasir
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             RIGHT EDIT PROFILE
        ================================================== -->

        <div class="profile-card">

            <div class="profile-card-header">

                <h2>Edit Profil</h2>

            </div>


            <div class="profile-card-body">

                <form
                    method="POST"
                    enctype="multipart/form-data">


                    <!-- NAMA -->
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="nama">

                            Nama Lengkap

                        </label>


                        <input
                            type="text"
                            id="nama"
                            name="nama"
                            class="form-input"
                            value="<?= htmlspecialchars($nama); ?>"
                            required>

                    </div>


                    <!-- EMAIL -->
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="email">

                            Email

                        </label>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            value="<?= htmlspecialchars($email); ?>"
                            required>


                        <div class="form-help">
                            Gunakan alamat email yang masih aktif.
                        </div>

                    </div>


                    <!-- FOTO -->
                    <div class="form-group">

                        <label
                            class="form-label"
                            for="foto_profil">

                            Foto Profil

                        </label>


                        <input
                            type="file"
                            id="foto_profil"
                            name="foto_profil"
                            class="form-input"
                            accept=".jpg,.jpeg,.png,.webp">


                        <div class="form-help">
                            Format JPG, JPEG, PNG, atau WEBP. Maksimal 2 MB.
                        </div>

                    </div>


                    <!-- BUTTON -->
                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn-save">

                            Simpan Perubahan

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</main>


<script>

/* =====================================================
   SIDEBAR
===================================================== */

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


/* ESC untuk menutup sidebar */

document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {

        closeSidebar();

    }

});

</script>

</body>
</html>