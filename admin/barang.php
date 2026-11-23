<?php

session_start();

require_once "../config/database.php";

// ======================================
// CEK LOGIN & ROLE
// ======================================

if (
    !isset($_SESSION['id_user']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

$error = "";
$success = "";


// ======================================
// PROSES TAMBAH BARANG
// ======================================

if (isset($_POST['tambah_barang'])) {

    $kode_barang  = trim($_POST['kode_barang'] ?? '');
    $nama_barang  = trim($_POST['nama_barang'] ?? '');
    $id_kategori  = (int) ($_POST['id_kategori'] ?? 0);
    $harga_beli   = (float) ($_POST['harga_beli'] ?? 0);
    $harga_jual   = (float) ($_POST['harga_jual'] ?? 0);
    $stok         = (int) ($_POST['stok'] ?? 0);
    $satuan       = trim($_POST['satuan'] ?? '');
    $stok_minimum = (int) ($_POST['stok_minimum'] ?? 0);

    $gambar = "";

    // ======================================
    // VALIDASI DASAR
    // ======================================

    if ($kode_barang === "") {
        $error = "Kode barang wajib diisi.";
    } elseif ($nama_barang === "") {
        $error = "Nama barang wajib diisi.";
    } elseif ($id_kategori <= 0) {
        $error = "Kategori wajib dipilih.";
    } elseif ($harga_beli < 0) {
        $error = "Harga beli tidak boleh kurang dari 0.";
    } elseif ($harga_jual < 0) {
        $error = "Harga jual tidak boleh kurang dari 0.";
    } elseif ($stok < 0) {
        $error = "Stok tidak boleh kurang dari 0.";
    } elseif ($satuan === "") {
        $error = "Satuan wajib diisi.";
    } elseif ($stok_minimum < 0) {
        $error = "Stok minimum tidak boleh kurang dari 0.";
    }


    // ======================================
    // CEK KODE BARANG
    // ======================================

    if ($error === "") {

        $stmtCek = mysqli_prepare(
            $conn,
            "SELECT id_barang
             FROM barang
             WHERE kode_barang = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmtCek,
            "s",
            $kode_barang
        );

        mysqli_stmt_execute($stmtCek);

        $resultCek = mysqli_stmt_get_result($stmtCek);

        if (mysqli_num_rows($resultCek) > 0) {
            $error = "Kode barang sudah digunakan.";
        }

        mysqli_stmt_close($stmtCek);
    }


    // ======================================
    // UPLOAD GAMBAR
    // ======================================

    if (
        $error === "" &&
        isset($_FILES['gambar']) &&
        $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {

            $error = "Gagal membaca file gambar.";

        } else {

            $namaFile = $_FILES['gambar']['name'];
            $tmpFile  = $_FILES['gambar']['tmp_name'];
            $ukuran   = $_FILES['gambar']['size'];

            $ext = strtolower(
                pathinfo($namaFile, PATHINFO_EXTENSION)
            );

            $allowed = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];

            if (!in_array($ext, $allowed, true)) {

                $error =
                    "Format gambar harus JPG, JPEG, PNG, atau WEBP.";

            } elseif ($ukuran > 2 * 1024 * 1024) {

                $error =
                    "Ukuran gambar maksimal 2 MB.";

            } else {

                $folderUpload = "../uploads/";

                if (!is_dir($folderUpload)) {
                    mkdir($folderUpload, 0777, true);
                }

                $gambar =
                    "barang_" .
                    time() .
                    "_" .
                    rand(100, 999) .
                    "." .
                    $ext;

                $tujuan =
                    $folderUpload . $gambar;

                if (!move_uploaded_file($tmpFile, $tujuan)) {

                    $error =
                        "Gagal mengupload gambar.";

                    $gambar = "";
                }
            }
        }
    }


    // ======================================
    // SIMPAN BARANG
    // ======================================

    if ($error === "") {

        $sql = "INSERT INTO barang
                (
                    kode_barang,
                    nama_barang,
                    gambar,
                    id_kategori,
                    harga_beli,
                    harga_jual,
                    stok,
                    satuan,
                    stok_minimum
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "sssiddisi",
            $kode_barang,
            $nama_barang,
            $gambar,
            $id_kategori,
            $harga_beli,
            $harga_jual,
            $stok,
            $satuan,
            $stok_minimum
        );

        if (mysqli_stmt_execute($stmt)) {

            $success = "Barang berhasil ditambahkan.";

        } else {

            // Jika database gagal, hapus gambar yang sudah terupload
            if (
                !empty($gambar) &&
                file_exists("../uploads/" . $gambar)
            ) {
                unlink("../uploads/" . $gambar);
            }

            $error =
                "Gagal menyimpan barang: " .
                mysqli_stmt_error($stmt);
        }

        mysqli_stmt_close($stmt);
    }
}


// ======================================
// PROSES EDIT BARANG
// ======================================

if (isset($_POST['edit_barang'])) {

    $id_barang   = (int) ($_POST['id_barang'] ?? 0);
    $kode_barang = trim($_POST['kode_barang'] ?? '');
    $nama_barang = trim($_POST['nama_barang'] ?? '');
    $id_kategori = (int) ($_POST['id_kategori'] ?? 0);
    $harga_beli  = (float) ($_POST['harga_beli'] ?? 0);
    $harga_jual  = (float) ($_POST['harga_jual'] ?? 0);
    $stok        = (int) ($_POST['stok'] ?? 0);
    $satuan      = trim($_POST['satuan'] ?? '');
    $stok_minimum = (int) ($_POST['stok_minimum'] ?? 0);

    $gambar = "";


    // ======================================
    // VALIDASI ID
    // ======================================

    if ($id_barang <= 0) {

        $error = "ID barang tidak valid.";

    } elseif ($kode_barang === "") {

        $error = "Kode barang wajib diisi.";

    } elseif ($nama_barang === "") {

        $error = "Nama barang wajib diisi.";

    } elseif ($id_kategori <= 0) {

        $error = "Kategori wajib dipilih.";

    } elseif ($harga_beli < 0) {

        $error = "Harga beli tidak boleh kurang dari 0.";

    } elseif ($harga_jual < 0) {

        $error = "Harga jual tidak boleh kurang dari 0.";

    } elseif ($stok < 0) {

        $error = "Stok tidak boleh kurang dari 0.";

    } elseif ($satuan === "") {

        $error = "Satuan wajib diisi.";

    } elseif ($stok_minimum < 0) {

        $error = "Stok minimum tidak boleh kurang dari 0.";
    }


    // ======================================
    // AMBIL DATA BARANG LAMA
    // ======================================

    $dataLama = null;

    if ($error === "") {

        $stmtLama = mysqli_prepare(
            $conn,
            "SELECT gambar
             FROM barang
             WHERE id_barang = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmtLama,
            "i",
            $id_barang
        );

        mysqli_stmt_execute($stmtLama);

        $resultLama = mysqli_stmt_get_result($stmtLama);

        $dataLama = mysqli_fetch_assoc($resultLama);

        mysqli_stmt_close($stmtLama);

        if (!$dataLama) {

            $error = "Data barang tidak ditemukan.";

        } else {

            $gambar = $dataLama['gambar'] ?? "";
        }
    }


    // ======================================
    // CEK KODE BARANG
    // ======================================

    if ($error === "") {

        $stmtCek = mysqli_prepare(
            $conn,
            "SELECT id_barang
             FROM barang
             WHERE kode_barang = ?
             AND id_barang != ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmtCek,
            "si",
            $kode_barang,
            $id_barang
        );

        mysqli_stmt_execute($stmtCek);

        $resultCek = mysqli_stmt_get_result($stmtCek);

        if (mysqli_num_rows($resultCek) > 0) {
            $error = "Kode barang sudah digunakan oleh barang lain.";
        }

        mysqli_stmt_close($stmtCek);
    }


    // ======================================
    // UPLOAD GAMBAR BARU
    // ======================================

    $gambarBaru = "";

    if (
        $error === "" &&
        isset($_FILES['gambar']) &&
        $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {

            $error = "Gagal membaca gambar baru.";

        } else {

            $namaFile = $_FILES['gambar']['name'];
            $tmpFile  = $_FILES['gambar']['tmp_name'];
            $ukuran   = $_FILES['gambar']['size'];

            $ext = strtolower(
                pathinfo($namaFile, PATHINFO_EXTENSION)
            );

            $allowed = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];

            if (!in_array($ext, $allowed, true)) {

                $error =
                    "Format gambar harus JPG, JPEG, PNG, atau WEBP.";

            } elseif ($ukuran > 2 * 1024 * 1024) {

                $error =
                    "Ukuran gambar maksimal 2 MB.";

            } else {

                $folderUpload = "../uploads/";

                if (!is_dir($folderUpload)) {
                    mkdir($folderUpload, 0777, true);
                }

                $gambarBaru =
                    "barang_" .
                    time() .
                    "_" .
                    rand(100, 999) .
                    "." .
                    $ext;

                $tujuan =
                    $folderUpload . $gambarBaru;

                if (move_uploaded_file($tmpFile, $tujuan)) {

                    $gambar = $gambarBaru;

                } else {

                    $error =
                        "Gagal mengupload gambar baru.";

                    $gambarBaru = "";
                }
            }
        }
    }


    // ======================================
    // UPDATE BARANG
    // ======================================

    if ($error === "") {

        $sql = "UPDATE barang SET
                    kode_barang = ?,
                    nama_barang = ?,
                    gambar = ?,
                    id_kategori = ?,
                    harga_beli = ?,
                    harga_jual = ?,
                    stok = ?,
                    satuan = ?,
                    stok_minimum = ?
                WHERE id_barang = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "sssiddisii",
            $kode_barang,
            $nama_barang,
            $gambar,
            $id_kategori,
            $harga_beli,
            $harga_jual,
            $stok,
            $satuan,
            $stok_minimum,
            $id_barang
        );

        if (mysqli_stmt_execute($stmt)) {

            // Hapus gambar lama jika berhasil diganti
            if (
                !empty($gambarBaru) &&
                !empty($dataLama['gambar']) &&
                file_exists(
                    "../uploads/" . $dataLama['gambar']
                )
            ) {

                unlink(
                    "../uploads/" . $dataLama['gambar']
                );
            }

            $success =
                "Barang berhasil diperbarui.";

        } else {

            // Jika update gagal, hapus gambar baru
            if (
                !empty($gambarBaru) &&
                file_exists("../uploads/" . $gambarBaru)
            ) {

                unlink(
                    "../uploads/" . $gambarBaru
                );
            }

            // Kembalikan gambar lama
            $gambar = $dataLama['gambar'] ?? "";

            $error =
                "Gagal mengubah barang: " .
                mysqli_stmt_error($stmt);
        }

        mysqli_stmt_close($stmt);
    }
}


