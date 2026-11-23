<?php

session_start();

include "../config/database.php";

$error = "";
$success = "";

if (isset($_POST['register'])) {

    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    /* ========================================
       VALIDASI
       ======================================== */

    if ($nama === '' || $email === '' || $password === '' || $konfirmasi_password === '') {

        $error = "Semua field wajib diisi!";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Format email tidak valid!";

    } elseif (strlen($nama) < 3) {

        $error = "Nama minimal 3 karakter!";

    } elseif (strlen($password) < 6) {

        $error = "Password minimal 6 karakter!";

    } elseif ($password !== $konfirmasi_password) {

        $error = "Konfirmasi password tidak cocok!";

    } else {

        /* ========================================
           CEK EMAIL
           ======================================== */

        $query = "SELECT id_user FROM users WHERE email = ? LIMIT 1";

        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {

            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) > 0) {

                $error = "Email sudah terdaftar. Silakan gunakan email lain.";

            } else {

                /* ========================================
                   BUAT USERNAME INTERNAL
                   ======================================== */

                $username = "pelanggan_" . time() . "_" . rand(100, 999);

                /* ========================================
                   HASH PASSWORD
                   ======================================== */

                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /* ========================================
                   INSERT PELANGGAN
                   ======================================== */

                $insert = "
                    INSERT INTO users
                    (nama, email, username, password, role)
                    VALUES (?, ?, ?, ?, 'pelanggan')
                ";

                $stmt_insert = mysqli_prepare($conn, $insert);

                if ($stmt_insert) {

                    mysqli_stmt_bind_param(
                        $stmt_insert,
                        "ssss",
                        $nama,
                        $email,
                        $username,
                        $password_hash
                    );

                    if (mysqli_stmt_execute($stmt_insert)) {

                        $success = "Pendaftaran berhasil! Silakan login menggunakan email dan password Anda.";

                        $_POST = [];

                    } else {

                        $error = "Pendaftaran gagal. Silakan coba lagi.";

                    }

                    mysqli_stmt_close($stmt_insert);

                } else {

                    $error = "Terjadi kesalahan pada sistem.";

                }
            }

            mysqli_stmt_close($stmt);

        } else {

            $error = "Terjadi kesalahan pada sistem.";

        }
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

    <title>Daftar Pelanggan - SembakoKu</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            display: flex;
            align-items: center;
            justify-content: center;

            position: relative;

            background:
                linear-gradient(
                    rgba(15, 35, 55, 0.58),
                    rgba(15, 35, 55, 0.58)
                ),
                url("../assets/toko1.jpg")
                center center / cover no-repeat;

            padding: 25px;
        }

        body::before {
            content: "";

            position: fixed;
            inset: 0;

            background:
                linear-gradient(
                    135deg,
                    rgba(16, 42, 67, 0.20),
                    rgba(22, 119, 210, 0.10)
                );

            pointer-events: none;
        }

        /* ========================================
           REGISTER CARD
           ======================================== */

        .register-container {
            position: relative;
            z-index: 2;

            width: 440px;
            max-width: 100%;

            padding: 34px 38px;

            background: rgba(255, 255, 255, 0.62);

            border: 1px solid rgba(255, 255, 255, 0.7);

            border-radius: 18px;

            box-shadow:
                0 25px 70px rgba(0, 0, 0, 0.30);

            backdrop-filter: blur(8px);
        }

        /* ========================================
           LOGO
           ======================================== */

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-image {
            width: 90px;
            height: 90px;

            object-fit: contain;

            display: block;

            margin: 0 auto 13px;
        }

        .logo h1 {
            margin-bottom: 6px;

            color: #102a43;

            font-size: 24px;
            font-weight: bold;
        }

        .logo p {
            color: #64748b;

            font-size: 13px;

            line-height: 1.5;
        }

        /* ========================================
           ALERT
           ======================================== */

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 8px;

            margin-bottom: 18px;
            padding: 11px 13px;

            border-radius: 8px;

            font-size: 13px;

            line-height: 1.4;
        }

        .alert-error {
            background: #fef2f2;

            border: 1px solid #fecaca;

            color: #b91c1c;
        }

        .alert-success {
            background: #f0fdf4;

            border: 1px solid #bbf7d0;

            color: #15803d;
        }

        /* ========================================
           FORM
           ======================================== */

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;

            margin-bottom: 7px;

            color: #334155;

            font-size: 13px;
            font-weight: bold;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;

            left: 13px;
            top: 50%;

            transform: translateY(-50%);

            font-size: 16px;

            color: #64748b;

            pointer-events: none;
        }

        .form-group input {
            width: 100%;

            height: 46px;

            padding:
                0 13px 0 40px;

            border:
                1px solid #d5dde7;

            border-radius: 8px;

            outline: none;

            background: #ffffff;

            color: #1f2937;

            font-size: 14px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        .form-group input:focus {
            border-color: #1677d2;

            box-shadow:
                0 0 0 3px rgba(22, 119, 210, 0.10);
        }

        /* ========================================
           PASSWORD
           ======================================== */

        .password-input {
            padding-right: 43px !important;
        }

        .toggle-password {
            position: absolute;

            top: 50%;
            right: 12px;

            transform: translateY(-50%);

            border: none;

            background: transparent;

            color: #64748b;

            font-size: 16px;

            cursor: pointer;

            padding: 4px;
        }

        .toggle-password:hover {
            color: #1677d2;
        }

        /* ========================================
           REGISTER BUTTON
           ======================================== */

        .btn-register {
            width: 100%;

            height: 46px;

            margin-top: 5px;

            border: none;

            border-radius: 8px;

            background: #1677d2;

            color: #ffffff;

            font-size: 14px;
            font-weight: bold;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.1s ease,
                box-shadow 0.2s ease;
        }

        .btn-register:hover {
            background: #1268b8;

            box-shadow:
                0 5px 15px rgba(22, 119, 210, 0.25);
        }

        .btn-register:active {
            transform: translateY(1px);
        }

        /* ========================================
           LOGIN LINK
           ======================================== */

        .login-link {
            margin-top: 18px;

            text-align: center;

            color: #64748b;

            font-size: 13px;
        }

        .login-link a {
            color: #1677d2;

            font-weight: bold;

            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* ========================================
           FOOTER
           ======================================== */

        .register-footer {
            margin-top: 20px;

            text-align: center;

            color: #94a3b8;

            font-size: 11px;
        }

        /* ========================================
           RESPONSIVE
           ======================================== */

        @media (max-width: 480px) {

            body {
                padding: 15px;
            }

            .register-container {
                padding: 28px 22px;

                border-radius: 15px;
            }

            .logo-image {
                width: 80px;
                height: 80px;
            }

            .logo h1 {
                font-size: 22px;
            }

        }

    </style>

</head>

<body>

    <div class="register-container">

        <!-- LOGO -->

        <div class="logo">

            <img
                src="../assets/SembakoKu (3).png"
                alt="Logo SembakoKu"
                class="logo-image"
            >

            <h1>
                Daftar Pelanggan
            </h1>

            <p>
                Buat akun untuk mulai berbelanja di SembakoKu
            </p>

        </div>


        <!-- ERROR -->

        <?php if ($error !== ""): ?>

            <div class="alert alert-error">

                <span>⚠️</span>

                <span>
                    <?= htmlspecialchars($error); ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- SUCCESS -->

        <?php if ($success !== ""): ?>

            <div class="alert alert-success">

                <span>✅</span>

                <span>
                    <?= htmlspecialchars($success); ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- FORM -->

        <form method="POST">

            <!-- NAMA -->

            <div class="form-group">

                <label for="nama">
                    Nama Lengkap
                </label>

                <div class="input-wrapper">

                    <span class="input-icon">
                        👤
                    </span>

                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        placeholder="Masukkan nama lengkap"
                        autocomplete="name"
                        value="<?= htmlspecialchars($_POST['nama'] ?? ''); ?>"
                        required
                    >

                </div>

            </div>


            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <div class="input-wrapper">

                    <span class="input-icon">
                        ✉️
                    </span>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Masukkan email"
                        autocomplete="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                    >

                </div>

            </div>


            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="input-wrapper">

                    <span class="input-icon">
                        🔒
                    </span>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="password-input"
                        placeholder="Minimal 6 karakter"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword('password', this)"
                        aria-label="Tampilkan password"
                    >
                        👁️
                    </button>

                </div>

            </div>


            <!-- KONFIRMASI PASSWORD -->

            <div class="form-group">

                <label for="konfirmasi_password">
                    Konfirmasi Password
                </label>

                <div class="input-wrapper">

                    <span class="input-icon">
                        🔒
                    </span>

                    <input
                        type="password"
                        id="konfirmasi_password"
                        name="konfirmasi_password"
                        class="password-input"
                        placeholder="Ulangi password"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword('konfirmasi_password', this)"
                        aria-label="Tampilkan password"
                    >
                        👁️
                    </button>

                </div>

            </div>


            <!-- BUTTON -->

            <button
                type="submit"
                name="register"
                class="btn-register"
            >
                Daftar Sekarang →
            </button>

        </form>


        <!-- LOGIN -->

        <div class="login-link">

            Sudah punya akun?

            <a href="login.php">
                Login di sini
            </a>

        </div>


        <!-- FOOTER -->

        <div class="register-footer">
            SembakoKu &copy; <?= date('Y'); ?>
        </div>

    </div>


    <script>

        function togglePassword(id, button) {

            const password =
                document.getElementById(id);

            if (password.type === "password") {

                password.type = "text";

                button.textContent = "🙈";

                button.setAttribute(
                    "aria-label",
                    "Sembunyikan password"
                );

            } else {

                password.type = "password";

                button.textContent = "👁️";

                button.setAttribute(
                    "aria-label",
                    "Tampilkan password"
                );

            }

        }

    </script>

</body>

</html>