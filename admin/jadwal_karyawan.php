<?php
session_start();
require_once "../config/database.php";

/* =========================================================
   AUTENTIKASI
========================================================= */
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

/* =========================================================
   HELPER
========================================================= */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tanggal_valid($tanggal): bool
{
    if (!is_string($tanggal)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

    return $date !== false && $date->format('Y-m-d') === $tanggal;
}

function tanggal_indo($tanggal): string
{
    if (!tanggal_valid($tanggal)) {
        return '-';
    }

    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April',
        'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'
    ];

    $date = new DateTime($tanggal);

    return (int)$date->format('j') . ' '
        . $bulan[(int)$date->format('n')] . ' '
        . $date->format('Y');
}

function waktu_format($waktu): string
{
    if (!$waktu || $waktu === '00:00:00') {
        return '—';
    }

    return substr((string)$waktu, 0, 5);
}

function kolom_tersedia(mysqli $conn, string $tabel, string $kolom): bool
{
    // Hanya izinkan nama tabel dan kolom yang aman.
    if (
        !preg_match('/^[a-zA-Z0-9_]+$/', $tabel) ||
        !preg_match('/^[a-zA-Z0-9_]+$/', $kolom)
    ) {
        return false;
    }

    // Nama tabel dan kolom sudah divalidasi dengan regex.
    $sql = "SHOW COLUMNS FROM `{$tabel}` LIKE '" .
           mysqli_real_escape_string($conn, $kolom) . "'";

    $result = mysqli_query($conn, $sql);

    if ($result === false) {
        error_log(
            '[Jadwal Karyawan] Gagal memeriksa kolom: ' .
            mysqli_error($conn)
        );

        return false;
    }

    $tersedia = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);

    return $tersedia;
}

function catat_error($pesan): void
{
    error_log('[Jadwal Karyawan] ' . $pesan);
}

/* =========================================================
   SESSION DAN FILTER TANGGAL
========================================================= */
$id_admin = (int)$_SESSION['id_user'];
$nama_user = $_SESSION['nama'] ?? 'Admin';
$today = date('Y-m-d');

