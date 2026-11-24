<?php
session_start();
require_once __DIR__ . "/../config/database.php";


if (PHP_SAPI === 'cli') {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        fwrite(STDERR, "Koneksi database tidak tersedia.\n");
        exit(1);
    }

    $col = mysqli_query($conn, "SHOW COLUMNS FROM pesanan LIKE 'tanggal_dikirim'");
    if (!$col || mysqli_num_rows($col) === 0) {
        $alter = mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN tanggal_dikirim DATETIME NULL DEFAULT NULL AFTER status");
        if (!$alter) {
            fwrite(STDERR, "Gagal membuat kolom tanggal_dikirim. Periksa izin ALTER TABLE.\n");
            exit(1);
        }
    }

    $sql_auto = "UPDATE pesanan
                 SET status = 'selesai'
                 WHERE status = 'dikirim'
                   AND tanggal_dikirim IS NOT NULL
                   AND tanggal_dikirim <= DATE_SUB(NOW(), INTERVAL 2 HOUR)";
    if (!mysqli_query($conn, $sql_auto)) {
        fwrite(STDERR, "Gagal memperbarui status pesanan.\n");
        exit(1);
    }

    echo "Pesanan otomatis selesai: " . mysqli_affected_rows($conn) . "\n";
    exit(0);
}

if (!isset($_SESSION['id_user'], $_SESSION['role']) || $_SESSION['role'] !== 'kasir') {
    header("Location: ../auth/login.php");
    exit;
}

$nama_user = $_SESSION['nama'] ?? 'Kasir';

function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}
function status_label($status) {
    $labels = [
        'menunggu' => 'Menunggu',
        'diproses' => 'Diproses',
        'dikirim' => 'Dikirim',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan'
    ];
    return $labels[$status] ?? ucfirst((string)$status);
}
function status_class($status) {
    $classes = [
        'menunggu' => 'status-menunggu',
        'diproses' => 'status-diproses',
        'dikirim' => 'status-dikirim',
        'selesai' => 'status-selesai',
        'dibatalkan' => 'status-dibatalkan'
    ];
    return $classes[$status] ?? 'status-menunggu';
}
function has_column($conn, $table, $column) {
    // Nama tabel/kolom berasal dari konstanta internal di bawah, bukan input pengguna.
    $table = str_replace('`', '', $table);
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $result && mysqli_num_rows($result) > 0;
}

$error = '';
$success = '';
$detail_has_catatan = has_column($conn, 'detail_pesanan', 'catatan');
$has_status_pembayaran = has_column($conn, 'pesanan', 'status_pembayaran');
$has_bukti_pembayaran = has_column($conn, 'pesanan', 'bukti_pembayaran');
$has_metode_pembayaran = has_column($conn, 'pesanan', 'metode_pembayaran');
$has_tanggal_dikirim = has_column($conn, 'pesanan', 'tanggal_dikirim');

// Simpan waktu mulai pengiriman agar hitungan 2 jam akurat.
// Jika kolom belum ada, coba buat otomatis; akun database perlu izin ALTER TABLE.
if (!$has_tanggal_dikirim) {
    @mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN tanggal_dikirim DATETIME NULL DEFAULT NULL AFTER status");
    $has_tanggal_dikirim = has_column($conn, 'pesanan', 'tanggal_dikirim');
}

// Pengaman tambahan: pesanan yang sudah dikirim otomatis selesai setelah 2 jam.
// Untuk berjalan meski halaman tidak dibuka, jadwalkan file ini sendiri via Cron Job (PHP CLI).
if ($has_tanggal_dikirim) {
    @mysqli_query($conn, "UPDATE pesanan SET status = 'selesai' WHERE status = 'dikirim' AND tanggal_dikirim IS NOT NULL AND tanggal_dikirim <= DATE_SUB(NOW(), INTERVAL 2 HOUR)");
}

