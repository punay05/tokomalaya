<?php

session_start();

include "../config/database.php";

// ========================================
// CEK LOGIN
// ========================================

if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'pelanggan') {
    header("Location: ../index.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? 'Pelanggan';
$email = $_SESSION['email'] ?? '';


// ========================================
// FORMAT RUPIAH
// ========================================

function rupiah($angka)
{
    return 'Rp ' . number_format(
        (float) $angka,
        0,
        ',',
        '.'
    );
}


// ========================================
// JUMLAH KERANJANG
// ========================================

$total_keranjang = 0;

$cek_keranjang = mysqli_query(
    $conn,
    "SHOW TABLES LIKE 'keranjang'"
);

if (
    $cek_keranjang &&
    mysqli_num_rows($cek_keranjang) > 0
) {

    $query_keranjang = mysqli_query(
        $conn,
        "SELECT COALESCE(SUM(jumlah), 0) AS total
         FROM keranjang
         WHERE id_user = $id_user"
    );

    if ($query_keranjang) {

        $data_keranjang =
            mysqli_fetch_assoc($query_keranjang);

        $total_keranjang =
            (int) ($data_keranjang['total'] ?? 0);
    }
}


// ========================================
// AMBIL DATA KERANJANG
// ========================================

$query_cart = "
    SELECT
        k.id_keranjang,
        k.id_barang,
        k.jumlah,
        k.catatan,
        b.nama_barang,
        b.harga_jual,
        b.stok,
        b.satuan,
        b.gambar,
        kat.nama_kategori
    FROM keranjang k
    INNER JOIN barang b
        ON k.id_barang = b.id_barang
    LEFT JOIN kategori kat
        ON b.id_kategori = kat.id_kategori
    WHERE k.id_user = ?
    ORDER BY k.id_keranjang DESC
";

$stmt_cart = mysqli_prepare(
    $conn,
    $query_cart
);

if (!$stmt_cart) {
    die("Gagal menyiapkan data keranjang.");
}

mysqli_stmt_bind_param(
    $stmt_cart,
    "i",
    $id_user
);

mysqli_stmt_execute(
    $stmt_cart
);

$result_cart = mysqli_stmt_get_result(
    $stmt_cart
);

$cart_items = [];

while ($row = mysqli_fetch_assoc($result_cart)) {
    $cart_items[] = $row;
}

mysqli_stmt_close($stmt_cart);


// ========================================
// CEK KERANJANG KOSONG
// ========================================

if (empty($cart_items)) {
    header("Location: keranjang.php");
    exit;
}


// ========================================
// HITUNG TOTAL
// ========================================

$total_harga = 0;

foreach ($cart_items as $item) {

    $harga =
        (float) $item['harga_jual'];

    $jumlah =
        (int) $item['jumlah'];

    $total_harga +=
        $harga * $jumlah;
}


// ========================================
// ERROR CHECKOUT
// ========================================

$error = '';


// ========================================
// PROSES CHECKOUT
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $alamat_pengiriman =
        trim(
            $_POST['alamat_pengiriman'] ?? ''
        );

    $metode_pembayaran =
        strtoupper(trim($_POST['metode_pembayaran'] ?? ''));

    $bukti_pembayaran_path = null;


    // ====================================
    // VALIDASI
    // ====================================

    if ($alamat_pengiriman === '') {

        $error =
            "Alamat pengiriman wajib diisi.";

    } elseif (!in_array($metode_pembayaran, ['QRIS', 'COD'], true)) {

        $error = "Pilih metode pembayaran QRIS atau COD.";

    } elseif ($metode_pembayaran === 'QRIS' &&
        (!isset($_FILES['bukti_pembayaran']) ||
         $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK)) {

        $error = "Untuk pembayaran QRIS, bukti pembayaran wajib diunggah.";

    } else {

        // Validasi bukti QRIS di sisi server (maksimal 3 MB).
        if ($metode_pembayaran === 'QRIS') {
            $file = $_FILES['bukti_pembayaran'];
            if ($file['size'] > 3 * 1024 * 1024) {
                $error = "Ukuran bukti pembayaran maksimal 3 MB.";
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($allowed[$mime])) {
                    $error = "Bukti harus berupa gambar JPG, PNG, atau WEBP.";
                } else {
                    $upload_dir = __DIR__ . '/../uploads/bukti_pembayaran';
                    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0750, true)) {
                        $error = "Folder upload bukti pembayaran tidak dapat dibuat.";
                    } else {
                        $file_name = 'qris_' . $id_user . '_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                        if (!move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $file_name)) {
                            $error = "Bukti pembayaran gagal disimpan. Coba lagi.";
                        } else {
                            $bukti_pembayaran_path = 'uploads/bukti_pembayaran/' . $file_name;
                        }
                    }
                }
            }
        }

        if ($error === '') {

        // ====================================
        // MULAI TRANSAKSI
        // ====================================

        mysqli_begin_transaction($conn);

        try {

            // ====================================
            // AMBIL ULANG DATA KERANJANG
            // ====================================

            $query_checkout = "
                SELECT
                    k.id_keranjang,
                    k.id_barang,
                    k.jumlah,
                    k.catatan,
                    b.nama_barang,
                    b.harga_jual,
                    b.stok,
                    b.satuan,
                    b.gambar
                FROM keranjang k
                INNER JOIN barang b
                    ON k.id_barang = b.id_barang
                WHERE k.id_user = ?
                ORDER BY k.id_keranjang DESC
                FOR UPDATE
            ";

            $stmt_checkout =
                mysqli_prepare(
                    $conn,
                    $query_checkout
                );

            if (!$stmt_checkout) {
                throw new Exception(
                    "Gagal mengambil data keranjang."
                );
            }

            mysqli_stmt_bind_param(
                $stmt_checkout,
                "i",
                $id_user
            );

            if (
                !mysqli_stmt_execute(
                    $stmt_checkout
                )
            ) {

                mysqli_stmt_close(
                    $stmt_checkout
                );

                throw new Exception(
                    "Gagal mengambil data keranjang."
                );
            }

            $result_checkout =
                mysqli_stmt_get_result(
                    $stmt_checkout
                );

            $checkout_items = [];

            while (
                $row =
                    mysqli_fetch_assoc(
                        $result_checkout
                    )
            ) {

                $checkout_items[] = $row;
            }

            mysqli_stmt_close(
                $stmt_checkout
            );


            // ====================================
            // CEK KERANJANG
            // ====================================

            if (empty($checkout_items)) {

                throw new Exception(
                    "Keranjang kosong."
                );
            }


            // ====================================
            // HITUNG ULANG TOTAL
            // + CEK STOK
            // ====================================

            $total_harga = 0;

            foreach (
                $checkout_items
                as $item
            ) {

                $id_barang =
                    (int) $item['id_barang'];

                $jumlah =
                    (int) $item['jumlah'];

                $stok =
                    (int) $item['stok'];

                $harga =
                    (float) $item['harga_jual'];


                if ($jumlah <= 0) {

                    throw new Exception(
                        "Jumlah produk tidak valid."
                    );
                }


                if ($jumlah > $stok) {

                    throw new Exception(
                        "Stok produk \"" .
                        $item['nama_barang'] .
                        "\" tidak mencukupi. " .
                        "Stok tersedia: " .
                        $stok .
                        "."
                    );
                }


                $total_harga +=
                    $harga * $jumlah;
            }


            // ====================================
            // INSERT PESANAN
            // ====================================

            $query_pesanan = "
                INSERT INTO pesanan
                (
                    id_user,
                    total_harga,
                    alamat_pengiriman,
                    metode_pembayaran,
                    status,
                    status_pembayaran,
                    bukti_pembayaran
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    'menunggu',
                    ?,
                    ?
                )
            ";

            $stmt_pesanan =
                mysqli_prepare(
                    $conn,
                    $query_pesanan
                );

            if (!$stmt_pesanan) {

                throw new Exception(
                    "Gagal menyiapkan pesanan."
                );
            }


            $status_pembayaran_awal = ($metode_pembayaran === 'QRIS') ? 'menunggu_verifikasi' : 'belum_bayar';

            mysqli_stmt_bind_param(
                $stmt_pesanan,
                "idssss",
                $id_user,
                $total_harga,
                $alamat_pengiriman,
                $metode_pembayaran,
                $status_pembayaran_awal,
                $bukti_pembayaran_path
            );


            if (
                !mysqli_stmt_execute(
                    $stmt_pesanan
                )
            ) {

                $pesanan_error =
                    mysqli_stmt_error(
                        $stmt_pesanan
                    );

                mysqli_stmt_close(
                    $stmt_pesanan
                );

                throw new Exception(
                    "Gagal membuat pesanan: " .
                    $pesanan_error
                );
            }


            $id_pesanan =
                mysqli_insert_id($conn);


            mysqli_stmt_close(
                $stmt_pesanan
            );


            // ====================================
            // INSERT DETAIL PESANAN
            // ====================================

            $query_detail = "
                INSERT INTO detail_pesanan
                (
                    id_pesanan,
                    id_barang,
                    jumlah,
                    catatan,
                    harga,
                    subtotal
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

            $stmt_detail =
                mysqli_prepare(
                    $conn,
                    $query_detail
                );

            if (!$stmt_detail) {

                throw new Exception(
                    "Gagal menyiapkan detail pesanan."
                );
            }


            // ====================================
            // UPDATE STOK
            // ====================================

            $query_stock = "
                UPDATE barang
                SET stok = stok - ?
                WHERE id_barang = ?
                AND stok >= ?
            ";

            $stmt_stock =
                mysqli_prepare(
                    $conn,
                    $query_stock
                );

            if (!$stmt_stock) {

                mysqli_stmt_close(
                    $stmt_detail
                );

                throw new Exception(
                    "Gagal menyiapkan update stok."
                );
            }


            // ====================================
            // PROSES SETIAP ITEM
            // ====================================

            foreach (
                $checkout_items
                as $item
            ) {

                $id_barang =
                    (int) $item['id_barang'];

                $jumlah =
                    (int) $item['jumlah'];

                $harga =
                    (float) $item['harga_jual'];


                // ================================
                // AMBIL CATATAN
                // ================================

                $catatan =
                    trim(
                        $item['catatan'] ?? ''
                    );


                // ================================
                // SUBTOTAL
                // ================================

                $subtotal_item =
                    $harga * $jumlah;


                // ================================
                // INSERT DETAIL
                // ================================

                mysqli_stmt_bind_param(
                    $stmt_detail,
                    "iiisdd",
                    $id_pesanan,
                    $id_barang,
                    $jumlah,
                    $catatan,
                    $harga,
                    $subtotal_item
                );


                if (
                    !mysqli_stmt_execute(
                        $stmt_detail
                    )
                ) {

                    $detail_error =
                        mysqli_stmt_error(
                            $stmt_detail
                        );

                    mysqli_stmt_close(
                        $stmt_detail
                    );

                    mysqli_stmt_close(
                        $stmt_stock
                    );

                    throw new Exception(
                        "Gagal menyimpan detail pesanan: " .
                        $detail_error
                    );
                }


                // ================================
                // KURANGI STOK
                // ================================

                mysqli_stmt_bind_param(
                    $stmt_stock,
                    "iii",
                    $jumlah,
                    $id_barang,
                    $jumlah
                );


                if (
                    !mysqli_stmt_execute(
                        $stmt_stock
                    )
                ) {

                    $stock_error =
                        mysqli_stmt_error(
                            $stmt_stock
                        );

                    mysqli_stmt_close(
                        $stmt_detail
                    );

                    mysqli_stmt_close(
                        $stmt_stock
                    );

                    throw new Exception(
                        "Gagal mengurangi stok: " .
                        $stock_error
                    );
                }


                if (
                    mysqli_stmt_affected_rows(
                        $stmt_stock
                    ) <= 0
                ) {

                    mysqli_stmt_close(
                        $stmt_detail
                    );

                    mysqli_stmt_close(
                        $stmt_stock
                    );

                    throw new Exception(
                        "Stok produk tidak mencukupi."
                    );
                }
            }


            mysqli_stmt_close(
                $stmt_detail
            );

            mysqli_stmt_close(
                $stmt_stock
            );


            // ====================================
            // HAPUS KERANJANG
            // ====================================

            $query_delete_cart = "
                DELETE FROM keranjang
                WHERE id_user = ?
            ";

            $stmt_delete_cart =
                mysqli_prepare(
                    $conn,
                    $query_delete_cart
                );

            if (!$stmt_delete_cart) {

                throw new Exception(
                    "Gagal menyiapkan penghapusan keranjang."
                );
            }


            mysqli_stmt_bind_param(
                $stmt_delete_cart,
                "i",
                $id_user
            );


            if (
                !mysqli_stmt_execute(
                    $stmt_delete_cart
                )
            ) {

                $delete_error =
                    mysqli_stmt_error(
                        $stmt_delete_cart
                    );

                mysqli_stmt_close(
                    $stmt_delete_cart
                );

                throw new Exception(
                    "Gagal mengosongkan keranjang: " .
                    $delete_error
                );
            }


            mysqli_stmt_close(
                $stmt_delete_cart
            );


            // ====================================
            // COMMIT
            // ====================================

            mysqli_commit($conn);


            // ====================================
            // REDIRECT
            // ====================================

            header(
                "Location: pesanan.php?success=" .
                urlencode(
                    "Pesanan berhasil dibuat."
                )
            );

            exit;


        } catch (Exception $e) {

            mysqli_rollback($conn);

            if ($bukti_pembayaran_path) {
                $saved_file = __DIR__ . '/../' . $bukti_pembayaran_path;
                if (is_file($saved_file)) { @unlink($saved_file); }
            }

            $error = $e->getMessage();
        }
        } // end if no upload error
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

    <title>
        Checkout - Toko Malaya
    </title>


    <style>

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

            background: #f8fafc;

            color: #1f2937;
        }


        /* ========================================
           NAVBAR
        ======================================== */

        .navbar {
            position: sticky;

            top: 0;

            z-index: 1000;

            background: #ffffff;

            border-bottom:
                1px solid #e5e7eb;

            box-shadow:
                0 2px 10px
                rgba(
                    15,
                    23,
                    42,
                    0.05
                );
        }


        .navbar-inner {
            max-width: 1200px;

            min-height: 72px;

            margin: 0 auto;

            padding:
                0 24px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 25px;
        }


        /* ========================================
           LOGO
        ======================================== */

        .logo {
            display: flex;

            align-items: center;

            gap: 10px;

            color: #153b63;

            text-decoration: none;

            flex-shrink: 0;
        }


        .logo-icon {
            width: 42px;
            height: 42px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #1677d2;

            border-radius: 11px;

            overflow: hidden;
        }


        .logo-icon img {
            width: 36px;
            height: 36px;

            object-fit: contain;
        }


        .logo-text {
            font-size: 20px;

            font-weight: bold;
        }


        /* ========================================
           NAV MENU
        ======================================== */

        .nav-menu {
            display: flex;

            align-items: center;

            gap: 28px;

            margin-left: auto;
        }


        .nav-menu a {
            position: relative;

            padding:
                26px 0;

            color: #4b5563;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

            transition:
                color 0.2s ease;
        }


        .nav-menu a:hover,
        .nav-menu a.active {
            color: #1677d2;
        }


        /*
         * Di checkout tidak ada menu yang aktif.
         * Jadi tidak menggunakan underline.
         */


        /* ========================================
           NAV RIGHT
        ======================================== */

        .nav-right {
            display: flex;

            align-items: center;

            gap: 10px;
        }


        .cart-button {
            position: relative;

            display: flex;

            align-items: center;

            justify-content: center;

            width: 42px;
            height: 42px;

            border-radius: 9px;

            background: #f1f6fc;

            color: #1677d2;

            text-decoration: none;

            font-size: 20px;

            transition:
                0.2s ease;
        }


        .cart-button:hover {
            background: #e3effb;

            transform:
                translateY(-1px);
        }


        .cart-badge {
            position: absolute;

            top: -5px;
            right: -5px;

            min-width: 19px;
            height: 19px;

            padding:
                0 5px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #dc3545;

            color: white;

            border-radius: 20px;

            font-size: 10px;

            font-weight: bold;
        }


        .profile-button {
            display: flex;

            align-items: center;

            gap: 8px;

            padding:
                9px 13px;

            background: #f8fafc;

            border:
                1px solid #e5e7eb;

            border-radius: 9px;

            color: #374151;

            text-decoration: none;

            font-size: 13px;

            font-weight: 600;
        }


        .profile-button:hover {
            border-color: #1677d2;

            color: #1677d2;
        }


        .logout-button {
            display: flex;

            align-items: center;

            justify-content: center;

            padding:
                10px 14px;

            border-radius: 8px;

            background: #ffffff;

            border:
                1px solid #dbe3ec;

            color: #6b7280;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;
        }


        .logout-button:hover {
            background: #f8fafc;

            color: #dc3545;

            border-color: #f1b5bc;
        }


        /* ========================================
           MAIN
        ======================================== */

        .main {
            max-width: 1200px;

            margin: 0 auto;

            padding:
                28px 24px 50px;
        }


        .page-title {
            margin:
                0 0 22px;

            color: #153b63;

            font-size: 26px;
        }


        .checkout-grid {
            display: grid;

            grid-template-columns:
                1.6fr 1fr;

            gap: 20px;

            align-items: start;
        }


        /* ========================================
           CARD
        ======================================== */

        .card {
            background: white;

            border:
                1px solid #e5e7eb;

            border-radius: 13px;

            padding: 20px;

            box-shadow:
                0 3px 12px
                rgba(
                    15,
                    23,
                    42,
                    0.04
                );
        }


        .card + .card {
            margin-top: 20px;
        }


        .card-title {
            margin:
                0 0 18px;

            color: #153b63;

            font-size: 19px;
        }


        /* ========================================
           ERROR
        ======================================== */

        .error-alert {
            margin-bottom: 20px;

            padding:
                13px 15px;

            background: #fff1f2;

            border:
                1px solid #fecdd3;

            border-radius: 9px;

            color: #be123c;

            font-size: 13px;

            line-height: 1.5;
        }


        /* ========================================
           FORM
        ======================================== */

        .form-group {
            margin-bottom: 18px;
        }


        .form-group:last-child {
            margin-bottom: 0;
        }


        .form-group label {
            display: block;

            margin-bottom: 8px;

            color: #374151;

            font-size: 13px;

            font-weight: bold;
        }


        .form-group textarea,
        .form-group select {
            width: 100%;

            padding:
                11px 12px;

            border:
                1px solid #dbe3ec;

            border-radius: 8px;

            background: white;

            color: #374151;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 13px;

            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }


        .form-group textarea {
            min-height: 110px;

            resize: vertical;

            line-height: 1.5;
        }


        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #1677d2;

            box-shadow:
                0 0 0 3px
                rgba(
                    22,
                    119,
                    210,
                    0.08
                );
        }


        /* ========================================
           PRODUCT
        ======================================== */

        .checkout-product {
            display: flex;

            align-items: flex-start;

            gap: 14px;

            padding:
                14px 0;

            border-bottom:
                1px solid #eef0f3;
        }


        .checkout-product:first-of-type {
            padding-top: 0;
        }


        .checkout-product:last-child {
            border-bottom: 0;

            padding-bottom: 0;
        }


        .checkout-product-image {
            width: 72px;
            height: 72px;

            flex-shrink: 0;

            display: block;

            object-fit: cover;

            background: #f3f4f6;

            border-radius: 8px;

            border:
                1px solid #eef0f3;
        }


        .checkout-product-info {
            flex: 1;

            min-width: 0;
        }


        .checkout-product-name {
            margin-bottom: 5px;

            color: #1f2937;

            font-size: 14px;

            font-weight: bold;

            line-height: 1.4;
        }


        .checkout-product-meta {
            color: #6b7280;

            font-size: 12px;

            line-height: 1.5;
        }


        /* ========================================
           CATATAN PRODUK
        ======================================== */

        .checkout-product-note {
            margin-top: 7px;

            padding:
                7px 9px;

            background: #fff8e1;

            border:
                1px solid #f1df9a;

            border-radius: 6px;

            color: #795548;

            font-size: 11px;

            line-height: 1.5;

            word-break: break-word;
        }


        .checkout-product-price {
            flex-shrink: 0;

            color: #1f2937;

            font-size: 13px;

            font-weight: bold;

            text-align: right;

            white-space: nowrap;
        }


        /* ========================================
           SUMMARY
        ======================================== */

        .summary-row {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            padding:
                10px 0;

            color: #6b7280;

            font-size: 13px;
        }


        .summary-row strong {
            color: #374151;
        }


        .summary-row.total {
            margin-top: 8px;

            padding-top: 16px;

            border-top:
                1px solid #e5e7eb;

            color: #153b63;

            font-size: 17px;

            font-weight: bold;
        }


        .summary-row.total span:last-child {
            color: #1677d2;
        }


        /* ========================================
           BUTTON
        ======================================== */

        .btn-checkout {
            width: 100%;

            margin-top: 18px;

            padding:
                12px 18px;

            border: 0;

            border-radius: 8px;

            background: #1677d2;

            color: white;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 13px;

            font-weight: bold;

            cursor: pointer;

            transition:
                0.2s ease;
        }


        .btn-checkout:hover {
            background: #125ca3;

            transform:
                translateY(-1px);
        }


        .btn-back {
            display: block;

            width: 100%;

            margin-top: 10px;

            padding:
                11px 18px;

            border:
                1px solid #dbe3ec;

            border-radius: 8px;

            background: white;

            color: #6b7280;

            text-align: center;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;
        }


        .btn-back:hover {
            border-color: #1677d2;

            color: #1677d2;

            background: #f8fafc;
        }


        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 950px) {

            .nav-menu {
                gap: 16px;
            }


            .nav-menu a {
                font-size: 13px;
            }


            .profile-button {
                display: none;
            }


            .checkout-grid {
                grid-template-columns:
                    1fr;
            }

        }


        @media (max-width: 700px) {

            .navbar-inner {
                min-height: 64px;

                padding:
                    0 15px;

                gap: 10px;
            }


            .logo-text {
                font-size: 17px;
            }


            .logo-icon {
                width: 38px;
                height: 38px;
            }


            .logo-icon img {
                width: 32px;
                height: 32px;
            }


            .nav-menu {
                display: none;
            }


            .logout-button {
                padding:
                    9px 10px;

                font-size: 0;
            }


            .logout-button::before {
                content: "↪";

                font-size: 18px;
            }


            .main {
                padding:
                    20px 14px 35px;
            }


            .page-title {
                font-size: 23px;
            }


            .card {
                padding: 17px;
            }

        }


        @media (max-width: 430px) {

            .checkout-product {
                gap: 10px;
            }


            .checkout-product-image {
                width: 62px;
                height: 62px;
            }


            .checkout-product-price {
                font-size: 12px;
            }


            .cart-button {
                width: 38px;
                height: 38px;
            }

        }

    </style>

