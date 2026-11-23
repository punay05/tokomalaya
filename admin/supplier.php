<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$pesan = "";
$tipePesan = "";


/* =========================================================
   TAMBAH SUPPLIER
========================================================= */

if (isset($_POST['tambah_supplier'])) {

    $nama_supplier = trim($_POST['nama_supplier']);
    $telepon = trim($_POST['telepon']);
    $alamat = trim($_POST['alamat']);

    if ($nama_supplier === "") {

        $pesan = "Nama supplier wajib diisi.";
        $tipePesan = "error";

    } else {

        $cek = mysqli_prepare(
            $conn,
            "SELECT id_supplier
             FROM supplier
             WHERE nama_supplier = ?"
        );

        mysqli_stmt_bind_param(
            $cek,
            "s",
            $nama_supplier
        );

        mysqli_stmt_execute($cek);

        $hasilCek = mysqli_stmt_get_result($cek);

        if (mysqli_num_rows($hasilCek) > 0) {

            $pesan = "Nama supplier tersebut sudah ada.";
            $tipePesan = "error";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO supplier
                (nama_supplier, telepon, alamat)
                VALUES (?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $nama_supplier,
                $telepon,
                $alamat
            );

            if (mysqli_stmt_execute($stmt)) {

                $pesan = "Supplier berhasil ditambahkan.";
                $tipePesan = "success";

            } else {

                $pesan = "Gagal menambahkan supplier.";
                $tipePesan = "error";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($cek);
    }
}


/* =========================================================
   EDIT SUPPLIER
========================================================= */

if (isset($_POST['edit_supplier'])) {

    $id_supplier = (int) $_POST['id_supplier'];
    $nama_supplier = trim($_POST['nama_supplier']);
    $telepon = trim($_POST['telepon']);
    $alamat = trim($_POST['alamat']);

    if ($nama_supplier === "") {

        $pesan = "Nama supplier wajib diisi.";
        $tipePesan = "error";

    } else {

        $cek = mysqli_prepare(
            $conn,
            "SELECT id_supplier
             FROM supplier
             WHERE nama_supplier = ?
             AND id_supplier != ?"
        );

        mysqli_stmt_bind_param(
            $cek,
            "si",
            $nama_supplier,
            $id_supplier
        );

        mysqli_stmt_execute($cek);

        $hasilCek = mysqli_stmt_get_result($cek);

        if (mysqli_num_rows($hasilCek) > 0) {

            $pesan = "Nama supplier tersebut sudah digunakan.";
            $tipePesan = "error";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE supplier
                 SET nama_supplier = ?,
                     telepon = ?,
                     alamat = ?
                 WHERE id_supplier = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssi",
                $nama_supplier,
                $telepon,
                $alamat,
                $id_supplier
            );

            if (mysqli_stmt_execute($stmt)) {

                $pesan = "Data supplier berhasil diperbarui.";
                $tipePesan = "success";

            } else {

                $pesan = "Gagal memperbarui supplier.";
                $tipePesan = "error";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($cek);
    }
}


/* =========================================================
   HAPUS SUPPLIER
========================================================= */

if (isset($_GET['hapus'])) {

    $id_supplier = (int) $_GET['hapus'];

    $cekPembelian = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS jumlah
         FROM pembelian
         WHERE id_supplier = ?"
    );

    mysqli_stmt_bind_param(
        $cekPembelian,
        "i",
        $id_supplier
    );

    mysqli_stmt_execute($cekPembelian);

    $hasilPembelian =
        mysqli_stmt_get_result($cekPembelian);

    $dataPembelian =
        mysqli_fetch_assoc($hasilPembelian);

    mysqli_stmt_close($cekPembelian);


    if ($dataPembelian['jumlah'] > 0) {

        $pesan =
            "Supplier tidak bisa dihapus karena sudah digunakan dalam data pembelian.";

        $tipePesan = "error";

    } else {

        $hapusRelasi = mysqli_prepare(
            $conn,
            "DELETE FROM supplier_barang
             WHERE id_supplier = ?"
        );

        mysqli_stmt_bind_param(
            $hapusRelasi,
            "i",
            $id_supplier
        );

        mysqli_stmt_execute($hapusRelasi);

        mysqli_stmt_close($hapusRelasi);


        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM supplier
             WHERE id_supplier = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id_supplier
        );

        if (mysqli_stmt_execute($stmt)) {

            $pesan = "Supplier berhasil dihapus.";
            $tipePesan = "success";

        } else {

            $pesan = "Gagal menghapus supplier.";
            $tipePesan = "error";
        }

        mysqli_stmt_close($stmt);
    }
}


/* =========================================================
   TAMBAH BARANG KE SUPPLIER
========================================================= */

if (isset($_POST['tambah_barang_supplier'])) {

    $id_supplier = (int) $_POST['id_supplier'];
    $id_barang = (int) $_POST['id_barang'];
    $harga_supplier = (float) $_POST['harga_supplier'];

    if ($id_supplier <= 0 || $id_barang <= 0) {

        $pesan = "Supplier dan barang harus dipilih.";
        $tipePesan = "error";

    } elseif ($harga_supplier < 0) {

        $pesan = "Harga supplier tidak valid.";
        $tipePesan = "error";

    } else {

        $cek = mysqli_prepare(
            $conn,
            "SELECT id_supplier
             FROM supplier_barang
             WHERE id_supplier = ?
             AND id_barang = ?"
        );

        mysqli_stmt_bind_param(
            $cek,
            "ii",
            $id_supplier,
            $id_barang
        );

        mysqli_stmt_execute($cek);

        $hasilCek = mysqli_stmt_get_result($cek);

        if (mysqli_num_rows($hasilCek) > 0) {

            $pesan =
                "Barang tersebut sudah terdaftar pada supplier ini.";

            $tipePesan = "error";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO supplier_barang
                (id_supplier, id_barang, harga_supplier)
                VALUES (?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "iid",
                $id_supplier,
                $id_barang,
                $harga_supplier
            );

            if (mysqli_stmt_execute($stmt)) {

                $pesan =
                    "Barang berhasil ditambahkan ke supplier.";

                $tipePesan = "success";

            } else {

                $pesan =
                    "Gagal menambahkan barang ke supplier.";

                $tipePesan = "error";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($cek);
    }
}


/* =========================================================
   HAPUS BARANG DARI SUPPLIER
========================================================= */

if (isset($_GET['hapus_barang_supplier'])) {

    $id_supplier = (int) $_GET['supplier'];
    $id_barang = (int) $_GET['hapus_barang_supplier'];

    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM supplier_barang
         WHERE id_supplier = ?
         AND id_barang = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $id_supplier,
        $id_barang
    );

    if (mysqli_stmt_execute($stmt)) {

        $pesan =
            "Barang berhasil dihapus dari supplier.";

        $tipePesan = "success";

    } else {

        $pesan =
            "Gagal menghapus barang dari supplier.";

        $tipePesan = "error";
    }

    mysqli_stmt_close($stmt);
}


/* =========================================================
   PENCARIAN SUPPLIER
========================================================= */

$cari = trim($_GET['cari'] ?? '');

if ($cari !== '') {

    $keyword = "%" . $cari . "%";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            s.id_supplier,
            s.nama_supplier,
            s.telepon,
            s.alamat,
            COUNT(DISTINCT sb.id_barang) AS jumlah_barang
         FROM supplier s
         LEFT JOIN supplier_barang sb
            ON s.id_supplier = sb.id_supplier
         WHERE s.nama_supplier LIKE ?
            OR s.telepon LIKE ?
            OR s.alamat LIKE ?
         GROUP BY
            s.id_supplier,
            s.nama_supplier,
            s.telepon,
            s.alamat
         ORDER BY s.nama_supplier ASC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $keyword,
        $keyword,
        $keyword
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

} else {

    $result = mysqli_query(
        $conn,
        "SELECT
            s.id_supplier,
            s.nama_supplier,
            s.telepon,
            s.alamat,
            COUNT(DISTINCT sb.id_barang) AS jumlah_barang
         FROM supplier s
         LEFT JOIN supplier_barang sb
            ON s.id_supplier = sb.id_supplier
         GROUP BY
            s.id_supplier,
            s.nama_supplier,
            s.telepon,
            s.alamat
         ORDER BY s.nama_supplier ASC"
    );
}


/* =========================================================
   DATA BARANG UNTUK MODAL
========================================================= */

$barangResult = mysqli_query(
    $conn,
    "SELECT
        id_barang,
        kode_barang,
        nama_barang,
        satuan
     FROM barang
     ORDER BY nama_barang ASC"
);

$semuaBarang = [];

while ($barang = mysqli_fetch_assoc($barangResult)) {
    $semuaBarang[] = $barang;
}

$jumlahSupplier = mysqli_num_rows($result);


/* =========================================================
   DETAIL SUPPLIER
========================================================= */

$detailSupplier = null;
$barangSupplierResult = false;

if (isset($_GET['detail'])) {

    $idDetailSupplier = (int) $_GET['detail'];

    $stmtSupplier = mysqli_prepare(
        $conn,
        "SELECT
            id_supplier,
            nama_supplier,
            telepon,
            alamat
         FROM supplier
         WHERE id_supplier = ?"
    );

    mysqli_stmt_bind_param(
        $stmtSupplier,
        "i",
        $idDetailSupplier
    );

    mysqli_stmt_execute($stmtSupplier);

    $hasilSupplier =
        mysqli_stmt_get_result($stmtSupplier);

    $detailSupplier =
        mysqli_fetch_assoc($hasilSupplier);

    mysqli_stmt_close($stmtSupplier);


    if ($detailSupplier) {

        $stmtBarangSupplier = mysqli_prepare(
            $conn,
            "SELECT
                sb.id_barang,
                sb.harga_supplier,
                b.kode_barang,
                b.nama_barang,
                b.satuan
             FROM supplier_barang sb
             INNER JOIN barang b
                ON sb.id_barang = b.id_barang
             WHERE sb.id_supplier = ?
             ORDER BY b.nama_barang ASC"
        );

        mysqli_stmt_bind_param(
            $stmtBarangSupplier,
            "i",
            $idDetailSupplier
        );

        mysqli_stmt_execute($stmtBarangSupplier);

        $barangSupplierResult =
            mysqli_stmt_get_result($stmtBarangSupplier);
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

    <title>Supplier - SembakoKu</title>

    <link
        rel="stylesheet"
        href="../assets/layout.css"
    >

    <style>

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 26px;
            color: #111827;
        }

        .page-header p {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .page-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 9px 13px;
            border: 0;
            border-radius: 7px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            transition:
                background 0.2s ease,
                transform 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: #1677d2;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1265b3;
        }

        .btn-edit {
            background: #f59e0b;
            color: #ffffff;
        }

        .btn-edit:hover {
            background: #d97706;
        }

        .btn-delete {
            background: #dc2626;
            color: #ffffff;
        }

        .btn-delete:hover {
            background: #b91c1c;
        }

        .btn-detail {
            background: #0f766e;
            color: #ffffff;
        }

        .btn-detail:hover {
            background: #115e59;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .alert {
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            border: 1px solid transparent;
        }

        .alert-success {
            background: #ecfdf3;
            color: #166534;
            border-color: #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .info-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            padding: 17px 18px;
            margin-bottom: 18px;
        }

        .info-card span {
            display: block;
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .info-card strong {
            display: block;
            font-size: 24px;
            color: #111827;
        }

        .search-box {
            display: flex;
            gap: 8px;
            align-items: center;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            padding: 14px;
            margin-bottom: 18px;
        }

        .search-box input {
            flex: 1;
            min-width: 0;
            height: 38px;
            padding: 8px 11px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            outline: none;
        }

        .search-box input:focus,
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #1677d2;
            box-shadow: 0 0 0 2px rgba(22, 119, 210, 0.10);
        }

        .table-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            overflow: hidden;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .data-table th,
        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
            font-size: 13px;
        }

        .data-table th {
            background: #f8fafc;
            color: #374151;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .data-table td {
            color: #374151;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .supplier-name {
            font-weight: bold;
            color: #111827;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 20px;
            background: #e8f1fb;
            color: #1677d2;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .aksi {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .empty-row {
            text-align: center !important;
            padding: 30px !important;
            color: #6b7280 !important;
        }

        .detail-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .detail-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-top h2 {
            margin: 0 0 7px;
            font-size: 20px;
            color: #111827;
        }

        .detail-info {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.7;
        }

        .detail-card h3 {
            margin: 0 0 12px;
            font-size: 16px;
            color: #111827;
        }

        .detail-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .detail-table {
            width: 100%;
            overflow-x: auto;
        }

        .detail-table table {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
        }

        .detail-table th,
        .detail-table td {
            padding: 11px 12px;
            border: 1px solid #e5e7eb;
            text-align: left;
            font-size: 13px;
        }

        .detail-table th {
            background: #f8fafc;
            color: #374151;
        }

        .harga {
            font-weight: bold;
            white-space: nowrap;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.55);
        }

        .modal-content {
            width: 600px;
            max-width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            background: #ffffff;
            border-radius: 10px;
            padding: 22px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.18);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 19px;
            color: #111827;
        }

        .close {
            color: #6b7280;
            font-size: 27px;
            line-height: 1;
            cursor: pointer;
        }

        .close:hover {
            color: #111827;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #374151;
            font-size: 13px;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 11px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            outline: none;
        }

        .form-group textarea {
            min-height: 90px;
            resize: vertical;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 20px;
        }

        .modal-info {
            margin-bottom: 18px;
            padding: 11px 12px;
            background: #f8fafc;
            border-radius: 7px;
            color: #6b7280;
            font-size: 13px;
        }

        .modal-info strong {
            color: #111827;
        }

        @media (max-width: 768px) {

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .search-box {
                flex-wrap: wrap;
            }

            .search-box input {
                flex-basis: 100%;
            }

            .detail-top {
                flex-direction: column;
            }

            .detail-actions {
                justify-content: flex-start;
            }

            .detail-card {
                padding: 16px;
            }
        }

        @media (max-width: 480px) {

            .page-actions,
            .page-actions .btn {
                width: 100%;
            }

            .page-actions .btn {
                justify-content: center;
            }

            .search-box {
                align-items: stretch;
                flex-direction: column;
            }

            .search-box .btn {
                width: 100%;
            }

            .modal {
                padding: 10px;
            }

            .modal-content {
                padding: 17px;
            }
        }

    </style>