/* Verifikasi QRIS */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verifikasi_pembayaran'])) {
    $id_verifikasi = (int)($_POST['id_pesanan'] ?? 0);
    $aksi = (string)($_POST['aksi_pembayaran'] ?? '');

    if (!$has_status_pembayaran || !$has_bukti_pembayaran || !$has_metode_pembayaran) {
        $error = "Kolom QRIS belum lengkap. Pastikan kolom metode_pembayaran, status_pembayaran, dan bukti_pembayaran tersedia di tabel pesanan.";
    } elseif ($id_verifikasi < 1 || !in_array($aksi, ['setujui', 'tolak'], true)) {
        $error = "Permintaan verifikasi tidak valid.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT metode_pembayaran, status_pembayaran, bukti_pembayaran FROM pesanan WHERE id_pesanan = ? LIMIT 1");
        if (!$stmt) {
            $error = "Gagal menyiapkan pemeriksaan pembayaran.";
        } else {
            mysqli_stmt_bind_param($stmt, "i", $id_verifikasi);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $pay = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$pay) {
                $error = "Pesanan tidak ditemukan.";
            } elseif (strtoupper((string)$pay['metode_pembayaran']) !== 'QRIS') {
                $error = "Verifikasi ini hanya berlaku untuk pembayaran QRIS.";
            } elseif ($aksi === 'setujui' && trim((string)$pay['bukti_pembayaran']) === '') {
                $error = "Bukti pembayaran belum diunggah pelanggan.";
            } elseif (!in_array((string)$pay['status_pembayaran'], ['menunggu_verifikasi', 'ditolak'], true)) {
                $error = "Pembayaran sudah diproses sebelumnya.";
            } else {
                $status_baru = $aksi === 'setujui' ? 'terverifikasi' : 'ditolak';
                $up = mysqli_prepare($conn, "UPDATE pesanan SET status_pembayaran = ? WHERE id_pesanan = ? AND status_pembayaran IN ('menunggu_verifikasi','ditolak')");
                if (!$up) {
                    $error = "Gagal menyiapkan perubahan status pembayaran.";
                } else {
                    mysqli_stmt_bind_param($up, "si", $status_baru, $id_verifikasi);
                    if (mysqli_stmt_execute($up) && mysqli_stmt_affected_rows($up) === 1) {
                        $nomor = str_pad((string)$id_verifikasi, 5, '0', STR_PAD_LEFT);
                        $success = $aksi === 'setujui'
                            ? "Pembayaran QRIS pesanan #{$nomor} disetujui. Pastikan dana benar-benar masuk sebelum menyetujui."
                            : "Bukti QRIS pesanan #{$nomor} ditolak. Minta pelanggan mengunggah bukti yang benar.";
                    } else {
                        $error = "Status pembayaran tidak berubah. Muat ulang halaman dan periksa statusnya.";
                    }
                    mysqli_stmt_close($up);
                }
            }
        }
    }
}

/* Update status pesanan */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id_pesanan_update = (int)($_POST['id_pesanan'] ?? 0);
    $status_baru = trim((string)($_POST['status_baru'] ?? ''));

    $status_valid = ['menunggu', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];
    if ($id_pesanan_update < 1) {
        $error = "Pesanan tidak valid.";
    } elseif (!in_array($status_baru, $status_valid, true)) {
        $error = "Status pesanan tidak valid.";
    } else {
        $payment_select = $has_status_pembayaran ? 'status_pembayaran' : "'' AS status_pembayaran";
        $method_select = $has_metode_pembayaran ? 'metode_pembayaran' : "'' AS metode_pembayaran";
        $stmt = mysqli_prepare($conn, "SELECT status, {$method_select}, {$payment_select} FROM pesanan WHERE id_pesanan = ? LIMIT 1");

        if (!$stmt) {
            $error = "Gagal memeriksa pesanan.";
        } else {
            mysqli_stmt_bind_param($stmt, "i", $id_pesanan_update);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $order_now = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$order_now) {
                $error = "Pesanan tidak ditemukan.";
            } else {
                $status_lama = (string)$order_now['status'];
                $is_qris = strtoupper((string)$order_now['metode_pembayaran']) === 'QRIS';
                if ($is_qris && in_array($status_baru, ['diproses', 'dikirim', 'selesai'], true) && (string)$order_now['status_pembayaran'] !== 'terverifikasi') {
                    $error = "Pesanan QRIS belum dapat diproses karena pembayaran belum terverifikasi kasir.";
                } else {
                    $urutan = ['menunggu' => 1, 'diproses' => 2, 'dikirim' => 3, 'selesai' => 4];
                    $boleh = false;
                    if ($status_baru === 'dibatalkan' && in_array($status_lama, ['menunggu', 'diproses'], true)) {
                        $boleh = true;
                    }
                    if (isset($urutan[$status_lama], $urutan[$status_baru]) && $urutan[$status_baru] === $urutan[$status_lama] + 1) {
                        $boleh = true;
                    }
                    if ($status_baru === $status_lama || in_array($status_lama, ['selesai', 'dibatalkan'], true)) {
                        $boleh = false;
                    }

                    if (!$boleh) {
                        $error = "Perubahan status tidak diperbolehkan. Status saat ini: " . status_label($status_lama) . ".";
                    } else {
                        if ($has_tanggal_dikirim && $status_baru === 'dikirim') {
                            $up = mysqli_prepare($conn, "UPDATE pesanan SET status = ?, tanggal_dikirim = NOW() WHERE id_pesanan = ? AND status = ?");
                        } else {
                            $up = mysqli_prepare($conn, "UPDATE pesanan SET status = ? WHERE id_pesanan = ? AND status = ?");
                        }
                        if (!$up) {
                            $error = "Status pesanan gagal diperbarui.";
                        } else {
                            mysqli_stmt_bind_param($up, "sis", $status_baru, $id_pesanan_update, $status_lama);
                            if (mysqli_stmt_execute($up) && mysqli_stmt_affected_rows($up) === 1) {
                                $success = "Status pesanan #" . str_pad((string)$id_pesanan_update, 5, '0', STR_PAD_LEFT) . " berhasil diubah menjadi " . status_label($status_baru) . ".";
                            } else {
                                $error = "Status pesanan gagal diperbarui atau statusnya tidak berubah.";
                            }
                            mysqli_stmt_close($up);
                        }
                    }
                }
            }
        }
    }
}