</head>


<body>


<!-- ==========================================
     NAVBAR - SAMA DENGAN DASHBOARD
========================================== -->

<header class="navbar">

    <div class="navbar-inner">


        <!-- LOGO -->

        <a
            href="dashboard.php"
            class="logo"
        >

            <div class="logo-icon">

                <img
                    src="../assets/SembakoKu (3).png"
                    alt="Toko Malaya"
                >

            </div>


            <div class="logo-text">
                Toko Malaya
            </div>

        </a>


        <!-- MENU -->

        <nav class="nav-menu">

            <a href="dashboard.php">
                Beranda
            </a>


            <a href="produk.php">
                Produk
            </a>


            <a href="pesanan.php">
                Pesanan
            </a>

        </nav>


        <!-- RIGHT -->

        <div class="nav-right">


            <!-- KERANJANG -->

            <a
                href="keranjang.php"
                class="cart-button"
                title="Keranjang"
            >

                🛒


                <?php if ($total_keranjang > 0): ?>

                    <span class="cart-badge">

                        <?= $total_keranjang; ?>

                    </span>

                <?php endif; ?>

            </a>


            <!-- PROFILE -->

            <a
                href="profil.php"
                class="profile-button"
            >

                👤

                <?= htmlspecialchars($nama); ?>

            </a>


            <!-- LOGOUT -->

            <a
                href="../auth/logout.php"
                class="logout-button"
            >
                Logout
            </a>


        </div>

    </div>

