<?php

session_start();

require_once "../config/database.php";

header('Content-Type: application/json');


// ======================================
// CEK LOGIN KASIR
// ======================================

if (
    !isset($_SESSION['id_user']) ||
    $_SESSION['role'] !== 'kasir'
) {

    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak.'
    ]);

    exit;
}


// ======================================
// AMBIL DATA JSON
// ======================================

$input =
    json_decode(
        file_get_contents(
            "php://input"
        ),
        true
    );


if (!$input) {

    echo json_encode([
        'success' => false,
        'message' => 'Data transaksi tidak valid.'
    ]);

    exit;
}


$items =
    $input['items'] ?? [];


$bayar =
    (float) (
        $input['bayar'] ?? 0
    );


// ======================================
// VALIDASI
// ======================================

if (empty($items)) {

    echo json_encode([
        'success' => false,
        'message' => 'Belum ada barang dalam transaksi.'
    ]);

    exit;
}


if ($bayar < 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Nominal pembayaran tidak valid.'
    ]);

    exit;
}


// ======================================
// MULAI TRANSAKSI
// ======================================

mysqli_begin_transaction($conn);


try {

    $total = 0;

    $barangTransaksi = [];


    // ==================================
    // AMBIL DATA BARANG DARI DATABASE
    // ==================================

    foreach ($items as $item) {

        $id_barang =
            (int) (
                $item['id_barang'] ?? 0
            );


        $jumlah =
            (int) (
                $item['jumlah'] ?? 0
            );


        if (
            $id_barang <= 0 ||
            $jumlah <= 0
        ) {

            throw new Exception(
                'Data barang atau jumlah tidak valid.'
            );
        }


        // Lock barang

        $stmt =
            mysqli_prepare(
                $conn,
                "SELECT
                    id_barang,
                    kode_barang,
                    nama_barang,
                    harga_jual,
                    stok
                 FROM barang
                 WHERE id_barang = ?
                 FOR UPDATE"
            );


        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id_barang
        );


        mysqli_stmt_execute(
            $stmt
        );


        $result =
            mysqli_stmt_get_result(
                $stmt
            );


        if (
            mysqli_num_rows(
                $result
            ) === 0
        ) {

            throw new Exception(
                'Barang tidak ditemukan.'
            );
        }


        $barang =
            mysqli_fetch_assoc(
                $result
            );


        // ==================================
        // CEK STOK SAAT SIMPAN
        // ==================================

        if (
            (int)$barang['stok']
            < $jumlah
        ) {

            throw new Exception(
                'Stok ' .
                $barang['nama_barang'] .
                ' tidak mencukupi. ' .
                'Stok tersedia: ' .
                $barang['stok'] .
                ', jumlah terjual: ' .
                $jumlah .
                '.'
            );
        }


        $harga_jual =
            (float)$barang['harga_jual'];


        $subtotal =
            $harga_jual * $jumlah;


        $total += $subtotal;


        $barangTransaksi[] = [

            'id_barang' =>
                $id_barang,

            'jumlah' =>
                $jumlah,

            'harga_jual' =>
                $harga_jual,

            'subtotal' =>
                $subtotal

        ];

    }


    // ==================================
    // CEK PEMBAYARAN
    // ==================================

    if ($bayar < $total) {

        throw new Exception(
            'Pembayaran kurang. Total transaksi: Rp ' .
            number_format(
                $total,
                0,
                ',',
                '.'
            )
        );
    }


    $kembalian =
        $bayar - $total;


    // ==================================
    // KODE PENJUALAN
    // ==================================

    $kode_penjualan =
        'PJ-' .
        date('YmdHis') .
        '-' .
        rand(100, 999);


    $tanggal_penjualan =
        date('Y-m-d');


    $id_user =
        (int)$_SESSION['id_user'];


    // ==================================
    // SIMPAN PENJUALAN
    // ==================================

    $stmt =
        mysqli_prepare(
            $conn,
            "INSERT INTO penjualan
            (
                kode_penjualan,
                tanggal_penjualan,
                waktu_penjualan,
                id_user,
                total_penjualan,
                bayar,
                kembalian
            )
            VALUES
            (?, ?, NOW(), ?, ?, ?, ?)"
        );


    mysqli_stmt_bind_param(
        $stmt,
        "ssiddd",
        $kode_penjualan,
        $tanggal_penjualan,
        $id_user,
        $total,
        $bayar,
        $kembalian
    );


    if (
        !mysqli_stmt_execute($stmt)
    ) {

        throw new Exception(
            'Gagal menyimpan transaksi penjualan.'
        );
    }


    $id_penjualan =
        mysqli_insert_id($conn);


    // ==================================
    // PREPARE DETAIL
    // ==================================

    $stmtDetail =
        mysqli_prepare(
            $conn,
            "INSERT INTO detail_penjualan
            (
                id_penjualan,
                id_barang,
                jumlah,
                harga_jual,
                subtotal
            )
            VALUES (?, ?, ?, ?, ?)"
        );


    // ==================================
    // PREPARE UPDATE STOK
    // ==================================

    $stmtStok =
        mysqli_prepare(
            $conn,
            "UPDATE barang
             SET stok = stok - ?
             WHERE id_barang = ?"
        );


    // ==================================
    // DETAIL + STOK
    // ==================================

    foreach (
        $barangTransaksi as $item
    ) {

        $id_barang =
            $item['id_barang'];

        $jumlah =
            $item['jumlah'];

        $harga_jual =
            $item['harga_jual'];

        $subtotal =
            $item['subtotal'];


        // DETAIL

        mysqli_stmt_bind_param(
            $stmtDetail,
            "iiidd",
            $id_penjualan,
            $id_barang,
            $jumlah,
            $harga_jual,
            $subtotal
        );


        if (
            !mysqli_stmt_execute(
                $stmtDetail
            )
        ) {

            throw new Exception(
                'Gagal menyimpan detail penjualan.'
            );
        }


        // KURANGI STOK

        mysqli_stmt_bind_param(
            $stmtStok,
            "ii",
            $jumlah,
            $id_barang
        );


        if (
            !mysqli_stmt_execute(
                $stmtStok
            )
        ) {

            throw new Exception(
                'Gagal mengurangi stok.'
            );
        }

    }


    // ==================================
    // COMMIT
    // ==================================

    mysqli_commit($conn);


    // ==================================
    // BERHASIL
    // ==================================

    echo json_encode([

        'success' =>
            true,

        'message' =>
            'Transaksi berhasil disimpan.',

        'kode_penjualan' =>
            $kode_penjualan,

        'id_penjualan' =>
            $id_penjualan,

        'total' =>
            $total,

        'bayar' =>
            $bayar,

        'kembalian' =>
            $kembalian

    ]);


} catch (Exception $e) {


    // ==================================
    // GAGAL → ROLLBACK
    // ==================================

    mysqli_rollback($conn);


    echo json_encode([

        'success' =>
            false,

        'message' =>
            $e->getMessage()

    ]);

}