</head>


<body class="admin">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-icon">
            🛒
        </div>

        <div class="brand-text">
            SembakoKu
        </div>

    </div>


    <nav class="menu">

        <a href="dashboard.php">
            🏠 <span>Dashboard</span>
        </a>

        <a href="barang.php">
            📦 <span>Barang</span>
        </a>

        <a href="kategori.php">
            🗂️ <span>Kategori</span>
        </a>

        <a href="supplier.php" class="active">
            🚚 <span>Supplier</span>
        </a>

        <a href="pembelian.php">
            🛒 <span>Pembelian</span>
        </a>

    </nav>


    <div class="sidebar-bottom">

        <a href="../auth/logout.php">
            🚪 <span>Logout</span>
        </a>

    </div>

</aside>


<!-- =====================================================
     CONTENT
===================================================== -->

<main class="content">


    <div class="page-header">

        <div>

            <h1>Supplier</h1>

            <p>
                Kelola supplier dan barang yang disediakan
            </p>

        </div>


        <div class="page-actions">

            <button
                type="button"
                class="btn btn-primary"
                onclick="bukaModalTambah()"
            >
                + Tambah Supplier
            </button>

        </div>

    </div>


    <!-- ALERT -->

    <?php if ($pesan !== ""): ?>

        <div class="alert
            <?= $tipePesan === 'success'
                ? 'alert-success'
                : 'alert-error'
            ?>"
        >

            <?= htmlspecialchars($pesan) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         DETAIL SUPPLIER
    ================================================== -->

    <?php if ($detailSupplier): ?>

        <section class="detail-card">

            <div class="detail-top">

                <div>

                    <h2>
                        <?= htmlspecialchars(
                            $detailSupplier['nama_supplier']
                        ) ?>
                    </h2>

                    <div class="detail-info">

                        Telepon:
                        <?= $detailSupplier['telepon'] !== ''
                            ? htmlspecialchars(
                                $detailSupplier['telepon']
                            )
                            : '-'
                        ?>

                        <br>

                        Alamat:
                        <?= $detailSupplier['alamat'] !== ''
                            ? nl2br(
                                htmlspecialchars(
                                    $detailSupplier['alamat']
                                )
                            )
                            : '-'
                        ?>

                    </div>

                </div>


                <div class="detail-actions">

                    <a
                        href="supplier.php"
                        class="btn btn-secondary"
                    >
                        ← Kembali
                    </a>

                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="bukaModalBarang()"
                    >
                        + Tambah Barang
                    </button>

                </div>

            </div>


            <h3>
                Barang yang Disediakan Supplier
            </h3>


            <div class="detail-table">

                <?php if (
                    $barangSupplierResult &&
                    mysqli_num_rows($barangSupplierResult) > 0
                ): ?>

                    <table>

                        <thead>

                            <tr>

                                <th>No</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Satuan</th>
                                <th>Harga Supplier</th>
                                <th>Aksi</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php $noBarang = 1; ?>

                            <?php while (
                                $barang =
                                mysqli_fetch_assoc(
                                    $barangSupplierResult
                                )
                            ): ?>

                                <tr>

                                    <td>
                                        <?= $noBarang++ ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $barang['kode_barang']
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $barang['nama_barang']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $barang['satuan']
                                        ) ?>
                                    </td>

                                    <td class="harga">

                                        Rp
                                        <?= number_format(
                                            $barang['harga_supplier'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                    </td>

                                    <td>

                                        <button
                                            type="button"
                                            class="btn btn-delete"
                                            onclick="hapusBarangSupplier(
                                                <?= (int)$detailSupplier['id_supplier'] ?>,
                                                <?= (int)$barang['id_barang'] ?>
                                            )"
                                        >
                                            Hapus
                                        </button>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        </tbody>

                    </table>

                <?php else: ?>

                    <div class="empty-row">

                        Supplier ini belum memiliki
                        barang yang disediakan.

                        <br><br>

                        Klik
                        <strong>+ Tambah Barang</strong>
                        untuk menambahkan barang.

                    </div>

                <?php endif; ?>

            </div>

        </section>

    <?php endif; ?>


    <!-- =================================================
         INFO
    ================================================== -->

    <div class="info-card">

        <span>
            Total Supplier
        </span>

        <strong>
            <?= $jumlahSupplier ?>
        </strong>

    </div>


    <!-- =================================================
         SEARCH
    ================================================== -->

    <form
        method="GET"
        class="search-box"
    >

        <input
            type="text"
            name="cari"
            placeholder="Cari nama, telepon, atau alamat supplier..."
            value="<?= htmlspecialchars($cari) ?>"
        >

        <button
            type="submit"
            class="btn btn-primary"
        >
            Cari
        </button>

        <?php if ($cari !== ""): ?>

            <a
                href="supplier.php"
                class="btn btn-secondary"
            >
                Reset
            </a>

        <?php endif; ?>

    </form>


    <!-- =================================================
         TABLE SUPPLIER
    ================================================== -->

    <section class="table-card">

        <div class="table-wrapper">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>No</th>
                        <th>Nama Supplier</th>
                        <th>Telepon</th>
                        <th>Alamat</th>
                        <th>Produk Disuplai</th>
                        <th>Aksi</th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (
                        mysqli_num_rows($result) > 0
                    ): ?>

                        <?php $no = 1; ?>

                        <?php while (
                            $supplier =
                            mysqli_fetch_assoc($result)
                        ): ?>

                            <tr>

                                <td>
                                    <?= $no++ ?>
                                </td>

                                <td>

                                    <div class="supplier-name">

                                        <?= htmlspecialchars(
                                            $supplier['nama_supplier']
                                        ) ?>

                                    </div>

                                </td>

                                <td>

                                    <?= $supplier['telepon'] !== ''
                                        ? htmlspecialchars(
                                            $supplier['telepon']
                                        )
                                        : '-'
                                    ?>

                                </td>

                                <td>

                                    <?= $supplier['alamat'] !== ''
                                        ? nl2br(
                                            htmlspecialchars(
                                                $supplier['alamat']
                                            )
                                        )
                                        : '-'
                                    ?>

                                </td>

                                <td>

                                    <span class="badge">

                                        <?= (int)$supplier['jumlah_barang'] ?>

                                        produk

                                    </span>

                                </td>

                                <td>

                                    <div class="aksi">

                                        <a
                                            href="supplier.php?detail=<?= (int)$supplier['id_supplier'] ?>"
                                            class="btn btn-detail"
                                        >
                                            Kelola Barang
                                        </a>


                                        <button
                                            type="button"
                                            class="btn btn-edit"
                                            onclick='bukaModalEdit(
                                                <?= json_encode($supplier["id_supplier"]) ?>,
                                                <?= json_encode($supplier["nama_supplier"]) ?>,
                                                <?= json_encode($supplier["telepon"]) ?>,
                                                <?= json_encode($supplier["alamat"]) ?>
                                            )'
                                        >
                                            Edit
                                        </button>


                                        <button
                                            type="button"
                                            class="btn btn-delete"
                                            onclick="hapusSupplier(
                                                <?= (int)$supplier['id_supplier'] ?>
                                            )"
                                        >
                                            Hapus
                                        </button>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="6"
                                class="empty-row"
                            >
                                Belum ada data supplier.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>


