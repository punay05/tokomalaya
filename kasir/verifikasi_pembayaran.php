<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['id_user']) || ($_SESSION['role'] ?? '') !== 'kasir') {
    header('Location: ../auth/login.php');
    exit;
}

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function rp($value): string { return 'Rp ' . number_format((float)$value, 0, ',', '.'); }

$error = '';
$success = '';

// Pastikan kolom yang dipakai memang sudah ditambahkan melalui migrasi_qris_cod.sql.
$columnCheck = mysqli_query($conn, "SHOW COLUMNS FROM pesanan LIKE 'status_pembayaran'");
if (!$columnCheck || mysqli_num_rows($columnCheck) === 0) {
    http_response_code(500);
    exit('Kolom status_pembayaran belum ada. Jalankan migrasi_qris_cod.sql terlebih dahulu.');
}
$proofCheck = mysqli_query($conn, "SHOW COLUMNS FROM pesanan LIKE 'bukti_pembayaran'");
if (!$proofCheck || mysqli_num_rows($proofCheck) === 0) {
    http_response_code(500);
    exit('Kolom bukti_pembayaran belum ada. Jalankan migrasi_qris_cod.sql terlebih dahulu.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id_pesanan'] ?? 0);
    $action = $_POST['aksi'] ?? '';
    $alasan = trim((string)($_POST['alasan_penolakan'] ?? ''));

    if ($id <= 0 || !in_array($action, ['setujui', 'tolak'], true)) {
        $error = 'Permintaan tidak valid.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id_pesanan, metode_pembayaran, status, status_pembayaran, bukti_pembayaran FROM pesanan WHERE id_pesanan = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$order) {
            $error = 'Pesanan tidak ditemukan.';
        } elseif (strtoupper((string)$order['metode_pembayaran']) !== 'QRIS') {
            $error = 'Verifikasi ini hanya untuk pesanan QRIS.';
        } elseif ($order['status_pembayaran'] !== 'menunggu_verifikasi') {
            $error = 'Pesanan ini tidak sedang menunggu verifikasi pembayaran.';
        } elseif ($action === 'setujui' && empty($order['bukti_pembayaran'])) {
            $error = 'Bukti pembayaran belum tersedia; pembayaran tidak bisa disetujui.';
        } elseif ($action === 'tolak' && $alasan === '') {
            $error = 'Isi alasan penolakan agar pelanggan mengetahui perbaikannya.';
        } else {
            $newPaymentStatus = $action === 'setujui' ? 'terverifikasi' : 'ditolak';
            // Update bersyarat mencegah dua kasir memutuskan pesanan yang sama secara bersamaan.
            $update = mysqli_prepare($conn, "UPDATE pesanan SET status_pembayaran = ? WHERE id_pesanan = ? AND status_pembayaran = 'menunggu_verifikasi' AND metode_pembayaran = 'QRIS'");
            mysqli_stmt_bind_param($update, 'si', $newPaymentStatus, $id);
            if (mysqli_stmt_execute($update) && mysqli_stmt_affected_rows($update) === 1) {
                $success = $action === 'setujui'
                    ? 'Pembayaran pesanan #' . $id . ' disetujui. Pesanan tetap berstatus menunggu sampai kasir memprosesnya.'
                    : 'Bukti pembayaran pesanan #' . $id . ' ditolak. Catatan penolakan: ' . $alasan;
                // Catatan penolakan ditampilkan pada hasil tindakan ini; belum disimpan karena skema DB belum memiliki kolom catatan.
            } else {
                $error = 'Status pembayaran tidak berubah. Muat ulang halaman dan periksa status pesanan.';
            }
            mysqli_stmt_close($update);
        }
    }
}

$sql = "SELECT p.id_pesanan, p.tanggal_pesanan, p.total_harga, p.alamat_pengiriman, p.metode_pembayaran, p.status, p.status_pembayaran, p.bukti_pembayaran, u.nama AS nama_pelanggan
        FROM pesanan p LEFT JOIN users u ON u.id_user = p.id_user
        WHERE p.metode_pembayaran = 'QRIS'
        ORDER BY CASE WHEN p.status_pembayaran = 'menunggu_verifikasi' THEN 0 ELSE 1 END, p.tanggal_pesanan DESC";