// ======================================
// PROSES HAPUS BARANG
// ======================================
//
// PENTING:
// Tidak menggunakan GET.
// Tidak menggunakan header redirect.
// Setelah berhasil, tetap di halaman barang.php.
//

if (isset($_POST['hapus_barang'])) {

    $id_barang = (int) ($_POST['id_barang'] ?? 0);

    if ($id_barang <= 0) {

        $error = "ID barang tidak valid.";

    } else {

        // ==================================
        // AMBIL GAMBAR BARANG
        // ==================================

        $stmtGambar = mysqli_prepare(
            $conn,
            "SELECT gambar
             FROM barang
             WHERE id_barang = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmtGambar,
            "i",
            $id_barang
        );

        mysqli_stmt_execute($stmtGambar);

        $resultGambar =
            mysqli_stmt_get_result($stmtGambar);

        $dataGambar =
            mysqli_fetch_assoc($resultGambar);

        mysqli_stmt_close($stmtGambar);


        if (!$dataGambar) {

            $error = "Barang tidak ditemukan.";

        } else {

            // ==================================
            // HAPUS BARANG
            // ==================================

            $stmtHapus = mysqli_prepare(
                $conn,
                "DELETE FROM barang
                 WHERE id_barang = ?"
            );

            mysqli_stmt_bind_param(
                $stmtHapus,
                "i",
                $id_barang
            );

            if (mysqli_stmt_execute($stmtHapus)) {

                // ==================================
                // HAPUS FILE GAMBAR
                // ==================================

                if (
                    !empty($dataGambar['gambar']) &&
                    file_exists(
                        "../uploads/" .
                        $dataGambar['gambar']
                    )
                ) {

                    unlink(
                        "../uploads/" .
                        $dataGambar['gambar']
                    );
                }

                $success =
                    "Barang berhasil dihapus.";

            } else {

                $error =
                    "Barang tidak dapat dihapus. " .
                    "Kemungkinan barang sudah digunakan " .
                    "dalam transaksi atau pembelian.";
            }

            mysqli_stmt_close($stmtHapus);
        }
    }
}