/* Filter status */
$filter_status = trim((string)($_GET['status'] ?? ''));
$status_filter_valid = ['', 'menunggu', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];
if (!in_array($filter_status, $status_filter_valid, true)) {
    $filter_status = '';
}

/* Daftar pesanan */
$pesanan = [];
$method_select = $has_metode_pembayaran ? 'p.metode_pembayaran' : "'' AS metode_pembayaran";
$payment_select = $has_status_pembayaran ? 'p.status_pembayaran' : "'' AS status_pembayaran";
$proof_select = $has_bukti_pembayaran ? 'p.bukti_pembayaran' : "'' AS bukti_pembayaran";
$catatan_select = $detail_has_catatan ? 'dp.catatan' : "'' AS catatan";

$sql = "
    SELECT p.id_pesanan, p.id_user, p.tanggal_pesanan, p.total_harga,
           p.alamat_pengiriman, {$method_select}, p.status,
           {$payment_select}, {$proof_select},
           u.nama, u.email,
           COUNT(DISTINCT dp.id_detail) AS jumlah_jenis_barang,
           COALESCE(SUM(dp.jumlah), 0) AS total_barang
    FROM pesanan p
    INNER JOIN users u ON p.id_user = u.id_user
    LEFT JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
";
if ($filter_status !== '') {
    $sql .= " WHERE p.status = ?";
}
$sql .= "
    GROUP BY p.id_pesanan, p.id_user, p.tanggal_pesanan, p.total_harga,
             p.alamat_pengiriman, p.status, u.nama, u.email
";
if ($has_metode_pembayaran) $sql .= ", p.metode_pembayaran";
if ($has_status_pembayaran) $sql .= ", p.status_pembayaran";
if ($has_bukti_pembayaran) $sql .= ", p.bukti_pembayaran";
$sql .= " ORDER BY p.id_pesanan DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    die("Gagal mengambil data pesanan: " . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8'));
}
if ($filter_status !== '') mysqli_stmt_bind_param($stmt, "s", $filter_status);
mysqli_stmt_execute($stmt);
$res_orders = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res_orders)) $pesanan[] = $row;
mysqli_stmt_close($stmt);

/* Detail barang hanya untuk pesanan yang tampil */
$detail_pesanan = [];
if ($pesanan) {
    $ids = array_map(static fn($o) => (int)$o['id_pesanan'], $pesanan);
    $ids = array_values(array_filter($ids, static fn($id) => $id > 0));
    if ($ids) {
        $id_list = implode(',', $ids);
        $catatan_select = $detail_has_catatan ? 'dp.catatan' : "'' AS catatan";
        $sql_detail = "
            SELECT dp.id_detail, dp.id_pesanan, dp.id_barang, dp.jumlah,
                   {$catatan_select}, dp.harga, dp.subtotal,
                   b.nama_barang, b.satuan, b.gambar
            FROM detail_pesanan dp
            INNER JOIN barang b ON dp.id_barang = b.id_barang
            WHERE dp.id_pesanan IN ({$id_list})
            ORDER BY dp.id_detail ASC
        ";
        $res_detail = mysqli_query($conn, $sql_detail);
        if ($res_detail) {
            while ($row = mysqli_fetch_assoc($res_detail)) {
                $detail_pesanan[(int)$row['id_pesanan']][] = $row;
            }
        } else {
            $error = "Gagal mengambil detail pesanan.";
        }
    }
}