if (empty($_SESSION['csrf_jadwal'])) {
    $_SESSION['csrf_jadwal'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_jadwal'];

$tanggal_filter = $_GET['tanggal'] ?? $today;

if (!tanggal_valid($tanggal_filter)) {
    $tanggal_filter = $today;
}

$pesan = '';
$jenis_pesan = 'success';

/* =========================================================
   CEK TABEL
========================================================= */
$tabel_wajib = ['users', 'shift', 'profil_karyawan', 'jadwal_shift'];
$tabel_tersedia = true;

foreach ($tabel_wajib as $tabel) {
    $nama_tabel = mysqli_real_escape_string($conn, $tabel);
    $cek = mysqli_query($conn, "SHOW TABLES LIKE '$nama_tabel'");

    if (!$cek || mysqli_num_rows($cek) === 0) {
        $tabel_tersedia = false;
        break;
    }
}

/* =========================================================
   SIMPAN / PERBARUI JADWAL
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$tabel_tersedia) {
        $pesan = 'Tabel yang diperlukan belum tersedia di database.';
        $jenis_pesan = 'error';
    } else {
        $token = $_POST['csrf_token'] ?? '';

        if (!is_string($token) || !hash_equals($csrf, $token)) {
            $pesan = 'Sesi formulir tidak valid. Muat ulang halaman.';
            $jenis_pesan = 'error';
        } else {
            $id_user = filter_var(
                $_POST['id_user'] ?? null,
                FILTER_VALIDATE_INT
            );

            $id_shift = filter_var(
                $_POST['id_shift'] ?? null,
                FILTER_VALIDATE_INT
            );

            $tanggal = $_POST['tanggal'] ?? '';
            $catatan_raw = $_POST['catatan'] ?? '';
            $catatan = is_string($catatan_raw)
                ? trim($catatan_raw)
                : '';

            if (
                !$id_user || $id_user < 1 ||
                !$id_shift || $id_shift < 1 ||
                !tanggal_valid($tanggal) ||
                mb_strlen($catatan) > 255
            ) {
                $pesan = 'Data jadwal belum lengkap atau tidak valid.';
                $jenis_pesan = 'error';
            } else {
                $transaksi = false;

                try {
                    if (!mysqli_begin_transaction($conn)) {
                        throw new RuntimeException('Gagal memulai transaksi.');
                    }

                    $transaksi = true;

                    /* Pastikan akun merupakan pegawai */
                    $stmt = mysqli_prepare(
                        $conn,
                        "SELECT id_user, role
                         FROM users
                         WHERE id_user = ?
                           AND role = 'karyawan'
                         LIMIT 1"
                    );

                    if (!$stmt) {
                        throw new RuntimeException(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param($stmt, "i", $id_user);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $pegawai = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);

                    if (!$pegawai) {
                        throw new DomainException(
                            'Pegawai tidak ditemukan atau role tidak sesuai.'
                        );
                    }

                    /* Pastikan shift valid */
                    $stmt = mysqli_prepare(
                        $conn,
                        "SELECT id_shift FROM shift WHERE id_shift = ? LIMIT 1"
                    );

                    if (!$stmt) {
                        throw new RuntimeException(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param($stmt, "i", $id_shift);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $shift = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);

                    if (!$shift) {
                        throw new DomainException('Shift tidak ditemukan.');
                    }

                    /* Simpan profil tanpa menimpa gaji yang sudah ada */
                    $jabatan = $pegawai['role'] === 'kasir'
                        ? 'kasir'
                        : 'staff';

                    $gaji_default = $jabatan === 'kasir'
                        ? 1200000
                        : 1500000;

                    $target_hari = 26;

                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO profil_karyawan
                            (id_user, jabatan, gaji_bulanan, target_hari_bulanan, aktif)
                         VALUES (?, ?, ?, ?, 1)
                         ON DUPLICATE KEY UPDATE
                            jabatan = VALUES(jabatan),
                            aktif = 1"
                    );

                    if (!$stmt) {
                        throw new RuntimeException(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "isdi",
                        $id_user,
                        $jabatan,
                        $gaji_default,
                        $target_hari
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        $error = mysqli_stmt_error($stmt);
                        mysqli_stmt_close($stmt);
                        throw new RuntimeException($error);
                    }

                    mysqli_stmt_close($stmt);

                    /* Simpan jadwal */
                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO jadwal_shift
                            (id_user, id_shift, tanggal, dibuat_oleh, catatan)
                         VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                            id_shift = VALUES(id_shift),
                            dibuat_oleh = VALUES(dibuat_oleh),
                            catatan = VALUES(catatan)"
                    );

                    if (!$stmt) {
                        throw new RuntimeException(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iisis",
                        $id_user,
                        $id_shift,
                        $tanggal,
                        $id_admin,
                        $catatan
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        $error = mysqli_stmt_error($stmt);
                        mysqli_stmt_close($stmt);
                        throw new RuntimeException($error);
                    }

                    mysqli_stmt_close($stmt);

                    if (!mysqli_commit($conn)) {
                        throw new RuntimeException(mysqli_error($conn));
                    }

                    $transaksi = false;

                    header(
                        'Location: jadwal_karyawan.php?tanggal='
                        . urlencode($tanggal)
                        . '&sukses=1'
                    );
                    exit;

                } catch (DomainException $ex) {
                    if ($transaksi) {
                        mysqli_rollback($conn);
                    }

                    $pesan = $ex->getMessage();
                    $jenis_pesan = 'error';

                } catch (Throwable $ex) {
                    if ($transaksi) {
                        mysqli_rollback($conn);
                    }

                    catat_error($ex->getMessage());

                    $pesan = 'Gagal menyimpan jadwal. Periksa struktur tabel database.';
                    $jenis_pesan = 'error';
                }
            }
        }
    }
}

if (isset($_GET['sukses']) && $_GET['sukses'] === '1') {
    $pesan = 'Jadwal shift berhasil disimpan.';
    $jenis_pesan = 'success';
}

/* =========================================================
   DATA SHIFT
========================================================= */
$daftar_shift = [];

if ($tabel_tersedia) {
    $q = mysqli_query(
        $conn,
        "SELECT id_shift, nama_shift, jam_mulai, jam_selesai
         FROM shift
         ORDER BY jam_mulai ASC"
    );

    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $daftar_shift[] = $row;
        }
    } else {
        catat_error(mysqli_error($conn));
    }
}

/* =========================================================
   DATA PEGAWAI
========================================================= */
$daftar_pegawai = [];

if ($tabel_tersedia) {
    $q = mysqli_query(
        $conn,
        "SELECT id_user, nama, username, role
         FROM users
         WHERE role = 'karyawan'
         ORDER BY nama ASC"
    );

    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $daftar_pegawai[] = $row;
        }
    } else {
        catat_error(mysqli_error($conn));
    }
}

