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
   BUAT PEMBELIAN
========================================================= */

if (isset($_POST['buat_pembelian'])) {

    $id_supplier = (int) ($_POST['id_supplier'] ?? 0);

    $id_barang_list = $_POST['id_barang'] ?? [];
    $jumlah_list = $_POST['jumlah'] ?? [];
    $harga_list = $_POST['harga_beli'] ?? [];

    if ($id_supplier <= 0) {

        $pesan = "Silakan pilih supplier.";
        $tipePesan = "error";

    } elseif (count($id_barang_list) === 0) {

        $pesan = "Minimal harus ada satu barang.";
        $tipePesan = "error";

    } else {

        mysqli_begin_transaction($conn);

        try {

            $total_pembelian = 0;
            $detail = [];

            for ($i = 0; $i < count($id_barang_list); $i++) {

                $id_barang = (int) $id_barang_list[$i];
                $jumlah = (int) ($jumlah_list[$i] ?? 0);
                $harga_beli = (float) ($harga_list[$i] ?? 0);

                if ($id_barang <= 0) {
                    throw new Exception("Barang tidak valid.");
                }

                if ($jumlah <= 0) {
                    throw new Exception(
                        "Jumlah barang harus lebih dari 0."
                    );
                }

                if ($harga_beli < 0) {
                    throw new Exception(
                        "Harga beli tidak valid."
                    );
                }


                $cekSupplierBarang = mysqli_prepare(
                    $conn,
                    "SELECT harga_supplier
                     FROM supplier_barang
                     WHERE id_supplier = ?
                     AND id_barang = ?
                     LIMIT 1
                     FOR UPDATE"
                );

                mysqli_stmt_bind_param(
                    $cekSupplierBarang,
                    "ii",
                    $id_supplier,
                    $id_barang
                );

                mysqli_stmt_execute($cekSupplierBarang);

                $hasilSupplierBarang =
                    mysqli_stmt_get_result(
                        $cekSupplierBarang
                    );

                if (
                    mysqli_num_rows(
                        $hasilSupplierBarang
                    ) === 0
                ) {

                    mysqli_stmt_close(
                        $cekSupplierBarang
                    );

                    throw new Exception(
                        "Ada barang yang belum terdaftar pada supplier tersebut."
                    );
                }

                $dataSupplierBarang =
                    mysqli_fetch_assoc(
                        $hasilSupplierBarang
                    );

                mysqli_stmt_close(
                    $cekSupplierBarang
                );


                if ($harga_beli <= 0) {

                    $harga_beli =
                        (float)
                        $dataSupplierBarang[
                            'harga_supplier'
                        ];
                }


                $cekBarang = mysqli_prepare(
                    $conn,
                    "SELECT nama_barang
                     FROM barang
                     WHERE id_barang = ?
                     LIMIT 1
                     FOR UPDATE"
                );

                mysqli_stmt_bind_param(
                    $cekBarang,
                    "i",
                    $id_barang
                );

                mysqli_stmt_execute($cekBarang);

                $hasilBarang =
                    mysqli_stmt_get_result(
                        $cekBarang
                    );

                if (
                    mysqli_num_rows(
                        $hasilBarang
                    ) === 0
                ) {

                    mysqli_stmt_close(
                        $cekBarang
                    );

                    throw new Exception(
                        "Barang tidak ditemukan."
                    );
                }

                $dataBarang =
                    mysqli_fetch_assoc(
                        $hasilBarang
                    );

                mysqli_stmt_close(
                    $cekBarang
                );


                $subtotal =
                    $jumlah * $harga_beli;

                $total_pembelian +=
                    $subtotal;

                $detail[] = [
                    'id_barang' => $id_barang,
                    'jumlah' => $jumlah,
                    'harga_beli' => $harga_beli,
                    'subtotal' => $subtotal
                ];
            }


            /*
             * Generate kode pembelian
             */
            $kode_pembelian =
                "PB-" .
                date("YmdHis") .
                "-" .
                rand(100, 999);


            /*
             * Simpan pembelian
             */
            $stmtPembelian = mysqli_prepare(
                $conn,
                "INSERT INTO pembelian
                (
                    kode_pembelian,
                    tanggal_pembelian,
                    waktu_pemesanan,
                    id_supplier,
                    id_user,
                    total_pembelian,
                    status
                )
                VALUES (?, CURDATE(), NOW(), ?, ?, ?, 'menunggu')"
            );

            mysqli_stmt_bind_param(
                $stmtPembelian,
                "siid",
                $kode_pembelian,
                $id_supplier,
                $_SESSION['id_user'],
                $total_pembelian
            );

            if (
                !mysqli_stmt_execute(
                    $stmtPembelian
                )
            ) {

                throw new Exception(
                    "Gagal menyimpan data pembelian."
                );
            }

            $id_pembelian =
                mysqli_insert_id($conn);

            mysqli_stmt_close(
                $stmtPembelian
            );


            /*
             * Simpan detail pembelian
             */
            $stmtDetail = mysqli_prepare(
                $conn,
                "INSERT INTO detail_pembelian
                (
                    id_pembelian,
                    id_barang,
                    jumlah,
                    harga_beli,
                    subtotal
                )
                VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($detail as $item) {

                mysqli_stmt_bind_param(
                    $stmtDetail,
                    "iiidd",
                    $id_pembelian,
                    $item['id_barang'],
                    $item['jumlah'],
                    $item['harga_beli'],
                    $item['subtotal']
                );

                if (
                    !mysqli_stmt_execute(
                        $stmtDetail
                    )
                ) {

                    throw new Exception(
                        "Gagal menyimpan detail pembelian."
                    );
                }
            }

            mysqli_stmt_close(
                $stmtDetail
            );


            mysqli_commit($conn);

            $pesan =
                "Pembelian berhasil dibuat. Status masih Menunggu.";

            $tipePesan = "success";

        } catch (Exception $e) {

            mysqli_rollback($conn);

            $pesan = $e->getMessage();
            $tipePesan = "error";
        }
    }
}


/* =========================================================
   TERIMA BARANG
========================================================= */

if (isset($_GET['terima'])) {

    $id_pembelian = (int) $_GET['terima'];

    mysqli_begin_transaction($conn);

    try {

        /*
         * Kunci data pembelian
         */
        $stmtPembelian = mysqli_prepare(
            $conn,
            "SELECT id_pembelian, status
             FROM pembelian
             WHERE id_pembelian = ?
             FOR UPDATE"
        );

        mysqli_stmt_bind_param(
            $stmtPembelian,
            "i",
            $id_pembelian
        );

        mysqli_stmt_execute($stmtPembelian);

        $hasilPembelian =
            mysqli_stmt_get_result(
                $stmtPembelian
            );

        if (
            mysqli_num_rows(
                $hasilPembelian
            ) === 0
        ) {

            throw new Exception(
                "Data pembelian tidak ditemukan."
            );
        }

        $dataPembelian =
            mysqli_fetch_assoc(
                $hasilPembelian
            );

        mysqli_stmt_close(
            $stmtPembelian
        );


        /*
         * Barang hanya bisa diterima
         * kalau statusnya dikirim.
         */
        if (
            $dataPembelian['status']
            !== 'dikirim'
        ) {

            throw new Exception(
                "Barang hanya bisa diterima ketika status Dikirim."
            );
        }


        /*
         * Ambil detail pembelian
         */
        $stmtDetail = mysqli_prepare(
            $conn,
            "SELECT id_barang, jumlah
             FROM detail_pembelian
             WHERE id_pembelian = ?
             FOR UPDATE"
        );

        mysqli_stmt_bind_param(
            $stmtDetail,
            "i",
            $id_pembelian
        );

        mysqli_stmt_execute($stmtDetail);

        $hasilDetail =
            mysqli_stmt_get_result(
                $stmtDetail
            );


        /*
         * Tambahkan stok
         */
        $stmtUpdateStok = mysqli_prepare(
            $conn,
            "UPDATE barang
             SET stok = stok + ?
             WHERE id_barang = ?"
        );

        while (
            $detail =
            mysqli_fetch_assoc(
                $hasilDetail
            )
        ) {

            $jumlah =
                (int) $detail['jumlah'];

            $id_barang =
                (int) $detail['id_barang'];

            mysqli_stmt_bind_param(
                $stmtUpdateStok,
                "ii",
                $jumlah,
                $id_barang
            );

            if (
                !mysqli_stmt_execute(
                    $stmtUpdateStok
                )
            ) {

                throw new Exception(
                    "Gagal menambahkan stok barang."
                );
            }
        }

        mysqli_stmt_close(
            $stmtUpdateStok
        );

        mysqli_stmt_close(
            $stmtDetail
        );


        /*
         * Ubah status menjadi diterima
         */
        $stmtStatus = mysqli_prepare(
            $conn,
            "UPDATE pembelian
             SET status = 'diterima'
             WHERE id_pembelian = ?"
        );

        mysqli_stmt_bind_param(
            $stmtStatus,
            "i",
            $id_pembelian
        );

        mysqli_stmt_execute(
            $stmtStatus
        );

        mysqli_stmt_close(
            $stmtStatus
        );


        mysqli_commit($conn);

        $pesan =
            "Barang berhasil diterima dan stok sudah bertambah.";

        $tipePesan = "success";

    } catch (Exception $e) {

        mysqli_rollback($conn);

        $pesan = $e->getMessage();
        $tipePesan = "error";
    }
}


/* =========================================================
   AMBIL DATA SUPPLIER
========================================================= */

$supplierResult = mysqli_query(
    $conn,
    "SELECT id_supplier, nama_supplier
     FROM supplier
     ORDER BY nama_supplier ASC"
);


/* =========================================================
   AMBIL BARANG + HARGA SUPPLIER
========================================================= */

$barangSupplierResult = mysqli_query(
    $conn,
    "SELECT
        sb.id_supplier,
        sb.id_barang,
        sb.harga_supplier,
        b.nama_barang,
        b.satuan,
        b.stok
     FROM supplier_barang sb
     INNER JOIN barang b
        ON sb.id_barang = b.id_barang
     ORDER BY b.nama_barang ASC"
);

$barangSupplier = [];

while (
    $row =
    mysqli_fetch_assoc(
        $barangSupplierResult
    )
) {

    $barangSupplier[] = $row;
}


/* =========================================================
   AMBIL DATA PEMBELIAN
========================================================= */

$pembelianResult = mysqli_query(
    $conn,
    "SELECT
        p.id_pembelian,
        p.kode_pembelian,
        p.tanggal_pembelian,
        p.waktu_pemesanan,
        p.total_pembelian,
        p.status,
        s.nama_supplier,
        u.nama AS nama_user
     FROM pembelian p
     INNER JOIN supplier s
        ON p.id_supplier = s.id_supplier
     INNER JOIN users u
        ON p.id_user = u.id_user
     ORDER BY p.waktu_pemesanan DESC"
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Pembelian - SembakoKu</title>

    <link
        rel="stylesheet"
        href="../assets/layout.css"
    >

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


/* Gaya khusus halaman pembelian */

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

        .btn-warning {
            background: #f59e0b;
            color: #ffffff;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-success {
            background: #198754;
            color: #ffffff;
        }

        .btn-success:hover {
            background: #157347;
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
            min-width: 1000px;
            border-collapse: collapse;
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

        .kode {
            font-weight: bold;
            color: #111827;
            white-space: nowrap;
        }

        .harga {
            font-weight: bold;
            white-space: nowrap;
        }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-menunggu {
            background: #fef3c7;
            color: #92400e;
        }

        .status-dikirim {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-diterima {
            background: #dcfce7;
            color: #166534;
        }

        .aksi-text {
            font-size: 12px;
            line-height: 1.5;
        }

        .aksi-menunggu {
            color: #92400e;
        }

        .aksi-diterima {
            color: #166534;
            font-weight: bold;
        }

        .empty-row {
            text-align: center !important;
            padding: 32px !important;
            color: #6b7280 !important;
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
            width: 850px;
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

        .form-group select,
        .form-group input {
            width: 100%;
            height: 39px;
            padding: 9px 11px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            outline: none;
        }

        .form-group select:focus,
        .form-group input:focus,
        .detail-table input:focus,
        .detail-table select:focus {
            border-color: #1677d2;
            box-shadow: 0 0 0 2px rgba(22, 119, 210, 0.10);
        }

        .detail-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 22px;
            margin-bottom: 10px;
        }

        .detail-header strong {
            color: #111827;
            font-size: 14px;
        }

        .detail-table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .detail-table {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
        }

        .detail-table th,
        .detail-table td {
            padding: 9px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
            font-size: 12px;
        }

        .detail-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: bold;
            white-space: nowrap;
        }

        .detail-table tbody tr:last-child td {
            border-bottom: none;
        }

        .detail-table input,
        .detail-table select {
            width: 100%;
            min-width: 90px;
            height: 36px;
            padding: 7px 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            outline: none;
        }

        .hapus-baris {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 6px 9px;
            background: #dc2626;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .hapus-baris:hover {
            background: #b91c1c;
        }

        .total-box {
            text-align: right;
            margin-top: 18px;
            padding: 14px 0;
            color: #374151;
            font-size: 14px;
        }

        .total-box strong {
            margin-left: 8px;
            color: #111827;
            font-size: 20px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        @media (max-width: 768px) {

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .modal {
                padding: 10px;
            }

            .modal-content {
                padding: 17px;
            }

            .detail-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .detail-header .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {

            .page-header .btn {
                width: 100%;
            }

            .modal-footer {
                flex-direction: column-reverse;
            }

            .modal-footer .btn {
                width: 100%;
            }

            .total-box {
                text-align: left;
            }

            .total-box strong {
                display: block;
                margin: 5px 0 0;
            }
        }

    
</style>

</head>


<body class="admin">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>

<aside class="drawer" id="drawer">
    <div class="drawer-header">
        <div class="drawer-brand">
            <div class="drawer-logo"><img src="../assets/SembakoKu (1).png" alt="Toko Malaya"></div>
            <div class="drawer-brand-text"><strong>Toko Malaya</strong><span>Panel Admin / Pemilik</span></div>
        </div>
        <button type="button" class="drawer-close" onclick="closeMenu()">×</button>
    </div>
    <div class="drawer-user">
        <div class="drawer-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="drawer-user-info"><strong><?= htmlspecialchars($_SESSION['nama'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></strong><span>Admin / Pemilik</span></div>
    </div>
    <nav class="drawer-menu">
        <div class="menu-label">MENU UTAMA</div>
        <a href="dashboard.php" class="menu-link"><span class="menu-icon">🏠</span>Dashboard</a>
        <a href="produk.php" class="menu-link"><span class="menu-icon">📦</span>Produk</a>
        <a href="kategori.php" class="menu-link"><span class="menu-icon">🏷️</span>Kategori</a>
        <a href="pengguna.php" class="menu-link"><span class="menu-icon">👥</span>Pengguna</a>
        <a href="pesanan.php" class="menu-link"><span class="menu-icon">🛒</span>Pesanan Online</a>
        <a href="stok.php" class="menu-link"><span class="menu-icon">📦</span>Stok</a>
        <a href="supplier.php" class="menu-link"><span class="menu-icon">🚚</span>Supplier</a>
        <a href="pembelian.php" class="menu-link active"><span class="menu-icon">🧾</span>Pembelian Supplier</a>
        <a href="laporan.php" class="menu-link"><span class="menu-icon">📊</span>Laporan</a>
        <div class="menu-label">AKUN</div>
        <a href="profil.php" class="menu-link"><span class="menu-icon">👤</span>Profil</a>
    </nav>
    <div class="drawer-footer"><a href="../auth/logout.php" class="logout-link" onclick="return confirm('Apakah kamu yakin ingin keluar?');"><span class="menu-icon">🚪</span>Keluar</a></div>
</aside>

<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="hamburger" onclick="toggleMenu()" aria-label="Buka menu">☰</button>
        <a href="dashboard.php" class="brand">
            <div class="brand-logo"><img src="../assets/SembakoKu (1).png" alt="Toko Malaya"></div>
            <div class="brand-text"><strong>Toko Malaya</strong><span>Panel Admin</span></div>
        </a>
    </div>
    <div class="top-user">
        <div class="top-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="top-user-info"><strong><?= htmlspecialchars($_SESSION['nama'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></strong><span>Admin / Pemilik</span></div>
    </div>
</header>

<main class="main">


    <div class="page-header">

        <div>

            <h1>Pembelian</h1>

            <p>
                Kelola pemesanan barang dari supplier
            </p>

        </div>


        <button
            type="button"
            class="btn btn-primary"
            onclick="bukaModal()"
        >
            + Buat Pembelian
        </button>

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
         TABLE PEMBELIAN
    ================================================== -->

    <section class="table-card">

        <div class="table-wrapper">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>No</th>
                        <th>Kode Pembelian</th>
                        <th>Tanggal</th>
                        <th>Waktu Pesan</th>
                        <th>Supplier</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Aksi</th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (
                        mysqli_num_rows(
                            $pembelianResult
                        ) > 0
                    ): ?>

                        <?php $no = 1; ?>

                        <?php while (
                            $pembelian =
                            mysqli_fetch_assoc(
                                $pembelianResult
                            )
                        ): ?>

                            <tr>

                                <td>
                                    <?= $no++ ?>
                                </td>


                                <td>

                                    <span class="kode">

                                        <?= htmlspecialchars(
                                            $pembelian[
                                                'kode_pembelian'
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= date(
                                        "d-m-Y",
                                        strtotime(
                                            $pembelian[
                                                'tanggal_pembelian'
                                            ]
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= date(
                                        "d-m-Y H:i",
                                        strtotime(
                                            $pembelian[
                                                'waktu_pemesanan'
                                            ]
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $pembelian[
                                            'nama_supplier'
                                        ]
                                    ) ?>

                                </td>


                                <td class="harga">

                                    Rp
                                    <?= number_format(
                                        $pembelian[
                                            'total_pembelian'
                                        ],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                </td>


                                <td>

                                    <?php if (
                                        $pembelian[
                                            'status'
                                        ] === 'menunggu'
                                    ): ?>

                                        <span
                                            class="status status-menunggu"
                                        >
                                            Menunggu
                                        </span>

                                    <?php elseif (
                                        $pembelian[
                                            'status'
                                        ] === 'dikirim'
                                    ): ?>

                                        <span
                                            class="status status-dikirim"
                                        >
                                            Dikirim
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="status status-diterima"
                                        >
                                            Diterima
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if (
                                        $pembelian[
                                            'status'
                                        ] === 'dikirim'
                                    ): ?>

                                        <button
                                            type="button"
                                            class="btn btn-success"
                                            onclick="terimaBarang(
                                                <?= (int)$pembelian[
                                                    'id_pembelian'
                                                ] ?>
                                            )"
                                        >
                                            Terima Barang
                                        </button>

                                    <?php elseif (
                                        $pembelian[
                                            'status'
                                        ] === 'menunggu'
                                    ): ?>

                                        <form method="POST" style="display:inline"
                                              onsubmit="return confirm('Konfirmasi bahwa supplier sudah mengirim barang ini?')">
                                            <input type="hidden" name="id_pembelian_kirim"
                                                   value="<?= (int)$pembelian['id_pembelian'] ?>">
                                            <button type="submit" name="tandai_dikirim"
                                                    class="btn btn-warning">
                                                Tandai Dikirim
                                            </button>
                                        </form>

                                    <?php else: ?>

                                        <span
                                            class="aksi-text aksi-diterima"
                                        >
                                            ✓ Sudah diterima
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="8"
                                class="empty-row"
                            >
                                Belum ada data pembelian.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>


<!-- =====================================================
     MODAL BUAT PEMBELIAN
===================================================== -->

<div
    class="modal"
    id="modalPembelian"
>

    <div class="modal-content">


        <div class="modal-header">

            <h2>
                Buat Pembelian
            </h2>

            <span
                class="close"
                onclick="tutupModal()"
            >
                &times;
            </span>

        </div>


        <form
            method="POST"
            id="formPembelian"
        >


            <!-- SUPPLIER -->

            <div class="form-group">

                <label>
                    Supplier
                </label>

                <select
                    name="id_supplier"
                    id="supplier"
                    onchange="ubahSupplier()"
                    required
                >

                    <option value="">
                        -- Pilih Supplier --
                    </option>

                    <?php while (
                        $supplier =
                        mysqli_fetch_assoc(
                            $supplierResult
                        )
                    ): ?>

                        <option
                            value="<?= (int)$supplier[
                                'id_supplier'
                            ] ?>"
                        >
                            <?= htmlspecialchars(
                                $supplier[
                                    'nama_supplier'
                                ]
                            ) ?>
                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <!-- DETAIL -->

            <div class="detail-header">

                <strong>
                    Daftar Barang
                </strong>

                <button
                    type="button"
                    class="btn btn-primary"
                    onclick="tambahBaris()"
                >
                    + Tambah Barang
                </button>

            </div>


            <div class="detail-table-wrapper">

                <table class="detail-table">

                    <thead>

                        <tr>

                            <th style="width:35%;">
                                Barang
                            </th>

                            <th style="width:15%;">
                                Jumlah
                            </th>

                            <th style="width:20%;">
                                Harga Beli
                            </th>

                            <th style="width:20%;">
                                Subtotal
                            </th>

                            <th style="width:10%;">
                                Aksi
                            </th>

                        </tr>

                    </thead>


                    <tbody id="detailBody">

                    </tbody>

                </table>

            </div>


            <!-- TOTAL -->

            <div class="total-box">

                Total Pembelian:

                <strong id="totalPembelian">
                    Rp 0
                </strong>

            </div>


            <!-- FOOTER -->

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="tutupModal()"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    name="buat_pembelian"
                    class="btn btn-primary"
                >
                    Simpan Pembelian
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =====================================================
   DATA BARANG DARI PHP
===================================================== */

const barangSupplier =
    <?= json_encode($barangSupplier) ?>;


/* =====================================================
   MODAL
===================================================== */

function bukaModal() {

    document.getElementById(
        "modalPembelian"
    ).style.display = "flex";

    document.getElementById(
        "detailBody"
    ).innerHTML = "";

    document.getElementById(
        "totalPembelian"
    ).innerText = "Rp 0";

    document.getElementById(
        "supplier"
    ).value = "";

}


function tutupModal() {

    document.getElementById(
        "modalPembelian"
    ).style.display = "none";

}


/* =====================================================
   TAMBAH BARIS BARANG
===================================================== */

function tambahBaris() {

    const supplier =
        document.getElementById(
            "supplier"
        ).value;

    if (supplier === "") {

        alert(
            "Pilih supplier terlebih dahulu."
        );

        return;
    }


    const tbody =
        document.getElementById(
            "detailBody"
        );


    const tr =
        document.createElement(
            "tr"
        );


    let options =
        '<option value="">-- Pilih Barang --</option>';


    barangSupplier.forEach(
        function(item) {

            if (
                String(
                    item.id_supplier
                ) === String(supplier)
            ) {

                options += `
                    <option
                        value="${item.id_barang}"
                        data-harga="${item.harga_supplier}"
                    >
                        ${escapeHtml(
                            item.nama_barang
                        )}
                        (${escapeHtml(
                            item.satuan
                        )})
                    </option>
                `;

            }

        }
    );


    tr.innerHTML = `

        <td>

            <select
                name="id_barang[]"
                class="barang-select"
                onchange="pilihBarang(this)"
                required
            >

                ${options}

            </select>

        </td>


        <td>

            <input
                type="number"
                name="jumlah[]"
                class="jumlah-input"
                min="1"
                value="1"
                oninput="hitungTotal()"
                required
            >

        </td>


        <td>

            <input
                type="number"
                name="harga_beli[]"
                class="harga-input"
                min="0"
                step="0.01"
                value="0"
                oninput="hitungTotal()"
                required
            >

        </td>


        <td class="subtotal">
            Rp 0
        </td>


        <td>

            <button
                type="button"
                class="hapus-baris"
                onclick="hapusBaris(this)"
            >
                X
            </button>

        </td>

    `;


    tbody.appendChild(tr);

}


/* =====================================================
   PILIH BARANG
===================================================== */

function pilihBarang(select) {

    const option =
        select.options[
            select.selectedIndex
        ];


    const harga =
        option.getAttribute(
            "data-harga"
        );


    const row =
        select.closest("tr");


    const hargaInput =
        row.querySelector(
            ".harga-input"
        );


    if (harga !== null) {

        hargaInput.value =
            harga;

    }


    hitungTotal();

}


/* =====================================================
   HITUNG TOTAL
===================================================== */

function hitungTotal() {

    const rows =
        document.querySelectorAll(
            "#detailBody tr"
        );


    let total = 0;


    rows.forEach(
        function(row) {

            const jumlah =
                parseFloat(
                    row.querySelector(
                        ".jumlah-input"
                    ).value
                ) || 0;


            const harga =
                parseFloat(
                    row.querySelector(
                        ".harga-input"
                    ).value
                ) || 0;


            const subtotal =
                jumlah * harga;


            row.querySelector(
                ".subtotal"
            ).innerText =
                formatRupiah(
                    subtotal
                );


            total += subtotal;

        }
    );


    document.getElementById(
        "totalPembelian"
    ).innerText =
        formatRupiah(
            total
        );

}


/* =====================================================
   HAPUS BARIS
===================================================== */

function hapusBaris(button) {

    const tbody =
        document.getElementById(
            "detailBody"
        );


    const rows =
        tbody.querySelectorAll(
            "tr"
        );


    if (rows.length <= 1) {

        alert(
            "Minimal harus ada satu barang."
        );

        return;
    }


    button
        .closest("tr")
        .remove();

    hitungTotal();

}


/* =====================================================
   GANTI SUPPLIER
===================================================== */

function ubahSupplier() {

    const tbody =
        document.getElementById(
            "detailBody"
        );


    tbody.innerHTML = "";

    tambahBaris();

}


/* =====================================================
   TERIMA BARANG
===================================================== */

function terimaBarang(id) {

    const yakin =
        confirm(
            "Yakin barang dari pembelian ini sudah diterima?\n\n" +
            "Stok barang akan langsung bertambah."
        );


    if (yakin) {

        window.location.href =
            "pembelian.php?terima=" +
            id;

    }

}


/* =====================================================
   FORMAT RUPIAH
===================================================== */

function formatRupiah(angka) {

    return "Rp " +
        Number(
            angka
        ).toLocaleString(
            "id-ID"
        );

}


/* =====================================================
   ESCAPE HTML
===================================================== */

function escapeHtml(text) {

    const div =
        document.createElement(
            "div"
        );

    div.textContent =
        text;

    return div.innerHTML;

}


/* =====================================================
   KLIK DI LUAR MODAL
===================================================== */

window.onclick =
    function(event) {

        const modal =
            document.getElementById(
                "modalPembelian"
            );


        if (
            event.target === modal
        ) {

            tutupModal();

        }

    };



function openMenu() {
    document.getElementById('drawer').classList.add('show');
    document.getElementById('menuOverlay').classList.add('show');
    document.body.classList.add('menu-open');
}
function closeMenu() {
    document.getElementById('drawer').classList.remove('show');
    document.getElementById('menuOverlay').classList.remove('show');
    document.body.classList.remove('menu-open');
}
function toggleMenu() {
    if (document.getElementById('drawer').classList.contains('show')) closeMenu();
    else openMenu();
}
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeMenu();
});
</script>

</body>

</html>