/* Statistik */
$statistik = ['semua' => 0, 'menunggu' => 0, 'diproses' => 0, 'dikirim' => 0, 'selesai' => 0, 'dibatalkan' => 0];
$res_stats = mysqli_query($conn, "SELECT status, COUNT(*) AS jumlah FROM pesanan GROUP BY status");
if ($res_stats) {
    while ($row = mysqli_fetch_assoc($res_stats)) {
        $key = (string)$row['status'];
        if (array_key_exists($key, $statistik)) $statistik[$key] = (int)$row['jumlah'];
    }
}
$statistik['semua'] = $statistik['menunggu'] + $statistik['diproses'] + $statistik['dikirim'] + $statistik['selesai'] + $statistik['dibatalkan'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pesanan Online - Toko Malaya</title>
<style>
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f4f7fb;color:#1f2937}body.sidebar-open{overflow:hidden}
.kasir-topbar{position:sticky;top:0;z-index:900;min-height:72px;padding:0 24px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #e5eaf0;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.topbar-left{display:flex;align-items:center;gap:14px;min-width:0}.hamburger{width:42px;height:42px;border:1px solid #dbe3ec;border-radius:9px;background:#fff;color:#1f2937;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:22px;line-height:1}.hamburger:hover{background:#eef6ff;border-color:#b9d7f2;color:#1677d2}
.topbar-brand{display:flex;align-items:center;gap:10px;min-width:0}.topbar-logo{width:40px;height:40px;display:flex;align-items:center;justify-content:center;background:#eef6ff;border-radius:10px;flex-shrink:0}.topbar-logo img{width:34px;height:34px;object-fit:contain}.topbar-brand-text{display:flex;flex-direction:column;min-width:0}.topbar-brand-text strong{font-size:17px}.topbar-brand-text span{margin-top:2px;color:#6b7280;font-size:11px}
.topbar-user{display:flex;align-items:center;gap:10px}.topbar-avatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e8f2fc;color:#1677d2;font-weight:bold}.topbar-user-info{display:flex;flex-direction:column}.topbar-user-info strong{font-size:13px}.topbar-user-info span{font-size:11px;color:#6b7280;margin-top:2px}
.content{width:100%;min-height:calc(100vh - 72px);padding:26px 30px;min-width:0}.page-container{max-width:1400px;margin:0 auto}.page-header{background:#fff;border:1px solid #e5eaf0;border-radius:12px;padding:22px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.05)}.page-header-top{display:flex;align-items:center;justify-content:space-between;gap:20px}.page-header h1{margin:0 0 6px;font-size:26px}.page-header p{margin:0;color:#6b7280;font-size:14px;line-height:1.5}.btn-dashboard{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;background:#eef6ff;color:#1677d2;border:1px solid #cfe3f8;padding:10px 15px;border-radius:8px;font-size:13px;font-weight:bold;white-space:nowrap}.btn-dashboard:hover{background:#1677d2;color:#fff}
.alert{padding:13px 16px;border-radius:9px;margin-bottom:18px;font-size:14px;line-height:1.5}.alert-success{background:#ecfdf3;color:#166534;border:1px solid #bbf7d0}.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:22px}.stat-card{display:block;background:#fff;border:1px solid #e5eaf0;border-radius:10px;padding:16px;text-decoration:none;color:inherit;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:transform .2s,border-color .2s}.stat-card:hover{transform:translateY(-2px);border-color:#b9d7f2}.stat-card.active{border:2px solid #1677d2;padding:15px;background:#eef6ff}.stat-label{margin-bottom:7px;color:#6b7280;font-size:11px;font-weight:bold;letter-spacing:.2px}.stat-number{color:#1677d2;font-size:23px;font-weight:bold}
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:15px}.toolbar-title{font-size:17px;font-weight:bold}.toolbar-count{color:#6b7280;font-size:13px;font-weight:normal}.order-list{display:flex;flex-direction:column;gap:16px}.order-card{background:#fff;border:1px solid #e5eaf0;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}.order-header{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:17px 20px;border-bottom:1px solid #edf0f3}.order-number{font-size:16px;font-weight:bold}.order-date{margin-top:5px;color:#6b7280;font-size:12px}
.status{display:inline-flex;align-items:center;justify-content:center;padding:6px 11px;border-radius:20px;font-size:11px;font-weight:bold;white-space:nowrap}.status-menunggu{background:#fff7df;color:#946200}.status-diproses{background:#e8f3ff;color:#1264b3}.status-dikirim{background:#f0ebff;color:#6240b8}.status-selesai{background:#e8f8ef;color:#187044}.status-dibatalkan{background:#fdecec;color:#b4232c}
.order-body{padding:20px}.customer-box{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:20px}.info-box{padding:12px 14px;border-radius:8px;background:#f8fafc;border:1px solid #edf0f3;min-width:0}.info-label{display:block;margin-bottom:5px;color:#6b7280;font-size:10px;text-transform:uppercase;font-weight:bold;letter-spacing:.3px}.info-value{color:#374151;font-size:13px;line-height:1.5;word-break:break-word}.address-box{grid-column:1/-1}
.qris-payment-box{min-width:0;padding:12px 14px;border:1px solid #dbeafe;border-radius:10px;background:linear-gradient(135deg,#f8fbff,#eef6ff)}.qris-payment-heading{display:flex;align-items:center;gap:7px;margin-bottom:8px;color:#1e3a8a;font-size:11px;font-weight:800;letter-spacing:.35px;text-transform:uppercase}.qris-heading-icon{display:inline-flex;width:25px;height:25px;align-items:center;justify-content:center;border-radius:9px;background:#dbeafe;font-size:16px}.qris-status-line{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:8px}.qris-status-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700}.qris-status-pill.is-approved{background:#dcfce7;color:#166534}.qris-status-pill.is-rejected{background:#fee2e2;color:#991b1b}.qris-proof-link{display:inline-flex;align-items:center;gap:7px;margin:0 0 8px;padding:7px 10px;border:1px solid #bfdbfe;border-radius:8px;background:#fff;color:#1d4ed8;font-size:13px;font-weight:700;text-decoration:none}.qris-proof-missing{display:inline-flex;margin:0 0 12px;padding:8px 10px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:12px}.qris-payment-actions{display:flex;flex-wrap:wrap;gap:7px;padding-top:9px;border-top:1px solid #dbeafe}.qris-action-btn{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:7px 10px;border:1px solid transparent;border-radius:8px;font:inherit;font-size:12px;font-weight:700;cursor:pointer}.qris-action-approve{background:#15803d;border-color:#15803d;color:#fff}.qris-action-reject{background:#fff;border-color:#fecaca;color:#b91c1c}.qris-payment-note{margin-top:7px;color:#64748b;font-size:11px;line-height:1.5}
.detail-title{margin:0 0 10px;color:#374151;font-size:14px;font-weight:bold}.product-list{display:flex;flex-direction:column}.product-item{display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-bottom:1px solid #edf0f3}.product-item:last-child{border-bottom:none}.product-image{width:50px;height:50px;border-radius:8px;overflow:hidden;background:#f3f4f6;flex-shrink:0}.product-image img{width:100%;height:100%;object-fit:cover}.product-no-image{width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:20px}.product-info{flex:1;min-width:0}.product-name{margin-bottom:4px;font-size:13px;font-weight:bold}.product-meta{color:#6b7280;font-size:12px;line-height:1.5}.product-note{margin-top:8px;padding:8px 10px;border-radius:7px;background:#fff8e8;border:1px solid #f3dfad;color:#765500;font-size:12px;line-height:1.5}.product-note-label{display:block;margin-bottom:2px;font-size:10px;font-weight:bold;text-transform:uppercase;color:#946200}.product-subtotal{color:#1677d2;font-size:13px;font-weight:bold;white-space:nowrap;padding-top:2px}
.order-footer{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:16px 20px;border-top:1px solid #edf0f3;background:#fafbfc}.total-label{color:#6b7280;font-size:11px}.total-value{margin-top:3px;color:#1677d2;font-size:19px;font-weight:bold}.status-form{display:flex;align-items:center;gap:8px}.status-form select{min-width:155px;padding:9px 10px;border:1px solid #d5dde6;border-radius:7px;background:#fff;color:#374151;font-family:inherit;font-size:12px}.btn-update{border:0;border-radius:7px;padding:9px 14px;background:#1677d2;color:#fff;cursor:pointer;font-family:inherit;font-size:12px;font-weight:bold}.btn-update:hover{background:#1264b3}
.empty-state{background:#fff;border:1px solid #e5eaf0;border-radius:12px;padding:60px 20px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.05)}.empty-icon{margin-bottom:12px;font-size:45px}.empty-state h2{margin:0 0 8px;font-size:19px}.empty-state p{margin:0;color:#6b7280;font-size:13px}
@media(max-width:1100px){.stats{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:800px){.content{padding:20px}.customer-box{grid-template-columns:1fr}.address-box{grid-column:auto}.order-header,.order-footer{align-items:flex-start;flex-direction:column}.status-form{width:100%}.status-form select{flex:1}}@media(max-width:650px){.kasir-topbar{padding:0 14px}.topbar-brand-text,.topbar-user-info{display:none}.content{padding:15px 12px}.page-header{padding:17px}.page-header-top{align-items:flex-start;flex-direction:column}.btn-dashboard{width:100%}.stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.stat-card{padding:13px}.stat-card.active{padding:12px}.stat-number{font-size:19px}.order-header,.order-body,.order-footer{padding:15px}.status-form{align-items:stretch;flex-direction:column}.status-form select,.btn-update{width:100%}.qris-payment-box{grid-column:1/-1;padding:12px}}@media(max-width:400px){.stats{grid-template-columns:1fr}.product-image{width:44px;height:44px}}
</style>
</head>
<body class="kasir">
<?php include "sidebar.php"; ?>
<header class="kasir-topbar">
  <div class="topbar-left">
    <button type="button" class="hamburger" onclick="toggleSidebar()" aria-label="Buka menu">☰</button>
    <div class="topbar-brand">
      <div class="topbar-logo"><img src="../assets/SembakoKu (1).png" alt="Logo Toko Malaya"></div>
      <div class="topbar-brand-text"><strong>Toko Malaya</strong><span>Panel Kasir</span></div>
    </div>
  </div>
  <div class="topbar-user">
    <div class="topbar-avatar"><?= htmlspecialchars(strtoupper(substr($nama_user, 0, 1)), ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="topbar-user-info"><strong><?= htmlspecialchars($nama_user, ENT_QUOTES, 'UTF-8'); ?></strong><span>Kasir</span></div>
  </div>
</header>

<main class="content"><div class="page-container">
  <div class="page-header"><div class="page-header-top"><div><h1>Pesanan Online</h1><p>Kelola pesanan pelanggan yang masuk dari Toko Malaya.</p></div><a href="dashboard.php" class="btn-dashboard">← Dashboard</a></div></div>

  <?php if ($success !== ''): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
  <?php if (!$detail_has_catatan): ?><div class="alert alert-error">⚠ Kolom <strong>catatan</strong> pada <strong>detail_pesanan</strong> belum tersedia. Jika fitur catatan diperlukan, jalankan <code>ALTER TABLE detail_pesanan ADD COLUMN catatan TEXT NULL AFTER jumlah;</code></div><?php endif; ?>

  <div class="stats">
    <?php
      $stat_cards = [
        '' => ['SEMUA', $statistik['semua']],
        'menunggu' => ['MENUNGGU', $statistik['menunggu']],
        'diproses' => ['DIPROSES', $statistik['diproses']],
        'dikirim' => ['DIKIRIM', $statistik['dikirim']],
        'selesai' => ['SELESAI', $statistik['selesai']],
        'dibatalkan' => ['DIBATALKAN', $statistik['dibatalkan']]
      ];
      foreach ($stat_cards as $key => $card):
        $href = $key === '' ? 'pesanan.php' : 'pesanan.php?status=' . urlencode($key);
    ?>
      <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="stat-card <?= $filter_status === $key ? 'active' : ''; ?>">
        <div class="stat-label"><?= htmlspecialchars($card[0], ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="stat-number"><?= (int)$card[1]; ?></div>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="toolbar"><div class="toolbar-title">
    <?= $filter_status === '' ? 'Semua Pesanan' : 'Pesanan ' . htmlspecialchars(status_label($filter_status), ENT_QUOTES, 'UTF-8'); ?>
    <span class="toolbar-count">(<?= count($pesanan); ?> pesanan)</span>
  </div></div>

  <?php if (!$pesanan): ?>
    <div class="empty-state"><div class="empty-icon">📦</div><h2>Belum Ada Pesanan</h2><p>Tidak ada pesanan dengan status yang dipilih.</p></div>
  <?php else: ?>
    <div class="order-list">
    <?php foreach ($pesanan as $order):
      $id = (int)$order['id_pesanan'];
      $status = (string)$order['status'];
      $tanggal_raw = strtotime((string)$order['tanggal_pesanan']);
      $tanggal = $tanggal_raw ? date('d/m/Y H:i', $tanggal_raw) : (string)$order['tanggal_pesanan'];
      $metode = (string)($order['metode_pembayaran'] ?? '');
      $status_qris = (string)($order['status_pembayaran'] ?? '');
    ?>
      <article class="order-card">
        <div class="order-header">
          <div><div class="order-number">Pesanan #<?= str_pad((string)$id, 5, '0', STR_PAD_LEFT); ?></div><div class="order-date"><?= htmlspecialchars($tanggal, ENT_QUOTES, 'UTF-8'); ?></div></div>
          <span class="status <?= htmlspecialchars(status_class($status), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(status_label($status), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="order-body">
          <div class="customer-box">
            <div class="info-box"><span class="info-label">Pelanggan</span><div class="info-value"><?= htmlspecialchars((string)$order['nama'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div class="info-box"><span class="info-label">Email</span><div class="info-value"><?= htmlspecialchars((string)$order['email'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div class="info-box"><span class="info-label">Pembayaran</span><div class="info-value"><?= htmlspecialchars($metode !== '' ? $metode : 'Belum ditentukan', ENT_QUOTES, 'UTF-8'); ?></div></div>

            <?php if (strtoupper($metode) === 'QRIS'): ?>
              <?php $qris_class = $status_qris === 'terverifikasi' ? 'is-approved' : ($status_qris === 'ditolak' ? 'is-rejected' : ''); ?>
              <div class="qris-payment-box">
                <div class="qris-payment-heading"><span class="qris-heading-icon">▦</span><span>Verifikasi Pembayaran QRIS</span></div>
                <div class="qris-status-line"><span class="qris-status-pill <?= $qris_class; ?>"><?= $status_qris === 'terverifikasi' ? '✓' : ($status_qris === 'ditolak' ? '!' : '◷'); ?> <?= htmlspecialchars($status_qris !== '' ? ucwords(str_replace('_', ' ', $status_qris)) : 'Belum ada status', ENT_QUOTES, 'UTF-8'); ?></span></div>
                <?php if (trim((string)($order['bukti_pembayaran'] ?? '')) !== ''): ?>
                  <?php $bukti_url = '../' . ltrim((string)$order['bukti_pembayaran'], '/'); ?>
                  <a class="qris-proof-link" href="<?= htmlspecialchars($bukti_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">↗ Lihat bukti pembayaran</a>
                <?php else: ?>
                  <div class="qris-proof-missing">⚠ Bukti pembayaran belum diunggah pelanggan</div>
                <?php endif; ?>
                <?php if (in_array($status_qris, ['menunggu_verifikasi', 'ditolak'], true) && trim((string)($order['bukti_pembayaran'] ?? '')) !== ''): ?>
                  <form method="post" class="qris-payment-actions" onsubmit="return confirm('Pastikan dana QRIS benar-benar masuk. Lanjutkan?');">
                    <input type="hidden" name="id_pesanan" value="<?= $id; ?>">
                    <button type="submit" name="verifikasi_pembayaran" value="1" class="qris-action-btn qris-action-approve" onclick="this.form.aksi_pembayaran.value='setujui'">✓ Setujui Pembayaran</button>
                    <button type="submit" name="verifikasi_pembayaran" value="1" class="qris-action-btn qris-action-reject" onclick="this.form.aksi_pembayaran.value='tolak'">✕ Tolak Bukti</button>
                    <input type="hidden" name="aksi_pembayaran" value="setujui">
                  </form>
                  <div class="qris-payment-note">Periksa mutasi merchant/bank terlebih dahulu. Screenshot saja bukan bukti dana telah masuk.</div>
                <?php elseif ($status_qris === 'terverifikasi'): ?>
                  <div class="qris-payment-note">Pembayaran sudah ditandai terverifikasi oleh kasir.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="info-box"><span class="info-label">Barang</span><div class="info-value"><?= (int)$order['total_barang']; ?> barang · <?= (int)$order['jumlah_jenis_barang']; ?> jenis</div></div>
            <div class="info-box address-box"><span class="info-label">Alamat Pengiriman</span><div class="info-value"><?= nl2br(htmlspecialchars((string)$order['alamat_pengiriman'], ENT_QUOTES, 'UTF-8')); ?></div></div>
          </div>

          <div class="detail-title">Detail Barang</div>
          <div class="product-list">
          <?php $items = $detail_pesanan[$id] ?? []; ?>
          <?php if ($items): foreach ($items as $item): ?>
            <div class="product-item">
              <div class="product-image">
                <?php
                  $gambar = trim((string)($item['gambar'] ?? ''));
                  $path_gambar = "../uploads/" . $gambar;
                ?>
                <?php if ($gambar !== '' && is_file($path_gambar)): ?><img src="<?= htmlspecialchars($path_gambar, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars((string)$item['nama_barang'], ENT_QUOTES, 'UTF-8'); ?>"><?php else: ?><div class="product-no-image">📦</div><?php endif; ?>
              </div>
              <div class="product-info">
                <div class="product-name"><?= htmlspecialchars((string)$item['nama_barang'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="product-meta"><?= (int)$item['jumlah']; ?> <?= htmlspecialchars((string)$item['satuan'], ENT_QUOTES, 'UTF-8'); ?> × <?= rupiah($item['harga']); ?></div>
                <?php $catatan = trim((string)($item['catatan'] ?? '')); if ($catatan !== ''): ?><div class="product-note"><span class="product-note-label">📝 Catatan Produk</span><?= nl2br(htmlspecialchars($catatan, ENT_QUOTES, 'UTF-8')); ?></div><?php endif; ?>
              </div>
              <div class="product-subtotal"><?= rupiah($item['subtotal']); ?></div>
            </div>
          <?php endforeach; else: ?><div class="product-meta">Detail barang tidak ditemukan.</div><?php endif; ?>
          </div>
        </div>
        <div class="order-footer">
          <div><div class="total-label">Total Pembayaran</div><div class="total-value"><?= rupiah($order['total_harga']); ?></div></div>
          <?php if ($status === 'menunggu'): ?>
            <form method="post" class="status-form" onsubmit="return confirm('Ubah status pesanan ini?');">
              <input type="hidden" name="id_pesanan" value="<?= $id; ?>">
              <select name="status_baru" required><option value="">Pilih Status</option><option value="diproses">Diproses</option><option value="dibatalkan">Batalkan</option></select>
              <button type="submit" name="update_status" value="1" class="btn-update">Update</button>
            </form>
          <?php elseif ($status === 'diproses'): ?>
            <form method="post" class="status-form" onsubmit="return confirm('Ubah status pesanan ini?');">
              <input type="hidden" name="id_pesanan" value="<?= $id; ?>">
              <select name="status_baru" required><option value="">Pilih Status</option><option value="dikirim">Dikirim</option><option value="dibatalkan">Batalkan</option></select>
              <button type="submit" name="update_status" value="1" class="btn-update">Update</button>
            </form>
          <?php elseif ($status === 'dikirim'): ?>
            <span class="status status-dikirim">⏳ Otomatis selesai 2 jam setelah dikirim</span>
          <?php elseif ($status === 'selesai'): ?>
            <span class="status status-selesai">✓ Pesanan Selesai</span>
          <?php elseif ($status === 'dibatalkan'): ?>
            <span class="status status-dibatalkan">Pesanan Dibatalkan</span>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div></main>

<script>
function openSidebar() {
  const sidebar = document.getElementById('kasirSidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (!sidebar || !overlay) return;
  sidebar.classList.add('show');
  overlay.classList.add('show');
  document.body.classList.add('sidebar-open');
}
function closeSidebar() {
  const sidebar = document.getElementById('kasirSidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (!sidebar || !overlay) return;
  sidebar.classList.remove('show');
  overlay.classList.remove('show');
  document.body.classList.remove('sidebar-open');
}
function toggleSidebar() {
  const sidebar = document.getElementById('kasirSidebar');
  if (!sidebar) return;
  sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
}
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') closeSidebar();
});
</script>
</body>
</html>
