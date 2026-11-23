<?php

session_start();

include "../config/database.php";

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'kasir') {
    header("Location: ../auth/login.php");
    exit;
}

function rupiah($angka)
{
    return "Rp " . number_format($angka, 0, ',', '.');
}

$id_pesanan = (int) ($_GET['id'] ?? 0);

if ($id_pesanan <= 0) {
    header("Location: pesanan.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA PESANAN
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        p.id_pesanan,
        p.id_user,
        p.tanggal_pesanan,
        p.total_harga,
        p.alamat_pengiriman,
        p.metode_pembayaran,
        p.status,
        u.nama,
        u.email
     FROM pesanan p
     INNER JOIN users u
        ON p.id_user = u.id_user
     WHERE p.id_pesanan = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_pesanan
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$pesanan = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$pesanan) {
    header("Location: pesanan.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| AMBIL DETAIL PESANAN
|--------------------------------------------------------------------------
*/
$detail_pesanan = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        dp.id_detail,
        dp.id_barang,
        dp.jumlah,
        dp.harga,
        dp.subtotal,
        b.nama_barang,
        b.kode_barang,
        b.satuan,
        b.gambar
     FROM detail_pesanan dp
     INNER JOIN barang b
        ON dp.id_barang = b.id_barang
     WHERE dp.id_pesanan = ?
     ORDER BY dp.id_detail ASC"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_pesanan
);

mysqli_stmt_execute($stmt);

$result_detail = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result_detail)) {
    $detail_pesanan[] = $row;
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| HITUNG JUMLAH ITEM
|--------------------------------------------------------------------------
*/
$total_item = 0;

foreach ($detail_pesanan as $detail) {
    $total_item += (int) $detail['jumlah'];
}

/*
|--------------------------------------------------------------------------
| LABEL STATUS
|--------------------------------------------------------------------------
*/
$status_label = [
    'menunggu'   => 'MENUNGGU',
    'diproses'   => 'DIPROSES',
    'dikirim'    => 'DIKIRIM',
    'selesai'    => 'SELESAI',
    'dibatalkan' => 'DIBATALKAN'
];

$status = $status_label[$pesanan['status']]
    ?? strtoupper($pesanan['status']);

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Struk #<?= str_pad(
            $id_pesanan,
            5,
            '0',
            STR_PAD_LEFT
        ); ?>
        - SembakoKu
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #f3f4f6;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            color: #111827;
        }

        .page-wrapper {
            width: 100%;
            max-width: 620px;
            margin: 0 auto;
        }

        .print-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn {
            border: 0;
            border-radius: 7px;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-print {
            background: #198754;
            color: #ffffff;
        }

        .btn-print:hover {
            background: #157347;
        }

        .btn-back {
            background: #ffffff;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .receipt {
            background: #ffffff;
            width: 100%;
            padding: 35px 38px;
            box-shadow:
                0 4px 18px rgba(0, 0, 0, 0.08);
        }

        .receipt-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px dashed #9ca3af;
        }

        .logo-box {
            width: 58px;
            height: 58px;
            margin: 0 auto 10px;
            border-radius: 12px;
            background: #1677d2;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-box img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .store-name {
            margin: 0;
            font-size: 23px;
            font-weight: bold;
            color: #153b63;
        }

        .store-description {
            margin-top: 5px;
            font-size: 12px;
            color: #6b7280;
        }

        .receipt-title {
            margin-top: 16px;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .order-info {
            padding: 18px 0;
            border-bottom: 1px dashed #9ca3af;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 7px;
            font-size: 12px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            color: #6b7280;
        }

        .info-value {
            color: #111827;
            font-weight: 600;
            text-align: right;
        }

        .customer-section {
            padding: 18px 0;
            border-bottom: 1px dashed #9ca3af;
        }

        .section-title {
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: bold;
            color: #6b7280;
            text-transform: uppercase;
        }

        .customer-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .customer-email {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .customer-address {
            font-size: 12px;
            color: #374151;
            line-height: 1.5;
        }

        .items {
            padding: 18px 0;
            border-bottom: 1px dashed #9ca3af;
        }

        .item {
            margin-bottom: 15px;
        }

        .item:last-child {
            margin-bottom: 0;
        }

        .item-name {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 5px;
        }

        .item-code {
            font-size: 10px;
            color: #9ca3af;
            margin-bottom: 5px;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            font-size: 12px;
        }

        .item-price {
            color: #6b7280;
        }

        .item-subtotal {
            font-weight: bold;
            color: #111827;
            white-space: nowrap;
        }

        .summary {
            padding: 18px 0;
            border-bottom: 1px dashed #9ca3af;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .summary-row:last-child {
            margin-bottom: 0;
        }

        .summary-label {
            color: #6b7280;
        }

        .summary-value {
            font-weight: 600;
        }

        .total-row {
            margin-top: 13px;
            padding-top: 13px;
            border-top: 1px solid #e5e7eb;
        }

        .total-row .summary-label {
            font-size: 14px;
            color: #111827;
            font-weight: bold;
        }

        .total-row .summary-value {
            font-size: 18px;
            color: #198754;
            font-weight: bold;
        }

        .status-section {
            padding: 17px 0;
            text-align: center;
        }

        .status-label {
            display: inline-block;
            padding: 7px 15px;
            border-radius: 20px;
            background: #dbeafe;
            color: #1e40af;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .receipt-footer {
            padding-top: 18px;
            text-align: center;
            border-top: 1px dashed #9ca3af;
        }

        .thank-you {
            font-size: 13px;
            font-weight: bold;
            color: #153b63;
            margin-bottom: 5px;
        }

        .footer-text {
            font-size: 11px;
            color: #9ca3af;
            line-height: 1.5;
        }

        @media (max-width: 600px) {

            body {
                padding: 12px;
            }

            .receipt {
                padding: 25px 20px;
            }

            .print-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }

        @media print {

            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                background: #ffffff;
                padding: 0;
            }

            .page-wrapper {
                max-width: none;
            }

            .print-actions {
                display: none;
            }

            .receipt {
                box-shadow: none;
                padding: 10px;
                max-width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="page-wrapper">

    <div class="print-actions">

        <a
            href="pesanan.php"
            class="btn btn-back"
        >
            ← Kembali ke Pesanan
        </a>

        <button
            type="button"
            class="btn btn-print"
            onclick="window.print()"
        >
            🖨 Cetak Struk
        </button>

    </div>

    <div class="receipt">

        <!-- HEADER -->

        <div class="receipt-header">

            <div class="logo-box">

                <img
                    src="../assets/logo-sembakoku.png"
                    alt="SembakoKu"
                >

            </div>

            <h1 class="store-name">
                SembakoKu
            </h1>

            <div class="store-description">
                Toko Sembako & Kebutuhan Harian
            </div>

            <div class="receipt-title">
                STRUK BELANJA
            </div>

        </div>


        <!-- INFORMASI PESANAN -->

        <div class="order-info">

            <div class="info-row">

                <span class="info-label">
                    No. Pesanan
                </span>

                <span class="info-value">

                    #<?= str_pad(
                        $id_pesanan,
                        5,
                        '0',
                        STR_PAD_LEFT
                    ); ?>

                </span>

            </div>

            <div class="info-row">

                <span class="info-label">
                    Tanggal
                </span>

                <span class="info-value">

                    <?= date(
                        'd-m-Y H:i',
                        strtotime(
                            $pesanan['tanggal_pesanan']
                        )
                    ); ?>

                </span>

            </div>

            <div class="info-row">

                <span class="info-label">
                    Pembayaran
                </span>

                <span class="info-value">

                    <?= htmlspecialchars(
                        $pesanan['metode_pembayaran']
                    ); ?>

                </span>

            </div>

        </div>


        <!-- DATA PELANGGAN -->

        <div class="customer-section">

            <div class="section-title">
                Pelanggan
            </div>

            <div class="customer-name">

                <?= htmlspecialchars(
                    $pesanan['nama']
                ); ?>

            </div>

            <div class="customer-email">

                <?= htmlspecialchars(
                    $pesanan['email']
                ); ?>

            </div>

            <div class="section-title">
                Alamat Pengiriman
            </div>

            <div class="customer-address">

                <?= nl2br(
                    htmlspecialchars(
                        $pesanan['alamat_pengiriman']
                    )
                ); ?>

            </div>

        </div>


        <!-- DAFTAR BARANG -->

        <div class="items">

            <?php if (empty($detail_pesanan)): ?>

                <div
                    style="
                        text-align:center;
                        font-size:12px;
                        color:#6b7280;
                    "
                >
                    Tidak ada detail barang.
                </div>

            <?php else: ?>

                <?php foreach ($detail_pesanan as $detail): ?>

                    <div class="item">

                        <div class="item-name">

                            <?= htmlspecialchars(
                                $detail['nama_barang']
                            ); ?>

                        </div>

                        <div class="item-code">

                            Kode:
                            <?= htmlspecialchars(
                                $detail['kode_barang']
                            ); ?>

                        </div>

                        <div class="item-row">

                            <span class="item-price">

                                <?= (int) $detail['jumlah']; ?>

                                <?= htmlspecialchars(
                                    $detail['satuan']
                                ); ?>

                                ×

                                <?= rupiah(
                                    $detail['harga']
                                ); ?>

                            </span>

                            <span class="item-subtotal">

                                <?= rupiah(
                                    $detail['subtotal']
                                ); ?>

                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>


        <!-- RINGKASAN -->

        <div class="summary">

            <div class="summary-row">

                <span class="summary-label">
                    Jumlah Item
                </span>

                <span class="summary-value">

                    <?= $total_item; ?> item

                </span>

            </div>

            <div class="summary-row total-row">

                <span class="summary-label">
                    TOTAL
                </span>

                <span class="summary-value">

                    <?= rupiah(
                        $pesanan['total_harga']
                    ); ?>

                </span>

            </div>

        </div>


        <!-- STATUS -->

        <div class="status-section">

            <span class="status-label">

                STATUS:
                <?= $status; ?>

            </span>

        </div>


        <!-- FOOTER -->

        <div class="receipt-footer">

            <div class="thank-you">
                Terima kasih telah berbelanja
            </div>

            <div class="footer-text">

                SembakoKu<br>

                Belanja kebutuhan harian lebih mudah.

            </div>

        </div>

    </div>

</div>

</body>

</html>