// ======================================
// AMBIL DATA KATEGORI
// ======================================

$kategori = mysqli_query(
    $conn,
    "SELECT
        id_kategori,
        nama_kategori
     FROM kategori
     ORDER BY nama_kategori ASC"
);


// ======================================
// AMBIL SEMUA BARANG
// ======================================

$queryBarang = "
    SELECT
        b.*,
        k.nama_kategori
    FROM barang b
    LEFT JOIN kategori k
        ON b.id_kategori = k.id_kategori
    ORDER BY b.id_barang DESC
";

$resultBarang =
    mysqli_query($conn, $queryBarang);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Data Barang - SembakoKu</title>

    <link
        rel="stylesheet"
        href="../assets/layout.css"
    >

    <style>

        /* ========================================
           HEADER
           ======================================== */

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .page-header h1 {
            margin: 0 0 6px;
            font-size: 26px;
            color: #1f2937;
        }

        .page-header p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }


        /* ========================================
           BUTTON
           ======================================== */

        .btn-primary {
            border: 0;
            background: #1677d2;
            color: #ffffff;
            padding: 10px 15px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-primary:hover {
            background: #1268ba;
        }

        .btn-edit {
            border: 0;
            background: #f59e0b;
            color: #ffffff;
            padding: 7px 11px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
        }

        .btn-edit:hover {
            background: #d97706;
        }

        .btn-hapus {
            border: 0;
            background: #dc3545;
            color: #ffffff;
            padding: 7px 11px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
        }

        .btn-hapus:hover {
            background: #c82333;
        }


        /* ========================================
           PESAN
           ======================================== */

        .alert {
            padding: 12px 14px;
            margin-bottom: 18px;
            border-radius: 7px;
            font-size: 13px;
        }

        .alert-success {
            background: #e8f7ee;
            border: 1px solid #b9e5c8;
            color: #176b35;
        }

        .alert-error {
            background: #fdecec;
            border: 1px solid #f3c2c2;
            color: #9b1c1c;
        }


        /* ========================================
           TABLE
           ======================================== */

        .table-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 11px 12px;
            border-bottom: 1px solid #edf0f3;
            text-align: left;
            font-size: 13px;
            vertical-align: middle;
        }

        .data-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: bold;
            white-space: nowrap;
        }

        .data-table tbody tr:hover {
            background: #fafcff;
        }

        .data-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .kode {
            color: #374151;
            font-weight: bold;
            white-space: nowrap;
        }

        .nama-barang {
            color: #1f2937;
            font-weight: bold;
        }

        .harga {
            white-space: nowrap;
        }

        .stok {
            font-weight: bold;
        }

        .aksi {
            white-space: nowrap;
        }

        .aksi form {
            display: inline;
            margin: 0;
        }


        /* ========================================
           GAMBAR BARANG
           ======================================== */

        .gambar-barang,
        .tanpa-gambar {
            width: 52px;
            height: 52px;
            border-radius: 7px;
        }

        .gambar-barang {
            display: block;
            object-fit: cover;
            border: 1px solid #e5e7eb;
        }

        .tanpa-gambar {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: #94a3b8;
            font-size: 10px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }


        /* ========================================
           MODAL
           ======================================== */

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            padding: 25px 15px;
            background: rgba(15, 23, 42, 0.55);
            overflow-y: auto;
        }

        .modal-content {
            width: 600px;
            max-width: 100%;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 17px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-header h2 {
            margin: 0;
            color: #1f2937;
            font-size: 18px;
        }

        .close {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            color: #6b7280;
            font-size: 23px;
            line-height: 1;
            cursor: pointer;
        }

        .close:hover {
            background: #f1f5f9;
            color: #1f2937;
        }

        .modal-body {
            padding: 20px;
        }


        /* ========================================
           FORM
           ======================================== */

        .form-group {
            margin-bottom: 15px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #374151;
            font-size: 13px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            min-height: 40px;
            padding: 9px 11px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #ffffff;
            color: #1f2937;
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #1677d2;
            box-shadow: 0 0 0 3px rgba(22, 119, 210, 0.10);
        }

        .form-group input[type="file"] {
            padding: 7px;
        }

        .form-hint {
            margin-top: 5px;
            color: #6b7280;
            font-size: 11px;
        }

        .btn-simpan {
            width: 100%;
            min-height: 42px;
            margin-top: 5px;
            border: 0;
            border-radius: 7px;
            background: #1677d2;
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-simpan:hover {
            background: #1268ba;
        }

        .preview-gambar {
            width: 90px;
            height: 90px;
            margin-top: 10px;
            object-fit: cover;
            border-radius: 7px;
            border: 1px solid #e5e7eb;
        }

        .gambar-kosong {
            margin-top: 8px;
            color: #6b7280;
            font-size: 12px;
        }


        /* ========================================
           RESPONSIVE
           ======================================== */

        @media (max-width: 700px) {

            .page-header {
                align-items: stretch;
                flex-direction: column;
            }

            .btn-primary {
                width: 100%;
            }

            .modal {
                padding: 10px;
            }

            .modal-content {
                margin: 5px auto;
            }

            .modal-body {
                padding: 16px;
            }

        }

    </style>

</head>


<body class="admin">


<!-- ========================================
     SIDEBAR
     ======================================== -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-icon">

            <img
                src="../assets/SembakoKu (3).png"
                alt="SembakoKu"
            >

        </div>

        <div class="brand-text">
            SembakoKu
        </div>

    </div>


     <nav class="drawer-menu">

        <div class="menu-label">MENU UTAMA</div>

        <a href="dashboard.php" class="menu-link">
            <span class="menu-icon">🏠</span>
            Dashboard
        </a>

        <a href="produk.php" class="menu-link active">
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


        <div class="menu-label">AKUN</div>

        <a href="profil.php" class="menu-link">
            <span class="menu-icon">👤</span>
            Profil
        </a>

    </nav>


    <div class="sidebar-bottom">

        <a href="../auth/logout.php">

            🚪

            <span>
                Logout
            </span>

        </a>

    </div>

</aside>


<!-- ========================================
     KONTEN UTAMA
     ======================================== -->

<main class="content">


    <!-- ====================================
         HEADER
         ==================================== -->

    <div class="page-header">

        <div>

            <h1>
                Data Barang
            </h1>

            <p>
                Kelola data barang yang tersedia di SembakoKu.
            </p>

        </div>


        <button
            type="button"
            class="btn-primary"
            onclick="bukaModalTambah()"
        >
            + Tambah Barang
        </button>

    </div>


    <!-- ====================================
         PESAN SUKSES
         ==================================== -->

    <?php if ($success !== ""): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <!-- ====================================
         PESAN ERROR
         ==================================== -->

    <?php if ($error !== ""): ?>

        <div class="alert alert-error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- ====================================
         TABLE BARANG
         ==================================== -->

    <div class="table-card">

        <div class="table-wrapper">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Gambar</th>

                        <th>Kode</th>

                        <th>Nama Barang</th>

                        <th>Kategori</th>

                        <th>Harga Beli</th>

                        <th>Harga Jual</th>

                        <th>Stok</th>

                        <th>Satuan</th>

                        <th>Stok Minimum</th>

                        <th>Aksi</th>

                    </tr>

                </thead>


                <tbody>

                <?php

                $no = 1;

                if ($resultBarang && mysqli_num_rows($resultBarang) > 0):

                    while (
                        $item = mysqli_fetch_assoc($resultBarang)
                    ):

                ?>

                    <tr>

                        <!-- No -->

                        <td>
                            <?= $no++ ?>
                        </td>


                        <!-- Gambar -->

                        <td>

                            <?php if (!empty($item['gambar'])): ?>

                                <img
                                    src="../uploads/<?= htmlspecialchars($item['gambar']) ?>"
                                    class="gambar-barang"
                                    alt="Gambar <?= htmlspecialchars($item['nama_barang']) ?>"
                                >

                            <?php else: ?>

                                <div class="tanpa-gambar">
                                    Tidak ada
                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- Kode -->

                        <td class="kode">

                            <?= htmlspecialchars(
                                $item['kode_barang']
                            ) ?>

                        </td>


                        <!-- Nama -->

                        <td class="nama-barang">

                            <?= htmlspecialchars(
                                $item['nama_barang']
                            ) ?>

                        </td>


                        <!-- Kategori -->

                        <td>

                            <?= htmlspecialchars(
                                $item['nama_kategori'] ?? '-'
                            ) ?>

                        </td>


                        <!-- Harga Beli -->

                        <td class="harga">

                            Rp <?= number_format(
                                (float) $item['harga_beli'],
                                0,
                                ',',
                                '.'
                            ) ?>

                        </td>


                        <!-- Harga Jual -->

                        <td class="harga">

                            Rp <?= number_format(
                                (float) $item['harga_jual'],
                                0,
                                ',',
                                '.'
                            ) ?>

                        </td>


                        <!-- Stok -->

                        <td class="stok">

                            <?= number_format(
                                (float) $item['stok'],
                                0,
                                ',',
                                '.'
                            ) ?>

                        </td>


                        <!-- Satuan -->

                        <td>

                            <?= htmlspecialchars(
                                $item['satuan']
                            ) ?>

                        </td>


                        <!-- Stok Minimum -->

                        <td>

                            <?= number_format(
                                (float) $item['stok_minimum'],
                                0,
                                ',',
                                '.'
                            ) ?>

                        </td>


                        <!-- Aksi -->

                        <td class="aksi">

                            <!-- EDIT -->

                            <button
                                type="button"
                                class="btn-edit"
                                onclick='bukaModalEdit(<?= json_encode(
                                    $item,
                                    JSON_HEX_TAG |
                                    JSON_HEX_AMP |
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT |
                                    JSON_UNESCAPED_UNICODE
                                ) ?>)'
                            >
                                Edit
                            </button>


                            <!-- HAPUS -->

                            <form
                                method="POST"
                                onsubmit="return konfirmasiHapus();"
                            >

                                <input
                                    type="hidden"
                                    name="id_barang"
                                    value="<?= (int) $item['id_barang'] ?>"
                                >

                                <button
                                    type="submit"
                                    name="hapus_barang"
                                    class="btn-hapus"
                                >
                                    Hapus
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php

                    endwhile;

                else:

                ?>

                    <tr>

                        <td
                            colspan="11"
                            style="
                                text-align:center;
                                padding:30px;
                                color:#6b7280;
                            "
                        >

                            Belum ada data barang.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