<!-- =====================================================
     MODAL TAMBAH SUPPLIER
===================================================== -->

<div
    class="modal"
    id="modalTambah"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                Tambah Supplier
            </h2>

            <span
                class="close"
                onclick="tutupModalTambah()"
            >
                &times;
            </span>

        </div>


        <form method="POST">

            <div class="form-group">

                <label>
                    Nama Supplier
                </label>

                <input
                    type="text"
                    name="nama_supplier"
                    placeholder="Masukkan nama supplier"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Nomor Telepon
                </label>

                <input
                    type="text"
                    name="telepon"
                    placeholder="Contoh: 081234567890"
                >

            </div>


            <div class="form-group">

                <label>
                    Alamat
                </label>

                <textarea
                    name="alamat"
                    placeholder="Masukkan alamat supplier"
                ></textarea>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="tutupModalTambah()"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    name="tambah_supplier"
                    class="btn btn-primary"
                >
                    Simpan Supplier
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =====================================================
     MODAL EDIT SUPPLIER
===================================================== -->

<div
    class="modal"
    id="modalEdit"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                Edit Supplier
            </h2>

            <span
                class="close"
                onclick="tutupModalEdit()"
            >
                &times;
            </span>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="id_supplier"
                id="edit_id_supplier"
            >


            <div class="form-group">

                <label>
                    Nama Supplier
                </label>

                <input
                    type="text"
                    name="nama_supplier"
                    id="edit_nama_supplier"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Nomor Telepon
                </label>

                <input
                    type="text"
                    name="telepon"
                    id="edit_telepon"
                >

            </div>


            <div class="form-group">

                <label>
                    Alamat
                </label>

                <textarea
                    name="alamat"
                    id="edit_alamat"
                ></textarea>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="tutupModalEdit()"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    name="edit_supplier"
                    class="btn btn-primary"
                >
                    Simpan Perubahan
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =====================================================
     MODAL TAMBAH BARANG SUPPLIER
