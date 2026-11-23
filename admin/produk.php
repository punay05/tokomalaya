<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| CEK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| ROLE ADMIN
|--------------------------------------------------------------------------
| Admin = Admin + Pemilik
*/

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| DATA USER
|--------------------------------------------------------------------------
*/

$id_user = (int) $_SESSION['id_user'];

$nama_user = $_SESSION['nama'] ?? 'Admin';

$email_user = $_SESSION['email'] ?? '';

$current_page = basename($_SERVER['PHP_SELF']);


/*
|--------------------------------------------------------------------------
| FUNGSI
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function rupiah($angka)
{
    return 'Rp ' . number_format(
        (float) $angka,
        0,
        ',',
        '.'
    );
}


/*
|--------------------------------------------------------------------------
| FOLDER UPLOAD
|--------------------------------------------------------------------------
*/

$upload_dir = "../uploads/";


if (!is_dir($upload_dir)) {
    @mkdir(
        $upload_dir,
        0777,
        true
    );
}


/*
|--------------------------------------------------------------------------
| PROSES FORM
|--------------------------------------------------------------------------
*/

$success = '';

$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | TAMBAH PRODUK
    |--------------------------------------------------------------------------
    */

    if ($action === 'tambah') {

        $kode_barang = trim(
            $_POST['kode_barang'] ?? ''
        );

        $nama_barang = trim(
            $_POST['nama_barang'] ?? ''
        );

        $id_kategori = (int) (
            $_POST['id_kategori'] ?? 0
        );

        $harga_beli = (float) (
            $_POST['harga_beli'] ?? 0
        );

        $harga_jual = (float) (
            $_POST['harga_jual'] ?? 0
        );

        $stok = (int) (
            $_POST['stok'] ?? 0
        );

        $satuan = trim(
            $_POST['satuan'] ?? ''
        );

        $stok_minimum = (int) (
            $_POST['stok_minimum'] ?? 5
        );


        if (
            $kode_barang === '' ||
            $nama_barang === '' ||
            $id_kategori <= 0 ||
            $harga_jual < 0 ||
            $stok < 0
        ) {

            $error =
                "Data produk belum lengkap.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | CEK KODE BARANG
            |--------------------------------------------------------------------------
            */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_barang
                 FROM barang
                 WHERE kode_barang = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $kode_barang
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            $kode_sudah_ada =
                mysqli_num_rows($result) > 0;

            mysqli_stmt_close($stmt);


            if ($kode_sudah_ada) {

                $error =
                    "Kode barang sudah digunakan.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | UPLOAD GAMBAR
                |--------------------------------------------------------------------------
                */

                $gambar = null;


                if (
                    isset($_FILES['gambar']) &&
                    $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE
                ) {

                    if (
                        $_FILES['gambar']['error'] !==
                        UPLOAD_ERR_OK
                    ) {

                        $error =
                            "Gagal mengupload gambar.";

                    } else {

                        $file_tmp =
                            $_FILES['gambar']['tmp_name'];

                        $file_name =
                            $_FILES['gambar']['name'];

                        $file_size =
                            (int) $_FILES['gambar']['size'];


                        $extension =
                            strtolower(
                                pathinfo(
                                    $file_name,
                                    PATHINFO_EXTENSION
                                )
                            );


                        $allowed =
                            [
                                'jpg',
                                'jpeg',
                                'png',
                                'webp'
                            ];


                        if (
                            !in_array(
                                $extension,
                                $allowed,
                                true
                            )
                        ) {

                            $error =
                                "Format gambar harus JPG, JPEG, PNG, atau WEBP.";

                        } elseif (
                            $file_size > 5 * 1024 * 1024
                        ) {

                            $error =
                                "Ukuran gambar maksimal 5 MB.";

                        } else {

                            $new_name =
                                'produk_' .
                                time() .
                                '_' .
                                bin2hex(
                                    random_bytes(4)
                                ) .
                                '.' .
                                $extension;


                            if (
                                move_uploaded_file(
                                    $file_tmp,
                                    $upload_dir . $new_name
                                )
                            ) {

                                $gambar = $new_name;

                            } else {

                                $error =
                                    "Gambar gagal disimpan.";
                            }
                        }
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | INSERT
                |--------------------------------------------------------------------------
                */

                if ($error === '') {

                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO barang
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
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );


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
                        $success = "Produk berhasil ditambahkan.";
                    } else {
                        $error = "Produk gagal ditambahkan. Periksa kembali data produk.";
                        if ($gambar && file_exists($upload_dir . $gambar)) {
                            @unlink($upload_dir . $gambar);
                        }
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE PRODUK
    |--------------------------------------------------------------------------
    */

    if ($action === 'edit') {

        $id_barang = (int) (
            $_POST['id_barang'] ?? 0
        );

        $kode_barang = trim(
            $_POST['kode_barang'] ?? ''
        );

        $nama_barang = trim(
            $_POST['nama_barang'] ?? ''
        );

        $id_kategori = (int) (
            $_POST['id_kategori'] ?? 0
        );

        $harga_beli = (float) (
            $_POST['harga_beli'] ?? 0
        );

        $harga_jual = (float) (
            $_POST['harga_jual'] ?? 0
        );

        $stok = (int) (
            $_POST['stok'] ?? 0
        );

        $satuan = trim(
            $_POST['satuan'] ?? ''
        );

        $stok_minimum = (int) (
            $_POST['stok_minimum'] ?? 5
        );


        if (
            $id_barang <= 0 ||
            $kode_barang === '' ||
            $nama_barang === '' ||
            $id_kategori <= 0
        ) {

            $error =
                "Data produk belum lengkap.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | CEK KODE DUPLIKAT
            |--------------------------------------------------------------------------
            */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_barang
                 FROM barang
                 WHERE kode_barang = ?
                 AND id_barang != ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $kode_barang,
                $id_barang
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $duplicate =
                mysqli_num_rows($result) > 0;

            mysqli_stmt_close($stmt);


            if ($duplicate) {

                $error =
                    "Kode barang sudah digunakan produk lain.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | AMBIL GAMBAR LAMA
                |--------------------------------------------------------------------------
                */

                $gambar_lama = null;


                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT gambar
                     FROM barang
                     WHERE id_barang = ?
                     LIMIT 1"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $id_barang
                );

                mysqli_stmt_execute($stmt);

                $result =
                    mysqli_stmt_get_result($stmt);

                $old =
                    mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);


                if ($old) {
                    $gambar_lama =
                        $old['gambar'];
                }


                $gambar_baru = null;


                /*
                |--------------------------------------------------------------------------
                | UPLOAD GAMBAR BARU
                |--------------------------------------------------------------------------
                */

                if (
                    isset($_FILES['gambar']) &&
                    $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE
                ) {

                    if (
                        $_FILES['gambar']['error'] !==
                        UPLOAD_ERR_OK
                    ) {

                        $error =
                            "Gagal mengupload gambar.";

                    } else {

                        $file_tmp =
                            $_FILES['gambar']['tmp_name'];

                        $file_name =
                            $_FILES['gambar']['name'];

                        $file_size =
                            (int) $_FILES['gambar']['size'];

                        $extension =
                            strtolower(
                                pathinfo(
                                    $file_name,
                                    PATHINFO_EXTENSION
                                )
                            );


                        $allowed =
                            [
                                'jpg',
                                'jpeg',
                                'png',
                                'webp'
                            ];


                        if (
                            !in_array(
                                $extension,
                                $allowed,
                                true
                            )
                        ) {

                            $error =
                                "Format gambar harus JPG, JPEG, PNG, atau WEBP.";

                        } elseif (
                            $file_size > 5 * 1024 * 1024
                        ) {

                            $error =
                                "Ukuran gambar maksimal 5 MB.";

                        } else {

                            $new_name =
                                'produk_' .
                                time() .
                                '_' .
                                bin2hex(
                                    random_bytes(4)
                                ) .
                                '.' .
                                $extension;


                            if (
                                move_uploaded_file(
                                    $file_tmp,
                                    $upload_dir . $new_name
                                )
                            ) {

                                $gambar_baru =
                                    $new_name;

                            } else {

                                $error =
                                    "Gambar gagal disimpan.";
                            }
                        }
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE
                |--------------------------------------------------------------------------
                */

                if ($error === '') {

                    if ($gambar_baru !== null) {

                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE barang
                             SET
                                kode_barang = ?,
                                nama_barang = ?,
                                gambar = ?,
                                id_kategori = ?,
                                harga_beli = ?,
                                harga_jual = ?,
                                stok = ?,
                                satuan = ?,
                                stok_minimum = ?
                             WHERE id_barang = ?"
                        );


                        mysqli_stmt_bind_param(
                            $stmt,
                            "sssiddisii",
                            $kode_barang,
                            $nama_barang,
                            $gambar_baru,
                            $id_kategori,
                            $harga_beli,
                            $harga_jual,
                            $stok,
                            $satuan,
                            $stok_minimum,
                            $id_barang
                        );

                    } else {

                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE barang
                             SET
                                kode_barang = ?,
                                nama_barang = ?,
                                id_kategori = ?,
                                harga_beli = ?,
                                harga_jual = ?,
                                stok = ?,
                                satuan = ?,
                                stok_minimum = ?
                             WHERE id_barang = ?"
                        );


                        mysqli_stmt_bind_param(
                            $stmt,
                            "ssiddisii",
                            $kode_barang,
                            $nama_barang,
                            $id_kategori,
                            $harga_beli,
                            $harga_jual,
                            $stok,
                            $satuan,
                            $stok_minimum,
                            $id_barang
                        );
                    }


                    if (
                        mysqli_stmt_execute($stmt)
                    ) {

                        /*
                        |--------------------------------------------------------------------------
                        | HAPUS GAMBAR LAMA
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $gambar_baru !== null &&
                            $gambar_lama &&
                            file_exists(
                                $upload_dir . $gambar_lama
                            )
                        ) {

                            @unlink(
                                $upload_dir .
                                $gambar_lama
                            );
                        }


                        $success =
                            "Produk berhasil diperbarui.";

                    } else {

                        $error =
                            "Produk gagal diperbarui.";
                    }


                    mysqli_stmt_close($stmt);
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | HAPUS PRODUK
    |--------------------------------------------------------------------------
    */

    if ($action === 'hapus') {

        $id_barang = (int) (
            $_POST['id_barang'] ?? 0
        );


        if ($id_barang <= 0) {

            $error =
                "Produk tidak ditemukan.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | AMBIL GAMBAR
            |--------------------------------------------------------------------------
            */

            $gambar = null;


            $stmt = mysqli_prepare(
                $conn,
                "SELECT gambar
                 FROM barang
                 WHERE id_barang = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id_barang
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $data =
                mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);


            if (!$data) {

                $error =
                    "Produk tidak ditemukan.";

            } else {

                $gambar =
                    $data['gambar'];


                /*
                |--------------------------------------------------------------------------
                | HAPUS
                |--------------------------------------------------------------------------
                */

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM barang
                     WHERE id_barang = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $id_barang
                );


                if (
                    mysqli_stmt_execute($stmt)
                ) {

                    if (
                        $gambar &&
                        file_exists(
                            $upload_dir . $gambar
                        )
                    ) {

                        @unlink(
                            $upload_dir . $gambar
                        );
                    }


                    $success =
                        "Produk berhasil dihapus.";

                } else {

                    /*
                    | Biasanya terjadi jika produk
                    | sudah digunakan transaksi.
                    */

                    $error =
                        "Produk tidak dapat dihapus karena kemungkinan sudah digunakan dalam transaksi.";
                }


                mysqli_stmt_close($stmt);
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| AMBIL KATEGORI
|--------------------------------------------------------------------------
*/

$kategori_list = [];


$result_kategori = mysqli_query(
    $conn,
    "SELECT
        id_kategori,
        nama_kategori
     FROM kategori
     ORDER BY nama_kategori ASC"
);


if ($result_kategori) {

    while (
        $row = mysqli_fetch_assoc(
            $result_kategori
        )
    ) {

        $kategori_list[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$filter_kategori = (int) (
    $_GET['kategori'] ?? 0
);


/*
|--------------------------------------------------------------------------
| QUERY PRODUK (SATU HALAMAN)
|--------------------------------------------------------------------------
| Tampilkan seluruh produk pada satu halaman, tanpa navigasi paginasi.
*/

$produk = [];

$whereSql = " FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id_kategori WHERE 1 = 1 ";
$params = [];
$types = "";

if ($search !== '') {
    $whereSql .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?) ";
    $keyword = '%' . $search . '%';
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= "ss";
}

if ($filter_kategori > 0) {
    $whereSql .= " AND b.id_kategori = ? ";
    $params[] = $filter_kategori;
    $types .= "i";
}

// Statistik dihitung di database untuk seluruh hasil filter, bukan hanya
// baris yang sedang ditampilkan di halaman aktif.
$sql_stat = "SELECT COUNT(*) AS total_produk,
                    COALESCE(SUM(b.stok), 0) AS total_stok,
                    COALESCE(SUM(CASE WHEN b.stok <= b.stok_minimum THEN 1 ELSE 0 END), 0) AS stok_menipis"
          . $whereSql;
$stmt_stat = mysqli_prepare($conn, $sql_stat);
if (!$stmt_stat) {
    error_log('Gagal menyiapkan statistik produk: ' . mysqli_error($conn));
    $total_produk = 0;
    $total_stok = 0;
    $stok_menipis = 0;
} else {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_stat, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_stat);
    $result_stat = mysqli_stmt_get_result($stmt_stat);
    $stat = $result_stat ? mysqli_fetch_assoc($result_stat) : null;
    $total_produk = (int) ($stat['total_produk'] ?? 0);
    $total_stok = (int) ($stat['total_stok'] ?? 0);
    $stok_menipis = (int) ($stat['stok_menipis'] ?? 0);
    mysqli_stmt_close($stmt_stat);
}

$sql = "SELECT b.id_barang, b.kode_barang, b.nama_barang, b.gambar,
               b.id_kategori, b.harga_beli, b.harga_jual, b.stok,
               b.satuan, b.stok_minimum, k.nama_kategori"
     . $whereSql
     . " ORDER BY b.id_barang DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (mysqli_stmt_execute($stmt)) {
        $result_produk = mysqli_stmt_get_result($stmt);
        while ($result_produk && ($row = mysqli_fetch_assoc($result_produk))) {
            $produk[] = $row;
        }
    } else {
        error_log('Gagal mengambil daftar produk: ' . mysqli_stmt_error($stmt));
        $error = $error !== '' ? $error : 'Data produk gagal dimuat. Periksa koneksi database.';
    }
    mysqli_stmt_close($stmt);
} else {
    error_log('Gagal menyiapkan daftar produk: ' . mysqli_error($conn));
    $error = $error !== '' ? $error : 'Data produk gagal dimuat. Periksa struktur database.';
}

/*
|--------------------------------------------------------------------------
| RESET POST MESSAGE
|--------------------------------------------------------------------------
*/

if ($success !== '') {
    // pesan ditampilkan di halaman
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

<title>
    Produk - Toko Malaya
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}


html {
    scroll-behavior: smooth;
}


body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f7fb;

    color: #1f2937;
}


body.menu-open {
    overflow: hidden;
}


button,
input,
select {

    font-family: inherit;
}


a {
    text-decoration: none;
    color: inherit;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height: 72px;

    background: #ffffff;

    border-bottom:
        1px solid #e5e7eb;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 24px;

    position: sticky;

    top: 0;

    z-index: 100;
}


.topbar-left {

    display: flex;

    align-items: center;

    gap: 15px;
}


.menu-button {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 10px;
    background: #eaf3ff;
    color: #1677d2;
    cursor: pointer;
    font-size: 24px;
    line-height: 1;
    transition: .2s;
}

.menu-button:hover {
    background: #dcecff;
    transform: translateY(-1px);
}

.brand {

    display: flex;

    align-items: center;

    gap: 10px;
}


.brand-logo {

    width: 42px;

    height: 42px;

    border-radius: 10px;

    background: #1677d2;

    flex-shrink: 0;

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

    color: #1f2937;
}


.brand-text span {

    font-size: 12px;

    color: #64748b;

    margin-top: 2px;
}


/* =========================================================
   TOPBAR USER
========================================================= */

.top-user {

    display: flex;

    align-items: center;

    gap: 10px;

    padding:
        6px 12px 6px 7px;

    border:
        1px solid #e5e7eb;

    border-radius: 12px;

    background: #ffffff;
}


.top-avatar {

    width: 38px;

    height: 38px;

    border-radius: 50%;

    background: #1677d2;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: bold;
}


.top-user-info {

    display: flex;

    flex-direction: column;
}


.top-user-info strong {

    font-size: 13px;
}


.top-user-info span {

    font-size: 11px;

    color: #64748b;
}


/* =========================================================
   DRAWER
========================================================= */

.menu-overlay {

    position: fixed;

    inset: 0;

    background:
        rgba(15, 23, 42, .45);

    opacity: 0;

    visibility: hidden;

    transition:
        opacity .25s ease,
        visibility .25s ease;

    z-index: 1000;
}


.menu-overlay.show {

    opacity: 1;

    visibility: visible;
}


.drawer {

    position: fixed;

    top: 0;

    left: 0;

    bottom: 0;

    width: 335px;

    max-width: 88vw;

    height: 100vh;

    height: 100dvh;

    background: #ffffff;

    z-index: 1100;

    overflow: hidden;

    transform:
        translateX(-100%);

    transition:
        transform .28s ease;

    box-shadow:
        8px 0 30px
        rgba(15, 23, 42, .12);

    display: flex;

    flex-direction: column;
}


.drawer.show {

    transform:
        translateX(0);
}


/* =========================================================
   DRAWER HEADER
========================================================= */

.drawer-header {

    min-height: 82px;

    padding:
        18px 20px;

    border-bottom:
        1px solid #e5e7eb;

    display: flex;

    align-items: center;

    justify-content: space-between;

    flex-shrink: 0;
}


.drawer-brand {

    display: flex;

    align-items: center;

    gap: 10px;
}


.drawer-logo {

    width: 43px;

    height: 43px;

    border-radius: 10px;

    background: #1677d2;

    overflow: hidden;

    display: flex;

    align-items: center;

    justify-content: center;
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

    font-size: 16px;
}


.drawer-brand-text span {

    color: #64748b;

    font-size: 12px;

    margin-top: 2px;
}


.drawer-close {

    width: 36px;

    height: 36px;

    flex-shrink: 0;

    border: 0;

    background: #f1f5f9;

    color: #475569;

    border-radius: 8px;

    font-size: 20px;

    cursor: pointer;
}

.drawer-close:hover {
    background: #e2e8f0;
}


/* =========================================================
   DRAWER USER
========================================================= */

.drawer-user {

    margin: 18px;

    padding: 14px;

    border:
        1px solid #e2e8f0;

    background: #f8fafc;

    border-radius: 12px;

    display: flex;

    align-items: center;

    gap: 11px;

    flex-shrink: 0;
}


.drawer-avatar {

    width: 42px;

    height: 42px;

    flex-shrink: 0;

    border-radius: 50%;

    background: #e8f3ff;

    color: #1677d2;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: bold;
}


.drawer-user-info {

    display: flex;

    flex-direction: column;

    min-width: 0;
}


.drawer-user-info strong {

    font-size: 13px;

    color: #1f2937;

    overflow-wrap: anywhere;
}


.drawer-user-info span {

    color: #64748b;

    font-size: 11px;

    margin-top: 3px;
}


/* =========================================================
   DRAWER MENU
========================================================= */

.drawer-menu {

    flex: 1;

    min-height: 0;

    overflow-y: auto;

    padding:
        0 12px 20px;
}


.menu-label {

    padding:
        10px 12px;

    color: #94a3b8;

    font-size: 11px;

    font-weight: bold;

    letter-spacing: .5px;
}


.menu-link {

    min-height: 45px;

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        10px 12px;

    margin-bottom: 4px;

    border-radius: 9px;

    color: #475569;

    font-size: 14px;

    font-weight: 700;

    text-decoration: none;

    transition:
        background .2s ease,
        color .2s ease;
}


.menu-link:hover {

    background: #f1f5f9;

    color: #1677d2;
}


.menu-link.active {

    background: #e8f3ff;

    color: #1677d2;

    font-weight: bold;

    border-left:
        3px solid #1677d2;
}


.menu-icon {

    width: 24px;

    text-align: center;

    font-size: 17px;
}


/* =========================================================
   DRAWER FOOTER
========================================================= */

.drawer-footer {

    padding:
        14px 12px;

    border-top:
        1px solid #e5e7eb;

    background: #fff;

    flex-shrink: 0;
}


.logout-link {

    min-height: 44px;

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        10px 12px;

    border-radius: 9px;

    color: #dc3545;

    font-size: 14px;

    font-weight: 700;

    text-decoration: none;
}


.logout-link:hover {

    background: #fff1f2;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    max-width: 1250px;

    margin: 0 auto;

    padding:
        30px 24px 50px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
}


.page-title h1 {

    margin: 0;

    font-size: 28px;

    color: #172b4d;
}


.page-title p {

    margin:
        7px 0 0;

    color: #64748b;

    font-size: 14px;
}


.btn-primary {

    border: 0;

    background: #1677d2;

    color: #ffffff;

    padding:
        11px 17px;

    border-radius: 9px;

    font-size: 13px;

    font-weight: bold;

    cursor: pointer;

    display: inline-flex;

    align-items: center;

    gap: 8px;

    transition:
        background .2s ease,
        transform .2s ease;
}


.btn-primary:hover {

    background: #1266b4;

    transform: translateY(-1px);
}


/* =========================================================
   ALERT
========================================================= */

.alert {

    padding:
        13px 15px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 13px;
}


.alert-success {

    background: #ecfdf3;

    border:
        1px solid #bbf7d0;

    color: #166534;
}


.alert-error {

    background: #fff1f2;

    border:
        1px solid #fecdd3;

    color: #be123c;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 16px;

    margin-bottom: 22px;
}


.stat-card {

    background: #ffffff;

    border:
        1px solid #e5e7eb;

    border-radius: 13px;

    padding: 20px;

    display: flex;

    align-items: center;

    gap: 15px;
}


.stat-icon {

    width: 48px;

    height: 48px;

    border-radius: 11px;

    background: #e8f3ff;

    color: #1677d2;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 22px;
}


.stat-content span {

    display: block;

    color: #64748b;

    font-size: 12px;

    margin-bottom: 5px;
}


.stat-content strong {

    font-size: 21px;

    color: #172b4d;
}


/* =========================================================
   FILTER CARD
========================================================= */

.filter-card {

    background: #ffffff;

    border:
        1px solid #e5e7eb;

    border-radius: 13px;

    padding: 16px;

    margin-bottom: 18px;
}


.filter-form {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        220px
        auto;

    gap: 10px;
}


.input,
.select {

    width: 100%;

    height: 42px;

    border:
        1px solid #dbe2ea;

    border-radius: 8px;

    padding:
        0 12px;

    background: #ffffff;

    color: #1f2937;

    outline: none;

    font-size: 13px;
}


.input:focus,
.select:focus {

    border-color: #1677d2;

    box-shadow:
        0 0 0 3px
        rgba(22,119,210,.10);
}


.btn-filter {

    height: 42px;

    border: 0;

    border-radius: 8px;

    padding:
        0 17px;

    background: #1677d2;

    color: #ffffff;

    font-weight: bold;

    cursor: pointer;
}


.btn-reset {

    height: 42px;

    padding:
        0 15px;

    border:
        1px solid #dbe2ea;

    background: #ffffff;

    border-radius: 8px;

    cursor: pointer;

    color: #475569;
}


/* =========================================================
   PRODUCT TABLE
========================================================= */

.table-card {

    background: #ffffff;

    border:
        1px solid #e5e7eb;

    border-radius: 13px;

    overflow: hidden;
}


.table-header {

    padding:
        17px 19px;

    border-bottom:
        1px solid #e5e7eb;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.table-header strong {

    font-size: 15px;

    color: #172b4d;
}


.table-header span {

    color: #64748b;

    font-size: 12px;
}


.table-wrapper {

    width: 100%;

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1050px;
}


thead {

    background: #f8fafc;
}


th {

    padding:
        13px 14px;

    text-align: left;

    color: #64748b;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .3px;

    white-space: nowrap;
}


td {

    padding:
        13px 14px;

    border-top:
        1px solid #eef2f7;

    font-size: 13px;

    vertical-align: middle;
}


.product-info {

    display: flex;

    align-items: center;

    gap: 11px;

    min-width: 220px;
}


.product-image {

    width: 48px;

    height: 48px;

    border-radius: 9px;

    border:
        1px solid #e5e7eb;

    object-fit: cover;

    background: #f8fafc;
}


.product-name {

    display: flex;

    flex-direction: column;

    gap: 4px;
}


.product-name strong {

    color: #1f2937;

    font-size: 13px;
}


.product-name span {

    color: #94a3b8;

    font-size: 11px;
}


.code {

    font-family:
        Consolas,
        monospace;

    font-size: 12px;

    color: #475569;
}


.category {

    display: inline-block;

    padding:
        5px 8px;

    border-radius: 6px;

    background: #f1f5f9;

    color: #475569;

    font-size: 11px;
}


.price {

    font-weight: bold;

    color: #1f2937;

    white-space: nowrap;
}


.stock {

    font-weight: bold;
}


.stock.good {

    color: #15803d;
}


.stock.warning {

    color: #dc2626;
}


.stock-badge {

    display: inline-block;

    margin-left: 5px;

    padding:
        3px 6px;

    border-radius: 5px;

    font-size: 9px;

    background: #fee2e2;

    color: #b91c1c;
}


.action-buttons {

    display: flex;

    align-items: center;

    gap: 6px;
}


.btn-action {

    border: 0;

    width: 34px;

    height: 34px;

    border-radius: 7px;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 15px;
}


.btn-edit {

    background: #e8f3ff;

    color: #1677d2;
}


.btn-delete {

    background: #fff1f2;

    color: #dc3545;
}


.empty {

    padding:
        50px 20px;

    text-align: center;

    color: #64748b;
}


.empty-icon {

    font-size: 40px;

    margin-bottom: 10px;
}


/* =========================================================
   MODAL
========================================================= */

.modal-overlay {

    position: fixed;

    inset: 0;

    background:
        rgba(15, 23, 42, .55);

    z-index: 500;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;
}


.modal-overlay.show {

    display: flex;
}


.modal {

    width: min(680px, 100%);

    max-height: 92vh;

    overflow-y: auto;

    background: #ffffff;

    border-radius: 14px;

    box-shadow:
        0 20px 60px
        rgba(15, 23, 42, .25);
}


.modal-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid #e5e7eb;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.modal-header h2 {

    margin: 0;

    font-size: 18px;

    color: #172b4d;
}


.modal-close {

    width: 34px;

    height: 34px;

    border: 0;

    border-radius: 7px;

    background: #f1f5f9;

    cursor: pointer;

    font-size: 19px;
}


.modal-body {

    padding: 20px;
}


.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 15px;
}


.form-group {

    display: flex;

    flex-direction: column;

    gap: 6px;
}


.form-group.full {

    grid-column:
        1 / -1;
}


.form-group label {

    font-size: 12px;

    font-weight: bold;

    color: #475569;
}


.form-help {

    color: #94a3b8;

    font-size: 10px;

    margin-top: 2px;
}


.modal-footer {

    padding:
        15px 20px;

    border-top:
        1px solid #e5e7eb;

    display: flex;

    justify-content: flex-end;

    gap: 8px;
}


.btn-secondary {

    height: 40px;

    padding:
        0 15px;

    border:
        1px solid #dbe2ea;

    background: #ffffff;

    border-radius: 8px;

    cursor: pointer;

    color: #475569;
}


.btn-save {

    height: 40px;

    padding:
        0 17px;

    border: 0;

    background: #1677d2;

    color: #ffffff;

    border-radius: 8px;

    cursor: pointer;

    font-weight: bold;
}


.preview-image {

    width: 75px;

    height: 75px;

    border-radius: 9px;

    object-fit: cover;

    border:
        1px solid #e5e7eb;

    margin-top: 8px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 850px) {

    .stats {

        grid-template-columns:
            1fr 1fr;
    }


    .filter-form {

        grid-template-columns:
            1fr 1fr;
    }


    .filter-form .input {

        grid-column:
            1 / -1;
    }

}


@media (max-width: 650px) {

    /* Samakan ukuran topbar dan tombol hamburger dengan dashboard admin */
    .topbar {
        height: 64px;
        padding: 0 15px;
    }

    .menu-button {
        width: 40px;
        height: 40px;
        font-size: 22px;
        flex: 0 0 40px;
    }


    .brand-text {

        display: none;
    }


    .top-user-info {

        display: none;
    }


    .top-user {

        padding:
            4px;
    }


    .main {

        padding:
            22px 15px 40px;
    }


    .page-header {

        flex-direction: column;
    }


    .page-header .btn-primary {

        width: 100%;

        justify-content: center;
    }


    .stats {

        grid-template-columns: 1fr;
    }


    .filter-form {

        grid-template-columns: 1fr;
    }


    .filter-form .input {

        grid-column:
            auto;
    }


    .form-grid {

        grid-template-columns: 1fr;
    }


    .form-group.full {

        grid-column:
            auto;
    }

}


@media (max-width: 420px) {

    .page-title h1 {

        font-size: 23px;
    }


    .drawer {

        width: 300px;

        max-width: 88vw;
    }

}



/* Tab gabungan Produk & Kategori */
.combined-tabs{display:flex;gap:10px;margin:0 0 22px;border-bottom:1px solid #e2e8f0;padding-bottom:10px;flex-wrap:wrap}
.combined-tab{display:inline-flex;align-items:center;gap:8px;padding:12px 18px;border:1px solid #dbe4ee;border-radius:10px;background:#fff;color:#475569;text-decoration:none;font-weight:800;font-size:14px;cursor:pointer}
.combined-tab.active{background:#1677d2;color:#fff;border-color:#1677d2}
.combined-panel[hidden]{display:none!important}
.category-frame{width:100%;height:calc(100vh - 220px);min-height:720px;border:0;border-radius:12px;background:#f5f7fb}


/* Sinkronisasi navbar dan drawer dengan dashboard.php */
.menu-overlay { pointer-events: none; }
.menu-overlay.show { pointer-events: auto; }
.topbar { z-index: 900; }
.topbar-left { gap: 14px; }
.menu-button {
    width: 42px; height: 42px; border: none; border-radius: 10px;
    background: #f1f5f9; color: #1e293b; font-size: 23px;
    cursor: pointer; transition: .2s;
}
.menu-button:hover { background: #e2e8f0; transform: none; }
.brand-logo { width: 44px; height: 44px; }
.brand-text strong { font-size: 17px; font-weight: 800; color: #111827; }
.brand-text span { font-size: 12px; margin-top: 3px; }
.top-user { padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 12px; }
.top-avatar { background: #e0efff; color: #1265a7; }
.top-user-info strong { font-size: 14px; }
.top-user-info span { font-size: 12px; margin-top: 2px; }
.drawer-header { min-height: 88px; padding: 18px 20px; }
.drawer-brand { gap: 12px; }
.drawer-logo { width: 46px; height: 46px; }
.drawer-brand-text strong { font-size: 18px; font-weight: 800; }
.drawer-brand-text span { margin-top: 3px; }
.drawer-close { width: 40px; height: 40px; border-radius: 10px; font-size: 25px; }
.drawer-user { margin: 20px; padding: 14px; border-radius: 14px; gap: 12px; }
.drawer-avatar { width: 48px; height: 48px; background: #e0efff; color: #1265a7; font-size: 17px; }
.drawer-user-info strong { font-size: 15px; }
.drawer-user-info span { font-size: 12px; }
.drawer-menu { padding: 0 14px 20px; }
.menu-label { padding: 10px 12px; margin-bottom: 7px; }
.menu-link { min-height: 48px; gap: 14px; padding: 0 14px; margin-bottom: 5px; border-radius: 10px; font-size: 15px; }
.menu-link.active { background: #e5f1ff; color: #0879e8; border-left: 0; position: relative; }
.menu-link.active::before { content: ""; position: absolute; left: 0; top: 8px; bottom: 8px; width: 3px; background: #0879e8; border-radius: 0 4px 4px 0; }
.menu-icon { width: 25px; min-width: 25px; font-size: 19px; }
.drawer-footer { padding: 14px; }
.logout-link { min-height: 48px; gap: 14px; padding: 0 14px; border-radius: 10px; }
@media (max-width: 700px) { .brand-text, .top-user-info { display: none; } .top-user { border: none; padding: 4px; } .topbar { padding: 0 15px; } }

</style>

</head>


<body>



<!-- OVERLAY -->
<div
    class="menu-overlay"
    id="menuOverlay"
    onclick="closeMenu()"
></div>

<!-- DRAWER -->
<aside class="drawer" id="drawer">

    <div class="drawer-header">
        <div class="drawer-brand">
            <div class="drawer-logo">
                <img
                    src="../assets/SembakoKu (3).png"
                    alt="Toko Malaya"
                >
            </div>

            <div class="drawer-brand-text">
                <strong>Toko Malaya</strong>
                <span>Panel Admin / Pemilik</span>
            </div>
        </div>

        <button
            type="button"
            class="drawer-close"
            onclick="closeMenu()"
            aria-label="Tutup menu"
        >×</button>
    </div>

    <div class="drawer-user">
        <div class="drawer-avatar">
            <?= e(strtoupper(substr($nama_user, 0, 1))) ?>
        </div>

        <div class="drawer-user-info">
            <strong><?= e($nama_user) ?></strong>
            <span>Admin / Pemilik</span>
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

        <a href="pengajuan_cuti.php" class="menu-link">
        <span class="menu-icon">📝</span>
        Pengajuan Cuti & Izin
        </a>


        <div class="menu-label">AKUN</div>

        <a href="profil.php" class="menu-link">
            <span class="menu-icon">👤</span>
            Profil
        </a>

    </nav>

    <div class="drawer-footer">
        <a
            href="../auth/logout.php"
            class="logout-link"
            onclick="return confirm('Apakah kamu yakin ingin keluar?');"
        >
            <span class="menu-icon">🚪</span>
            Keluar
        </a>
    </div>

</aside>


<!-- =========================================================
     TOPBAR — disamakan dengan dashboard.php
========================================================= -->
<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="menu-button" onclick="toggleMenu()" id="menuButton" aria-label="Buka menu" aria-controls="drawer" aria-expanded="false" title="Menu">☰</button>
        <a href="dashboard.php" class="brand">
            <div class="brand-logo"><img src="../assets/SembakoKu (3).png" alt="Toko Malaya"></div>
            <div class="brand-text"><strong>Toko Malaya</strong><span>Panel Admin</span></div>
        </a>
    </div>
    <div class="top-user">
        <div class="top-avatar"><?= e(strtoupper(substr($nama_user, 0, 1))) ?></div>
        <div class="top-user-info"><strong><?= e($nama_user) ?></strong><span>Admin / Pemilik</span></div>
    </div>
</header>

<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">

    <div class="combined-tabs" role="tablist" aria-label="Manajemen Produk dan Kategori">
        <button type="button" class="combined-tab active" id="tabProduk" onclick="switchCombinedTab('produk')"> Produk</button>
        <button type="button" class="combined-tab" id="tabKategori" onclick="switchCombinedTab('kategori')"> Kategori</button>
    </div>

    <section id="panelProduk" class="combined-panel">


    <!-- HEADER -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                Produk
            </h1>

            <p>
                Kelola produk, harga, stok, dan informasi barang Toko Malaya.
            </p>

        </div>


        <button
            type="button"
            class="btn-primary"
            onclick="openTambahModal()"
        >

            <span>
                ＋
            </span>

            Tambah Produk

        </button>

    </div>


    <!-- ALERT -->

    <?php if ($success !== ''): ?>

        <div class="alert alert-success">

            ✓
            <?= e($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert alert-error">

            ⚠
            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         STATISTIK
    ====================================================== -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-icon">
                📦
            </div>

            <div class="stat-content">

                <span>
                    Total Produk
                </span>

                <strong>
                    <?= number_format(
                        $total_produk
                    ) ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🏷️
            </div>

            <div class="stat-content">

                <span>
                    Total Stok
                </span>

                <strong>
                    <?= number_format(
                        $total_stok
                    ) ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div
                class="stat-icon"
                style="
                    background:#fff1f2;
                    color:#dc2626;
                "
            >
                ⚠️
            </div>

            <div class="stat-content">

                <span>
                    Stok Menipis
                </span>

                <strong
                    style="
                        color:
                        <?= $stok_menipis > 0
                            ? '#dc2626'
                            : '#172b4d'
                        ?>;
                    "
                >
                    <?= number_format(
                        $stok_menipis
                    ) ?>
                </strong>

            </div>

        </div>

    </div>


    <!-- =====================================================
         FILTER
    ====================================================== -->

    <div class="filter-card">

        <form
            method="GET"
            class="filter-form"
        >

            <input
                type="text"
                name="search"
                class="input"
                placeholder="Cari kode atau nama produk..."
                value="<?= e($search) ?>"
            >


            <select
                name="kategori"
                class="select"
            >

                <option value="0">
                    Semua Kategori
                </option>


                <?php foreach (
                    $kategori_list
                    as $kategori
                ): ?>

                    <option
                        value="<?= (int) $kategori['id_kategori'] ?>"
                        <?= $filter_kategori ==
                            $kategori['id_kategori']
                            ? 'selected'
                            : '' ?>
                    >

                        <?= e(
                            $kategori['nama_kategori']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <button
                type="submit"
                class="btn-filter"
            >
                Cari
            </button>


            <?php if (
                $search !== '' ||
                $filter_kategori > 0
            ): ?>

                <a
                    href="produk.php"
                    class="btn-reset"
                    style="
                        display:flex;
                        align-items:center;
                        justify-content:center;
                    "
                >
                    Reset
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- =====================================================
         TABLE
    ====================================================== -->

    <div class="table-card">


        <div class="table-header">

            <strong>
                Daftar Produk
            </strong>

            <span>
                <?= number_format(count($produk)) ?> produk
            </span>

        </div>


        <div class="table-wrapper">

            <?php if (
                empty($produk)
            ): ?>

                <div class="empty">

                    <div class="empty-icon">
                        📦
                    </div>

                    <strong>
                        Produk belum ditemukan
                    </strong>

                    <p>
                        Coba ubah kata pencarian atau tambahkan produk baru.
                    </p>

                </div>

            <?php else: ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                Produk
                            </th>

                            <th>
                                Kategori
                            </th>

                            <th>
                                Harga Beli
                            </th>

                            <th>
                                Harga Jual
                            </th>

                            <th>
                                Stok
                            </th>

                            <th>
                                Satuan
                            </th>

                            <th>
                                Aksi
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach (
                        $produk
                        as $item
                    ): ?>


                        <?php

                        $gambar =
                            trim(
                                (string)
                                $item['gambar']
                            );


                        if ($gambar !== '') {

                            $gambar_src =
                                "../uploads/" .
                                ltrim(
                                    $gambar,
                                    '/'
                                );

                        } else {

                            $gambar_src =
                                "../assets/SembakoKu (3).png";
                        }


                        $stok_item =
                            (int)
                            $item['stok'];

                        $stok_min =
                            (int)
                            $item['stok_minimum'];

                        $stok_warning =
                            $stok_item <=
                            $stok_min;

                        ?>


                        <tr>


                            <td>

                                <div class="product-info">

                                    <img
                                        src="<?= e($gambar_src) ?>"
                                        class="product-image"
                                        alt="<?= e(
                                            $item['nama_barang']
                                        ) ?>"
                                        onerror="
                                            this.src='../assets/SembakoKu (3).png';
                                        "
                                    >


                                    <div class="product-name">

                                        <strong>
                                            <?= e(
                                                $item['nama_barang']
                                            ) ?>
                                        </strong>

                                        <span class="code">
                                            <?= e(
                                                $item['kode_barang']
                                            ) ?>
                                        </span>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <span class="category">

                                    <?= e(
                                        $item['nama_kategori']
                                        ??
                                        '-'
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span class="price">

                                    <?= rupiah(
                                        $item['harga_beli']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span class="price">

                                    <?= rupiah(
                                        $item['harga_jual']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="
                                        stock
                                        <?= $stok_warning
                                            ? 'warning'
                                            : 'good'
                                        ?>
                                    "
                                >

                                    <?= number_format(
                                        $stok_item
                                    ) ?>

                                    <?php if (
                                        $stok_warning
                                    ): ?>

                                        <span class="stock-badge">
                                            Menipis
                                        </span>

                                    <?php endif; ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $item['satuan']
                                ) ?>

                            </td>


                            <td>

                                <div class="action-buttons">


                                    <!-- EDIT -->

                                    <button
                                        type="button"
                                        class="
                                            btn-action
                                            btn-edit
                                        "
                                        title="Edit produk"
                                        onclick='openEditModal(
                                            <?= json_encode(
                                                $item,
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ) ?>
                                        )'
                                    >
                                        ✏️
                                    </button>


                                    <!-- HAPUS -->

                                    <form
                                        method="POST"
                                        onsubmit="
                                            return confirm(
                                                'Yakin ingin menghapus produk ini?'
                                            );
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="hapus"
                                        >

                                        <input
                                            type="hidden"
                                            name="id_barang"
                                            value="<?= (int)
                                                $item['id_barang'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="
                                                btn-action
                                                btn-delete
                                            "
                                            title="Hapus produk"
                                        >
                                            🗑️
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>


                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

    </div>

    </section>

    <section id="panelKategori" class="combined-panel" hidden>
        <iframe class="category-frame" data-src="kategori.php?embed=1" title="Manajemen Kategori" loading="lazy"></iframe>
    </section>

</main>


<!-- =========================================================
     MODAL TAMBAH
========================================================= -->

<div
    class="modal-overlay"
    id="modalTambah"
>


    <div class="modal">


        <div class="modal-header">

            <h2>
                Tambah Produk
            </h2>


            <button
                type="button"
                class="modal-close"
                onclick="closeModal('modalTambah')"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="action"
                value="tambah"
            >


            <div class="modal-body">


                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Kode Barang *
                        </label>

                        <input
                            type="text"
                            name="kode_barang"
                            class="input"
                            placeholder="Contoh: BRG001"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Nama Barang *
                        </label>

                        <input
                            type="text"
                            name="nama_barang"
                            class="input"
                            placeholder="Nama produk"
                            required
                        >

                    </div>


                    <div class="form-group full">

                        <label>
                            Kategori *
                        </label>

                        <select
                            name="id_kategori"
                            class="select"
                            required
                        >

                            <option value="">
                                Pilih kategori
                            </option>


                            <?php foreach (
                                $kategori_list
                                as $kategori
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $kategori['id_kategori'] ?>"
                                >

                                    <?= e(
                                        $kategori['nama_kategori']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Harga Beli
                        </label>

                        <input
                            type="number"
                            name="harga_beli"
                            class="input"
                            min="0"
                            step="0.01"
                            value="0"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Harga Jual *
                        </label>

                        <input
                            type="number"
                            name="harga_jual"
                            class="input"
                            min="0"
                            step="0.01"
                            value="0"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Stok
                        </label>

                        <input
                            type="number"
                            name="stok"
                            class="input"
                            min="0"
                            value="0"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Satuan *
                        </label>

                        <input
                            type="text"
                            name="satuan"
                            class="input"
                            placeholder="pcs / kg / liter"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Stok Minimum
                        </label>

                        <input
                            type="number"
                            name="stok_minimum"
                            class="input"
                            min="0"
                            value="5"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Gambar Produk
                        </label>

                        <input
                            type="file"
                            name="gambar"
                            class="input"
                            accept="
                                image/jpeg,
                                image/png,
                                image/webp
                            "
                        >

                        <span class="form-help">
                            Maksimal 5 MB.
                        </span>

                    </div>


                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModal('modalTambah')"
                >
                    Batal
                </button>


                <button
                    type="submit"
                    class="btn-save"
                >
                    Simpan Produk
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     MODAL EDIT
========================================================= -->

<div
    class="modal-overlay"
    id="modalEdit"
>


    <div class="modal">


        <div class="modal-header">

            <h2>
                Edit Produk
            </h2>


            <button
                type="button"
                class="modal-close"
                onclick="closeModal('modalEdit')"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="action"
                value="edit"
            >


            <input
                type="hidden"
                name="id_barang"
                id="edit_id_barang"
            >


            <div class="modal-body">


                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Kode Barang *
                        </label>

                        <input
                            type="text"
                            name="kode_barang"
                            id="edit_kode_barang"
                            class="input"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Nama Barang *
                        </label>

                        <input
                            type="text"
                            name="nama_barang"
                            id="edit_nama_barang"
                            class="input"
                            required
                        >

                    </div>


                    <div class="form-group full">

                        <label>
                            Kategori *
                        </label>

                        <select
                            name="id_kategori"
                            id="edit_id_kategori"
                            class="select"
                            required
                        >

                            <option value="">
                                Pilih kategori
                            </option>


                            <?php foreach (
                                $kategori_list
                                as $kategori
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $kategori['id_kategori'] ?>"
                                >

                                    <?= e(
                                        $kategori['nama_kategori']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Harga Beli
                        </label>

                        <input
                            type="number"
                            name="harga_beli"
                            id="edit_harga_beli"
                            class="input"
                            min="0"
                            step="0.01"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Harga Jual *
                        </label>

                        <input
                            type="number"
                            name="harga_jual"
                            id="edit_harga_jual"
                            class="input"
                            min="0"
                            step="0.01"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Stok
                        </label>

                        <input
                            type="number"
                            name="stok"
                            id="edit_stok"
                            class="input"
                            min="0"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Satuan *
                        </label>

                        <input
                            type="text"
                            name="satuan"
                            id="edit_satuan"
                            class="input"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Stok Minimum
                        </label>

                        <input
                            type="number"
                            name="stok_minimum"
                            id="edit_stok_minimum"
                            class="input"
                            min="0"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Ganti Gambar
                        </label>

                        <input
                            type="file"
                            name="gambar"
                            class="input"
                            accept="
                                image/jpeg,
                                image/png,
                                image/webp
                            "
                        >

                        <span class="form-help">
                            Kosongkan jika tidak ingin mengganti gambar.
                        </span>

                    </div>


                    <div class="form-group full">

                        <label>
                            Gambar Saat Ini
                        </label>

                        <img
                            id="edit_preview"
                            class="preview-image"
                            src="../assets/no-image.png"
                            alt="Preview"
                            onerror="
                                this.src='../assets/SembakoKu (3).png';
                            "
                        >

                    </div>


                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModal('modalEdit')"
                >
                    Batal
                </button>


                <button
                    type="submit"
                    class="btn-save"
                >
                    Simpan Perubahan
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =========================================================
   DRAWER
========================================================= */

const drawer =
    document.getElementById(
        'drawer'
    );

const menuOverlay =
    document.getElementById(
        'menuOverlay'
    );


const menuButton = document.getElementById('menuButton');

function openMenu()
{
    drawer.classList.add('show');
    menuOverlay.classList.add('show');
    document.body.classList.add('menu-open');

    if (menuButton) {
        menuButton.setAttribute('aria-expanded', 'true');
    }
}

function closeMenu()
{
    drawer.classList.remove('show');
    menuOverlay.classList.remove('show');
    document.body.classList.remove('menu-open');

    if (menuButton) {
        menuButton.setAttribute('aria-expanded', 'false');
    }
}

function toggleMenu()
{
    if (drawer.classList.contains('show')) {
        closeMenu();
    } else {
        openMenu();
    }
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeMenu();
    }
});


/* =========================================================
   MODAL
========================================================= */

function openModal(id)
{
    document
        .getElementById(id)
        .classList.add('show');

    document.body.style.overflow =
        'hidden';
}


function closeModal(id)
{
    document
        .getElementById(id)
        .classList.remove('show');

    document.body.style.overflow =
        '';
}


/* =========================================================
   TAMBAH
========================================================= */

function openTambahModal()
{
    openModal(
        'modalTambah'
    );
}


/* =========================================================
   EDIT
========================================================= */

function openEditModal(item)
{

    document.getElementById(
        'edit_id_barang'
    ).value =
        item.id_barang || '';


    document.getElementById(
        'edit_kode_barang'
    ).value =
        item.kode_barang || '';


    document.getElementById(
        'edit_nama_barang'
    ).value =
        item.nama_barang || '';


    document.getElementById(
        'edit_id_kategori'
    ).value =
        item.id_kategori || '';


    document.getElementById(
        'edit_harga_beli'
    ).value =
        item.harga_beli || '0';


    document.getElementById(
        'edit_harga_jual'
    ).value =
        item.harga_jual || '0';


    document.getElementById(
        'edit_stok'
    ).value =
        item.stok || '0';


    document.getElementById(
        'edit_satuan'
    ).value =
        item.satuan || '';


    document.getElementById(
        'edit_stok_minimum'
    ).value =
        item.stok_minimum || '0';


    let gambar =
        item.gambar || '';


    let preview =
        document.getElementById(
            'edit_preview'
        );


    if (gambar !== '') {

        preview.src =
            '../uploads/' +
            gambar;

    } else {

        preview.src =
            '../assets/no-image.png';
    }


    openModal(
        'modalEdit'
    );
}


/* =========================================================
   KLIK LUAR MODAL
========================================================= */

document
    .querySelectorAll(
        '.modal-overlay'
    )
    .forEach(function(overlay) {

        overlay.addEventListener(
            'click',
            function(event) {

                if (
                    event.target ===
                    overlay
                ) {

                    overlay.classList.remove(
                        'show'
                    );

                    document.body.style.overflow =
                        '';
                }

            }
        );

    });


/* =========================================================
   ESC
========================================================= */

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeMenu();

            document
                .querySelectorAll(
                    '.modal-overlay.show'
                )
                .forEach(function(modal) {

                    modal.classList.remove(
                        'show'
                    );

                });

            document.body.style.overflow =
                '';
        }

    }
);


function switchCombinedTab(tab) {
    const produk = tab === 'produk';
    const panelProduk = document.getElementById('panelProduk');
    const panelKategori = document.getElementById('panelKategori');
    const tabProduk = document.getElementById('tabProduk');
    const tabKategori = document.getElementById('tabKategori');

    panelProduk.hidden = !produk;
    panelKategori.hidden = produk;
    tabProduk.classList.toggle('active', produk);
    tabKategori.classList.toggle('active', !produk);

    // Muat halaman kategori hanya saat tab Kategori dibuka.
    // Ini mencegah iframe kategori menahan loading halaman Produk.
    if (!produk) {
        const frame = panelKategori.querySelector('iframe[data-src]');
        if (frame && !frame.getAttribute('src')) {
            frame.setAttribute('src', frame.getAttribute('data-src'));
            frame.removeAttribute('data-src');
        }
    }

    try { sessionStorage.setItem('produkKategoriTab', tab); } catch (e) {}
}
</script>


</body>

</html>