$result = mysqli_query($conn, $sql);
if (!$result) {
    http_response_code(500);
    exit('Gagal mengambil pesanan: ' . e(mysqli_error($conn)));
}
$orders = [];
while ($row = mysqli_fetch_assoc($result)) $orders[] = $row;
$name = $_SESSION['nama'] ?? 'Kasir';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verifikasi Pembayaran QRIS - Toko Malaya</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#172b4d;font-family:Arial,Helvetica,sans-serif}.top{background:#fff;border-bottom:1px solid #e2e8f0;padding:18px max(20px,calc((100% - 1180px)/2));display:flex;justify-content:space-between;align-items:center;gap:15px}.brand{font-size:21px;font-weight:800;color:#174477}.sub{font-size:13px;color:#64748b;margin-top:5px}.top a{color:#176dcc;text-decoration:none;font-weight:700}.wrap{max-width:1180px;margin:28px auto;padding:0 18px}.heading{display:flex;justify-content:space-between;align-items:end;gap:15px;margin-bottom:20px}.heading h1{margin:0;font-size:27px}.muted{color:#64748b;font-size:14px}.notice{padding:13px 15px;border-radius:10px;margin:14px 0}.err{background:#fff1f2;color:#9f1239;border:1px solid #fecdd3}.ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}.list{display:grid;gap:16px}.order{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:20px;box-shadow:0 4px 15px #0f172a08}.order-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;border-bottom:1px solid #edf1f5;padding-bottom:14px;margin-bottom:16px}.order-head h2{font-size:18px;margin:0 0 5px}.amount{font-size:21px;font-weight:800;color:#1672ce}.pill{display:inline-block;padding:6px 10px;border-radius:999px;background:#fff7ed;color:#9a3412;font-size:12px;font-weight:700}.pill.good{background:#dcfce7;color:#166534}.pill.bad{background:#fee2e2;color:#991b1b}.grid{display:grid;grid-template-columns:minmax(220px,320px) 1fr;gap:20px}.proof{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;min-height:160px;padding:12px;display:flex;align-items:center;justify-content:center}.proof img{display:block;max-width:100%;max-height:420px;object-fit:contain;border-radius:6px}.no-proof{color:#9f1239;text-align:center;font-size:14px}.details p{margin:7px 0;line-height:1.45}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.btn{border:0;border-radius:9px;padding:11px 15px;font-weight:700;cursor:pointer;font-size:14px}.approve{background:#16834a;color:#fff}.reject{background:#fff;color:#b42318;border:1px solid #fecaca}.reason{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;margin-top:8px;font:inherit}.actions form{flex:1;min-width:220px}.empty{padding:35px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;text-align:center;color:#64748b}@media(max-width:700px){.grid{grid-template-columns:1fr}.heading{align-items:start;flex-direction:column}.wrap{margin-top:20px}.order{padding:15px}.top{padding:15px 18px}}
</style>
</head>
<body>
<header class="top"><div><div class="brand">Toko Malaya · Kasir</div><div class="sub">Verifikasi bukti pembayaran QRIS</div></div><div class="muted">Masuk sebagai <strong><?= e($name) ?></strong> · <a href="pesanan.php">Kembali ke Pesanan</a></div></header>
<main class="wrap">
<div class="heading"><div><h1>Verifikasi Pembayaran</h1><p class="muted">Periksa nominal dan transaksi pada bukti sebelum menyetujui pembayaran.</p></div><span class="pill"><?= count(array_filter($orders, fn($o) => $o['status_pembayaran'] === 'menunggu_verifikasi')) ?> menunggu verifikasi</span></div>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="notice ok"><?= e($success) ?></div><?php endif; ?>
<?php if (!$orders): ?><div class="empty">Belum ada pesanan dengan metode pembayaran QRIS.</div><?php else: ?>
<div class="list">
<?php foreach ($orders as $o): $st = $o['status_pembayaran']; $path = trim((string)$o['bukti_pembayaran']); $url = $path !== '' ? '../' . ltrim($path, '/') : ''; ?>
<article class="order">
<div class="order-head"><div><h2>Pesanan #<?= (int)$o['id_pesanan'] ?> · <?= e($o['nama_pelanggan'] ?? 'Pelanggan') ?></h2><div class="muted"><?= e($o['tanggal_pesanan']) ?></div></div><div><div class="amount"><?= rp($o['total_harga']) ?></div><span class="pill <?= $st === 'terverifikasi' ? 'good' : ($st === 'ditolak' ? 'bad' : '') ?>"><?= e(str_replace('_',' ',ucfirst($st))) ?></span></div></div>
<div class="grid"><div><div class="proof"><?php if ($url !== ''): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener"><img src="<?= e($url) ?>" alt="Bukti pembayaran pesanan <?= (int)$o['id_pesanan'] ?>"></a><?php else: ?><div class="no-proof">Pelanggan belum mengunggah bukti pembayaran.</div><?php endif; ?></div></div><div class="details"><p><strong>Alamat pengiriman:</strong><br><?= nl2br(e($o['alamat_pengiriman'])) ?></p><p><strong>Status pesanan:</strong> <?= e(ucfirst($o['status'])) ?></p><p><strong>Status pembayaran:</strong> <?= e(str_replace('_',' ',ucfirst($st))) ?></p>
<?php if ($st === 'menunggu_verifikasi'): ?>
<div class="actions"><form method="post" onsubmit="return confirm('Pastikan pembayaran benar-benar masuk. Setujui pembayaran ini?')"><input type="hidden" name="id_pesanan" value="<?= (int)$o['id_pesanan'] ?>"><input type="hidden" name="aksi" value="setujui"><button class="btn approve" type="submit" <?= $path === '' ? 'disabled title="Bukti belum tersedia"' : '' ?>>✓ Setujui Pembayaran</button></form>
<form method="post" onsubmit="return confirm('Tolak bukti pembayaran ini?')"><input type="hidden" name="id_pesanan" value="<?= (int)$o['id_pesanan'] ?>"><input type="hidden" name="aksi" value="tolak"><label class="muted" for="alasan-<?= (int)$o['id_pesanan'] ?>">Alasan penolakan</label><input class="reason" id="alasan-<?= (int)$o['id_pesanan'] ?>" name="alasan_penolakan" required maxlength="250" placeholder="Contoh: nominal tidak sesuai"><button class="btn reject" style="margin-top:8px" type="submit">Tolak Bukti</button></form></div>
<?php elseif ($st === 'ditolak'): ?><p class="muted">Bukti ditolak. Saat ini alasan penolakan belum disimpan permanen ke database.</p><?php else: ?><p class="muted">Pembayaran sudah disetujui. Lanjutkan proses pesanan dari halaman Pesanan setelah memastikan transaksi benar-benar masuk.</p><?php endif; ?>
</div></div></article>
<?php endforeach; ?>
</div><?php endif; ?>
</main>
</body></html>
