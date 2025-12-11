<?php
// --- SETUP WAJIB (Agar Error Terlihat) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Cek Login
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$pesan_error = '';
$pesan_sukses = '';

// Ambil Data User Awal
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
if(!$user_query) { die("Error Database: " . mysqli_error($conn)); }
$user = mysqli_fetch_assoc($user_query);


// ==========================================
// 1. PROSES UPDATE PROFIL (FOTO & DATA)
// ==========================================
if(isset($_POST['update_profile'])) {
    
    // Ambil Inputan
    $nama    = mysqli_real_escape_string($conn, $_POST['nama']);
    $email   = mysqli_real_escape_string($conn, $_POST['email']);
    $jurusan = mysqli_real_escape_string($conn, $_POST['jurusan']);
    $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp']);
    
    // Default: Pakai foto lama
    $foto_final = $user['foto_profil']; 

    // --- LOGIKA UPLOAD FOTO (SAMA PERSIS DENGAN VERSI DEBUG) ---
    if(isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];

        if(in_array($ext, $allowed)) {
            // Nama file baru (Unik + Random biar browser refresh)
            $newname = 'profile_' . $user_id . '_' . uniqid() . '.' . $ext;
            $path = 'uploads/profiles/';
            
            // Cek Folder (Pakai __DIR__ agar aman di Windows)
            if(!is_dir(__DIR__ . '/' . $path)) {
                mkdir(__DIR__ . '/' . $path, 0777, true);
            }

            // Pindahkan File
            if(move_uploaded_file($_FILES['foto']['tmp_name'], $path . $newname)) {
                // Jika sukses, ganti variabel foto
                $foto_final = $newname;
                
                // Hapus foto lama (Kecuali default)
                if($user['foto_profil'] != 'default.jpg' && file_exists($path . $user['foto_profil'])) {
                    unlink($path . $user['foto_profil']);
                }
            } else {
                $pesan_error = "Gagal upload file (Permission Error).";
            }
        } else {
            $pesan_error = "Format file harus JPG/PNG.";
        }
    }

    // --- SIMPAN KE DATABASE ---
    if(empty($pesan_error)) {
        $sql = "UPDATE users SET 
                nama='$nama', 
                email='$email', 
                jurusan='$jurusan', 
                no_telp='$no_telp', 
                foto_profil='$foto_final' 
                WHERE id=$user_id";
        
        if(mysqli_query($conn, $sql)) {
            $_SESSION['nama'] = $nama; // Update Session
            $pesan_sukses = "Profil Berhasil Disimpan!";
            
            // Refresh data user dari DB agar tampilan langsung berubah
            $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
        } else {
            $pesan_error = "Database Error: " . mysqli_error($conn);
        }
    }
}

// ==========================================
// 2. PROSES GANTI PASSWORD
// ==========================================
if(isset($_POST['change_password'])) {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $cfm_pass = $_POST['confirm_password'];
    
    if(empty($old_pass) || empty($new_pass) || empty($cfm_pass)) {
        $pesan_error = "Semua kolom password harus diisi.";
    } elseif($new_pass !== $cfm_pass) {
        $pesan_error = "Konfirmasi password baru tidak cocok.";
    } elseif(strlen($new_pass) < 6) {
        $pesan_error = "Password minimal 6 karakter.";
    } else {
        // Cek password lama
        if(password_verify($old_pass, $user['password'])) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $sql_pass = "UPDATE users SET password='$hash' WHERE id=$user_id";
            
            if(mysqli_query($conn, $sql_pass)) {
                $pesan_sukses = "Password berhasil diubah!";
            } else {
                $pesan_error = "Gagal update password DB.";
            }
        } else {
            $pesan_error = "Password lama salah!";
        }
    }
}