</main>


<!-- ========================================
     MODAL TAMBAH BARANG
     ======================================== -->

<div
    class="modal"
    id="modalTambah"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                Tambah Barang
            </h2>

            <span
                class="close"
                onclick="tutupModalTambah()"
            >
                &times;
            </span>

        </div>


        <div class="modal-body">

            <form
                method="POST"
                enctype="multipart/form-data"
            >


                <!-- KODE -->

                <div class="form-group">

                    <label>
                        Kode Barang
                    </label>

                    <input
                        type="text"
                        name="kode_barang"
                        required
                    >

                </div>


                <!-- NAMA -->

                <div class="form-group">

                    <label>
                        Nama Barang
                    </label>

                    <input
                        type="text"
                        name="nama_barang"
                        required
                    >

                </div>


                <!-- KATEGORI -->

                <div class="form-group">

                    <label>
                        Kategori
                    </label>

                    <select
                        name="id_kategori"
                        required
                    >

                        <option value="">
                            -- Pilih Kategori --
                        </option>

                        <?php

                        if ($kategori):

                            mysqli_data_seek(
                                $kategori,
                                0
                            );

                            while (
                                $kat =
                                mysqli_fetch_assoc($kategori)
                            ):

                        ?>

                            <option
                                value="<?= (int) $kat['id_kategori'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $kat['nama_kategori']
                                ) ?>

                            </option>

                        <?php

                            endwhile;

                        endif;

                        ?>

                    </select>

                </div>


                <!-- HARGA BELI -->

                <div class="form-group">

                    <label>
                        Harga Beli
                    </label>

                    <input
                        type="number"
                        name="harga_beli"
                        min="0"
                        step="1"
                        required
                    >

                </div>


                <!-- HARGA JUAL -->

                <div class="form-group">

                    <label>
                        Harga Jual
                    </label>

                    <input
                        type="number"
                        name="harga_jual"
                        min="0"
                        step="1"
                        required
                    >

                </div>


                <!-- STOK -->

                <div class="form-group">

                    <label>
                        Stok
                    </label>

                    <input
                        type="number"
                        name="stok"
                        min="0"
                        step="1"
                        required
                    >

                </div>


                <!-- SATUAN -->

                <div class="form-group">

                    <label>
                        Satuan
                    </label>

                    <input
                        type="text"
                        name="satuan"
                        placeholder="Contoh: pcs, kg, liter"
                        required
                    >

                </div>


                <!-- STOK MINIMUM -->

                <div class="form-group">

                    <label>
                        Stok Minimum
                    </label>

                    <input
                        type="number"
                        name="stok_minimum"
                        min="0"
                        step="1"
                        required
                    >

                </div>


                <!-- GAMBAR -->

                <div class="form-group">

                    <label>
                        Gambar Barang
                    </label>

                    <input
                        type="file"
                        name="gambar"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <div class="form-hint">
                        Format JPG, JPEG, PNG, atau WEBP.
                        Maksimal 2 MB.
                    </div>

                </div>


                <!-- SIMPAN -->

                <button
                    type="submit"
                    name="tambah_barang"
                    class="btn-simpan"
                >
                    Simpan Barang
                </button>

            </form>

        </div>

    </div>

