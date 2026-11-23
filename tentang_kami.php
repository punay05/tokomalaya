
<?php
session_start();

$is_login = isset($_SESSION['id_user']);
$nama = $_SESSION['nama'] ?? '';
$role = $_SESSION['role'] ?? '';

function dashboard_url_tentang($role)
{
    switch ($role) {
        case 'admin': return 'admin/dashboard.php';
        case 'kasir': return 'kasir/dashboard.php';
        case 'pemilik': return 'pemilik/dashboard.php';
        case 'pelanggan': return 'pelanggan/dashboard.php';
        default: return 'auth/login.php';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami | Toko Malaya</title>
    <style>
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f6f8fb;
            color: #1f2937;
            line-height: 1.7;
        }

        a { text-decoration: none; }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 6%;
        }

        .navbar-inner {
            max-width: 1200px;
            min-height: 72px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #153b63;
            flex-shrink: 0;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .brand-name {
            font-size: 20px;
            font-weight: 700;
        }

        .nav-menu, .nav-right {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        /* Semua menu secara default tidak tebal */
        .nav-menu a {
            color: #4b5563;
            font-size: 14px;
            font-weight: 400;
            transition: .2s;
        }

        /* Warna menu ketika hover atau aktif */
        .nav-menu a:hover,
        .nav-menu a.active {
            color: #1677d2;
        }

        /* Hanya menu yang aktif yang tebal */
        .nav-menu a.active {
            font-weight: 600;
        }

        .nav-right { gap: 9px; }

        .user-name {
            max-width: 130px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 17px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-login {
            color: #1677d2;
            border: 1px solid #1677d2;
        }

        .btn-login:hover { background: #eff6ff; }

        .btn-primary {
            background: #1677d2;
            color: #fff;
            border: 1px solid #1677d2;
        }

        .btn-primary:hover { background: #105fae; }

        .btn-logout {
            background: #f3f4f6;
            color: #374151;
        }

        .hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(
                110deg,
                rgba(13, 48, 83, .94),
                rgba(22, 119, 210, .82)
            ), url('assets/toko1.jpg') center/cover no-repeat;
            color: #fff;
            padding: 88px 6%;
        }

        .hero-inner {
            max-width: 1200px;
            margin: auto;
        }

        .eyebrow {
            display: inline-block;
            padding: 7px 13px;
            border: 1px solid rgba(255,255,255,.35);
            border-radius: 30px;
            background: rgba(255,255,255,.10);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .3px;
        }

        .hero h1 {
            max-width: 720px;
            margin: 20px 0 15px;
            font-size: clamp(34px, 5vw, 54px);
            line-height: 1.15;
        }

        .hero p {
            max-width: 680px;
            margin: 0;
            color: #e7f1fb;
            font-size: 17px;
        }

        .content {
            max-width: 1100px;
            margin: auto;
            padding: 70px 24px;
        }

        .intro {
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            gap: 48px;
            align-items: center;
        }

        .intro-image {
            width: 100%;
            height: 350px;
            object-fit: cover;
            border-radius: 18px;
            box-shadow: 0 15px 35px rgba(15, 44, 75, .12);
        }

        .section-label {
            color: #1677d2;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        h2 {
            margin: 8px 0 16px;
            color: #153b63;
            font-size: clamp(27px, 3vw, 36px);
            line-height: 1.25;
        }

        .intro p, .section-description {
            color: #64748b;
            margin: 0 0 14px;
        }

        .values-section {
            background: #fff;
            padding: 68px 6%;
        }

        .section-inner {
            max-width: 1100px;
            margin: auto;
        }

        .section-heading {
            max-width: 650px;
            margin: 0 auto 35px;
            text-align: center;
        }

        .section-heading p {
            color: #64748b;
            margin: 0;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .info-card {
            padding: 27px 24px;
            background: #f8fbff;
            border: 1px solid #e5edf6;
            border-radius: 15px;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: #e7f2ff;
            color: #1677d2;
            font-size: 23px;
        }

        .info-card h3 {
            margin: 17px 0 8px;
            color: #153b63;
            font-size: 19px;
        }

        .info-card p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .mission-wrap {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .mission-card {
            padding: 30px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid #e5eaf0;
        }

        .mission-card ul {
            padding-left: 20px;
            margin: 12px 0 0;
            color: #64748b;
        }

        .mission-card li { padding: 4px 0; }

        .closing {
            max-width: 1100px;
            margin: 0 auto 70px;
            padding: 0 24px;
        }

        .closing-box {
            padding: 38px;
            border-radius: 18px;
            background: linear-gradient(110deg, #153b63, #1677d2);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
        }

        .closing-box h2 {
            color: #fff;
            margin: 0 0 7px;
            font-size: 27px;
        }

        .closing-box p {
            margin: 0;
            color: #e5f1ff;
        }

        .closing-box .btn {
            background: #fff;
            color: #145b9e;
            white-space: nowrap;
        }

       /* FOOTER */
        footer {
            background: #102d4f;
            color: white;
            padding: 45px 7% 20px;
            margin-top: 70px;
        }

        .footer-content {
            max-width: 1200px;
            margin: auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 50px;
            margin-bottom: 30px;
        }

        .footer-content h3 {
            font-size: 16px;
            margin: 0 0 15px;
            color: white;
        }

        .footer-content p {
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.8;
            margin: 0 0 10px;
        }

        .footer-links,
        .footer-contact {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-links a,
        .footer-contact a {
            color: #cbd5e1;
            font-size: 14px;
            text-decoration: none;
        }

        .footer-links a:hover,
        .footer-contact a:hover {
            color: white;
        }

        .footer-bottom {
            max-width: 1200px;
            margin: auto;
            border-top: 1px solid #36506c;
            padding-top: 20px;
            text-align: center;
            color: #cbd5e1;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 25px;
            }
        }

        @media (max-width: 800px) {
            .navbar-inner {
                flex-wrap: wrap;
                padding: 12px 0;
                gap: 12px;
            }

            .nav-menu {
                order: 3;
                width: 100%;
                justify-content: center;
                gap: 22px;
                padding: 8px 0 3px;
            }

            .nav-right { margin-left: auto; }

            .intro {
                grid-template-columns: 1fr;
                gap: 28px;
            }

            .intro-image { height: 290px; }

            .cards { grid-template-columns: 1fr 1fr; }

            .closing-box {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 520px) {
            .navbar { padding: 0 4%; }

            .brand-name { font-size: 18px; }

            .nav-menu { gap: 15px; }

            .nav-menu a { font-size: 12px; }

            .user-name { display: none; }

            .btn {
                padding: 9px 12px;
                font-size: 12px;
            }

            .hero { padding: 65px 6%; }

            .hero p { font-size: 15px; }

            .content { padding: 52px 20px; }

            .cards, .mission-wrap {
                grid-template-columns: 1fr;
            }

            .values-section { padding: 52px 20px; }

            .closing-box { padding: 27px 23px; }

            .footer-inner {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-inner">

        <a href="index.php" class="brand">
            <img src="assets/SembakoKu (3).png"
                 alt="Logo Toko Malaya"
                 class="brand-logo">
            <span class="brand-name">Toko Malaya</span>
        </a>

        <div class="nav-menu">
            <a href="index.php">Beranda</a>
            <a href="index.php#keunggulan">Keunggulan</a>
            <a href="index.php#produk">Produk</a>
            <a href="tentang_kami.php" class="active">Tentang Kami</a>
            <a href="testimoni.php">Ulasan</a>
        </div>

        <div class="nav-right">
            <?php if (!$is_login): ?>

                <a href="auth/login.php" class="btn btn-login">Login</a>
                <a href="auth/register.php" class="btn btn-primary">Daftar</a>

            <?php else: ?>

                <span class="user-name">
                    Halo, <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>
                </span>

                <a href="<?= htmlspecialchars(dashboard_url_tentang($role), ENT_QUOTES, 'UTF-8'); ?>"
                   class="btn btn-primary">Dashboard</a>

                <a href="auth/logout.php" class="btn btn-logout">Logout</a>

            <?php endif; ?>
        </div>

    </div>
</nav>

<header class="hero">
    <div class="hero-inner">
        <span class="eyebrow">Lebih dekat dengan Toko Malaya</span>
        <h1>Mengenal Toko Malaya</h1>
        <p>
            Toko Malaya hadir untuk membantu memenuhi kebutuhan sehari-hari
            melalui pengalaman belanja yang praktis, mudah, dan nyaman.
        </p>
    </div>
</header>

<main>

    <section class="content">
        <div class="intro">

            <div>
                <span class="section-label">Cerita Kami</span>

                <h2>Belanja kebutuhan harian jadi lebih mudah</h2>

                <p>
                    Toko Malaya merupakan toko yang menyediakan berbagai
                    kebutuhan sehari-hari, mulai dari sembako, makanan,
                    minuman, hingga perlengkapan rumah tangga.
                </p>

                <p>
                    Melalui website ini, pelanggan dapat mengenal produk
                    yang tersedia dan melakukan aktivitas belanja secara
                    lebih praktis. Kami ingin menghadirkan pengalaman
                    berbelanja yang sederhana sehingga pelanggan dapat
                    menemukan kebutuhan mereka dengan lebih mudah.
                </p>

                <p>
                    Kami terus berupaya mengembangkan layanan dan memberikan
                    pengalaman belanja yang nyaman bagi setiap pelanggan.
                </p>
            </div>

            <img src="assets/toko1.jpg"
                 alt="Suasana toko"
                 class="intro-image">

        </div>
    </section>

    <section class="values-section">
        <div class="section-inner">

            <div class="section-heading">
                <span class="section-label">Yang Kami Utamakan</span>

                <h2>Belanja nyaman bersama Toko Malaya</h2>

                <p>
                    Kami ingin memberikan pengalaman berbelanja yang
                    berfokus pada kebutuhan pelanggan.
                </p>
            </div>

            <div class="cards">

                <article class="info-card">
                    <div class="card-icon">🛒</div>
                    <h3>Kebutuhan Beragam</h3>
                    <p>
                        Menyediakan berbagai pilihan produk untuk membantu
                        memenuhi kebutuhan sehari-hari.
                    </p>
                </article>

                <article class="info-card">
                    <div class="card-icon">💻</div>
                    <h3>Belanja Praktis</h3>
                    <p>
                        Website membantu pelanggan melihat produk dan
                        berbelanja tanpa harus mencari satu per satu
                        secara langsung.
                    </p>
                </article>

                <article class="info-card">
                    <div class="card-icon">🤝</div>
                    <h3>Berorientasi Pelanggan</h3>
                    <p>
                        Berupaya memberikan pelayanan yang ramah, jelas,
                        dan memudahkan pelanggan.
                    </p>
                </article>

            </div>
        </div>
    </section>

    <section class="closing">
        <div class="closing-box">

            <div>
                <h2>Temukan kebutuhanmu di Toko Malaya</h2>
                <p>
                    Jelajahi pilihan produk dan mulai pengalaman belanja
                    yang lebih praktis.
                </p>
            </div>

            <a href="index.php#produk" class="btn">
                Lihat Produk &rarr;
            </a>

        </div>
    </section>

</main>

<footer>

    <div class="footer-content">

        <!-- KOLOM 1 -->
        <div class="footer-about">

            <h3>Toko Malaya</h3>

            <p>
                Toko Malaya menyediakan berbagai kebutuhan
                sehari-hari untuk membantu memenuhi kebutuhan
                pelanggan dengan pengalaman belanja yang
                praktis, mudah, dan nyaman.
            </p>

        </div>

        <!-- KOLOM 2 -->
        <div>

            <h3>Tautan</h3>

            <div class="footer-links">

                <a href="index.php">Beranda</a>

                <a href="index.php#produk">Produk</a>

                <a href="tentang_kami.php">Tentang Kami</a>

                <a href="testimoni.php">Ulasan</a>
                
                <a href="syarat_ketentuan.php">Syarat dan Ketentuan</a>
                
                <a href="kebijakan_privasi.php">Kebijakan Privasi</a>
                
                <a href="faq.php">FAQ</a>

            </div>

        </div>

        <!-- KOLOM 3 -->
        <div>

            <h3>Kontak</h3>

            <div class="footer-contact">

                <p>📍 Kampung Tengah, DKI Jakarta</p>

                <p>☎ 088590043576</p>

                <p>✉ sembako@tokomalaya</p>

                <p>🕒 07.00 - 19.00 WIB</p>

            </div>

        </div>

    </div>

    <!-- COPYRIGHT -->
    <div class="footer-bottom">

        © <?= date('Y'); ?> Toko Malaya.
        Semua hak dilindungi.

    </div>

</footer>

</body>
</html>