===================================================== -->

<?php if ($detailSupplier): ?>

<div
    class="modal"
    id="modalBarang"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                Tambah Barang Supplier
            </h2>

            <span
                class="close"
                onclick="tutupModalBarang()"
            >
                &times;
            </span>

        </div>


        <div class="modal-info">

            Supplier:
            <strong>
                <?= htmlspecialchars(
                    $detailSupplier['nama_supplier']
                ) ?>
            </strong>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="id_supplier"
                value="<?= (int)$detailSupplier['id_supplier'] ?>"
            >


            <div class="form-group">

                <label>
                    Pilih Barang
                </label>

                <select
                    name="id_barang"
                    required
                >

                    <option value="">
                        -- Pilih Barang --
                    </option>

                    <?php foreach (
                        $semuaBarang as $barang
                    ): ?>

                        <option
                            value="<?= (int)$barang['id_barang'] ?>"
                        >

                            <?= htmlspecialchars(
                                $barang['kode_barang']
                            ) ?>

                            -
                            <?= htmlspecialchars(
                                $barang['nama_barang']
                            ) ?>

                            (<?= htmlspecialchars(
                                $barang['satuan']
                            ) ?>)

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Harga Supplier
                </label>

                <input
                    type="number"
                    name="harga_supplier"
                    min="0"
                    step="0.01"
                    placeholder="Contoh: 15000"
                    required
                >

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="tutupModalBarang()"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    name="tambah_barang_supplier"
                    class="btn btn-primary"
                >
                    Tambahkan Barang
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>