</div>


<!-- ========================================
     MODAL EDIT BARANG
     ======================================== -->

<div
    class="modal"
    id="modalEdit"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                Edit Barang
            </h2>

            <span
                class="close"
                onclick="tutupModalEdit()"
            >
                &times;
            </span>

        </div>


        <div class="modal-body">

            <form
                method="POST"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="id_barang"
                    id="edit_id_barang"
                >


                <!-- KODE -->

                <div class="form-group">

                    <label>
                        Kode Barang
                    </label>

                    <input
                        type="text"
                        name="kode_barang"
                        id="edit_kode_barang"
                        required
                    >

                </div>


                <!-- NAMA -->

                <div class="form-group">

                    <label>
                        Nama Barang
                    </label>

                    <input
                        type="text"
                        name="nama_barang"
                        id="edit_nama_barang"
                        required
                    >

                </div>


                <!-- KATEGORI -->

                <div class="form-group">

                    <label>
                        Kategori
                    </label>

                    <select
                        name="id_kategori"
                        id="edit_id_kategori"
                        required
                    >

                        <option value="">
                            -- Pilih Kategori --
                        </option>

                        <?php

                        if ($kategori):

                            mysqli_data_seek(
                                $kategori,
                                0
                            );

                            while (
                                $kat =
                                mysqli_fetch_assoc($kategori)
                            ):

                        ?>

                            <option
                                value="<?= (int) $kat['id_kategori'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $kat['nama_kategori']
                                ) ?>

                            </option>

                        <?php

                            endwhile;

                        endif;

                        ?>

                    </select>

                </div>


                <!-- HARGA BELI -->

                <div class="form-group">

                    <label>
                        Harga Beli
                    </label>

                    <input
                        type="number"
                        name="harga_beli"
                        id="edit_harga_beli"
                        min="0"
                        step="1"
                        required
                    >

                </div>


                <!-- HARGA JUAL -->

                <div class="form-group">

                    <label>
                        Harga Jual
                    </label>

                    <input
                        type="number"
                        name="harga_jual"
                        id="edit_harga_jual"
                        min="0"
                        step="1"
                        required
                    >

                </div>


                <!-- STOK -->

                <div class="form-group">

                    <label>
                        Stok
                    </label>

                    <input
                        type="number"
                        name="stok"
                        id="edit_stok"
                        min="0"
                        step="1"
                        required
                    >

                </div>


                <!-- SATUAN -->

                <div class="form-group">

                    <label>
                        Satuan
                    </label>

                    <input
                        type="text"
                        name="satuan"
                        id="edit_satuan"
                        required
                    >

                </div>


                <!-- STOK MINIMUM -->

                <div class="form-group">

                    <label>
                        Stok Minimum
                    </label>

                    <input
                        type="number"
                        name="stok_minimum"
                        id="edit_stok_minimum"
                        min="0"
                        step="1"
                        required
                    >

                </div>


                <!-- GAMBAR -->

                <div class="form-group">

                    <label>
                        Ganti Gambar
                    </label>

                    <input
                        type="file"
                        name="gambar"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <div class="form-hint">
                        Kosongkan jika tidak ingin mengganti gambar.
                    </div>

                    <div id="gambar_lama"></div>

                </div>


                <!-- SIMPAN -->

                <button
                    type="submit"
                    name="edit_barang"
                    class="btn-simpan"
                >
                    Simpan Perubahan
                </button>

            </form>

        </div>

    </div>