</header>


<!-- ==========================================
     MAIN
========================================== -->

<main class="main">


    <h1 class="page-title">
        Checkout
    </h1>


    <?php if ($error !== ''): ?>

        <div class="error-alert">

            <?= htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        action=""
        enctype="multipart/form-data"
    >

        <div class="checkout-grid">


            <!-- ======================================
                 KIRI
            ======================================= -->

            <div>


                <!-- INFORMASI PENGIRIMAN -->

                <section class="card">

                    <h2 class="card-title">
                        Informasi Pengiriman
                    </h2>


                    <div class="form-group">

                        <label for="alamat_pengiriman">
                            Alamat Pengiriman
                        </label>


                        <textarea
                            id="alamat_pengiriman"
                            name="alamat_pengiriman"
                            placeholder="Masukkan alamat lengkap pengiriman..."
                            required
                        ><?= htmlspecialchars(
                            $_POST['alamat_pengiriman'] ?? ''
                        ); ?></textarea>

                    </div>


                    <div class="form-group">

                        <label for="metode_pembayaran">
                            Metode Pembayaran
                        </label>


                        <select
                            id="metode_pembayaran"
                            name="metode_pembayaran"
                            required
                        >

                            <option value="">
                                -- Pilih Pembayaran --
                            </option>


                            <option
                                value="COD"
                                <?= (
                                    ($_POST['metode_pembayaran'] ?? '') === 'COD'
                                )
                                    ? 'selected'
                                    : ''; ?>
                            >
                                COD
                            </option>


                            <option
                                value="QRIS"
                                <?= (($_POST['metode_pembayaran'] ?? '') === 'QRIS') ? 'selected' : ''; ?>
                            >QRIS (unggah bukti pembayaran)</option>

                        </select>

                    </div>

                    <!-- Upload QRIS ditampilkan di langkah berikutnya, setelah tombol Buat Pesanan diklik. -->
                    <div class="form-group" id="qris-upload-group" style="display:none;">
                        <label for="bukti_pembayaran">Bukti Pembayaran QRIS *</label>
                        <p style="font-size:13px;color:#667085;margin:0 0 10px;">Unggah screenshot/foto transaksi berhasil (JPG, PNG, WEBP; maksimal 3 MB). Bukti akan diperiksa kasir sebelum pesanan diproses.</p>
                        <input type="file" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/jpeg,image/png,image/webp" />
                    </div>

                </section>


                <!-- PRODUK -->

                <section class="card">

                    <h2 class="card-title">
                        Produk yang Dibeli
                    </h2>


                    <?php foreach (
                        $cart_items
                        as $item
                    ): ?>

                        <?php

                        $harga_item =
                            (float)
                            $item['harga_jual'];

                        $jumlah_item =
                            (int)
                            $item['jumlah'];

                        $subtotal_item =
                            $harga_item *
                            $jumlah_item;

                        $catatan_item =
                            trim(
                                $item['catatan'] ?? ''
                            );

                        $gambar =
                            trim(
                                $item['gambar'] ?? ''
                            );

                        /*
                         * Dashboard menggunakan:
                         * ../uploads/
                         */
                        $gambar_src =
                            "../uploads/" .
                            ltrim(
                                $gambar,
                                '/'
                            );

                        ?>


                        <div class="checkout-product">


                            <!-- GAMBAR -->

                            <?php if (
                                $gambar !== ''
                            ): ?>

                                <img
                                    src="<?= htmlspecialchars(
                                        $gambar_src
                                    ); ?>"
                                    alt="<?= htmlspecialchars(
                                        $item['nama_barang']
                                    ); ?>"
                                    class="checkout-product-image"
                                    onerror="this.onerror=null;this.src='../assets/no-image.png';"
                                >

                            <?php else: ?>

                                <img
                                    src="../assets/no-image.png"
                                    alt="Tidak ada gambar"
                                    class="checkout-product-image"
                                >

                            <?php endif; ?>


                            <!-- INFORMASI -->

                            <div class="checkout-product-info">


                                <div class="checkout-product-name">

                                    <?= htmlspecialchars(
                                        $item['nama_barang']
                                    ); ?>

                                </div>


                                <div class="checkout-product-meta">

                                    <?= $jumlah_item; ?>

                                    <?= htmlspecialchars(
                                        $item['satuan'] ?? 'Pcs'
                                    ); ?>

                                    ×

                                    <?= rupiah(
                                        $harga_item
                                    ); ?>

                                </div>


                                <!-- CATATAN PRODUK -->

                                <?php if (
                                    $catatan_item !== ''
                                ): ?>

                                    <div class="checkout-product-note">

                                        📝

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $catatan_item
                                            )
                                        ); ?>

                                    </div>

                                <?php endif; ?>


                            </div>


                            <!-- SUBTOTAL -->

                            <div class="checkout-product-price">

                                <?= rupiah(
                                    $subtotal_item
                                ); ?>

                            </div>


                        </div>

                    <?php endforeach; ?>


                </section>

            </div>


            <!-- ======================================
                 KANAN
            ======================================= -->

            <div>


                <section class="card">

                    <h2 class="card-title">
                        Ringkasan Pesanan
                    </h2>


                    <div class="summary-row">

                        <span>
                            Subtotal
                        </span>


                        <strong>
                            <?= rupiah(
                                $total_harga
                            ); ?>
                        </strong>

                    </div>


                    <div class="summary-row total">

                        <span>
                            Total
                        </span>


                        <span>
                            <?= rupiah(
                                $total_harga
                            ); ?>
                        </span>

                    </div>


                    <button
                        type="button"
                        class="btn-checkout"
                        id="btn-buat-pesanan"
                    >
                        Buat Pesanan
                    </button>


                    <a
                        href="keranjang.php"
                        class="btn-back"
                    >
                        Kembali ke Keranjang
                    </a>

                </section>


            </div>


        </div>

        <!-- Dialog langkah pembayaran QRIS -->
        <div class="qris-modal" id="qris-modal" aria-hidden="true">
            <div class="qris-modal-backdrop" data-close-qris></div>
            <section class="qris-modal-card" role="dialog" aria-modal="true" aria-labelledby="qris-modal-title">
                <button type="button" class="qris-modal-close" aria-label="Tutup" data-close-qris>&times;</button>
                <div class="qris-modal-icon">▦</div>
                <h2 id="qris-modal-title">Pembayaran QRIS</h2>
                <p class="qris-modal-intro">Pindai QRIS resmi Toko Malaya melalui aplikasi pembayaran kamu, lalu unggah bukti transaksi berhasil di bawah ini.</p>
                <div class="qris-placeholder">
                    <strong>QRIS Toko Malaya</strong>
                    <img
                        src="../uploads/qris_toko_malaya.png"
                        alt="Kode QRIS pembayaran Toko Malaya"
                        class="qris-code-image"
                        loading="eager"
                    >
                    <p>Pindai kode QRIS di atas melalui aplikasi pembayaran kamu.</p>
                    <small>Pastikan nama merchant dan nominal pembayaran benar sebelum menyelesaikan transaksi.</small>
                </div>
                <div class="qris-upload-modal" id="qris-upload-modal-slot"></div>
                <p class="qris-review-note">Setelah pesanan diajukan, kasir akan memeriksa bukti pembayaran. Pesanan QRIS tidak boleh dikirim sebelum pembayaran dikonfirmasi.</p>
                <div class="qris-modal-actions">
                    <button type="button" class="btn-back" data-close-qris>Periksa Kembali</button>
                    <button type="button" class="btn-checkout" id="btn-proses-pesanan">Proses Pesanan</button>
                </div>
            </section>
        </div>

    </form>