<script>

/* =========================================================
   MODAL TAMBAH SUPPLIER
========================================================= */

function bukaModalTambah() {

    document.getElementById(
        "modalTambah"
    ).style.display = "flex";

}


function tutupModalTambah() {

    document.getElementById(
        "modalTambah"
    ).style.display = "none";

}


/* =========================================================
   MODAL EDIT SUPPLIER
========================================================= */

function bukaModalEdit(
    id,
    nama,
    telepon,
    alamat
) {

    document.getElementById(
        "edit_id_supplier"
    ).value = id;

    document.getElementById(
        "edit_nama_supplier"
    ).value = nama;

    document.getElementById(
        "edit_telepon"
    ).value = telepon;

    document.getElementById(
        "edit_alamat"
    ).value = alamat;

    document.getElementById(
        "modalEdit"
    ).style.display = "flex";

}


function tutupModalEdit() {

    document.getElementById(
        "modalEdit"
    ).style.display = "none";

}


/* =========================================================
   MODAL BARANG SUPPLIER
========================================================= */

function bukaModalBarang() {

    const modal =
        document.getElementById(
            "modalBarang"
        );

    if (modal) {
        modal.style.display = "flex";
    }

}


function tutupModalBarang() {

    const modal =
        document.getElementById(
            "modalBarang"
        );

    if (modal) {
        modal.style.display = "none";
    }

}