// Statistik (Khusus Mahasiswa)
$stats = ['total'=>0, 'pending'=>0, 'selesai'=>0];
if($user['role'] == 'mahasiswa') {
    $q = mysqli_query($conn, "SELECT 
        (SELECT COUNT(*) FROM komplain WHERE user_id=$user_id) as total,
        (SELECT COUNT(*) FROM komplain WHERE user_id=$user_id AND status='pending') as pending,
        (SELECT COUNT(*) FROM komplain WHERE user_id=$user_id AND status='selesai') as selesai");
    if($q) $stats = mysqli_fetch_assoc($q);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">Komplain FST</div>
            <ul class="nav-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="profil.php" class="active">Profil</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <section class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1>Edit Profil</h1>
            </div>

            <?php if($pesan_error): ?>
                <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:bold;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $pesan_error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($pesan_sukses): ?>
                <div style="background:#d1fae5; color:#047857; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:bold;">
                    <i class="fas fa-check-circle"></i> <?php echo $pesan_sukses; ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                
                <div class="card" style="text-align: center; padding: 2rem;">
                    <?php 
                        // TAMPILAN GAMBAR ANTI-CACHE
                        $file_foto = 'uploads/profiles/' . $user['foto_profil'];
                        
                        // Cek file ada atau tidak
                        if(!empty($user['foto_profil']) && file_exists($file_foto)) {
                            // Pakai ?v=time() biar browser refresh gambar baru
                            $src = $file_foto . '?v=' . time();
                        } else {
                            // Avatar jika tidak ada file
                            $src = 'https://ui-avatars.com/api/?name=' . urlencode($user['nama']) . '&size=200&background=2563eb&color=fff';
                        }
                    ?>
                    <img src="<?php echo $src; ?>" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #2563eb; margin-bottom:15px;">
                    
                    <h3><?php echo htmlspecialchars($user['nama']); ?></h3>
                    <p style="color:gray;"><?php echo htmlspecialchars($user['nim']); ?></p>
                    
                    <?php if($user['role'] == 'mahasiswa'): ?>
                        <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
                        <div style="display:flex; justify-content:space-around;">
                            <div><b><?php echo $stats['total']; ?></b><br><small>Total</small></div>
                            <div><b><?php echo $stats['pending']; ?></b><br><small>Pending</small></div>
                            <div><b><?php echo $stats['selesai']; ?></b><br><small>Selesai</small></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <div class="card" style="margin-bottom: 2rem; padding: 2rem;">
                        <h3 style="margin-bottom:20px;"><i class="fas fa-user-edit"></i> Update Data</h3>
                        
                        <form method="POST" enctype="multipart/form-data">
                            
                            <div class="form-group">
                                <label>Ganti Foto:</label>
                                <input type="file" name="foto" class="form-control" accept="image/*" style="padding:10px;">
                            </div>

                            <div class="form-group">
                                <label>Nama Lengkap:</label>
                                <input type="text" name="nama" value="<?php echo htmlspecialchars($user['nama']); ?>" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>Email:</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>Jurusan:</label>
                                <input type="text" name="jurusan" value="<?php echo htmlspecialchars($user['jurusan']); ?>" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>No. Telepon:</label>
                                <input type="text" name="no_telp" value="<?php echo htmlspecialchars($user['no_telp']); ?>" class="form-control">
                            </div>

                            <button type="submit" name="update_profile" class="btn btn-primary-form" style="width:100%; margin-top:10px;">
                                <i class="fas fa-save"></i> Simpan Profil
                            </button>
                        </form>
                    </div>

                    <div class="card" style="padding: 2rem;">
                        <h3 style="margin-bottom:20px;"><i class="fas fa-key"></i> Ganti Password</h3>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label>Password Lama:</label>
                                <input type="password" name="old_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Password Baru:</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Konfirmasi Password Baru:</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>

                            <button type="submit" name="change_password" class="btn btn-warning" style="width:100%; margin-top:10px; background:#f59e0b; color:white; border:none; padding:12px; border-radius:8px; cursor:pointer;">
                                Ganti Password
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>
</body>
</html>