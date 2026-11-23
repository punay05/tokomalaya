<?php

$nama_sidebar = $_SESSION['nama'] ?? 'Kasir';

$current_page = basename($_SERVER['PHP_SELF']);

?>

<style>

/* ========================================
   SIDEBAR OVERLAY
   ======================================== */

.sidebar-overlay {
    position: fixed;

    inset: 0;

    background: rgba(15, 23, 42, 0.42);

    z-index: 999;

    opacity: 0;

    visibility: hidden;

    pointer-events: none;

    transition:
        opacity 0.25s ease,
        visibility 0.25s ease;
}

.sidebar-overlay.show {
    opacity: 1;

    visibility: visible;

    pointer-events: auto;
}


/* ========================================
   SIDEBAR
   ======================================== */

.kasir-sidebar {
    position: fixed;

    top: 0;
    left: 0;
    bottom: 0;

    width: 280px;

    background: #ffffff;

    border-right: 1px solid #e5eaf0;

    box-shadow:
        8px 0 30px rgba(15, 23, 42, 0.10);

    z-index: 1000;

    display: flex;
    flex-direction: column;

    overflow-y: auto;

    transform: translateX(-105%);

    transition:
        transform 0.28s ease;
}

.kasir-sidebar.show {
    transform: translateX(0);
}


/* ========================================
   SIDEBAR BRAND
   ======================================== */

.sidebar-brand {
    min-height: 72px;

    display: flex;
    align-items: center;

    gap: 11px;

    padding: 0 20px;

    border-bottom:
        1px solid #edf0f3;
}

.sidebar-logo {
    width: 40px;
    height: 40px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #1677d2;

    border-radius: 10px;

    flex-shrink: 0;

    overflow: hidden;
}

.sidebar-logo img {
    width: 34px;
    height: 34px;

    object-fit: contain;
}

.sidebar-brand-text {
    display: flex;

    flex-direction: column;

    line-height: 1.15;
}

.sidebar-brand-text strong {
    color: #1f2937;

    font-size: 16px;

    font-weight: bold;
}

.sidebar-brand-text span {
    margin-top: 4px;

    color: #7b8794;

    font-size: 11px;
}


/* ========================================
   SIDEBAR USER
   ======================================== */

.sidebar-user {
    display: flex;
    align-items: center;

    gap: 11px;

    margin: 16px;

    padding: 12px;

    background: #f5f8fc;

    border: 1px solid #e8edf3;

    border-radius: 10px;
}

.sidebar-user-avatar {
    width: 38px;
    height: 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: #e5f1fc;

    color: #1677d2;

    font-size: 14px;

    font-weight: bold;

    flex-shrink: 0;
}

.sidebar-user-info {
    min-width: 0;

    display: flex;

    flex-direction: column;
}

