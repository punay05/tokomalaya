<?php
session_start();
include "../config/database.php";

/* =========================================================
   CEK LOGIN
========================================================= */
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

/* =========================================================
   FUNGSI
========================================================= */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   FOLDER UPLOAD
========================================================= */
$upload_dir = "../uploads/profil/";
$upload_url = "../uploads/profil/";

if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

/* =========================================================
   AMBIL DATA USER
========================================================= */
$data_user = null;

$query_user = mysqli_query(
    $conn,
    "SELECT *
     FROM users
     WHERE id_user = $id_user
     LIMIT 1"
);

if ($query_user) {
    $data_user = mysqli_fetch_assoc($query_user);
}

if (!$data_user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

/* =========================================================
   DATA USER
========================================================= */
$nama = $data_user['nama'] ?? $nama;
$email = $data_user['email'] ?? $email;
$foto_profil = $data_user['foto_profil'] ?? '';

/* =========================================================
   FOTO VALID
========================================================= */
$foto_ada = false;

if ($foto_profil !== '') {
    $foto_file = $upload_dir . basename($foto_profil);

    if (is_file($foto_file)) {
        $foto_ada = true;
    }
}

/* =========================================================
   INISIAL
========================================================= */
$nama_bersih = trim($nama);

if ($nama_bersih !== '') {
    $inisial = strtoupper(substr($nama_bersih, 0, 1));
} else {
    $inisial = 'P';
}

/* =========================================================
   HITUNG KERANJANG
========================================================= */
$total_keranjang = 0;

$cek_keranjang = mysqli_query(
    $conn,
    "SHOW TABLES LIKE 'keranjang'"
);

if ($cek_keranjang && mysqli_num_rows($cek_keranjang) > 0) {

    $query_keranjang = mysqli_query(
        $conn,
        "SELECT COALESCE(SUM(jumlah), 0) AS total
         FROM keranjang
         WHERE id_user = $id_user"
    );

    if ($query_keranjang) {
        $data_keranjang = mysqli_fetch_assoc($query_keranjang);
        $total_keranjang = (int) ($data_keranjang['total'] ?? 0);
    }
}

/* =========================================================
   PESAN
========================================================= */
$pesan = '';
$tipe_pesan = '';

/* =========================================================
   PROSES POST
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';

    /* =====================================================
       UPLOAD / GANTI FOTO
    ===================================================== */
    if ($aksi === 'upload_foto') {

        if (!isset($_FILES['foto_profil'])) {

            $pesan = 'Silakan pilih foto terlebih dahulu.';
            $tipe_pesan = 'error';

        } else {

            $file = $_FILES['foto_profil'];

            if ($file['error'] !== UPLOAD_ERR_OK) {

                switch ($file['error']) {

                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $pesan = 'Ukuran foto terlalu besar.';
                        break;

                    case UPLOAD_ERR_NO_FILE:
                        $pesan = 'Silakan pilih foto terlebih dahulu.';
                        break;

                    default:
                        $pesan = 'Foto gagal diupload.';
                        break;
                }

                $tipe_pesan = 'error';

            } else {

                /* Maksimal 2 MB */
                $max_size = 2 * 1024 * 1024;

                if ($file['size'] > $max_size) {

                    $pesan = 'Ukuran foto maksimal 2 MB.';
                    $tipe_pesan = 'error';

                } else {

                    /* Validasi MIME */
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);

                    if ($finfo) {
                        $mime = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);
                    } else {
                        $mime = '';
                    }

                    $mime_allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($mime_allowed[$mime])) {

                        $pesan = 'Format foto harus JPG, JPEG, PNG, atau WEBP.';
                        $tipe_pesan = 'error';

                    } elseif (@getimagesize($file['tmp_name']) === false) {

                        $pesan = 'File yang dipilih bukan gambar yang valid.';
                        $tipe_pesan = 'error';

                    } else {

                        $ekstensi = $mime_allowed[$mime];

                        /*
                         * Nama file unik berdasarkan ID user.
                         */
                        $nama_file_baru =
                            'profil_' .
                            $id_user .
                            '_' .
                            bin2hex(random_bytes(8)) .
                            '.' .
                            $ekstensi;

                        $tujuan = $upload_dir . $nama_file_baru;

                        if (move_uploaded_file($file['tmp_name'], $tujuan)) {

                            /*
                             * Simpan nama file baru ke database.
                             */
                            $stmt = mysqli_prepare(
                                $conn,
                                "UPDATE users
                                 SET foto_profil = ?
                                 WHERE id_user = ?"
                            );

                            if ($stmt) {

                                mysqli_stmt_bind_param(
                                    $stmt,
                                    "si",
                                    $nama_file_baru,
                                    $id_user
                                );

                                if (mysqli_stmt_execute($stmt)) {

                                    /*
                                     * Hapus foto lama setelah
                                     * database berhasil diupdate.
                                     */
                                    if (
                                        $foto_profil !== '' &&
                                        $foto_profil !== $nama_file_baru
                                    ) {

                                        $foto_lama =
                                            $upload_dir .
                                            basename($foto_profil);

                                        if (is_file($foto_lama)) {
                                            @unlink($foto_lama);
                                        }
                                    }

                                    $foto_profil = $nama_file_baru;
                                    $foto_ada = true;

                                    $pesan = 'Foto profil berhasil diperbarui.';
                                    $tipe_pesan = 'success';

                                } else {

                                    /*
                                     * Jika database gagal,
                                     * hapus file yang baru diupload.
                                     */
                                    @unlink($tujuan);

                                    $pesan = 'Foto gagal disimpan ke database.';
                                    $tipe_pesan = 'error';
                                }

                                mysqli_stmt_close($stmt);

                            } else {

                                @unlink($tujuan);

                                $pesan = 'Terjadi kesalahan pada database.';
                                $tipe_pesan = 'error';
                            }

                        } else {

                            $pesan = 'Foto gagal diupload.';
                            $tipe_pesan = 'error';
                        }
                    }
                }
            }
        }
    }

    /* =====================================================
       HAPUS FOTO
    ===================================================== */
    elseif ($aksi === 'hapus_foto') {

        if ($foto_profil !== '') {

            $file_lama =
                $upload_dir .
                basename($foto_profil);

            if (is_file($file_lama)) {
                @unlink($file_lama);
            }
        }

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET foto_profil = NULL
             WHERE id_user = ?"
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id_user
            );

            if (mysqli_stmt_execute($stmt)) {

                $foto_profil = '';
                $foto_ada = false;

                $pesan = 'Foto profil berhasil dihapus.';
                $tipe_pesan = 'success';

            } else {

                $pesan = 'Foto profil gagal dihapus.';
                $tipe_pesan = 'error';
            }

            mysqli_stmt_close($stmt);

        } else {

            $pesan = 'Terjadi kesalahan pada database.';
            $tipe_pesan = 'error';
        }
    }

    /* =====================================================
       UPDATE PROFIL
    ===================================================== */
    elseif ($aksi === 'update_profil') {

        $nama_baru = trim($_POST['nama'] ?? '');
        $email_baru = trim($_POST['email'] ?? '');

        if ($nama_baru === '') {

            $pesan = 'Nama tidak boleh kosong.';
            $tipe_pesan = 'error';

        } elseif ($email_baru === '') {

            $pesan = 'Email tidak boleh kosong.';
            $tipe_pesan = 'error';

        } elseif (!filter_var($email_baru, FILTER_VALIDATE_EMAIL)) {

            $pesan = 'Format email tidak valid.';
            $tipe_pesan = 'error';

        } else {

            /* Cek email */
            $stmt_cek = mysqli_prepare(
                $conn,
                "SELECT id_user
                 FROM users
                 WHERE email = ?
                 AND id_user != ?
                 LIMIT 1"
            );

            $email_terpakai = false;

            if ($stmt_cek) {

                mysqli_stmt_bind_param(
                    $stmt_cek,
                    "si",
                    $email_baru,
                    $id_user
                );

                mysqli_stmt_execute($stmt_cek);

                $hasil_cek = mysqli_stmt_get_result($stmt_cek);

                if (
                    $hasil_cek &&
                    mysqli_num_rows($hasil_cek) > 0
                ) {
                    $email_terpakai = true;
                }

                mysqli_stmt_close($stmt_cek);
            }

            if ($email_terpakai) {

                $pesan = 'Email tersebut sudah digunakan oleh akun lain.';
                $tipe_pesan = 'error';

            } else {

                $stmt_update = mysqli_prepare(
                    $conn,
                    "UPDATE users
                     SET nama = ?, email = ?
                     WHERE id_user = ?"
                );

                if ($stmt_update) {

                    mysqli_stmt_bind_param(
                        $stmt_update,
                        "ssi",
                        $nama_baru,
                        $email_baru,
                        $id_user
                    );

                    if (mysqli_stmt_execute($stmt_update)) {

                        $nama = $nama_baru;
                        $email = $email_baru;

                        $_SESSION['nama'] = $nama_baru;
                        $_SESSION['email'] = $email_baru;

                        $inisial = strtoupper(
                            substr(trim($nama_baru), 0, 1)
                        );

                        $pesan = 'Profil berhasil diperbarui.';
                        $tipe_pesan = 'success';

                    } else {

                        $pesan = 'Profil gagal diperbarui.';
                        $tipe_pesan = 'error';
                    }

                    mysqli_stmt_close($stmt_update);

                } else {

                    $pesan = 'Terjadi kesalahan pada database.';
                    $tipe_pesan = 'error';
                }
            }
        }
    }

    /* =====================================================
       UPDATE PASSWORD
    ===================================================== */
    elseif ($aksi === 'update_password') {

        $password_lama = $_POST['password_lama'] ?? '';
        $password_baru = $_POST['password_baru'] ?? '';
        $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

        if ($password_lama === '') {

            $pesan = 'Password lama wajib diisi.';
            $tipe_pesan = 'error';

        } elseif ($password_baru === '') {

            $pesan = 'Password baru wajib diisi.';
            $tipe_pesan = 'error';

        } elseif (strlen($password_baru) < 6) {

            $pesan = 'Password baru minimal 6 karakter.';
            $tipe_pesan = 'error';

        } elseif ($password_baru !== $konfirmasi_password) {

            $pesan = 'Konfirmasi password tidak cocok.';
            $tipe_pesan = 'error';

        } else {

            $password_db = '';

            if (isset($data_user['password'])) {
                $password_db = $data_user['password'];
            } elseif (isset($data_user['password_hash'])) {
                $password_db = $data_user['password_hash'];
            }

            if ($password_db === '') {

                $pesan = 'Data password tidak ditemukan.';
                $tipe_pesan = 'error';

            } else {

                $password_valid = false;

                if (password_verify($password_lama, $password_db)) {
                    $password_valid = true;
                } elseif ($password_lama === $password_db) {
                    $password_valid = true;
                }

                if (!$password_valid) {

                    $pesan = 'Password lama tidak benar.';
                    $tipe_pesan = 'error';

                } else {

                    $password_hash = password_hash(
                        $password_baru,
                        PASSWORD_DEFAULT
                    );

                    if (isset($data_user['password'])) {

                        $stmt_password = mysqli_prepare(
                            $conn,
                            "UPDATE users
                             SET password = ?
                             WHERE id_user = ?"
                        );

                    } elseif (isset($data_user['password_hash'])) {

                        $stmt_password = mysqli_prepare(
                            $conn,
                            "UPDATE users
                             SET password_hash = ?
                             WHERE id_user = ?"
                        );

                    } else {

                        $stmt_password = false;
                    }

                    if ($stmt_password) {

                        mysqli_stmt_bind_param(
                            $stmt_password,
                            "si",
                            $password_hash,
                            $id_user
                        );

                        if (mysqli_stmt_execute($stmt_password)) {

                            $pesan = 'Password berhasil diubah.';
                            $tipe_pesan = 'success';

                        } else {

                            $pesan = 'Password gagal diubah.';
                            $tipe_pesan = 'error';
                        }

                        mysqli_stmt_close($stmt_password);

                    } else {

                        $pesan = 'Terjadi kesalahan pada database.';
                        $tipe_pesan = 'error';
                    }
                }
            }
        }
    }
}

