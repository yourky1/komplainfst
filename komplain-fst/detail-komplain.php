<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
require_once 'config.php';

if(!isLoggedIn()) {
    redirect('login.php');
}

$komplain_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// ===========================
//   UPDATE STATUS (ADMIN) - PROSES DULUAN!
// ===========================
if($role == 'admin' && isset($_POST['update_status'])) {
    
    $new_status = trim($_POST['status']);
    
    // Validasi status
    $valid_status = ['pending', 'diproses', 'selesai', 'ditolak'];
    if(!in_array($new_status, $valid_status)) {
        $_SESSION['flash_error'] = 'Status tidak valid: ' . htmlspecialchars($new_status);
        header("Location: detail-komplain.php?id=$komplain_id");
        exit;
    }

    // METODE 1: Gunakan mysqli_query biasa (lebih simple)
    $new_status_escaped = mysqli_real_escape_string($conn, $new_status);
    $update_query = "UPDATE komplain 
                     SET status = '$new_status_escaped', 
                         tanggal_update = NOW()
                     WHERE id = $komplain_id";
    
    // Execute query
    $result = mysqli_query($conn, $update_query);
    
    // Cek error
    if(!$result) {
        $_SESSION['flash_error'] = 'MySQL Error: ' . mysqli_error($conn);
        header("Location: detail-komplain.php?id=$komplain_id");
        exit;
    }
    
    // Cek affected rows
    $affected = mysqli_affected_rows($conn);
    
    if($affected > 0) {
        // BERHASIL!
        $_SESSION['flash_success'] = 'Status berhasil diubah menjadi ' . ucfirst($new_status);
        
        // Insert notifikasi
        $user_query = mysqli_query($conn, "SELECT user_id FROM komplain WHERE id = $komplain_id");
        $user_row = mysqli_fetch_assoc($user_query);
        $target_user = $user_row['user_id'];
        
        $notif_judul = mysqli_real_escape_string($conn, 'Status Komplain Diperbarui');
        $notif_pesan = mysqli_real_escape_string($conn, "Status komplain telah diubah menjadi: " . ucfirst($new_status));
        
        mysqli_query($conn, "INSERT INTO notifikasi (user_id, komplain_id, judul, pesan) 
                            VALUES ($target_user, $komplain_id, '$notif_judul', '$notif_pesan')");
    } else {
        // Cek kenapa 0 affected
        $check = mysqli_query($conn, "SELECT status FROM komplain WHERE id = $komplain_id");
        $current = mysqli_fetch_assoc($check);
        
        if($current && $current['status'] == $new_status) {
            $_SESSION['flash_info'] = 'Status sudah ' . ucfirst($new_status) . ' (tidak ada perubahan)';
        } else {
            $_SESSION['flash_error'] = 'Update gagal! Affected: ' . $affected . ' | Current: ' . ($current ? $current['status'] : 'null');
        }
    }
    
    // Redirect
    header("Location: detail-komplain.php?id=$komplain_id");
    exit;
}

// ===========================
//  GET KOMPLAIN DATA
// ===========================
$query = "SELECT k.*, u.nama, u.nim, u.email, u.jurusan 
          FROM komplain k 
          JOIN users u ON k.user_id = u.id 
          WHERE k.id = $komplain_id";

if($role != 'admin') {
    $query .= " AND k.user_id = $user_id";
}

$result = mysqli_query($conn, $query);

if(!$result || mysqli_num_rows($result) == 0) {
    redirect('dashboard.php');
    exit;
}

$komplain = mysqli_fetch_assoc($result);

// ===========================
//   TANGGAPAN
// ===========================
if(isset($_POST['submit_tanggapan'])) {
    $pesan = mysqli_real_escape_string($conn, trim($_POST['pesan']));

    if($pesan != "") {
        
        mysqli_query($conn, "INSERT INTO tanggapan (komplain_id, user_id, pesan)
                            VALUES ($komplain_id, $user_id, '$pesan')");

        // Target notifikasi
        if ($role == 'admin') {
            $target = $komplain['user_id'];
            $msg = "Admin memberikan tanggapan baru";
        } else {
            $admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE role='admin' LIMIT 1"));
            $target = $admin['id'];
            $msg = $_SESSION['nama'] . " memberikan tanggapan baru";
        }

        $msg_escaped = mysqli_real_escape_string($conn, $msg);
        mysqli_query($conn, "INSERT INTO notifikasi (user_id, komplain_id, judul, pesan)
                            VALUES ($target, $komplain_id, 'Tanggapan Baru', '$msg_escaped')");

        $_SESSION['flash_success'] = 'Tanggapan berhasil dikirim';
        header("Location: detail-komplain.php?id=$komplain_id#tanggapan");
        exit;
    }
}

// ===========================
//   GET TANGGAPAN
// ===========================
$tanggapan_result = mysqli_query($conn, "
    SELECT t.*, u.nama, u.role
    FROM tanggapan t
    JOIN users u ON u.id = t.user_id
    WHERE t.komplain_id = $komplain_id
    ORDER BY t.tanggal ASC
");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Komplain #<?php echo $komplain_id; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
<nav class="navbar">
    <div class="container">
        <div class="nav-brand">
            <i class="fas fa-university"></i>
            <span>Komplain FST</span>
        </div>
        <ul class="nav-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="komplain-saya.php">Komplain Saya</a></li>
            <li><a href="profil.php"><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['nama']); ?></a></li>
            <li><a href="logout.php" class="btn-nav-outline">Logout</a></li>
        </ul>
    </div>
</nav>

<section class="dashboard">
    <div class="container">
        <div style="max-width:1000px; margin:40px auto;">

            <?php if(isset($_SESSION['flash_error'])): ?>
                <div style="background:#fee2e2; color:#991b1b; padding:1rem; border-radius:8px; margin-bottom:1rem; border-left:4px solid #dc2626;">
                    <i class="fas fa-exclamation-circle"></i> <strong>Error:</strong> <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['flash_success'])): ?>
                <div style="background:#d1fae5; color:#065f46; padding:1rem; border-radius:8px; margin-bottom:1rem; border-left:4px solid #10b981;">
                    <i class="fas fa-check-circle"></i> <strong>Sukses:</strong> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['flash_info'])): ?>
                <div style="background:#e0f2fe; color:#075985; padding:1rem; border-radius:8px; margin-bottom:1rem; border-left:4px solid #0284c7;">
                    <i class="fas fa-info-circle"></i> <strong>Info:</strong> <?php echo $_SESSION['flash_info']; unset($_SESSION['flash_info']); ?>
                </div>
            <?php endif; ?>

            <!-- DEBUG INFO (hapus ini setelah berhasil) -->
            <div style="background:#fff3cd; border:2px dashed #ffc107; padding:1rem; margin-bottom:1rem; border-radius:8px; font-size:0.9em;">
                <strong>🔍 Debug Info:</strong><br>
                Role: <?php echo $role; ?><br>
                Komplain ID: <?php echo $komplain_id; ?><br>
                Status Saat Ini: <strong style="color:#dc2626;"><?php echo $komplain['status']; ?></strong><br>
                User ID: <?php echo $user_id; ?>
            </div>

            <!-- BACK -->
            <a href="<?php echo $role == 'admin' ? 'dashboard.php' : 'komplain-saya.php'; ?>" 
               class="btn" style="background:#6b7280; color:white; display:inline-block; margin-bottom:20px; text-decoration:none; padding:10px 20px; border-radius:6px;">
               <i class="fas fa-arrow-left"></i> Kembali
            </a>

            <!-- CARD -->
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h3><i class="fas fa-file-alt"></i> Detail Komplain #<?php echo $komplain_id; ?></h3>

                    <?php
                    $status = $komplain['status'];
                    $badge_styles = [
                        'pending'   => 'background:#fef3c7; color:#92400e;',
                        'diproses'  => 'background:#dbeafe; color:#1e40af;',
                        'selesai'   => 'background:#d1fae5; color:#065f46;',
                        'ditolak'   => 'background:#fee2e2; color:#991b1b;'
                    ];
                    ?>
                    <span class="badge" style="<?php echo $badge_styles[$status]; ?> padding:8px 16px; border-radius:20px; font-weight:600; font-size:1em;">
                        <?php echo strtoupper($status); ?>
                    </span>
                </div>

                <div style="padding:2rem;">

                    <!-- INFORMASI PELAPOR -->
                    <div style="background:#f9fafb; padding:1.5rem; border-radius:10px; margin-bottom:2rem;">
                        <h4 style="margin-bottom:1rem;"><i class="fas fa-user"></i> Informasi Pelapor</h4>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem;">
                            <div><strong>Nama:</strong><br><?php echo htmlspecialchars($komplain['nama']); ?></div>
                            <div><strong>NIM:</strong><br><?php echo htmlspecialchars($komplain['nim']); ?></div>
                            <div><strong>Jurusan:</strong><br><?php echo htmlspecialchars($komplain['jurusan']); ?></div>
                            <div><strong>Email:</strong><br><?php echo htmlspecialchars($komplain['email']); ?></div>
                        </div>
                    </div>

                    <!-- DETAIL KOMPLAIN -->
                    <h4 style="margin-bottom:1rem;"><i class="fas fa-info-circle"></i> Detail Komplain</h4>

                    <div style="margin-bottom:1rem;">
                        <strong>Kategori:</strong><br>
                        <span class="badge" style="background:#e0e7ff; color:#3730a3; padding:5px 12px; border-radius:15px; display:inline-block; margin-top:5px;">
                            <?php echo ucfirst($komplain['kategori']); ?>
                        </span>
                    </div>

                    <p><strong>Prioritas:</strong> 
                        <span style="padding:3px 10px; border-radius:12px; font-size:0.9em; 
                            <?php 
                            if($komplain['prioritas'] == 'tinggi') echo 'background:#fee2e2; color:#991b1b;';
                            elseif($komplain['prioritas'] == 'sedang') echo 'background:#fef3c7; color:#92400e;';
                            else echo 'background:#dbeafe; color:#1e40af;';
                            ?>">
                            <?php echo ucfirst($komplain['prioritas']); ?>
                        </span>
                    </p>
                    
                    <p><strong>Judul:</strong><br><?php echo htmlspecialchars($komplain['judul']); ?></p>
                    <p><strong>Deskripsi:</strong><br><?php echo nl2br(htmlspecialchars($komplain['deskripsi'])); ?></p>

                    <?php if($komplain['lampiran']): ?>
                    <p><strong>Lampiran:</strong><br>
                        <a href="uploads/<?php echo htmlspecialchars($komplain['lampiran']); ?>" target="_blank" class="btn btn-primary-form" style="display:inline-block; margin-top:5px;">
                            <i class="fas fa-download"></i> Unduh Lampiran
                        </a>
                    </p>
                    <?php endif; ?>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid #e5e7eb;">
                        <div><strong>Tanggal Dibuat:</strong><br><?php echo date('d/m/Y H:i', strtotime($komplain['tanggal_submit'])); ?></div>
                        <div><strong>Terakhir Update:</strong><br><?php echo date('d/m/Y H:i', strtotime($komplain['tanggal_update'])); ?></div>
                    </div>

                    <!-- ADMIN CONTROL -->
                    <?php if($role == 'admin'): ?>
                    <div style="background:#eff6ff; padding:1.5rem; border-radius:10px; margin-top:2rem; border:3px solid #3b82f6;">
                        <h4 style="margin-bottom:1rem; color:#1e40af;"><i class="fas fa-cog"></i> Kontrol Admin - Update Status</h4>
                        
                        <div style="background:#fff; padding:1rem; border-radius:6px; margin-bottom:1rem;">
                            <p style="margin:0; font-size:0.95em;">
                                <strong>Status Saat Ini:</strong> 
                                <span style="background:#<?php 
                                    echo $status=='pending' ? 'fef3c7' : ($status=='diproses' ? 'dbeafe' : ($status=='selesai' ? 'd1fae5' : 'fee2e2')); 
                                ?>; color:#<?php 
                                    echo $status=='pending' ? '92400e' : ($status=='diproses' ? '1e40af' : ($status=='selesai' ? '065f46' : '991b1b')); 
                                ?>; padding:5px 12px; border-radius:12px; font-weight:600;">
                                    <?php echo strtoupper($status); ?>
                                </span>
                            </p>
                        </div>

                        <form method="POST" action="" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;" 
                              onsubmit="return confirm('⚠️ Yakin ingin mengubah status komplain ini?');">
                            
                            <label style="font-weight:600; color:#1e40af;">Ubah Ke:</label>
                            
                            <select name="status" class="form-control" style="flex:1; min-width:200px; max-width:300px; padding:10px; border:2px solid #3b82f6; border-radius:6px; font-weight:600;" required>
                                <option value="">-- Pilih Status --</option>
                                <option value="pending" <?php echo $status=='pending'?'selected':''; ?>>⏳ Pending</option>
                                <option value="diproses" <?php echo $status=='diproses'?'selected':''; ?>>🔄 Diproses</option>
                                <option value="selesai" <?php echo $status=='selesai'?'selected':''; ?>>✅ Selesai</option>
                                <option value="ditolak" <?php echo $status=='ditolak'?'selected':''; ?>>❌ Ditolak</option>
                            </select>

                            <button type="submit" name="update_status" class="btn btn-primary-form" style="white-space:nowrap; background:#3b82f6; padding:10px 24px; font-weight:600;">
                                <i class="fas fa-save"></i> UPDATE STATUS
                            </button>
                        </form>
                        
                        <p style="margin-top:1rem; font-size:0.85em; color:#6b7280; margin-bottom:0;">
                            <i class="fas fa-info-circle"></i> Mahasiswa akan menerima notifikasi saat status diubah.
                        </p>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- TANGGAPAN -->
            <div class="card" id="tanggapan" style="margin-top:20px;">
                <div class="card-header">
                    <h3><i class="fas fa-comments"></i> Tanggapan</h3>
                </div>
                <div style="padding:2rem;">

                    <?php if(mysqli_num_rows($tanggapan_result) > 0): ?>
                        <?php while($t = mysqli_fetch_assoc($tanggapan_result)): ?>
                            <div style="background:<?php echo $t['role']=='admin'?'#eff6ff':'#f9fafb'; ?>; padding:1.2rem; border-radius:10px; margin-bottom:1rem; border-left:4px solid <?php echo $t['role']=='admin'?'#3b82f6':'#6b7280'; ?>;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                                    <strong style="color:#1f2937;">
                                        <i class="fas <?php echo $t['role']=='admin'?'fa-user-shield':'fa-user'; ?>"></i>
                                        <?php echo htmlspecialchars($t['nama']); ?>
                                        <?php if($t['role']=='admin'): ?>
                                            <span style="background:#3b82f6; color:white; padding:2px 8px; border-radius:10px; font-size:0.75em; margin-left:5px;">Admin</span>
                                        <?php endif; ?>
                                    </strong>
                                    <span style="color:#6b7280; font-size:0.9em;">
                                        <i class="far fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($t['tanggal'])); ?>
                                    </span>
                                </div>
                                <p style="margin:0; color:#374151;"><?php echo nl2br(htmlspecialchars($t['pesan'])); ?></p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:2rem; color:#6b7280;">
                            <i class="fas fa-comments" style="font-size:3rem; opacity:0.3; margin-bottom:1rem;"></i>
                            <p>Belum ada tanggapan.</p>
                        </div>
                    <?php endif; ?>

                    <?php if($status != 'selesai' && $status != 'ditolak'): ?>
                    <form method="POST" action="" style="margin-top:2rem;">
                        <label style="font-weight:600; margin-bottom:0.5rem; display:block;">
                            <i class="fas fa-reply"></i> Tambahkan Tanggapan:
                        </label>
                        <textarea name="pesan" class="form-control" rows="4" placeholder="Tulis tanggapan Anda di sini..." required style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:6px;"></textarea>
                        <button name="submit_tanggapan" class="btn btn-primary-form" style="margin-top:1rem; padding:10px 24px;">
                            <i class="fas fa-paper-plane"></i> Kirim Tanggapan
                        </button>
                    </form>
                    <?php else: ?>
                    <div style="background:#fef3c7; padding:1.2rem; text-align:center; border-radius:10px; border:2px dashed #f59e0b;">
                        <i class="fas fa-lock"></i> Komplain sudah <strong><?php echo strtoupper($status); ?></strong>. Tanggapan tidak dapat ditambahkan lagi.
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</section>

<script>
// Log saat form disubmit
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[method="POST"]');
    if(form && form.querySelector('select[name="status"]')) {
        form.addEventListener('submit', function(e) {
            const status = this.querySelector('select[name="status"]').value;
            console.log('📤 Submitting form dengan status:', status);
        });
    }
});
</script>

</body>
</html>