/* =========================================================
   DETEKSI STRUKTUR TABEL ABSENSI
   Kolom absensi bisa berbeda di setiap proyek.
========================================================= */
$absensi_tersedia = false;
$kolom_absensi = [];

$cek_absensi = mysqli_query($conn, "SHOW TABLES LIKE 'absensi'");

if ($cek_absensi && mysqli_num_rows($cek_absensi) > 0) {
    $kolom_absensi = [
        'id_user' => kolom_tersedia($conn, 'absensi', 'id_user'),
        'tanggal' => kolom_tersedia($conn, 'absensi', 'tanggal'),
        'jam_masuk' => kolom_tersedia($conn, 'absensi', 'jam_masuk'),
        'jam_pulang' => kolom_tersedia($conn, 'absensi', 'jam_pulang'),
        'status' => kolom_tersedia($conn, 'absensi', 'status'),
    ];

    $absensi_tersedia =
        $kolom_absensi['id_user'] &&
        $kolom_absensi['tanggal'] &&
        $kolom_absensi['jam_masuk'] &&
        $kolom_absensi['jam_pulang'];
}

/* =========================================================
   JADWAL HARIAN + DATA ABSENSI
========================================================= */
$daftar_jadwal = [];

if ($tabel_tersedia) {
    $sql = "
        SELECT
            j.id_jadwal,
            j.id_user,
            j.id_shift,
            j.tanggal,
            j.catatan,
            u.nama,
            u.username,
            u.role,
            s.nama_shift,
            s.jam_mulai,
            s.jam_selesai
        FROM jadwal_shift j
        INNER JOIN users u ON u.id_user = j.id_user
        INNER JOIN shift s ON s.id_shift = j.id_shift
        WHERE j.tanggal = ?
        ORDER BY s.jam_mulai ASC, u.nama ASC
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $tanggal_filter);

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $row['jam_masuk'] = null;
                $row['jam_pulang'] = null;
                $row['status_absensi'] = 'Belum absen';

                $daftar_jadwal[] = $row;
            }
        } else {
            catat_error(mysqli_stmt_error($stmt));
        }

        mysqli_stmt_close($stmt);
    } else {
        catat_error(mysqli_error($conn));
    }
}

/* =========================================================
   ISI JAM MASUK, PULANG, DAN STATUS DARI TABEL ABSENSI
========================================================= */
if ($absensi_tersedia && count($daftar_jadwal) > 0) {
    $sql_absensi = "
        SELECT jam_masuk, jam_pulang"
        . ($kolom_absensi['status'] ? ", status" : "") . "
        FROM absensi
        WHERE id_user = ? AND tanggal = ?
        ORDER BY jam_masuk DESC
        LIMIT 1
    ";

    $stmt_absensi = mysqli_prepare($conn, $sql_absensi);

    if ($stmt_absensi) {
        foreach ($daftar_jadwal as &$jadwal) {
            $id_user_absensi = (int)$jadwal['id_user'];

            mysqli_stmt_bind_param(
                $stmt_absensi,
                "is",
                $id_user_absensi,
                $tanggal_filter
            );

            if (mysqli_stmt_execute($stmt_absensi)) {
                $result_absensi = mysqli_stmt_get_result($stmt_absensi);
                $absensi = mysqli_fetch_assoc($result_absensi);

                if ($absensi) {
                    $jadwal['jam_masuk'] = $absensi['jam_masuk'];
                    $jadwal['jam_pulang'] = $absensi['jam_pulang'];

                    $jam_masuk = $absensi['jam_masuk'] ?? '';
                    $jam_pulang = $absensi['jam_pulang'] ?? '';
                    $status_database = strtolower(trim(
                        (string)($absensi['status'] ?? '')
                    ));

                    if ($jam_masuk === '' || $jam_masuk === null) {
                        $jadwal['status_absensi'] = 'Belum absen';
                    } elseif (
                        strpos($status_database, 'terlambat') !== false
                    ) {
                        $jadwal['status_absensi'] = 'Terlambat';
                    } elseif (
                        $jam_pulang !== '' && $jam_pulang !== null
                    ) {
                        $jadwal['status_absensi'] = 'Sudah pulang';
                    } else {
                        $jadwal['status_absensi'] = 'Hadir';
                    }
                }
            }
        }

        unset($jadwal);
        mysqli_stmt_close($stmt_absensi);
    } else {
        catat_error(mysqli_error($conn));
    }
}

/* =========================================================
   KPI
========================================================= */
$total_staff = count($daftar_pegawai);

