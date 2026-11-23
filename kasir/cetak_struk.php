<?php

session_start();

require_once "../config/database.php";


// ======================================
// CEK LOGIN
// ======================================

if (
    !isset($_SESSION['id_user']) ||
    $_SESSION['role'] !== 'kasir'
) {
    header("Location: ../auth/login.php");
    exit;
}


// ======================================
// AMBIL ID TRANSAKSI
// ======================================

$id_penjualan =
    (int)($_GET['id'] ?? 0);


if ($id_penjualan <= 0) {

    die("Transaksi tidak ditemukan.");

}


// ======================================
// AMBIL DATA PENJUALAN
// ======================================

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        p.id_penjualan,
        p.kode_penjualan,
        p.tanggal_penjualan,
        p.waktu_penjualan,
        p.total_penjualan,
        p.bayar,
        p.kembalian,
        u.nama AS nama_kasir
     FROM penjualan p
     JOIN users u
        ON p.id_user = u.id_user
     WHERE p.id_penjualan = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_penjualan
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);


if (
    mysqli_num_rows($result) === 0
) {

    die("Transaksi tidak ditemukan.");

}


$penjualan =
    mysqli_fetch_assoc($result);


// ======================================
// AMBIL DETAIL
// ======================================

$stmtDetail = mysqli_prepare(
    $conn,
    "SELECT
        d.jumlah,
        d.harga_jual,
        d.subtotal,
        b.nama_barang
     FROM detail_penjualan d
     JOIN barang b
        ON d.id_barang = b.id_barang
     WHERE d.id_penjualan = ?
     ORDER BY d.id_detail_penjualan ASC"
);

mysqli_stmt_bind_param(
    $stmtDetail,
    "i",
    $id_penjualan
);

mysqli_stmt_execute(
    $stmtDetail
);

$detailResult =
    mysqli_stmt_get_result(
        $stmtDetail
);


// ======================================
// FORMAT RUPIAH
// ======================================

function rupiah($angka)
{
    return 'Rp ' .
        number_format(
            $angka,
            0,
            ',',
            '.'
        );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <title>
        Struk <?= htmlspecialchars(
            $penjualan['kode_penjualan']
        ) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            background: #eee;

            font-family:
                "Courier New",
                monospace;

        }

        .struk {

            width: 80mm;

            margin: 20px auto;

            background: white;

            padding: 5mm;

            font-size: 12px;

        }

        .center {
            text-align: center;
        }

        .center h2 {

            margin:
                0 0 3px;

            font-size: 20px;

        }

        .center p {

            margin:
                2px 0;

        }

        .garis {

            border-top:
                1px dashed #000;

            margin:
                8px 0;

        }

        .info {

            font-size: 11px;

            line-height: 1.5;

        }

        .item {

            margin-bottom: 7px;

        }

        .item-name {

            font-weight: bold;

        }

        .item-detail {

            display: flex;

            justify-content:
                space-between;

            gap: 10px;

        }

        .total-row {

            display: flex;

            justify-content:
                space-between;

            margin: 4px 0;

        }

        .total-row.bold {

            font-weight: bold;

            font-size: 14px;

        }

        .footer {

            text-align: center;

            margin-top: 12px;

        }

        .btn-print {

            display: block;

            width: 80mm;

            margin: 15px auto;

            padding: 12px;

            border: none;

            background: #198754;

            color: white;

            cursor: pointer;

            font-weight: bold;

        }

        @media print {

            @page {

                size: 80mm auto;

                margin: 0;

            }

            body {

                background: white;

            }

            .struk {

                width: 80mm;

                margin: 0;

                padding: 4mm;

            }

            .btn-print {

                display: none;

            }

        }

    </style>

</head>

<body>


<div class="struk">


    <!-- HEADER -->

    <div class="center">

        <h2>SEMBAKOKU</h2>

        <p>TOKO SEMBAKO</p>

        <p>Selamat Berbelanja</p>

    </div>


    <div class="garis"></div>


    <!-- INFORMASI TRANSAKSI -->

    <div class="info">

        <div>

            No:
            <?= htmlspecialchars(
                $penjualan['kode_penjualan']
            ) ?>

        </div>

        <div>

            Tanggal:
            <?= date(
                'd/m/Y H:i',
                strtotime(
                    $penjualan[
                        'waktu_penjualan'
                    ]
                )
            ) ?>

        </div>

        <div>

            Kasir:
            <?= htmlspecialchars(
                $penjualan['nama_kasir']
            ) ?>

        </div>

    </div>


    <div class="garis"></div>


    <!-- BARANG -->

    <?php while (
        $item =
        mysqli_fetch_assoc(
            $detailResult
        )
    ): ?>

        <div class="item">

            <div class="item-name">

                <?= htmlspecialchars(
                    $item['nama_barang']
                ) ?>

            </div>

            <div class="item-detail">

                <span>

                    <?= $item['jumlah'] ?>
                    x
                    <?= rupiah(
                        $item['harga_jual']
                    ) ?>

                </span>

                <span>

                    <?= rupiah(
                        $item['subtotal']
                    ) ?>

                </span>

            </div>

        </div>

    <?php endwhile; ?>


    <div class="garis"></div>


    <!-- TOTAL -->

    <div class="total-row bold">

        <span>TOTAL</span>

        <span>

            <?= rupiah(
                $penjualan[
                    'total_penjualan'
                ]
            ) ?>

        </span>

    </div>


    <div class="total-row">

        <span>BAYAR</span>

        <span>

            <?= rupiah(
                $penjualan['bayar']
            ) ?>

        </span>

    </div>


    <div class="total-row">

        <span>KEMBALI</span>

        <span>

            <?= rupiah(
                $penjualan['kembalian']
            ) ?>

        </span>

    </div>


    <div class="garis"></div>


    <!-- FOOTER -->

    <div class="footer">

        <p>
            TERIMA KASIH
        </p>

        <p>
            Selamat Berbelanja
        </p>

    </div>

</div>


<button
    class="btn-print"
    onclick="window.print()"
>
    🖨 CETAK STRUK
</button>


<script>

window.onload = function() {

    window.print();

};

</script>


</body>

</html>