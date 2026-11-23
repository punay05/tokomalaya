<?php

session_start();

require_once "../config/database.php";

header('Content-Type: application/json');


// ========================================
// CEK LOGIN
// ========================================

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


// ========================================
// AMBIL KATA PENCARIAN
// ========================================

$q = trim($_GET['q'] ?? '');


// Minimal 1 karakter
if ($q === '') {

    echo json_encode([
        'success' => true,
        'barang' => []
    ]);

    exit;
}


// ========================================
// CARI BARANG
// ========================================
//
// Bisa mencari:
// - nama_barang
// - kode_barang
//
// Tetapi di halaman kasir kita utamakan nama.
// ========================================

$keyword = "%" . $q . "%";


$stmt = mysqli_prepare(
    $conn,
    "SELECT
        id_barang,
        kode_barang,
        nama_barang,
        harga_jual,
        satuan
     FROM barang
     WHERE nama_barang LIKE ?
        OR kode_barang LIKE ?
     ORDER BY nama_barang ASC
     LIMIT 10"
);


mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $keyword,
    $keyword
);


mysqli_stmt_execute($stmt);


$result =
    mysqli_stmt_get_result($stmt);


$barang = [];


while (
    $row = mysqli_fetch_assoc($result)
) {

    $barang[] = $row;

}


// ========================================
// HASIL
// ========================================

echo json_encode([
    'success' => true,
    'barang' => $barang
]);

?>