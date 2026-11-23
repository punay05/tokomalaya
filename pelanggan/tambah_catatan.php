<?php

session_start();

require_once "../config/database.php";

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| CEK LOGIN
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['id_user']) ||
    ($_SESSION['role'] ?? '') !== 'pelanggan'
) {
    echo json_encode([
        'success' => false,
        'message' => 'Anda belum login sebagai pelanggan.'
    ]);
    exit;
}

$id_user = (int) $_SESSION['id_user'];

/*
|--------------------------------------------------------------------------
| AMBIL DATA POST
|--------------------------------------------------------------------------
*/
$id_keranjang = (int) ($_POST['id_keranjang'] ?? 0);
$catatan = trim($_POST['catatan'] ?? '');

/*
|--------------------------------------------------------------------------
| VALIDASI ID KERANJANG
|--------------------------------------------------------------------------
*/
if ($id_keranjang <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Data keranjang tidak valid.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDASI PANJANG CATATAN
|--------------------------------------------------------------------------
| Disamakan dengan maxlength textarea di keranjang.php
*/
if (mb_strlen($catatan) > 500) {
    echo json_encode([
        'success' => false,
        'message' => 'Catatan maksimal 500 karakter.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| CEK APAKAH ITEM KERANJANG MILIK USER
|--------------------------------------------------------------------------
*/
$query_check = "
    SELECT id_keranjang
    FROM keranjang
    WHERE id_keranjang = ?
      AND id_user = ?
    LIMIT 1
";

$stmt_check = mysqli_prepare($conn, $query_check);

if (!$stmt_check) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memproses data keranjang.'
    ]);
    exit;
}

mysqli_stmt_bind_param(
    $stmt_check,
    "ii",
    $id_keranjang,
    $id_user
);

mysqli_stmt_execute($stmt_check);

$result_check = mysqli_stmt_get_result($stmt_check);

if (!$result_check || mysqli_num_rows($result_check) === 0) {
    mysqli_stmt_close($stmt_check);

    echo json_encode([
        'success' => false,
        'message' => 'Item keranjang tidak ditemukan atau bukan milik Anda.'
    ]);
    exit;
}

mysqli_stmt_close($stmt_check);

/*
|--------------------------------------------------------------------------
| UPDATE CATATAN
|--------------------------------------------------------------------------
*/
$query_update = "
    UPDATE keranjang
    SET catatan = ?
    WHERE id_keranjang = ?
      AND id_user = ?
    LIMIT 1
";

$stmt_update = mysqli_prepare($conn, $query_update);

if (!$stmt_update) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyiapkan penyimpanan catatan.'
    ]);
    exit;
}

mysqli_stmt_bind_param(
    $stmt_update,
    "sii",
    $catatan,
    $id_keranjang,
    $id_user
);

if (mysqli_stmt_execute($stmt_update)) {

    mysqli_stmt_close($stmt_update);

    echo json_encode([
        'success' => true,
        'message' => 'Catatan berhasil disimpan.'
    ]);
    exit;

}

/*
|--------------------------------------------------------------------------
| ERROR UPDATE
|--------------------------------------------------------------------------
*/
$error = mysqli_stmt_error($stmt_update);

mysqli_stmt_close($stmt_update);

echo json_encode([
    'success' => false,
    'message' => 'Catatan gagal disimpan: ' . $error
]);

exit;