</div>


<script>

// ========================================
// MODAL TAMBAH
// ========================================

function bukaModalTambah() {

    document.getElementById(
        "modalTambah"
    ).style.display = "block";

}


function tutupModalTambah() {

    document.getElementById(
        "modalTambah"
    ).style.display = "none";

}


// ========================================
// MODAL EDIT
// ========================================

function bukaModalEdit(data) {

    document.getElementById(
        "modalEdit"
    ).style.display = "block";


    document.getElementById(
        "edit_id_barang"
    ).value = data.id_barang;


    document.getElementById(
        "edit_kode_barang"
    ).value = data.kode_barang || "";


    document.getElementById(
        "edit_nama_barang"
    ).value = data.nama_barang || "";


    document.getElementById(
        "edit_id_kategori"
    ).value = data.id_kategori || "";


    document.getElementById(
        "edit_harga_beli"
    ).value = data.harga_beli || 0;


    document.getElementById(
        "edit_harga_jual"
    ).value = data.harga_jual || 0;


    document.getElementById(
        "edit_stok"
    ).value = data.stok || 0;


    document.getElementById(
        "edit_satuan"
    ).value = data.satuan || "";


    document.getElementById(
        "edit_stok_minimum"
    ).value = data.stok_minimum || 0;


    // ====================================
    // GAMBAR LAMA
    // ====================================

    const gambarLama =
        document.getElementById("gambar_lama");


    if (data.gambar) {

        gambarLama.innerHTML =
            '<img src="../uploads/' +
            encodeURIComponent(data.gambar) +
            '" class="preview-gambar" alt="Gambar barang">';

    } else {

        gambarLama.innerHTML =
            '<div class="gambar-kosong">' +
            'Tidak ada gambar.' +
            '</div>';
    }

}


function tutupModalEdit() {

    document.getElementById(
        "modalEdit"
    ).style.display = "none";

}


// ========================================
// KONFIRMASI HAPUS
// ========================================

function konfirmasiHapus() {

    return confirm(
        "Apakah kamu yakin ingin menghapus barang ini?"
    );

}


// ========================================
// TUTUP MODAL KLIK DI LUAR
// ========================================

window.onclick = function(event) {

    const modalTambah =
        document.getElementById("modalTambah");

    const modalEdit =
        document.getElementById("modalEdit");


    if (event.target === modalTambah) {

        modalTambah.style.display = "none";

    }


    if (event.target === modalEdit) {

        modalEdit.style.display = "none";

    }

};

</script>


</body>

</html>