</main>

<style>
.qris-modal{display:none;position:fixed;inset:0;z-index:2000;align-items:center;justify-content:center;padding:18px}
.qris-modal.is-open{display:flex}
.qris-modal-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.62);backdrop-filter:blur(3px)}
.qris-modal-card{position:relative;width:min(100%,540px);max-height:90vh;overflow:auto;background:#fff;border-radius:20px;padding:30px;box-shadow:0 24px 70px rgba(15,23,42,.25)}
.qris-modal-close{position:absolute;right:16px;top:12px;border:0;background:#f1f5f9;border-radius:50%;width:36px;height:36px;font-size:25px;cursor:pointer;color:#475569}
.qris-modal-icon{width:54px;height:54px;display:grid;place-items:center;margin:0 auto 12px;background:#e8f2ff;color:#1677d2;border-radius:16px;font-size:30px}
.qris-modal-card h2{text-align:center;color:#153b63;margin:0 0 10px;font-size:24px}
.qris-modal-intro{text-align:center;color:#64748b;font-size:14px;line-height:1.6;margin:0 0 20px}
.qris-placeholder{border:1px dashed #b8cce3;border-radius:14px;background:#f8fbff;padding:22px;text-align:center;margin-bottom:20px}
.qris-code-image{display:block;width:min(100%,320px);height:auto;aspect-ratio:1/1;object-fit:contain;margin:14px auto;padding:8px;background:#fff;border:1px solid #e2e8f0;border-radius:12px}\n.qris-placeholder-mark{width:120px;height:120px;margin:0 auto 14px;display:grid;place-items:center;border:1px solid #dbe4ee;border-radius:10px;background:repeating-linear-gradient(45deg,#f1f5f9,#f1f5f9 7px,#fff 7px,#fff 14px);color:#64748b;font-weight:800;font-size:23px}
.qris-placeholder strong{color:#153b63}.qris-placeholder p{margin:7px 0;color:#b45309;font-size:14px}.qris-placeholder small{display:block;color:#64748b;line-height:1.5}
.qris-upload-modal .form-group{display:block!important;margin:0 0 16px!important}.qris-upload-modal input[type=file]{display:block;width:100%;margin-top:10px;padding:12px;border:1px solid #dbe3ed;border-radius:10px;background:#fff}
.qris-review-note{font-size:13px;line-height:1.5;color:#92400e;background:#fffbeb;border-radius:10px;padding:12px;margin:14px 0}
.qris-modal-actions{display:flex;gap:10px}.qris-modal-actions button{flex:1;min-height:44px;cursor:pointer}.qris-modal-actions .btn-back{display:flex;align-items:center;justify-content:center;text-decoration:none}
@media(max-width:520px){.qris-modal-card{padding:24px 18px}.qris-modal-actions{flex-direction:column}}
</style>

<script>
(function () {
    const form = document.querySelector('form[method="POST"]');
    const payment = document.getElementById('metode_pembayaran');
    const group = document.getElementById('qris-upload-group');
    const proof = document.getElementById('bukti_pembayaran');
    const modal = document.getElementById('qris-modal');
    const openButton = document.getElementById('btn-buat-pesanan');
    const processButton = document.getElementById('btn-proses-pesanan');
    const slot = document.getElementById('qris-upload-modal-slot');
    let confirmedQrisStep = false;

    function openModal() {
        if (group && slot && group.parentElement !== slot) slot.appendChild(group);
        group.style.display = 'block';
        if (proof) proof.required = true;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        // Keep the upload control in the modal once opened, so the chosen file is not reset.
    }
    function toggleQris() {
        const isQris = payment && payment.value === 'QRIS';
        // Bukti baru diwajibkan setelah dialog QRIS dibuka.
        if (proof) proof.required = !!(isQris && modal.classList.contains('is-open'));
        if (!isQris && group && slot && group.parentElement === slot) {
            const infoCard = document.querySelector('.card');
            infoCard.appendChild(group);
            group.style.display = 'none';
        }
    }
    if (payment) payment.addEventListener('change', toggleQris);
    if (openButton) openButton.addEventListener('click', function () {
        // Validasi alamat dan metode terlebih dahulu; bukti QRIS diisi pada dialog berikutnya.
        if (proof) proof.required = false;
        if (!form.reportValidity()) return;
        if (!payment.value) { payment.focus(); return; }
        if (payment.value === 'QRIS') {
            openModal();
        } else {
            confirmedQrisStep = true;
            if (proof) proof.required = false;
            form.requestSubmit();
        }
    });
    if (processButton) processButton.addEventListener('click', function () {
        if (!proof || !proof.files || proof.files.length === 0) {
            if (proof) { proof.setCustomValidity('Unggah bukti pembayaran QRIS terlebih dahulu.'); proof.reportValidity(); proof.setCustomValidity(''); }
            return;
        }
        if (proof.files[0].size > 3 * 1024 * 1024) {
            proof.setCustomValidity('Ukuran bukti pembayaran maksimal 3 MB.'); proof.reportValidity(); proof.setCustomValidity('');
            return;
        }
        confirmedQrisStep = true;
        closeModal();
        form.requestSubmit();
    });
    document.querySelectorAll('[data-close-qris]').forEach(function (el) { el.addEventListener('click', closeModal); });
    if (form) form.addEventListener('submit', function (event) {
        if (payment && payment.value === 'QRIS' && !confirmedQrisStep) {
            event.preventDefault();
            openModal();
        }
    });
    toggleQris();
})();
</script>

</body>

</html>