$total_terjadwal = count($daftar_jadwal);

$id_pegawai_terjadwal = [];

foreach ($daftar_jadwal as $jadwal) {
    $id_pegawai_terjadwal[(int)$jadwal['id_user']] = true;
}

$total_belum_terjadwal = max(
    0,
    count($daftar_pegawai) - count($id_pegawai_terjadwal)
);

/* =========================================================
   WARNA STATUS
========================================================= */
function status_class($status): string
{
    switch ($status) {
        case 'Hadir':
            return 'hadir';
        case 'Terlambat':
            return 'terlambat';
        case 'Sudah pulang':
            return 'pulang';
        default:
            return 'belum';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jadwal Shift Karyawan - Toko Malaya</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #172033;
}

body.menu-open {
    overflow: hidden;
}

a {
    color: inherit;
    text-decoration: none;
}

/* TOPBAR */
.topbar {
    height: 72px;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    position: sticky;
    top: 0;
    z-index: 900;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.hamburger {
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 10px;
    background: #f1f5f9;
    color: #1e293b;
    font-size: 23px;
    cursor: pointer;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.brand-logo {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #1677d2;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.brand-logo img,
.drawer-logo img {
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
    font-weight: 800;
    color: #111827;
}

.brand-text span {
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}

.top-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.top-avatar,
.drawer-avatar {
    border-radius: 50%;
    background: #e0efff;
    color: #1265a7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
}

.top-avatar {
    width: 38px;
    height: 38px;
}

.top-user-info,
.drawer-user-info {
    display: flex;
    flex-direction: column;
}

.top-user-info strong {
    font-size: 14px;
}

.top-user-info span,
.drawer-user-info span {
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}

/* DRAWER */
.menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .45);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: .25s;
}

.menu-overlay.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.drawer {
    position: fixed;
    top: 0;
    left: 0;
    width: 335px;
    max-width: 90vw;
    height: 100vh;
    background: #fff;
    z-index: 1100;
    transform: translateX(-100%);
    transition: transform .28s ease;
    display: flex;
    flex-direction: column;
    box-shadow: 8px 0 30px rgba(15, 23, 42, .12);
}

.drawer.show {
    transform: translateX(0);
}

.drawer-header {
    min-height: 88px;
    padding: 18px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.drawer-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.drawer-logo {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    background: #1677d2;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.drawer-brand-text {
    display: flex;
    flex-direction: column;
}

.drawer-brand-text strong {
    font-size: 18px;
    font-weight: 800;
}

.drawer-brand-text span {
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}

.drawer-close {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 10px;
    background: #f1f5f9;
    font-size: 25px;
    cursor: pointer;
}

.drawer-user {
    margin: 20px;
    padding: 14px;
    border: 1px solid #dbe4ee;
    background: #f8fafc;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.drawer-avatar {
    width: 48px;
    height: 48px;
    font-size: 17px;
}

.drawer-user-info strong {
    font-size: 15px;
}

.drawer-menu {
    padding: 0 14px 20px;
    overflow-y: auto;
    flex: 1;
}

.menu-label {
    padding: 10px 12px;
    margin-bottom: 7px;
    font-size: 11px;
    font-weight: 800;
    color: #94a3b8;
    letter-spacing: .5px;
}

.menu-link {
    min-height: 48px;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 0 14px;
    margin-bottom: 5px;
    border-radius: 10px;
    color: #475569;
    font-size: 15px;
    font-weight: 700;
    transition: .2s;
}

.menu-link:hover {
    background: #f1f5f9;
    color: #0879e8;
}

.menu-link.active {
    background: #e5f1ff;
    color: #0879e8;
    font-weight: 800;
    position: relative;
}

.menu-link.active::before {
    content: "";
    position: absolute;
    left: 0;
    top: 8px;
    bottom: 8px;
    width: 3px;
    background: #0879e8;
    border-radius: 0 4px 4px 0;
}

.menu-icon {
    width: 25px;
    min-width: 25px;
    text-align: center;
    font-size: 19px;
}

.drawer-footer {
    border-top: 1px solid #e5e7eb;
    padding: 14px;
}

.logout-link {
    min-height: 48px;
    padding: 0 14px;
    display: flex;
    align-items: center;
    gap: 14px;
    border-radius: 10px;
    color: #ef4444;
    font-size: 15px;
    font-weight: 700;
}

.logout-link:hover {
    background: #fef2f2;
}

/* KONTEN */
.main {
    max-width: 1250px;
    margin: auto;
    padding: 30px 24px 55px;
}

.page-header {
    margin-bottom: 22px;
}

.page-header h1 {
    font-size: 29px;
    color: #102a43;
    margin-bottom: 7px;
}

.page-header p {
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
}

.welcome {
    background: linear-gradient(135deg, #1667aa, #195b96);
    color: #fff;
    border-radius: 16px;
    padding: 24px 26px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.welcome h2 {
    font-size: 21px;
    margin-bottom: 7px;
}

.welcome p {
    color: #dbeafe;
    font-size: 13px;
    line-height: 1.6;
}

.today-box {
    min-width: 170px;
    padding: 13px 15px;
    border: 1px solid rgba(255,255,255,.25);
    background: rgba(255,255,255,.10);
    border-radius: 12px;
    text-align: right;
}

.today-box span {
    display: block;
    color: #dbeafe;
    font-size: 11px;
    margin-bottom: 4px;
}

.today-box strong {
    font-size: 14px;
}

/* KPI */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.kpi {
    background: #fff;
    border: 1px solid #e3e9f1;
    border-radius: 14px;
    padding: 18px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
    min-height: 125px;
}

.kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.kpi-title {
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.kpi-icon {
    width: 39px;
    height: 39px;
    border-radius: 10px;
    background: #eaf4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.kpi-value {
    margin-top: 14px;
    color: #155fa0;
    font-size: 26px;
    font-weight: 800;
}

.kpi-note {
    margin-top: 5px;
    color: #7b8798;
    font-size: 11px;
}

.two-col {
    display: grid;
    grid-template-columns: 1fr 1.35fr;
    gap: 20px;
    align-items: start;
}

.card {
    background: #fff;
    border: 1px solid #e3e9f1;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
    min-width: 0;
}

.card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 17px;
}

.card-header h2 {
    font-size: 17px;
    color: #1e293b;
}

.card-header p {
    margin-top: 5px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.6;
}

/* FORM */
.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    color: #334155;
    font-size: 12px;
    font-weight: 800;
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid #d8e0e9;
    border-radius: 9px;
    background: #fff;
    color: #172033;
    font-family: inherit;
    font-size: 13px;
    outline: none;
}

.form-control:focus {
    border-color: #1677d2;
    box-shadow: 0 0 0 3px rgba(22,119,210,.12);
}

textarea.form-control {
    resize: vertical;
    min-height: 82px;
}

.form-help {
    display: block;
    color: #94a3b8;
    font-size: 11px;
    margin-top: 6px;
    line-height: 1.5;
}

.btn {
    min-height: 43px;
    padding: 10px 16px;
    border: none;
    border-radius: 9px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: .2s;
}

.btn-primary {
    background: #1677d2;
    color: #fff;
}

.btn-primary:hover {
    background: #0f63b5;
}

.btn-light {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}

.btn-block {
    width: 100%;
}

.btn:disabled {
    opacity: .55;
    cursor: not-allowed;
}

/* NOTIFIKASI */
.alert {
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 13px;
    line-height: 1.6;
}

.alert.success {
    background: #e9f9f2;
    color: #047857;
    border: 1px solid #b8ead4;
}

.alert.error {
    background: #fff0f0;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.alert.warning {
    background: #fff7df;
    color: #92400e;
    border: 1px solid #fde68a;
}

/* FILTER TANGGAL */
.filter-row {
    display: flex;
    align-items: end;
    gap: 10px;
    margin-bottom: 18px;
}

.filter-row .form-group {
    flex: 1;
    margin-bottom: 0;
}

/* =========================================================
   TABEL JADWAL HARIAN - TEMA PUTIH
========================================================= */
.daily-card {
    background: #ffffff;
    color: #172033;
    border: 1px solid #e3e9f1;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
    min-width: 0;
}

.daily-card .card-header {
    padding: 0 0 12px;
    margin-bottom: 0;
}

.daily-card .card-header h2 {
    color: #1e293b;
    font-size: 17px;
}

.daily-card .card-header p {
    color: #64748b;
}

.daily-card .filter-row label {
    color: #334155;
}

.daily-card .form-control {
    background: #ffffff;
    color: #172033;
    border-color: #d8e0e9;
}

.daily-card .btn-primary {
    background: #1677d2;
    color: #ffffff;
}

.daily-table-wrap {
    width: 100%;
    overflow-x: auto;
    border-radius: 7px;
}

.daily-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    min-width: 540px;
    background: #ffffff;
    color: #172033;
}

.daily-table thead {
    background: #f1f5f9;
}

.daily-table th {
    padding: 9px 8px;
    text-align: left;
    font-size: 11px;
    font-weight: 800;
    color: #334155;
    white-space: nowrap;
}

.daily-table th:first-child {
    border-radius: 7px 0 0 7px;
}

.daily-table th:last-child {
    border-radius: 0 7px 7px 0;
}

.daily-table td {
    padding: 17px 8px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 12px;
    color: #172033;
    vertical-align: middle;
    overflow-wrap: anywhere;
}

.daily-table tbody tr:last-child td {
    border-bottom: none;
}

.daily-table th:nth-child(1),
.daily-table td:nth-child(1) {
    width: 24%;
}

.daily-table th:nth-child(2),
.daily-table td:nth-child(2) {
    width: 19%;
}

.daily-table th:nth-child(3),
.daily-table td:nth-child(3) {
    width: 17%;
}

.daily-table th:nth-child(4),
.daily-table td:nth-child(4) {
    width: 17%;
}

.daily-table th:nth-child(5),
.daily-table td:nth-child(5) {
    width: 23%;
}

.daily-employee {
    font-size: 12px;
    font-weight: 600;
    color: #172033;
}

.daily-sub {
    color: #64748b;
    font-size: 10px;
    margin-top: 4px;
}

.daily-shift {
    color: #172033;
    font-size: 12px;
}

.daily-time {
    font-size: 12px;
    color: #172033;
    white-space: nowrap;
}

.status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: #334155;
    white-space: nowrap;
}

.status-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    min-width: 10px;
    border-radius: 50%;
}

.status.hadir .status-dot {
    background: #00d12e;
}

.status.belum .status-dot {
    background: #ff1744;
}

.status.terlambat .status-dot {
    background: #ff6b00;
}

.status.pulang .status-dot {
    background: #00d12e;
}

.daily-empty {
    text-align: center;
    padding: 28px 12px !important;
    color: #64748b !important;
    line-height: 1.8;
}

.daily-caption {
    padding-top: 8px;
    font-size: 10px;
    color: #64748b;
    line-height: 1.6;
}

.info-note {
    margin-top: 16px;
    padding: 12px 14px;
    background: #f8fafc;
    border-radius: 10px;
    color: #64748b;
    font-size: 11px;
    line-height: 1.7;
}

@media (max-width: 1000px) {
    .two-col {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .topbar {
        padding: 0 15px;
    }

    .brand-text,
    .top-user-info {
        display: none;
    }

    .top-user {
        border: none;
        padding: 4px;
    }

    .main {
        padding: 24px 15px 40px;
    }

    .page-header h1 {
        font-size: 25px;
    }

    .welcome {
        flex-direction: column;
        align-items: flex-start;
    }

    .today-box {
        width: 100%;
        text-align: left;
    }

    .kpi-grid {
        grid-template-columns: 1fr 1fr;
    }

    .filter-row {
        align-items: stretch;
        flex-direction: column;
    }

    .filter-row .btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .kpi-grid {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 16px;
    }

    .daily-card {
        padding: 8px;
    }

    .daily-table th,
    .daily-table td {
        padding-left: 6px;
        padding-right: 6px;
    }
}
</style>
</head>

<body>

<div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>

<aside class="drawer" id="drawer">
    <div class="drawer-header">
        <div class="drawer-brand">
            <div class="drawer-logo">
                <img src="../assets/SembakoKu (1).png" alt="Toko Malaya">
            </div>

            <div class="drawer-brand-text">
                <strong>Toko Malaya</strong>
                <span>Panel Admin / Pemilik</span>
            </div>
        </div>

        <button type="button" class="drawer-close" onclick="closeMenu()" aria-label="Tutup menu">×</button>
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
            <span class="menu-icon">🏠</span>Dashboard
        </a>

        <a href="produk.php" class="menu-link">
            <span class="menu-icon">📦</span>Produk dan Kategori
        </a>

        <a href="pengguna.php" class="menu-link">
            <span class="menu-icon">👥</span>Pengguna
        </a>

        <a href="stok.php" class="menu-link">
            <span class="menu-icon">📦</span>Stok & Supplier
        </a>

        <a href="pengeluaran.php" class="menu-link">
            <span class="menu-icon">🧾</span>Pengeluaran
        </a>

        <a href="laporan.php" class="menu-link">
            <span class="menu-icon">📊</span>Laporan
        </a>

        <div class="menu-label">KEPEGAWAIAN</div>

        <a href="jadwal_karyawan.php" class="menu-link active">
            <span class="menu-icon">🗓️</span>Jadwal Shift Karyawan
        </a>

        <a href="penggajian.php" class="menu-link">
            <span class="menu-icon">💰</span>Penggajian Karyawan
        </a>

        <a href="pengajuan_cuti.php" class="menu-link">
            <span class="menu-icon">📝</span>Pengajuan Cuti & Izin
        </a>

        <div class="menu-label">AKUN</div>

        <a href="profil.php" class="menu-link">
            <span class="menu-icon">👤</span>Profil
        </a>

    </nav>

    <div class="drawer-footer">
        <a href="../auth/logout.php" class="logout-link"
           onclick="return confirm('Apakah kamu yakin ingin keluar?');">
            <span class="menu-icon">🚪</span>Keluar
        </a>
    </div>
</aside>

<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="hamburger"
                onclick="toggleMenu()"
                aria-label="Buka menu"
                aria-controls="drawer"
                aria-expanded="false"
                id="menuToggle">☰</button>

        <a href="dashboard.php" class="brand">
            <div class="brand-logo">
                <img src="../assets/SembakoKu (1).png" alt="Toko Malaya">
            </div>

            <div class="brand-text">
                <strong>Toko Malaya</strong>
                <span>Panel Admin</span>
            </div>
        </a>
    </div>

    <div class="top-user">
        <div class="top-avatar">
            <?= e(strtoupper(substr($nama_user, 0, 1))) ?>
        </div>

        <div class="top-user-info">
            <strong><?= e($nama_user) ?></strong>
            <span>Admin / Pemilik</span>
        </div>
    </div>
</header>

<main class="main">

    <div class="page-header">
        <h1>Jadwal Shift Karyawan</h1>
        <p>Atur jadwal kerja karyawan dan pantau pembagian shift harian.</p>
    </div>

    <section class="welcome">
        <div>
            <h2>Pengaturan jadwal kerja </h2>
            <p>Tentukan pegawai, tanggal, dan shift yang akan dijalankan.</p>
        </div>

        <div class="today-box">
            <span>Tanggal yang dipilih</span>
            <strong><?= e(tanggal_indo($tanggal_filter)) ?></strong>
        </div>
    </section>

    <?php if (!$tabel_tersedia): ?>
        <div class="alert warning">
            Tabel <code>users</code>, <code>shift</code>,
            <code>profil_karyawan</code>, atau <code>jadwal_shift</code>
            belum tersedia. Periksa database <code>db_sembakoku</code>.
        </div>
    <?php endif; ?>

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= e($jenis_pesan) ?>" role="status">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <section class="kpi-grid">
        <div class="kpi">
            <div class="kpi-top">
                <span class="kpi-title">Total Karyawan</span>
            </div>
            <div class="kpi-value"><?= number_format($total_staff) ?></div>
            <div class="kpi-note">Pengguna dengan role karyawan</div>
        </div>

        <div class="kpi">
            <div class="kpi-top">
                <span class="kpi-title">Jadwal Tanggal Ini</span>
            </div>
            <div class="kpi-value"><?= number_format($total_terjadwal) ?></div>
            <div class="kpi-note">
                <?= number_format($total_belum_terjadwal) ?>
                pegawai belum terjadwal pada tanggal pilihan
            </div>
        </div>
    </section>

    <section class="two-col">

        <!-- FORM JADWAL -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2>Tambah / Ubah Jadwal</h2>
                    <p>Pilih pegawai, tanggal, dan shift kerja.</p>
                </div>
            </div>

            <?php if (count($daftar_pegawai) === 0): ?>
                <div class="alert warning">
                    Belum ada akun dengan role karyawan.
                </div>
            <?php endif; ?>

            <?php if (count($daftar_shift) === 0 && $tabel_tersedia): ?>
                <div class="alert warning">
                    Data shift belum tersedia. Tambahkan data pada tabel <code>shift</code>.
                </div>
            <?php endif; ?>

            <form method="POST" action="jadwal_karyawan.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                <div class="form-group">
                    <label for="id_user">Pegawai</label>
                    <select class="form-control" name="id_user" id="id_user" required>
                        <option value="">-- Pilih pegawai --</option>
                        <?php foreach ($daftar_pegawai as $pegawai): ?>
                            <option value="<?= (int)$pegawai['id_user'] ?>">
                                <?= e($pegawai['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tanggal">Tanggal jadwal</label>
                    <input class="form-control" type="date" name="tanggal" id="tanggal"
                           value="<?= e($tanggal_filter) ?>" required>
                </div>

                <div class="form-group">
                    <label for="id_shift">Shift kerja</label>
                    <select class="form-control" name="id_shift" id="id_shift" required>
                        <option value="">-- Pilih shift --</option>
                        <?php foreach ($daftar_shift as $shift): ?>
                            <option value="<?= (int)$shift['id_shift'] ?>">
                                <?= e($shift['nama_shift']) ?>
                                (<?= e(waktu_format($shift['jam_mulai'])) ?>
                                – <?= e(waktu_format($shift['jam_selesai'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="catatan">Catatan (opsional)</label>
                    <textarea class="form-control" name="catatan" id="catatan"
                              maxlength="255"
                              placeholder="Contoh: menggantikan pegawai yang libur"></textarea>
                    <span class="form-help">Maksimal 255 karakter.</span>
                </div>

                <button type="submit" class="btn btn-primary btn-block"
                    <?= (
                        !$tabel_tersedia ||
                        count($daftar_pegawai) === 0 ||
                        count($daftar_shift) === 0
                    ) ? 'disabled' : '' ?>>
                        Simpan Jadwal
                </button>
            </form>

            <div class="info-note">
                <strong>Catatan:</strong><br>
                Jam shift yang tampil mengikuti data pada tabel <code>shift</code>.
            </div>
        </div>

        <!-- JADWAL HARIAN: TAMPILAN PUTIH -->
        <div class="daily-card">
            <div class="card-header">
                <div>
                    <h2>Jadwal Harian</h2>
                    <p>Daftar pegawai dan status kehadiran pada tanggal pilihan.</p>
                </div>
            </div>

            <form method="GET" action="jadwal_karyawan.php" class="filter-row">
                <div class="form-group">
                    <label for="tanggal_filter">Pilih tanggal</label>
                    <input class="form-control" type="date" name="tanggal"
                           id="tanggal_filter" value="<?= e($tanggal_filter) ?>" required>
                </div>

                <button type="submit" class="btn btn-primary">
                     Tampilkan
                </button>
            </form>

            <div class="daily-table-wrap">
                <table class="daily-table">
                    <thead>
                        <tr>
                            <th>Pegawai</th>
                            <th>Shift</th>
                            <th>Masuk</th>
                            <th>Pulang</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (count($daftar_jadwal) > 0): ?>
                        <?php foreach ($daftar_jadwal as $jadwal): ?>
                            <?php
                            $status = $jadwal['status_absensi'] ?? 'Belum absen';
                            $class_status = status_class($status);
                            ?>
                            <tr>
                                <td>
                                    <div class="daily-employee">
                                        <?= e($jadwal['nama']) ?>
                                    </div>
                                    <div class="daily-sub">
                                        <?= e($jadwal['username']) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="daily-shift">
                                        <?= e($jadwal['nama_shift']) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="daily-time">
                                        <?= e(waktu_format($jadwal['jam_masuk'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="daily-time">
                                        <?= e(waktu_format($jadwal['jam_pulang'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status <?= e($class_status) ?>">
                                        <span class="status-dot"></span>
                                        <?= e($status) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="daily-empty">
                                Belum ada jadwal untuk tanggal ini.<br>
                                Tambahkan jadwal melalui formulir di sebelah kiri.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="daily-caption">
                <?php if (!$absensi_tersedia): ?>
                    Data jam masuk, jam pulang, dan status absensi belum tersedia
                    dari struktur tabel absensi yang dikenali.
                <?php else: ?>
                    Jam masuk, jam pulang, dan status ditampilkan berdasarkan
                    data absensi pada tanggal yang dipilih.
                <?php endif; ?>
            </div>
        </div>

    </section>

</main>

<script>
function openMenu() {
    document.getElementById('drawer').classList.add('show');
    document.getElementById('menuOverlay').classList.add('show');
    document.body.classList.add('menu-open');

    const toggle = document.getElementById('menuToggle');

    if (toggle) {
        toggle.setAttribute('aria-expanded', 'true');
    }
}

function closeMenu() {
    document.getElementById('drawer').classList.remove('show');
    document.getElementById('menuOverlay').classList.remove('show');
    document.body.classList.remove('menu-open');

    const toggle = document.getElementById('menuToggle');

    if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
    }
}

function toggleMenu() {
    const drawer = document.getElementById('drawer');

    if (drawer.classList.contains('show')) {
        closeMenu();
    } else {
        openMenu();
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeMenu();
    }
});
</script>

</body>
</html>