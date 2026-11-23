<?php

session_start();

include "../config/database.php";

header('Content-Type: application/json');


/*
|--------------------------------------------------------------------------
| CEK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['id_user'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CEK ROLE
|--------------------------------------------------------------------------
*/

if ($_SESSION['role'] !== 'pelanggan') {

    echo json_encode([
        'success' => false,
        'message' => 'Akses tidak diizinkan.'
    ]);

    exit;
}


$id_user =
    (int) $_SESSION['id_user'];

$id_barang =
    (int) ($_POST['id_barang'] ?? 0);


/*
|--------------------------------------------------------------------------
| VALIDASI PRODUK
|--------------------------------------------------------------------------
*/

if ($id_barang <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Produk tidak valid.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| AMBIL DATA BARANG
|--------------------------------------------------------------------------
*/

$query_barang = mysqli_prepare(
    $conn,
    "SELECT
        id_barang,
        nama_barang,
        stok
     FROM barang
     WHERE id_barang = ?
     LIMIT 1"
);


mysqli_stmt_bind_param(
    $query_barang,
    "i",
    $id_barang
);


mysqli_stmt_execute(
    $query_barang
);


$result_barang =
    mysqli_stmt_get_result(
        $query_barang
    );


$barang =
    mysqli_fetch_assoc(
        $result_barang
    );


mysqli_stmt_close(
    $query_barang
);


/*
|--------------------------------------------------------------------------
| PRODUK TIDAK DITEMUKAN
|--------------------------------------------------------------------------
*/

if (!$barang) {

    echo json_encode([
        'success' => false,
        'message' => 'Produk tidak ditemukan.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CEK STOK
|--------------------------------------------------------------------------
*/

if ((int) $barang['stok'] <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Stok produk sedang habis.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CEK KERANJANG
|--------------------------------------------------------------------------
*/

$query_cek = mysqli_prepare(
    $conn,
    "SELECT
        id_keranjang,
        jumlah
     FROM keranjang
     WHERE id_user = ?
     AND id_barang = ?
     LIMIT 1"
);


mysqli_stmt_bind_param(
    $query_cek,
    "ii",
    $id_user,
    $id_barang
);


mysqli_stmt_execute(
    $query_cek
);


$result_cek =
    mysqli_stmt_get_result(
        $query_cek
    );


$keranjang =
    mysqli_fetch_assoc(
        $result_cek
    );


mysqli_stmt_close(
    $query_cek
);


/*
|--------------------------------------------------------------------------
| PRODUK SUDAH ADA
|--------------------------------------------------------------------------
*/

if ($keranjang) {


    $jumlah_baru =
        (int) $keranjang['jumlah'] + 1;


    /*
    |--------------------------------------------------------------------------
    | JANGAN MELEBIHI STOK
    |--------------------------------------------------------------------------
    */

    if (
        $jumlah_baru >
        (int) $barang['stok']
    ) {

        echo json_encode([
            'success' => false,
            'message' =>
                'Jumlah sudah mencapai stok yang tersedia.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE JUMLAH
    |--------------------------------------------------------------------------
    */

    $query_update =
        mysqli_prepare(
            $conn,
            "UPDATE keranjang
             SET jumlah = ?
             WHERE id_keranjang = ?
             AND id_user = ?"
        );


    mysqli_stmt_bind_param(
        $query_update,
        "iii",
        $jumlah_baru,
        $keranjang['id_keranjang'],
        $id_user
    );


    $berhasil =
        mysqli_stmt_execute(
            $query_update
        );


    mysqli_stmt_close(
        $query_update
    );


    if (!$berhasil) {

        echo json_encode([
            'success' => false,
            'message' =>
                'Gagal memperbarui keranjang.'
        ]);

        exit;
    }


/*
|--------------------------------------------------------------------------
| PRODUK BELUM ADA
|--------------------------------------------------------------------------
*/

} else {


    $jumlah = 1;


    /*
    |--------------------------------------------------------------------------
    | INSERT KE KERANJANG
    |--------------------------------------------------------------------------
    */

    $query_insert =
        mysqli_prepare(
            $conn,
            "INSERT INTO keranjang
                (
                    id_user,
                    id_barang,
                    jumlah
                )
             VALUES
                (?, ?, ?)"
        );


    mysqli_stmt_bind_param(
        $query_insert,
        "iii",
        $id_user,
        $id_barang,
        $jumlah
    );


    $berhasil =
        mysqli_stmt_execute(
            $query_insert
        );


    mysqli_stmt_close(
        $query_insert
    );


    if (!$berhasil) {

        echo json_encode([
            'success' => false,
            'message' =>
                'Gagal menambahkan produk ke keranjang.'
        ]);

        exit;
    }

}


/*
|--------------------------------------------------------------------------
| HITUNG TOTAL ITEM KERANJANG
|--------------------------------------------------------------------------
*/

$query_total =
    mysqli_prepare(
        $conn,
        "SELECT
            COALESCE(SUM(jumlah), 0) AS total
         FROM keranjang
         WHERE id_user = ?"
    );


mysqli_stmt_bind_param(
    $query_total,
    "i",
    $id_user
);


mysqli_stmt_execute(
    $query_total
);


$result_total =
    mysqli_stmt_get_result(
        $query_total
    );


$data_total =
    mysqli_fetch_assoc(
        $result_total
    );


mysqli_stmt_close(
    $query_total
);


$total_keranjang =
    (int) ($data_total['total'] ?? 0);


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([

    'success' => true,

    'message' =>
        $barang['nama_barang'] .
        ' berhasil ditambahkan ke keranjang.',

    'total_keranjang' =>
        $total_keranjang

]);

exit;