.sidebar-user-info strong {
    color: #1f2937;

    font-size: 13px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.sidebar-user-info span {
    margin-top: 3px;

    color: #7b8794;

    font-size: 11px;
}


/* ========================================
   MENU
   ======================================== */

.sidebar-menu {
    display: flex;

    flex-direction: column;

    gap: 4px;

    padding: 0 12px;
}

.sidebar-menu-label {
    padding: 9px 10px 7px;

    color: #9aa5b1;

    font-size: 10px;

    font-weight: bold;

    letter-spacing: 0.7px;
}

.sidebar-menu-label-second {
    margin-top: 10px;
}


/* ========================================
   MENU LINK
   ======================================== */

.sidebar-link {
    min-height: 42px;

    display: flex;
    align-items: center;

    gap: 11px;

    padding: 10px 12px;

    border-radius: 8px;

    color: #526173;

    text-decoration: none;

    font-size: 13px;

    font-weight: 500;

    transition:
        background 0.2s ease,
        color 0.2s ease;
}

.sidebar-link:hover {
    background: #f1f7fd;

    color: #1677d2;
}

.sidebar-link.active {
    background: #e8f3ff;

    color: #1677d2;

    font-weight: bold;
}


/* ========================================
   SIDEBAR BOTTOM
   ======================================== */

.sidebar-bottom {
    margin-top: auto;

    padding: 15px 12px;

    border-top:
        1px solid #edf0f3;
}

.sidebar-logout {
    min-height: 42px;

    display: flex;
    align-items: center;

    gap: 11px;

    padding: 10px 12px;

    border-radius: 8px;

    color: #dc3545;

    text-decoration: none;

    font-size: 13px;

    font-weight: 500;

    transition:
        background 0.2s ease,
        color 0.2s ease;
}

.sidebar-logout:hover {
    background: #fff1f2;

    color: #bb2d3b;
}


/* ========================================
   RESPONSIVE
   ======================================== */

@media (max-width: 480px) {

    .kasir-sidebar {
        width: min(280px, 86vw);
    }

}

</style>


<!-- ========================================
     SIDEBAR OVERLAY
     ======================================== -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
    onclick="closeSidebar()"
></div>


<!-- ========================================
     SIDEBAR
     ======================================== -->

<aside
    class="kasir-sidebar"
    id="kasirSidebar"
>


    <!-- ====================================
         BRAND
         ==================================== -->

    <div class="sidebar-brand">

        <div class="sidebar-logo">

            <img
                src="../assets/SembakoKu (1).png"
                alt="Logo Toko Malaya"
            >

        </div>


        <div class="sidebar-brand-text">

            <strong>
                Toko Malaya
            </strong>

            <span>
                Panel Kasir
            </span>

        </div>

    </div>


    <!-- ====================================
         USER
         ==================================== -->

    <div class="sidebar-user">

        <div class="sidebar-user-avatar">

            <?= htmlspecialchars(
                strtoupper(
                    substr(
                        $nama_sidebar,
                        0,
                        1
                    )
                )
            ) ?>

        </div>


        <div class="sidebar-user-info">

            <strong>
                <?= htmlspecialchars(
                    $nama_sidebar
                ) ?>
            </strong>

            <span>
                Kasir
            </span>

        </div>

    </div>


    <!-- ====================================
         MENU
         ==================================== -->

    <nav class="sidebar-menu">


        <div class="sidebar-menu-label">
            MENU UTAMA
        </div>


        <!-- DASHBOARD -->

        <a
            href="dashboard.php"
            class="sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>"
        >
            <span>🏠</span>
            <span>Dashboard</span>
        </a>


        <!-- PENJUALAN -->

        <a
            href="penjualan.php"
            class="sidebar-link <?= $current_page === 'penjualan.php' ? 'active' : '' ?>"
        >
            <span>🧾</span>
            <span>Penjualan</span>
        </a>


        <!-- RIWAYAT PENJUALAN -->

        <a
            href="riwayat_penjualan.php"
            class="sidebar-link <?= $current_page === 'riwayat_penjualan.php' ? 'active' : '' ?>"
        >
            <span>📋</span>
            <span>Riwayat Penjualan</span>
        </a>


        <!-- PESANAN ONLINE -->

        <a
            href="pesanan.php"
            class="sidebar-link <?= $current_page === 'pesanan.php' ? 'active' : '' ?>"
        >
            <span>🛒</span>
            <span>Pesanan Online</span>
        </a>


        <!-- STOK -->

        <a
            href="stok.php"
            class="sidebar-link <?= $current_page === 'stok.php' ? 'active' : '' ?>"
        >
            <span>📦</span>
            <span>Stok</span>
        </a>


        <!-- LAPORAN -->

        <a
            href="laporan.php"
            class="sidebar-link <?= $current_page === 'laporan.php' ? 'active' : '' ?>"
        >
            <span>📊</span>
            <span>Laporan</span>
        </a>


        <!-- AKUN -->

        <div class="sidebar-menu-label sidebar-menu-label-second">
            AKUN
        </div>


        <!-- PROFIL -->

        <a
            href="profil.php"
            class="sidebar-link <?= $current_page === 'profil.php' ? 'active' : '' ?>"
        >
            <span>👤</span>
            <span>Profil</span>
        </a>

    </nav>


    <!-- ====================================
         LOGOUT
         ==================================== -->

    <div class="sidebar-bottom">

        <a
            href="../auth/logout.php"
            class="sidebar-logout"
            onclick="
                return confirm(
                    'Apakah kamu yakin ingin keluar?'
                );
            "
        >

            <span>🚪</span>

            <span>
                Keluar
            </span>

        </a>

    </div>

</aside>


<script>

/* ========================================
   BUKA SIDEBAR
   ======================================== */

function openSidebar() {

    const sidebar =
        document.getElementById(
            'kasirSidebar'
        );

    const overlay =
        document.getElementById(
            'sidebarOverlay'
        );


    if (!sidebar || !overlay) {
        return;
    }


    sidebar.classList.add(
        'show'
    );

    overlay.classList.add(
        'show'
    );

    document.body.classList.add(
        'sidebar-open'
    );

}


/* ========================================
   TUTUP SIDEBAR
   ======================================== */

function closeSidebar() {

    const sidebar =
        document.getElementById(
            'kasirSidebar'
        );

    const overlay =
        document.getElementById(
            'sidebarOverlay'
        );


    if (!sidebar || !overlay) {
        return;
    }


    sidebar.classList.remove(
        'show'
    );

    overlay.classList.remove(
        'show'
    );

    document.body.classList.remove(
        'sidebar-open'
    );

}


/* ========================================
   TOGGLE SIDEBAR
   ======================================== */

function toggleSidebar() {

    const sidebar =
        document.getElementById(
            'kasirSidebar'
        );


    if (!sidebar) {
        return;
    }


    if (
        sidebar.classList.contains(
            'show'
        )
    ) {

        closeSidebar();

    } else {

        openSidebar();

    }

}


/* ========================================
   ESC UNTUK MENUTUP
   ======================================== */

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeSidebar();

        }

    }
);

</script>