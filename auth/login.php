<?php

session_start();

include "../config/database.php";

$error = "";

if (isset($_POST['login'])) {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $error = "Email dan password wajib diisi!";

    } else {

        $query = "SELECT * FROM users WHERE email = ? LIMIT 1";

        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {

            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) === 1) {

                $user = mysqli_fetch_assoc($result);

                if (password_verify($password, $user['password'])) {

                    $_SESSION['id_user'] = $user['id_user'];
                    $_SESSION['nama'] = $user['nama'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    if ($user['role'] === 'admin') {
                        header("Location: ../admin/dashboard.php");
                        exit;
                    }

                    if ($user['role'] === 'kasir') {
                        header("Location: ../kasir/dashboard.php");
                        exit;
                    }

                    if ($user['role'] === 'pemilik') {
                        header("Location: ../pemilik/dashboard.php");
                        exit;
                    }

                    
                    if ($user['role'] === 'karyawan') {
                        header("Location: ../karyawan/dashboard.php");
                        exit;
                    }

                    if ($user['role'] === 'pelanggan') {
                        header("Location: ../pelanggan/dashboard.php");
                        exit;
                    }

                    $error = "Role pengguna tidak dikenali.";


                } else {

                    $error = "Email atau password salah!";

                }

            } else {

                $error = "Email atau password salah!";

            }

            mysqli_stmt_close($stmt);

        } else {

            $error = "Terjadi kesalahan pada sistem login.";

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

    <title>Login - SembakoKu</title>

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

        .login-container {
            position: relative;
            z-index: 2;

            width: 410px;
            max-width: 100%;

            padding: 35px 38px;

            background: rgba(255, 255, 255, 0.62);

            border: 1px solid rgba(255, 255, 255, 0.7);

            border-radius: 18px;

            box-shadow:
                0 25px 70px rgba(0, 0, 0, 0.30);

            backdrop-filter: blur(8px);
        }

        .logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo-image {
            width: 105px;
            height: 105px;

            object-fit: contain;

            display: block;

            margin: 0 auto 15px;
        }

        .logo h1 {
            margin-bottom: 7px;

            color: #102a43;

            font-size: 25px;
            font-weight: bold;
        }

        .logo p {
            color: #64748b;

            font-size: 13px;

            line-height: 1.5;
        }

        .error {
            display: flex;
            align-items: center;
            gap: 8px;

            margin-bottom: 18px;
            padding: 11px 13px;

            background: #fef2f2;

            border: 1px solid #fecaca;

            border-radius: 8px;

            color: #b91c1c;

            font-size: 13px;
            line-height: 1.4;
        }

        .form-group {
            margin-bottom: 17px;
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

        .btn-login {
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

        .btn-login:hover {
            background: #1268b8;

            box-shadow:
                0 5px 15px rgba(22, 119, 210, 0.25);
        }

        .btn-login:active {
            transform: translateY(1px);
        }

        .register-link {
            margin-top: 18px;

            text-align: center;

            color: #64748b;

            font-size: 13px;
        }

        .register-link a {
            color: #1677d2;

            font-weight: bold;

            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .login-footer {
            margin-top: 22px;

            text-align: center;

            color: #94a3b8;

            font-size: 11px;
        }

        @media (max-width: 480px) {

            body {
                padding: 15px;
            }

            .login-container {
                padding: 28px 22px;

                border-radius: 15px;
            }

            .logo-image {
                width: 90px;
                height: 90px;
            }

            .logo h1 {
                font-size: 22px;
            }

        }

    </style>

</head>

<body>

    <div class="login-container">

        <div class="logo">

            <img
                src="../assets/SembakoKu (3).png"
                alt="Logo SembakoKu"
                class="logo-image"
            >

            <h1>
                Selamat Datang
            </h1>

            <p>
                Silakan login untuk melanjutkan
            </p>

        </div>

        <?php if ($error != ""): ?>

            <div class="error">

                <span>⚠️</span>

                <span>
                    <?= htmlspecialchars($error); ?>
                </span>

            </div>

        <?php endif; ?>

        <form method="POST">

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
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword()"
                        aria-label="Tampilkan password"
                    >
                        👁️
                    </button>

                </div>

            </div>

            <button
                type="submit"
                name="login"
                class="btn-login"
            >
                Login →
            </button>

        </form>

        <div class="register-link">
            Belum punya akun?
            <a href="register.php">
                Daftar sebagai pelanggan
            </a>
        </div>

        <div class="login-footer">
            SembakoKu &copy; <?= date('Y'); ?>
        </div>

    </div>

    <script>

        function togglePassword() {

            const password =
                document.getElementById("password");

            const button =
                document.querySelector(".toggle-password");

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