/* =========================================================
   URL FOTO TERBARU
========================================================= */
$foto_url = '';

if ($foto_ada && $foto_profil !== '') {
    $foto_url =
        $upload_url .
        rawurlencode(basename($foto_profil));
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

    <title>Profil - Toko Malaya</title>

    <style>

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
            color: #1f2937;
        }

        /* =====================================================
           NAVBAR - SAMA DENGAN DASHBOARD
        ===================================================== */

        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
        }

        .navbar-inner {
            max-width: 1200px;
            min-height: 72px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
        }

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

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 28px;
            margin-left: auto;
        }

        .nav-menu a {
            position: relative;
            padding: 26px 0;
            color: #4b5563;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            color: #1677d2;
        }

        .nav-menu a.active::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 17px;
            height: 2px;
            background: #1677d2;
            border-radius: 2px;
        }

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
            transition: 0.2s ease;
        }

        .cart-button:hover {
            background: #e3effb;
            transform: translateY(-1px);
        }

        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 19px;
            height: 19px;
            padding: 0 5px;
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
            padding: 7px 12px 7px 8px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            color: #1677d2;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .profile-button:hover {
            border-color: #1677d2;
            color: #1677d2;
        }

        .nav-profile-photo,
        .nav-profile-initial {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .nav-profile-photo {
            object-fit: cover;
            display: block;
        }

        .nav-profile-initial {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8f2fc;
            color: #1677d2;
            font-size: 12px;
            font-weight: bold;
        }

        .logout-button {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid #dbe3ec;
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

        /* =====================================================
           MAIN
        ===================================================== */

        .main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 24px 60px;
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-header h1 {
            margin: 0 0 7px;
            color: #153b63;
            font-size: 28px;
        }

        .page-header p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        /* =====================================================
           ALERT
        ===================================================== */

        .alert {
            padding: 13px 16px;
            margin-bottom: 22px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
        }

        .alert.success {
            color: #166534;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
        }

        .alert.error {
            color: #b91c1c;
            background: #fee2e2;
            border: 1px solid #fecaca;
        }

        /* =====================================================
           PROFILE LAYOUT
        ===================================================== */

        .profile-layout {
            display: grid;
            grid-template-columns: 330px minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .profile-card,
        .form-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
        }

        /* =====================================================
           PROFILE CARD
        ===================================================== */

        .profile-card {
            padding: 28px;
            text-align: center;
        }

        .avatar-area {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .avatar {
            width: 110px;
            height: 110px;
            margin: 0 auto 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8f2fc;
            color: #1677d2;
            border-radius: 50%;
            font-size: 42px;
            font-weight: bold;
            overflow: hidden;
            border: 4px solid #ffffff;
            box-shadow:
                0 0 0 1px #dbe3ec,
                0 5px 15px rgba(15, 23, 42, 0.08);
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .photo-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }

        .photo-button {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 35px;
            padding: 8px 13px;
            border-radius: 7px;
            font-family: inherit;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .photo-button.primary {
            background: #1677d2;
            border: 1px solid #1677d2;
            color: #ffffff;
        }

        .photo-button.primary:hover {
            background: #1267b8;
            border-color: #1267b8;
        }

        .photo-button.delete {
            background: #ffffff;
            border: 1px solid #f1b5bc;
            color: #dc3545;
        }

        .photo-button.delete:hover {
            background: #fff5f6;
        }

        .photo-button input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .photo-hint {
            margin: 0 0 18px;
            color: #9ca3af;
            font-size: 10px;
            line-height: 1.5;
        }

        .profile-card h2 {
            margin: 0 0 7px;
            color: #153b63;
            font-size: 21px;
        }

        .profile-email {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
            word-break: break-word;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 18px;
            padding: 7px 13px;
            background: #e8f2fc;
            color: #1677d2;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        /* =====================================================
           INFO
        ===================================================== */

        .profile-info {
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid #edf0f3;
            text-align: left;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 17px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-icon {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f6fc;
            border-radius: 8px;
            font-size: 16px;
        }

        .info-content {
            min-width: 0;
        }

        .info-label {
            margin-bottom: 3px;
            color: #9ca3af;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .info-value {
            color: #374151;
            font-size: 13px;
            font-weight: 600;
            word-break: break-word;
        }

        /* =====================================================
           FORMS
        ===================================================== */

        .forms {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .form-card {
            padding: 28px;
        }

        .form-header {
            margin-bottom: 24px;
            padding-bottom: 17px;
            border-bottom: 1px solid #edf0f3;
        }

        .form-header h2 {
            margin: 0 0 5px;
            color: #153b63;
            font-size: 19px;
        }

        .form-header p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group:last-of-type {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: #374151;
            font-size: 13px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            min-height: 45px;
            padding: 11px 13px;
            border: 1px solid #dbe3ec;
            border-radius: 8px;
            outline: none;
            background: #ffffff;
            color: #1f2937;
            font-family: inherit;
            font-size: 14px;
            transition: 0.2s ease;
        }

        .form-control:focus {
            border-color: #1677d2;
            box-shadow: 0 0 0 3px rgba(22, 119, 210, 0.09);
        }

        .input-wrap {
            position: relative;
        }

        .password-input {
            padding-right: 45px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 11px;
            transform: translateY(-50%);
            width: 30px;
            height: 30px;
            border: 0;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            font-size: 16px;
        }

        .toggle-password:hover {
            color: #1677d2;
        }

        .form-hint {
            margin-top: 6px;
            color: #9ca3af;
            font-size: 11px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 43px;
            padding: 10px 18px;
            border: 0;
            border-radius: 8px;
            background: #1677d2;
            color: #ffffff;
            font-family: inherit;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .button:hover {
            background: #1267b8;
            transform: translateY(-1px);
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px 35px;
            color: #9ca3af;
            text-align: center;
            font-size: 12px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

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

            .profile-layout {
                grid-template-columns: 280px minmax(0, 1fr);
            }
        }

        @media (max-width: 800px) {

            .profile-layout {
                grid-template-columns: 1fr;
            }

            .profile-info {
                max-width: 420px;
                margin-left: auto;
                margin-right: auto;
            }
        }

        @media (max-width: 700px) {

            .navbar-inner {
                min-height: 64px;
                padding: 0 15px;
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
                padding: 9px 10px;
                font-size: 0;
            }

            .logout-button::before {
                content: "↪";
                font-size: 18px;
            }

            .main {
                padding: 30px 15px 45px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .profile-card,
            .form-card {
                padding: 22px;
            }
        }

        @media (max-width: 430px) {

            .cart-button {
                width: 38px;
                height: 38px;
            }

            .main {
                padding-top: 25px;
            }

            .profile-card,
            .form-card {
                padding: 18px;
            }

            .form-header h2 {
                font-size: 17px;
            }

            .photo-actions {
                flex-direction: column;
            }

            .photo-button {
                width: 100%;
                max-width: 180px;
            }
        }

    </style>
</head>

<body>

    <!-- =====================================================
         NAVBAR
    ====================================================== -->

    <header class="navbar">

        <div class="navbar-inner">

            <a href="dashboard.php" class="logo">

                <span class="logo-icon">
                    <img
                        src="../assets/SembakoKu (3).png"
                        alt="Toko Malaya"
                    >
                </span>

                <span class="logo-text">
                    Toko Malaya
                </span>

            </a>


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


            <div class="nav-right">

                <a
                    href="keranjang.php"
                    class="cart-button"
                    title="Keranjang"
                >
                    🛒

                    <?php if ($total_keranjang > 0): ?>

                        <span class="cart-badge">
                            <?= $total_keranjang > 99 ? '99+' : $total_keranjang ?>
                        </span>

                    <?php endif; ?>

                </a>


                <a
                    href="profil.php"
                    class="profile-button"
                >

                    <?php if ($foto_ada && $foto_url !== ''): ?>

                        <img
                            src="<?= e($foto_url) ?>"
                            alt="Foto profil"
                            class="nav-profile-photo"
                        >

                    <?php else: ?>

                        <span class="nav-profile-initial">
                            <?= e($inisial) ?>
                        </span>

                    <?php endif; ?>

                    <span>
                        <?= e($nama) ?>
                    </span>

                </a>


                <a
                    href="../auth/logout.php"
                    class="logout-button"
                >
                    Keluar
                </a>

            </div>

        </div>

    </header>


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main">

        <div class="page-header">

            <h1>
                Profil Saya
            </h1>

            <p>
                Kelola foto, informasi, dan keamanan akun Toko Malaya.
            </p>

        </div>


        <?php if ($pesan !== ''): ?>

            <div class="alert <?= $tipe_pesan === 'success' ? 'success' : 'error' ?>">
                <?= e($pesan) ?>
            </div>

        <?php endif; ?>


        <div class="profile-layout">


            <!-- =================================================
                 PROFILE CARD
            ================================================== -->

            <section class="profile-card">

                <div class="avatar-area">

                    <div class="avatar">

                        <?php if ($foto_ada && $foto_url !== ''): ?>

                            <img
                                src="<?= e($foto_url) ?>"
                                alt="Foto profil <?= e($nama) ?>"
                            >

                        <?php else: ?>

                            <?= e($inisial) ?>

                        <?php endif; ?>

                    </div>


                    <!-- =========================================
                         FORM UPLOAD FOTO
                    ========================================== -->

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        id="formFoto"
                    >

                        <input
                            type="hidden"
                            name="aksi"
                            value="upload_foto"
                        >

                        <div class="photo-actions">

                            <label class="photo-button primary">

                                📷
                                <?= $foto_ada ? 'Ganti Foto' : 'Tambah Foto' ?>

                                <input
                                    type="file"
                                    name="foto_profil"
                                    id="foto_profil"
                                    accept="image/jpeg,image/png,image/webp"
                                    onchange="previewFoto(this)"
                                    required
                                >

                            </label>

                        </div>

                    </form>


                    <p class="photo-hint">
                        JPG, PNG, atau WEBP<br>
                        Maksimal 2 MB
                    </p>


                    <?php if ($foto_ada): ?>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="aksi"
                                value="hapus_foto"
                            >

                            <button
                                type="submit"
                                class="photo-button delete"
                                onclick="return confirm('Yakin ingin menghapus foto profil?')"
                            >
                                🗑 Hapus Foto
                            </button>

                        </form>

                    <?php endif; ?>

                </div>


                <h2>
                    <?= e($nama) ?>
                </h2>

                <p class="profile-email">
                    <?= e($email) ?>
                </p>

                <span class="role-badge">
                    Pelanggan
                </span>


                <!-- =============================================
                     INFO
                ============================================== -->

                <div class="profile-info">

                    <div class="info-row">

                        <div class="info-icon">
                            👤
                        </div>

                        <div class="info-content">

                            <div class="info-label">
                                Nama
                            </div>

                            <div class="info-value">
                                <?= e($nama) ?>
                            </div>

                        </div>

                    </div>


                    <div class="info-row">

                        <div class="info-icon">
                            ✉️
                        </div>

                        <div class="info-content">

                            <div class="info-label">
                                Email
                            </div>

                            <div class="info-value">
                                <?= e($email) ?>
                            </div>

                        </div>

                    </div>


                    <div class="info-row">

                        <div class="info-icon">
                            🛒
                        </div>

                        <div class="info-content">

                            <div class="info-label">
                                Keranjang
                            </div>

                            <div class="info-value">
                                <?= $total_keranjang ?> item
                            </div>

                        </div>

                    </div>

                </div>

            </section>


            <!-- =================================================
                 FORMS
            ================================================== -->

            <div class="forms">


                <!-- =============================================
                     INFORMASI PROFIL
                ============================================== -->

                <section class="form-card">

                    <div class="form-header">

                        <h2>
                            Informasi Profil
                        </h2>

                        <p>
                            Perbarui nama dan email yang digunakan pada akun kamu.
                        </p>

                    </div>


                    <form method="POST">

                        <input
                            type="hidden"
                            name="aksi"
                            value="update_profil"
                        >


                        <div class="form-group">

                            <label for="nama">
                                Nama Lengkap
                            </label>

                            <input
                                type="text"
                                id="nama"
                                name="nama"
                                class="form-control"
                                value="<?= e($nama) ?>"
                                placeholder="Masukkan nama lengkap"
                                autocomplete="name"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="email">
                                Email
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                value="<?= e($email) ?>"
                                placeholder="Masukkan email"
                                autocomplete="email"
                                required
                            >

                        </div>


                        <button
                            type="submit"
                            class="button"
                        >
                            Simpan Perubahan
                        </button>

                    </form>

                </section>


                <!-- =============================================
                     PASSWORD
                ============================================== -->

                <section class="form-card">

                    <div class="form-header">

                        <h2>
                            Keamanan Akun
                        </h2>

                        <p>
                            Gunakan password yang kuat untuk menjaga keamanan akun.
                        </p>

                    </div>


                    <form method="POST">

                        <input
                            type="hidden"
                            name="aksi"
                            value="update_password"
                        >


                        <div class="form-group">

                            <label for="password_lama">
                                Password Lama
                            </label>

                            <div class="input-wrap">

                                <input
                                    type="password"
                                    id="password_lama"
                                    name="password_lama"
                                    class="form-control password-input"
                                    placeholder="Masukkan password lama"
                                    autocomplete="current-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    onclick="togglePassword('password_lama', this)"
                                >
                                    👁
                                </button>

                            </div>

                        </div>


                        <div class="form-group">

                            <label for="password_baru">
                                Password Baru
                            </label>

                            <div class="input-wrap">

                                <input
                                    type="password"
                                    id="password_baru"
                                    name="password_baru"
                                    class="form-control password-input"
                                    placeholder="Masukkan password baru"
                                    autocomplete="new-password"
                                    minlength="6"
                                    required
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    onclick="togglePassword('password_baru', this)"
                                >
                                    👁
                                </button>

                            </div>

                            <div class="form-hint">
                                Password minimal 6 karakter.
                            </div>

                        </div>


                        <div class="form-group">

                            <label for="konfirmasi_password">
                                Konfirmasi Password Baru
                            </label>

                            <div class="input-wrap">

                                <input
                                    type="password"
                                    id="konfirmasi_password"
                                    name="konfirmasi_password"
                                    class="form-control password-input"
                                    placeholder="Ulangi password baru"
                                    autocomplete="new-password"
                                    minlength="6"
                                    required
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    onclick="togglePassword('konfirmasi_password', this)"
                                >
                                    👁
                                </button>

                            </div>

                        </div>


                        <button
                            type="submit"
                            class="button"
                        >
                            Ubah Password
                        </button>

                    </form>

                </section>

            </div>

        </div>

    </main>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="footer">
        &copy; <?= date('Y') ?> Toko Malaya. Semua hak dilindungi.
    </footer>


    <script>

        /* =====================================================
           PREVIEW FOTO
        ===================================================== */

        function previewFoto(input) {

            if (!input.files || !input.files[0]) {
                return;
            }

            const file = input.files[0];

            /* Maksimal 2 MB */
            if (file.size > 2 * 1024 * 1024) {

                alert("Ukuran foto maksimal 2 MB.");

                input.value = "";

                return;
            }

            /* Validasi tipe */
            const allowedTypes = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            if (!allowedTypes.includes(file.type)) {

                alert("Foto harus berformat JPG, PNG, atau WEBP.");

                input.value = "";

                return;
            }

            /* Preview */
            const reader = new FileReader();

            reader.onload = function(event) {

                const avatar = document.querySelector(".avatar");

                if (!avatar) {
                    return;
                }

                avatar.innerHTML = "";

                const img = document.createElement("img");

                img.src = event.target.result;

                img.alt = "Preview foto profil";

                avatar.appendChild(img);

            };

            reader.readAsDataURL(file);

            /*
             * Setelah memilih file,
             * langsung submit form upload.
             */
            setTimeout(function() {

                const konfirmasi = confirm(
                    "Gunakan foto ini sebagai foto profil?"
                );

                if (konfirmasi) {

                    document
                        .getElementById("formFoto")
                        .submit();

                } else {

                    input.value = "";

                    location.reload();
                }

            }, 100);

        }


        /* =====================================================
           TOGGLE PASSWORD
        ===================================================== */

        function togglePassword(id, button) {

            const input = document.getElementById(id);

            if (!input) {
                return;
            }

            if (input.type === "password") {

                input.type = "text";

                button.textContent = "🙈";

            } else {

                input.type = "password";

                button.textContent = "👁";

            }
        }

    </script>

</body>

</html>