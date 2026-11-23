<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Koneksi database tidak ditemukan.");
}

mysqli_set_charset($conn, "utf8mb4");

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string {
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function wa_number($phone): string {
    $phone = preg_replace('/\D+/', '', (string)$phone);

    if ($phone === '') return '';
    if (strpos($phone, '0') === 0) return '62' . substr($phone, 1);
    if (strpos($phone, '8') === 0) return '62' . $phone;

    return $phone;
}

function redirectPesan($pesan): void {
    header("Location: stok.php?pesan=" . urlencode($pesan));
    exit;
}

$nama_user = $_SESSION['nama'] ?? 'Admin';

if (empty($_SESSION['csrf_stok'])) {
    $_SESSION['csrf_stok'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_stok'];
$pesan = '';
$jenis_pesan = 'success';

$notifikasi = [
    'tambah' => 'Supplier berhasil ditambahkan.',
    'edit' => 'Data supplier berhasil diperbarui.',
    'hapus' => 'Supplier berhasil dinonaktifkan. Riwayat pembelian tetap tersimpan.',
    'aktif' => 'Supplier berhasil diaktifkan kembali.'
];

if (isset($_GET['pesan'], $notifikasi[$_GET['pesan']])) {
    $pesan = $notifikasi[$_GET['pesan']];
}

/*
|--------------------------------------------------------------------------
| PROSES FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($csrf, $token)) {
        $pesan = 'Permintaan tidak valid. Silakan muat ulang halaman.';
        $jenis_pesan = 'error';
    } else {
        $aksi = $_POST['aksi'] ?? '';

        /*
        |------------------------------------------------------------------
        | TAMBAH SUPPLIER + HUBUNGKAN BARANG
        |------------------------------------------------------------------
        */
        if ($aksi === 'tambah_supplier' || $aksi === 'edit_supplier') {
            $id = (int)($_POST['id_supplier'] ?? 0);
            $nama = trim($_POST['nama_supplier'] ?? '');
            $telepon = trim($_POST['telepon'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');

            $barangDipilih = $_POST['barang'] ?? [];

            if (!is_array($barangDipilih)) {
                $barangDipilih = [];
            }

            $barangDipilih = array_values(array_unique(
                array_filter(
                    array_map('intval', $barangDipilih),
                    fn($v) => $v > 0
                )
            ));

            if ($nama === '') {
                $pesan = 'Nama supplier wajib diisi.';
                $jenis_pesan = 'error';
            } elseif ($aksi === 'edit_supplier' && $id <= 0) {
                $pesan = 'Supplier tidak valid.';
                $jenis_pesan = 'error';
            } else {
                try {
                    $conn->begin_transaction();

                    if ($aksi === 'tambah_supplier') {
                        $stmt = $conn->prepare(
                            "INSERT INTO supplier
                             (nama_supplier, telepon, alamat, status)
                             VALUES (?, ?, ?, 'aktif')"
                        );

                        if (!$stmt) {
                            throw new Exception($conn->error);
                        }

                        $stmt->bind_param("sss", $nama, $telepon, $alamat);
                        $stmt->execute();
                        $idSupplier = (int)$conn->insert_id;
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare(
                            "UPDATE supplier
                             SET nama_supplier = ?, telepon = ?, alamat = ?
                             WHERE id_supplier = ? AND status = 'aktif'"
                        );

                        if (!$stmt) {
                            throw new Exception($conn->error);
                        }

                        $stmt->bind_param("sssi", $nama, $telepon, $alamat, $id);
                        $stmt->execute();

                        if ($stmt->affected_rows === 0) {
                            $cek = $conn->prepare(
                                "SELECT id_supplier FROM supplier
                                 WHERE id_supplier = ? AND status = 'aktif'"
                            );
                            $cek->bind_param("i", $id);
                            $cek->execute();
                            $ada = $cek->get_result()->num_rows > 0;
                            $cek->close();

                            if (!$ada) {
                                throw new Exception('Supplier tidak ditemukan atau sudah nonaktif.');
                            }
                        }

                        $stmt->close();
                        $idSupplier = $id;
                    }

                    /*
                     * Pastikan semua barang yang dipilih tersedia.
                     * Satu barang hanya boleh dimiliki satu supplier.
                     */
                    foreach ($barangDipilih as $idBarang) {
                        $cekBarang = $conn->prepare(
                            "SELECT id_barang FROM barang WHERE id_barang = ?"
                        );
                        $cekBarang->bind_param("i", $idBarang);
                        $cekBarang->execute();

                        if ($cekBarang->get_result()->num_rows === 0) {
                            $cekBarang->close();
                            throw new Exception('Ada barang yang tidak ditemukan.');
                        }
                        $cekBarang->close();

                        $cekRelasi = $conn->prepare(
                            "SELECT sb.id_supplier, s.nama_supplier
                             FROM supplier_barang sb
                             INNER JOIN supplier s
                                ON s.id_supplier = sb.id_supplier
                             WHERE sb.id_barang = ?
                             AND sb.id_supplier <> ?
                             LIMIT 1"
                        );

                        $cekRelasi->bind_param("ii", $idBarang, $idSupplier);
                        $cekRelasi->execute();
                        $relasi = $cekRelasi->get_result()->fetch_assoc();
                        $cekRelasi->close();

                        if ($relasi) {
                            throw new Exception(
                                'Ada barang yang sudah terhubung dengan supplier lain (' .
                                $relasi['nama_supplier'] .
                                '). Lepaskan hubungan sebelumnya terlebih dahulu.'
                            );
                        }
                    }

                    /*
                     * Saat edit, lepaskan hubungan lama milik supplier ini.
                     * Barang yang dipilih akan dihubungkan kembali.
                     */
                    if ($aksi === 'edit_supplier') {
                        $hapusRelasi = $conn->prepare(
                            "DELETE FROM supplier_barang WHERE id_supplier = ?"
                        );
                        $hapusRelasi->bind_param("i", $idSupplier);
                        $hapusRelasi->execute();
                        $hapusRelasi->close();
                    }

                    /*
                     * Simpan hubungan supplier dengan barang.
                     * harga_supplier diisi 0 sebagai nilai awal.
                     */
                    if (!empty($barangDipilih)) {
                        $insertRelasi = $conn->prepare(
                            "INSERT INTO supplier_barang
                             (id_supplier, id_barang, harga_supplier)
                             VALUES (?, ?, 0)"
                        );

                        if (!$insertRelasi) {
                            throw new Exception($conn->error);
                        }

                        foreach ($barangDipilih as $idBarang) {
                            $insertRelasi->bind_param("ii", $idSupplier, $idBarang);
                            $insertRelasi->execute();
                        }

                        $insertRelasi->close();
                    }

                    $conn->commit();

                    redirectPesan(
                        $aksi === 'tambah_supplier' ? 'tambah' : 'edit'
                    );

                } catch (Throwable $e) {
                    $conn->rollback();
                    $pesan = 'Gagal menyimpan supplier: ' . $e->getMessage();
                    $jenis_pesan = 'error';
                }
            }
        }

        /*
        |------------------------------------------------------------------
        | NONAKTIFKAN SUPPLIER
        |------------------------------------------------------------------
        */
        elseif ($aksi === 'hapus_supplier') {
            $id = (int)($_POST['id_supplier'] ?? 0);

            if ($id <= 0) {
                $pesan = 'Supplier tidak valid.';
                $jenis_pesan = 'error';
            } else {
                $stmt = $conn->prepare(
                    "UPDATE supplier
                     SET status = 'nonaktif'
                     WHERE id_supplier = ? AND status = 'aktif'"
                );

                $stmt->bind_param("i", $id);

                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $stmt->close();
                    redirectPesan('hapus');
                }

                $pesan = 'Supplier gagal dinonaktifkan atau sudah nonaktif.';
                $jenis_pesan = 'error';
                $stmt->close();
            }
        }

        /*
        |------------------------------------------------------------------
        | AKTIFKAN KEMBALI SUPPLIER
        |------------------------------------------------------------------
        */
        elseif ($aksi === 'aktifkan_supplier') {
            $id = (int)($_POST['id_supplier'] ?? 0);

            $stmt = $conn->prepare(
                "UPDATE supplier
                 SET status = 'aktif'
                 WHERE id_supplier = ?"
            );

            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $stmt->close();
                redirectPesan('aktif');
            }

            $pesan = 'Gagal mengaktifkan supplier.';
            $jenis_pesan = 'error';
            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA BARANG
|--------------------------------------------------------------------------
*/

$data_barang = [];

$qBarang = mysqli_query(
    $conn,
    "SELECT id_barang, kode_barang, nama_barang, stok,
            stok_minimum, satuan, harga_beli
     FROM barang
     ORDER BY nama_barang ASC"
);

if ($qBarang) {
    while ($row = mysqli_fetch_assoc($qBarang)) {
        $data_barang[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA SUPPLIER
|--------------------------------------------------------------------------
*/

$data_supplier = [];

$qSupplier = mysqli_query(
    $conn,
    "SELECT id_supplier, nama_supplier, telepon, alamat, status
     FROM supplier
     ORDER BY status ASC, nama_supplier ASC"
);

if ($qSupplier) {
    while ($row = mysqli_fetch_assoc($qSupplier)) {
        $data_supplier[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| AMBIL RELASI SUPPLIER DAN BARANG
|--------------------------------------------------------------------------
*/

$supplier_per_barang = [];
$barang_per_supplier = [];

$qRelasi = mysqli_query(
    $conn,
    "SELECT sb.id_barang, sb.id_supplier, sb.harga_supplier,
            s.nama_supplier, s.telepon, s.status
     FROM supplier_barang sb
     INNER JOIN supplier s ON s.id_supplier = sb.id_supplier"
);

if ($qRelasi) {
    while ($row = mysqli_fetch_assoc($qRelasi)) {
        $idBarang = (int)$row['id_barang'];
        $idSupplier = (int)$row['id_supplier'];

        $supplier_per_barang[$idBarang] = $row;
        $barang_per_supplier[$idSupplier][] = $idBarang;
    }
}

/*
|--------------------------------------------------------------------------
| STATISTIK
|--------------------------------------------------------------------------
*/

$total_barang = count($data_barang);
$total_supplier = count($data_supplier);
$total_stok_rendah = 0;
$total_stok_habis = 0;
$data_stok_perlu_restock = [];

foreach ($data_barang as $barang) {
    $stok = (int)$barang['stok'];
    $minimum = (int)$barang['stok_minimum'];

    if ($stok <= $minimum) {
        $total_stok_rendah++;
        $data_stok_perlu_restock[] = $barang;
    }

    if ($stok <= 0) {
        $total_stok_habis++;
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stok & Supplier | Toko Malaya</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
    --primary:#0879e8;
    --background:#f3f6fb;
    --text:#172b45;
    --muted:#64748b;
    --border:#dfe7f1;
}
body{
    min-height:100vh;
    background:var(--background);
    color:var(--text);
    font-family:Arial,Helvetica,sans-serif;
}
body.menu-open{overflow:hidden}
button,input,textarea{font:inherit}
button{cursor:pointer}
a{color:inherit;text-decoration:none}

/* NAVBAR */
.topbar{
    height:72px;padding:0 24px;display:flex;align-items:center;
    background:#fff;border-bottom:1px solid #e5e7eb;
    position:sticky;top:0;z-index:900;
}
.topbar-left{display:flex;align-items:center;gap:14px}
.menu-button{
    width:42px;height:42px;border:0;border-radius:10px;
    background:#f1f5f9;color:#1e293b;font-size:23px;
}
.brand{display:flex;align-items:center;gap:10px}
.brand-logo{
    width:44px;height:44px;border-radius:10px;
    background:#1677d2;overflow:hidden;
}
.brand-logo img,.drawer-logo img{
    width:100%;height:100%;object-fit:contain;
}
.brand-text,.top-user-info,.drawer-brand-text,.drawer-user-info{
    display:flex;flex-direction:column;gap:4px;
}
.brand-text strong{font-size:17px}
.brand-text span,.top-user-info span,.drawer-brand-text span,
.drawer-user-info span{font-size:12px;color:#64748b}
.topbar-spacer{flex:1}
.top-user{
    display:flex;align-items:center;gap:10px;padding:6px 10px;
    border:1px solid #e5e7eb;border-radius:12px;
}
.avatar,.drawer-avatar{
    width:40px;height:40px;border-radius:50%;
    background:#e0efff;color:#1265a7;
    display:flex;align-items:center;justify-content:center;font-weight:800;
}
.top-user-info strong{font-size:13px}

/* DRAWER */
.menu-overlay{
    position:fixed;inset:0;background:rgba(15,23,42,.45);
    z-index:1000;opacity:0;visibility:hidden;pointer-events:none;
    transition:.25s;
}
.menu-overlay.show{opacity:1;visibility:visible;pointer-events:auto}
.drawer{
    position:fixed;top:0;left:0;width:335px;max-width:90vw;
    height:100vh;height:100dvh;background:#fff;z-index:1100;
    transform:translateX(-100%);transition:transform .28s ease;
    display:flex;flex-direction:column;
    box-shadow:8px 0 30px rgba(15,23,42,.12);
}
.drawer.show{transform:translateX(0)}
.drawer-header{
    min-height:88px;padding:18px 20px;border-bottom:1px solid #e5e7eb;
    display:flex;align-items:center;justify-content:space-between;
}
.drawer-brand{display:flex;align-items:center;gap:12px}
.drawer-logo{
    width:46px;height:46px;border-radius:10px;
    background:#1677d2;overflow:hidden;
}
.drawer-brand-text strong{font-size:18px}
.drawer-close{
    width:40px;height:40px;border:0;border-radius:10px;
    background:#f1f5f9;font-size:25px;
}
.drawer-user{
    margin:20px;padding:14px;border:1px solid #dbe4ee;
    background:#f8fafc;border-radius:14px;
    display:flex;align-items:center;gap:12px;
}
.drawer-avatar{width:48px;height:48px}
.drawer-user-info strong{font-size:15px}
.drawer-menu{padding:0 14px 20px;overflow-y:auto;flex:1}
.menu-label{
    padding:10px 12px;margin-bottom:7px;font-size:11px;
    font-weight:800;color:#94a3b8;letter-spacing:.5px;
}
.menu-link{
    min-height:48px;display:flex;align-items:center;gap:14px;
    padding:0 14px;margin-bottom:5px;border-radius:10px;
    color:#475569;font-size:15px;font-weight:700;
}
.menu-link:hover{background:#f1f5f9;color:#0879e8}
.menu-link.active{background:#e5f1ff;color:#0879e8;position:relative}
.menu-link.active:before{
    content:"";position:absolute;left:0;top:8px;bottom:8px;
    width:3px;background:#0879e8;border-radius:0 4px 4px 0;
}
.menu-icon{width:25px;min-width:25px;text-align:center;font-size:19px}
.drawer-footer{border-top:1px solid #e5e7eb;padding:14px}
.logout-link{
    min-height:48px;padding:0 14px;display:flex;align-items:center;
    gap:14px;border-radius:10px;color:#ef4444;font-size:15px;font-weight:700;
}
.logout-link:hover{background:#fef2f2}

/* KONTEN */
.container{
    width:100%;
    max-width:1323px;
    margin:0 auto;
    padding:30px 24px 55px;
}
.page-heading{
    display:flex;align-items:flex-start;justify-content:space-between;
    gap:15px;margin-bottom:25px;
}
.page-heading h1{font-size:26px;font-weight:800}
.page-heading p{margin-top:8px;color:var(--muted);font-size:14px;line-height:1.5}
.stats-grid{
    display:grid;grid-template-columns:repeat(3,minmax(0,1fr));
    gap:18px;margin-bottom:25px;
}
.stat-card{
    display:flex;align-items:center;gap:17px;padding:22px;
    border:1px solid var(--border);border-radius:15px;background:#fff;
}
.stat-icon{
    width:54px;height:54px;flex-shrink:0;border-radius:14px;
    display:flex;align-items:center;justify-content:center;
    background:#eff6ff;color:#2563eb;font-size:22px;
}
.stat-icon.orange{background:#fff7ed;color:#ea580c}
.stat-icon.red{background:#fef2f2;color:#dc2626}
.stat-info span{display:block;margin-bottom:8px;color:var(--muted);font-size:13px}
.stat-info strong{font-size:27px;font-weight:800}
.panel{
    margin-bottom:25px;overflow:hidden;border:1px solid var(--border);
    border-radius:15px;background:#fff;
}
.panel-header{
    display:flex;align-items:center;justify-content:space-between;
    gap:15px;padding:20px 22px;border-bottom:1px solid var(--border);
}
.panel-header h2{font-size:17px}
.panel-header p{margin-top:5px;color:var(--muted);font-size:12px}
.table-wrap{width:100%;overflow-x:auto}
table{width:100%;border-collapse:collapse;white-space:nowrap}
th,td{padding:14px 15px;border-bottom:1px solid #edf0f4;text-align:left;font-size:13px}
th{
    background:#f8fafc;color:#64748b;font-size:11px;
    text-transform:uppercase;letter-spacing:.3px;
}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover{background:#fafafa}
.badge{
    display:inline-block;padding:6px 9px;border-radius:7px;
    font-size:11px;font-weight:700;
}
.badge-normal{background:#dcfce7;color:#166534}
.badge-low{background:#fee2e2;color:#991b1b}
.badge-active{background:#dcfce7;color:#166534}
.badge-inactive{background:#f1f5f9;color:#475569}
.btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    min-height:38px;padding:9px 13px;border:0;border-radius:8px;
    font-size:12px;font-weight:700;cursor:pointer;
}
.btn-primary{background:#2563eb;color:#fff}
.btn-primary:hover{background:#1d4ed8}
.btn-light{background:#fff;border:1px solid #dbe3ed;color:#334155}
.btn-danger{background:#fef2f2;color:#b91c1c}
.btn-success{background:#dcfce7;color:#166534}
.btn-whatsapp{background:#16a34a;color:#fff}
.actions{display:flex;align-items:center;gap:7px}
.actions form{margin:0}
.alert{
    padding:13px 16px;margin-bottom:20px;border-radius:10px;
    font-size:13px;line-height:1.5;
}
.alert.success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.alert.error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.empty-state{padding:35px 15px;color:var(--muted);text-align:center;font-size:13px}
.empty-state i{display:block;margin-bottom:12px;color:#9ca3af;font-size:28px}

/* RESTOCK */
.restock-panel{
    margin-bottom:25px;padding:23px;border:1px solid #f6d98a;
    border-radius:16px;background:#fffbeb;
}
.restock-heading{display:flex;align-items:flex-start;gap:13px;margin-bottom:18px}
.restock-symbol{
    width:46px;height:46px;flex-shrink:0;border-radius:13px;
    display:flex;align-items:center;justify-content:center;
    background:#fef3c7;color:#b45309;font-size:20px;
}
.restock-heading h2{font-size:18px;color:#92400e;margin:2px 0 7px}
.restock-heading p{font-size:13px;line-height:1.6;color:#78350f}
.restock-table-wrap{
    overflow-x:auto;background:rgba(255,255,255,.7);
    border:1px solid #f5e6bd;border-radius:10px;
}
.restock-table th{background:#fff7df}
.restock-table td{vertical-align:middle}
.restock-actions{display:flex;align-items:center;gap:9px}
.whatsapp-missing{
    display:inline-block;max-width:250px;color:#92400e;
    font-size:12px;line-height:1.5;white-space:normal;
}
.restock-note{margin-top:13px;color:#854d0e;font-size:12px;line-height:1.6}

/* MODAL */
.modal-overlay{
    position:fixed;inset:0;z-index:2000;display:none;
    align-items:center;justify-content:center;padding:18px;
    background:rgba(15,23,42,.55);
}
.modal-overlay.show{display:flex}
.modal{
    width:100%;max-width:560px;max-height:90vh;overflow-y:auto;
    border-radius:16px;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.2);
}
.modal-header{
    display:flex;align-items:center;justify-content:space-between;
    padding:21px 23px;border-bottom:1px solid var(--border);
}
.modal-header h2{font-size:18px}
.modal-close{
    width:34px;height:34px;border:0;border-radius:8px;
    background:#f3f4f6;color:#374151;
}
.form-body{display:flex;flex-direction:column;gap:16px;padding:23px}
.form-group{display:flex;flex-direction:column;gap:8px}
.form-group label{color:#374151;font-size:13px;font-weight:700}
.form-group input,.form-group textarea{
    width:100%;padding:12px 13px;border:1px solid #d1d5db;
    border-radius:9px;outline:none;background:#fff;color:#111827;font-size:13px;
}
.form-group input:focus,.form-group textarea:focus{
    border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1);
}
.form-group textarea{min-height:85px;resize:vertical}
.product-picker{
    max-height:240px;overflow-y:auto;border:1px solid #dbe3ed;
    border-radius:9px;padding:8px;
}
.product-option{
    display:flex;align-items:center;gap:10px;padding:10px;
    border-bottom:1px solid #f1f5f9;font-size:13px;
}
.product-option:last-child{border-bottom:0}
.product-option input{width:17px;height:17px}
.product-option small{display:block;color:#64748b;margin-top:3px}
.search-product{
    width:100%;padding:10px 12px;border:1px solid #d1d5db;
    border-radius:8px;margin-bottom:8px;
}
.picker-note{font-size:11px;color:#64748b;line-height:1.5}
.modal-actions{display:flex;justify-content:flex-end;gap:10px;padding-top:4px}

@media(max-width:850px){
    .container{padding:20px}
    .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:560px){
    .topbar{padding:0 14px}
    .brand-text,.top-user-info{display:none}
    .top-user{border:0;padding:4px}
    .container{padding:17px 13px}
    .stats-grid{grid-template-columns:1fr;gap:12px}
    .stat-card{padding:18px}
    .page-heading{flex-direction:column}
    .panel-header{padding:16px;align-items:flex-start}
    .restock-panel{padding:16px}
    .restock-heading{gap:10px}
    .restock-actions{align-items:flex-start;flex-direction:column}
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
        <button type="button" class="drawer-close" onclick="closeMenu()">×</button>
    </div>

    <div class="drawer-user">
        <div class="drawer-avatar"><?= e(strtoupper(substr($nama_user, 0, 1))) ?></div>
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

        <a href="stok.php" class="menu-link active">
            <span class="menu-icon">📦</span>Stok & Supplier
        </a>

        <a href="pengeluaran.php" class="menu-link">
            <span class="menu-icon">🧾</span>Pengeluaran
        </a>

        <a href="laporan.php" class="menu-link">
            <span class="menu-icon">📊</span>Laporan
        </a>

        <div class="menu-label">KEPEGAWAIAN</div>

        <a href="jadwal_karyawan.php" class="menu-link">
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
           onclick="return confirm('Apakah kamu yakin ingin keluar?')">
            <span class="menu-icon">🚪</span>Keluar
        </a>
    </div>
</aside>

<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="menu-button" id="menuButton"
                onclick="toggleMenu()" aria-label="Buka menu">☰</button>

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

    <div class="topbar-spacer"></div>

    <div class="top-user">
        <div class="avatar"><?= e(strtoupper(substr($nama_user, 0, 1))) ?></div>
        <div class="top-user-info">
            <strong><?= e($nama_user) ?></strong>
            <span>Admin / Pemilik</span>
        </div>
    </div>
</header>

<main class="container">

    <div class="page-heading">
        <div>
            <h1>Stok & Supplier</h1>
            <p>Pantau persediaan barang dan kelola informasi pemasok toko.</p>
        </div>

        <button type="button" class="btn btn-primary" onclick="openTambahSupplier()">
            <i class="fa-solid fa-plus"></i> Tambah Supplier
        </button>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= e($jenis_pesan) ?>"><?= e($pesan) ?></div>
    <?php endif; ?>

    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="stat-info">
                <span>Jenis Barang</span>
                <strong><?= number_format($total_barang, 0, ',', '.') ?></strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange"><i class="fa-solid fa-truck-field"></i></div>
            <div class="stat-info">
                <span>Total Supplier</span>
                <strong><?= number_format($total_supplier, 0, ',', '.') ?></strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="stat-info">
                <span>Stok Habis / Menipis</span>
                <strong><?= number_format($total_stok_rendah, 0, ',', '.') ?></strong>
            </div>
        </div>
    </section>

    <!-- RESTOCK -->
    <section class="restock-panel">
        <div class="restock-heading">
            <div class="restock-symbol"><i class="fa-solid fa-box-open"></i></div>
            <div>
                <h2>Stok Habis atau Menipis?</h2>
                <p>Hubungi supplier aktif yang terhubung dengan barang tersebut.</p>
            </div>
        </div>

        <?php if ($data_stok_perlu_restock): ?>
            <div class="restock-table-wrap">
                <table class="restock-table">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Stok Saat Ini</th>
                            <th>Stok Minimum</th>
                            <th>Status</th>
                            <th>Supplier / WhatsApp</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($data_stok_perlu_restock as $barang): ?>
                        <?php
                        $stok = (int)$barang['stok'];
                        $minimum = (int)$barang['stok_minimum'];
                        $supplier = $supplier_per_barang[(int)$barang['id_barang']] ?? null;

                        $nomor_wa = ($supplier && $supplier['status'] === 'aktif')
                            ? wa_number($supplier['telepon'])
                            : '';

                        $link_wa = '';

                        if ($nomor_wa !== '') {
                            $pesanWA =
                                "Halo " . $supplier['nama_supplier'] .
                                ", saya dari Toko Malaya ingin melakukan pemesanan ulang.\n\n" .
                                "Barang: " . $barang['nama_barang'] . "\n" .
                                "Kode: " . $barang['kode_barang'] . "\n" .
                                "Stok saat ini: " . $stok . " " . $barang['satuan'] . "\n" .
                                "Stok minimum: " . $minimum . " " . $barang['satuan'] . "\n\n" .
                                "Mohon informasi ketersediaan dan harga terbaru. Terima kasih.";

                            $link_wa = "https://wa.me/" . $nomor_wa . "?text=" . rawurlencode($pesanWA);
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($barang['nama_barang']) ?></strong><br>
                                <small><?= e($barang['kode_barang']) ?></small>
                            </td>
                            <td><?= number_format($stok, 0, ',', '.') ?> <?= e($barang['satuan']) ?></td>
                            <td><?= number_format($minimum, 0, ',', '.') ?> <?= e($barang['satuan']) ?></td>
                            <td>
                                <span class="badge badge-low">
                                    <?= $stok <= 0 ? 'STOK HABIS' : 'STOK MENIPIS' ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!$supplier): ?>
                                    <span class="whatsapp-missing">Barang belum memiliki supplier.</span>
                                <?php elseif ($supplier['status'] !== 'aktif'): ?>
                                    <span class="whatsapp-missing">
                                        Supplier <?= e($supplier['nama_supplier']) ?> sedang nonaktif.
                                    </span>
                                <?php elseif (!$nomor_wa): ?>
                                    <span class="whatsapp-missing">
                                        <?= e($supplier['nama_supplier']) ?> belum memiliki nomor WhatsApp.
                                    </span>
                                <?php else: ?>
                                    <div class="restock-actions">
                                        <span><?= e($supplier['nama_supplier']) ?></span>
                                        <a class="btn btn-whatsapp" href="<?= e($link_wa) ?>"
                                           target="_blank" rel="noopener noreferrer">
                                            <i class="fa-brands fa-whatsapp"></i> Hubungi WhatsApp
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="restock-note">
                <i class="fa-solid fa-circle-info"></i>
                WhatsApp akan membuka pesan yang sudah disiapkan. Periksa kembali pesan sebelum mengirimkannya.
            </p>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-circle-check"></i>
                Semua stok berada di atas batas minimum.
            </div>
        <?php endif; ?>
    </section>

    <!-- DAFTAR STOK -->
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Daftar Stok Barang</h2>
                <p>Informasi persediaan barang saat ini.</p>
            </div>
        </div>

        <div class="table-wrap">
            <?php if ($data_barang): ?>
                <table>
                    <thead>
                        <tr>
                            <th>No.</th><th>Kode Barang</th><th>Nama Barang</th>
                            <th>Stok</th><th>Stok Minimum</th><th>Satuan</th>
                            <th>Harga Beli</th><th>Supplier</th><th>Status Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($data_barang as $index => $barang): ?>
                        <?php
                        $stok = (int)$barang['stok'];
                        $minimum = (int)$barang['stok_minimum'];
                        $supplierBarang = $supplier_per_barang[(int)$barang['id_barang']] ?? null;
                        ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= e($barang['kode_barang']) ?></td>
                            <td><strong><?= e($barang['nama_barang']) ?></strong></td>
                            <td><?= number_format($stok, 0, ',', '.') ?></td>
                            <td><?= number_format($minimum, 0, ',', '.') ?></td>
                            <td><?= e($barang['satuan']) ?></td>
                            <td><?= rupiah($barang['harga_beli']) ?></td>
                            <td>
                                <?php if ($supplierBarang): ?>
                                    <?= e($supplierBarang['nama_supplier']) ?>
                                    <?php if ($supplierBarang['status'] !== 'aktif'): ?>
                                        <span class="badge badge-inactive">Nonaktif</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($stok <= 0): ?>
                                    <span class="badge badge-low">Stok Habis</span>
                                <?php elseif ($stok <= $minimum): ?>
                                    <span class="badge badge-low">Stok Menipis</span>
                                <?php else: ?>
                                    <span class="badge badge-normal">Stok Aman</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><i class="fa-solid fa-box-open"></i>Belum ada data barang.</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- DATA SUPPLIER -->
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Data Supplier</h2>
                <p>Daftar pemasok dan barang yang disuplai.</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="openTambahSupplier()">
                <i class="fa-solid fa-plus"></i> Tambah Supplier
            </button>
        </div>

        <div class="table-wrap">
            <?php if ($data_supplier): ?>
                <table>
                    <thead>
                        <tr>
                            <th>No.</th><th>Nama Supplier</th><th>Telepon</th>
                            <th>Alamat</th><th>Barang yang Disuplai</th>
                            <th>Status</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($data_supplier as $index => $supplier): ?>
                        <?php
                        $idSupplier = (int)$supplier['id_supplier'];
                        $barangSupplier = $barang_per_supplier[$idSupplier] ?? [];
                        $namaBarangSupplier = [];

                        foreach ($data_barang as $b) {
                            if (in_array((int)$b['id_barang'], $barangSupplier, true)) {
                                $namaBarangSupplier[] = $b['nama_barang'];
                            }
                        }
                        ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= e($supplier['nama_supplier']) ?></strong></td>
                            <td><?= e($supplier['telepon'] ?: '-') ?></td>
                            <td><?= e($supplier['alamat'] ?: '-') ?></td>
                            <td style="white-space:normal;min-width:180px">
                                <?= $namaBarangSupplier
                                    ? e(implode(', ', $namaBarangSupplier))
                                    : '<span style="color:#94a3b8">Belum ada barang</span>' ?>
                            </td>
                            <td>
                                <?php if ($supplier['status'] === 'aktif'): ?>
                                    <span class="badge badge-active">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <?php if ($supplier['status'] === 'aktif'): ?>
                                        <button type="button" class="btn btn-light"
                                            onclick='editSupplier(<?= json_encode(
                                                $supplier,
                                                JSON_HEX_TAG | JSON_HEX_APOS |
                                                JSON_HEX_QUOT | JSON_HEX_AMP
                                            ) ?>, <?= json_encode($barangSupplier) ?>)'>
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>

                                        <form method="POST" onsubmit="return confirm('Nonaktifkan supplier ini? Riwayat pembelian tidak akan dihapus.')">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="aksi" value="hapus_supplier">
                                            <input type="hidden" name="id_supplier" value="<?= $idSupplier ?>">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fa-solid fa-ban"></i> Nonaktifkan
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" onsubmit="return confirm('Aktifkan kembali supplier ini?')">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="aksi" value="aktifkan_supplier">
                                            <input type="hidden" name="id_supplier" value="<?= $idSupplier ?>">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fa-solid fa-check"></i> Aktifkan
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-truck-field"></i>
                    Belum ada supplier. Silakan tambahkan supplier terlebih dahulu.
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>

<!-- MODAL TAMBAH SUPPLIER -->
<div class="modal-overlay" id="modalTambahSupplier">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Tambah Supplier</h2>
            <button type="button" class="modal-close" onclick="closeModal('modalTambahSupplier')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="stok.php" class="form-body">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="aksi" value="tambah_supplier">

            <div class="form-group">
                <label>Nama Supplier *</label>
                <input type="text" name="nama_supplier" maxlength="100"
                       placeholder="Masukkan nama supplier" required>
            </div>

            <div class="form-group">
                <label>Nomor Telepon / WhatsApp</label>
                <input type="text" name="telepon" maxlength="20"
                       placeholder="Contoh: 081234567890">
            </div>

            <div class="form-group">
                <label>Alamat</label>
                <textarea name="alamat" placeholder="Masukkan alamat supplier"></textarea>
            </div>

            <div class="form-group">
                <label>Pilih Barang yang Disuplai</label>
                <input type="text" class="search-product"
                       placeholder="Cari nama atau kode barang..."
                       oninput="filterBarang(this, 'pilihanBarangTambah')">

                <div class="product-picker" id="pilihanBarangTambah">
                    <?php foreach ($data_barang as $barang): ?>
                        <?php
                        $idBarang = (int)$barang['id_barang'];
                        $pemilikBarang = $supplier_per_barang[$idBarang] ?? null;
                        $terkunci = $pemilikBarang && $pemilikBarang['status'] === 'aktif';
                        ?>
                        <label class="product-option"
                               data-search="<?= e(strtolower($barang['nama_barang'] . ' ' . $barang['kode_barang'])) ?>">
                            <input type="checkbox" name="barang[]"
                                   value="<?= $idBarang ?>" <?= $terkunci ? 'disabled' : '' ?>>
                            <span>
                                <?= e($barang['nama_barang']) ?>
                                <small>
                                    <?= e($barang['kode_barang']) ?>
                                    <?php if ($terkunci): ?>
                                        - Sudah terhubung dengan <?= e($pemilikBarang['nama_supplier']) ?>
                                    <?php endif; ?>
                                </small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="picker-note">
                    Setiap barang hanya dapat memiliki satu supplier aktif. Barang yang sudah terhubung tidak dapat dipilih lagi.
                </p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-light" onclick="closeModal('modalTambahSupplier')">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT SUPPLIER -->
<div class="modal-overlay" id="modalEditSupplier">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Edit Supplier</h2>
            <button type="button" class="modal-close" onclick="closeModal('modalEditSupplier')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="stok.php" class="form-body">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="aksi" value="edit_supplier">
            <input type="hidden" name="id_supplier" id="editIdSupplier">

            <div class="form-group">
                <label>Nama Supplier *</label>
                <input type="text" name="nama_supplier" id="editNamaSupplier"
                       maxlength="100" required>
            </div>

            <div class="form-group">
                <label>Nomor Telepon / WhatsApp</label>
                <input type="text" name="telepon" id="editTeleponSupplier" maxlength="20">
            </div>

            <div class="form-group">
                <label>Alamat</label>
                <textarea name="alamat" id="editAlamatSupplier"></textarea>
            </div>

            <div class="form-group">
                <label>Barang yang Disuplai</label>
                <input type="text" class="search-product"
                       placeholder="Cari nama atau kode barang..."
                       oninput="filterBarang(this, 'pilihanBarangEdit')">

                <div class="product-picker" id="pilihanBarangEdit">
                    <?php foreach ($data_barang as $barang): ?>
                        <?php
                        $idBarang = (int)$barang['id_barang'];
                        $pemilikBarang = $supplier_per_barang[$idBarang] ?? null;
                        ?>
                        <label class="product-option"
                               data-search="<?= e(strtolower($barang['nama_barang'] . ' ' . $barang['kode_barang'])) ?>"
                               data-product-id="<?= $idBarang ?>">
                            <input type="checkbox" name="barang[]"
                                   value="<?= $idBarang ?>">
                            <span>
                                <?= e($barang['nama_barang']) ?>
                                <small>
                                    <?= e($barang['kode_barang']) ?>
                                    <?php if ($pemilikBarang): ?>
                                        - Supplier: <?= e($pemilikBarang['nama_supplier']) ?>
                                    <?php endif; ?>
                                </small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="picker-note">
                    Centang barang yang ingin dipasok oleh supplier ini. Barang yang sudah dimiliki supplier lain tidak bisa dipilih.
                </p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-light" onclick="closeModal('modalEditSupplier')">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openMenu() {
    document.getElementById('drawer').classList.add('show');
    document.getElementById('menuOverlay').classList.add('show');
    document.body.classList.add('menu-open');
    document.getElementById('menuButton').setAttribute('aria-expanded', 'true');
}

function closeMenu() {
    document.getElementById('drawer').classList.remove('show');
    document.getElementById('menuOverlay').classList.remove('show');
    document.body.classList.remove('menu-open');
    document.getElementById('menuButton').setAttribute('aria-expanded', 'false');
}

function toggleMenu() {
    if (document.getElementById('drawer').classList.contains('show')) {
        closeMenu();
    } else {
        openMenu();
    }
}

function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function openTambahSupplier() {
    const modal = document.getElementById('modalTambahSupplier');
    modal.querySelector('form').reset();
    modal.querySelectorAll('.product-option').forEach(item => item.style.display = '');
    openModal('modalTambahSupplier');
}

function filterBarang(input, containerId) {
    const keyword = input.value.toLowerCase();
    const container = document.getElementById(containerId);

    container.querySelectorAll('.product-option').forEach(item => {
        const text = item.dataset.search || '';
        item.style.display = text.includes(keyword) ? 'flex' : 'none';
    });
}

function editSupplier(supplier, barangTerpilih) {
    document.getElementById('editIdSupplier').value = supplier.id_supplier || '';
    document.getElementById('editNamaSupplier').value = supplier.nama_supplier || '';
    document.getElementById('editTeleponSupplier').value = supplier.telepon || '';
    document.getElementById('editAlamatSupplier').value = supplier.alamat || '';

    const modal = document.getElementById('modalEditSupplier');

    modal.querySelectorAll('.product-option').forEach(item => {
        const checkbox = item.querySelector('input[type="checkbox"]');
        const idBarang = parseInt(checkbox.value);
        const pemilikId = <?= json_encode(array_map(
            fn($row) => (int)$row['id_supplier'],
            $supplier_per_barang
        )) ?>;

        const idSupplier = parseInt(supplier.id_supplier);
        const barangIniMilikSupplier = barangTerpilih.map(Number).includes(idBarang);

        checkbox.checked = barangIniMilikSupplier;

        // Barang milik supplier lain dikunci.
        const pemilik = <?= json_encode(array_map(
            fn($row) => (int)$row['id_supplier'],
            $supplier_per_barang
        )) ?>;

        const idPemilik = pemilik[idBarang] || 0;
        checkbox.disabled = idPemilik !== 0 && idPemilik !== idSupplier;
        item.style.display = 'flex';
    });

    modal.querySelectorAll('.search-product').forEach(input => input.value = '');
    openModal('modalEditSupplier');
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(event) {
        if (event.target === overlay) {
            overlay.classList.remove('show');
        }
    });
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeMenu();
        document.querySelectorAll('.modal-overlay.show').forEach(modal => {
            modal.classList.remove('show');
        });
    }
});
</script>

</body>
</html>