/* =========================================================
   HAPUS SUPPLIER
========================================================= */

function hapusSupplier(id) {

    const yakin = confirm(
        "Yakin ingin menghapus supplier ini?"
    );

    if (yakin) {

        window.location.href =
            "supplier.php?hapus=" + id;

    }

}


/* =========================================================
   HAPUS BARANG DARI SUPPLIER
========================================================= */

function hapusBarangSupplier(
    idSupplier,
    idBarang
) {

    const yakin = confirm(
        "Yakin ingin menghapus barang ini dari supplier?"
    );

    if (yakin) {

        window.location.href =
            "supplier.php?detail=" +
            idSupplier +
            "&hapus_barang_supplier=" +
            idBarang;

    }

}


/* =========================================================
   KLIK DI LUAR MODAL
========================================================= */

window.onclick = function(event) {

    const modalTambah =
        document.getElementById(
            "modalTambah"
        );

    const modalEdit =
        document.getElementById(
            "modalEdit"
        );

    const modalBarang =
        document.getElementById(
            "modalBarang"
        );


    if (event.target === modalTambah) {
        tutupModalTambah();
    }

    if (event.target === modalEdit) {
        tutupModalEdit();
    }

    if (
        modalBarang &&
        event.target === modalBarang
    ) {
        tutupModalBarang();
    }

};

</script>

</body>

</html>