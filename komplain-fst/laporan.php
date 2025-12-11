<?php
require_once 'config.php';

if(!isLoggedIn() || !isAdmin()) {
    redirect('dashboard.php');
}

// Date filter
$start_date = isset($_GET['start_date']) ? clean($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean($_GET['end_date']) : date('Y-m-d');

// Status filter
$status_filter = isset($_GET['status']) ? clean($_GET['status']) : 'semua';

// General Statistics
$total_komplain = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM komplain WHERE tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59'"))['total'];
$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM komplain WHERE status='pending' AND tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59'"))['total'];
$diproses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM komplain WHERE status='diproses' AND tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59'"))['total'];
$selesai = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM komplain WHERE status='selesai' AND tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59'"))['total'];
$ditolak = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM komplain WHERE status='ditolak' AND tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59'"))['total'];

// By Category
$kategori_result = mysqli_query($conn, "SELECT kategori, COUNT(*) as total FROM komplain WHERE tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59' GROUP BY kategori ORDER BY total DESC");

// By Priority
$prioritas_result = mysqli_query($conn, "SELECT prioritas, COUNT(*) as total FROM komplain WHERE tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59' GROUP BY prioritas ORDER BY FIELD(prioritas, 'tinggi', 'sedang', 'rendah')");

// Top Complainants
$top_users = mysqli_query($conn, "SELECT u.nama, u.nim, u.jurusan, COUNT(k.id) as total FROM komplain k JOIN users u ON k.user_id = u.id WHERE k.tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59' GROUP BY k.user_id ORDER BY total DESC LIMIT 10");

// Response Time Analysis
$response_time = mysqli_query($conn, "SELECT k.id, k.judul, k.tanggal_submit, MIN(t.tanggal) as first_response, TIMESTAMPDIFF(HOUR, k.tanggal_submit, MIN(t.tanggal)) as response_hours FROM komplain k LEFT JOIN tanggapan t ON k.id = t.komplain_id WHERE k.tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59' GROUP BY k.id HAVING first_response IS NOT NULL ORDER BY response_hours DESC LIMIT 10");

// Daily Trend
$daily_trend = mysqli_query($conn, "SELECT DATE(tanggal_submit) as tanggal, COUNT(*) as total FROM komplain WHERE tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59' GROUP BY DATE(tanggal_submit) ORDER BY tanggal");

// Average resolution time
$avg_resolution = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(TIMESTAMPDIFF(DAY, tanggal_submit, tanggal_update)) as avg_days FROM komplain WHERE status='selesai' AND tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59'"))['avg_days'];

// Get complaints for table with status filter
$where_status = "";
if($status_filter != 'semua') {
    $where_status = " AND k.status = '$status_filter'";
}
$komplain_list = mysqli_query($conn, "
    SELECT k.*, u.nama, u.nim, u.jurusan 
    FROM komplain k 
    JOIN users u ON k.user_id = u.id 
    WHERE k.tanggal_submit BETWEEN '$start_date' AND '$end_date 23:59:59' $where_status
    ORDER BY k.tanggal_submit DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Sistem Komplain FST</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .filter-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .checkbox-col {
            width: 40px;
            text-align: center;
        }
        .btn-cetak-terpilih {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-cetak-terpilih:hover {
            background: #45a049;
        }
        .btn-cetak-terpilih:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .selection-info {
            background: #e3f2fd;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: none;
            align-items: center;
            gap: 10px;
        }
        .selection-info.active {
            display: flex;
        }
        .info-guide {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .info-guide i {
            color: #856404;
        }
    </style>
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
                <li><a href="komplain-saya.php">Semua Komplain</a></li>
                <li><a href="pengguna.php">Pengguna</a></li>
                <li><a href="laporan.php" class="active"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                <li><a href="profil.php"><?php echo $_SESSION['nama']; ?></a></li>
                <li><a href="logout.php" class="btn-nav-outline">Logout</a></li>
            </ul>
        </div>
    </nav>

    <section class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-chart-bar"></i> Laporan & Statistik</h1>
                    <p>Analisis data komplain mahasiswa</p>
                </div>
            </div>

            <div class="card filter-section">
                <form method="GET" action="">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                        <div class="form-group">
                            <label for="start_date"><i class="fas fa-calendar"></i> Tanggal Mulai</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $start_date; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date"><i class="fas fa-calendar"></i> Tanggal Akhir</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="status"><i class="fas fa-filter"></i> Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="semua" <?php echo $status_filter == 'semua' ? 'selected' : ''; ?>>Semua Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="diproses" <?php echo $status_filter == 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                <option value="selesai" <?php echo $status_filter == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                <option value="ditolak" <?php echo $status_filter == 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary-form">
                            <i class="fas fa-search"></i> Tampilkan
                        </button>
                    </div>
                </form>
            </div>

            <div class="grid-5">
                <div class="stat-card">Total: <strong><?php echo $total_komplain; ?></strong></div>
                <div class="stat-card">Pending: <strong><?php echo $pending; ?></strong></div>
                <div class="stat-card">Diproses: <strong><?php echo $diproses; ?></strong></div>
                <div class="stat-card">Selesai: <strong><?php echo $selesai; ?></strong></div>
                <div class="stat-card">Ditolak: <strong><?php echo $ditolak; ?></strong></div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h3>Kategori Komplain</h3>
                    <div style="max-width: 350px; margin: 0 auto;">
                        <canvas id="kategoriChart"></canvas>
                    </div>
                </div>
                <div class="card">
                    <h3>Prioritas Komplain</h3>
                    <div style="max-width: 350px; margin: 0 auto;">
                        <canvas id="prioritasChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Tren Harian</h3>
                <div style="max-width: 800px; margin: 0 auto;">
                <canvas id="trendChart"></canvas>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h3>Top 10 Pengguna</h3>
                    <table>
                        <tr><th>Nama</th><th>NIM</th><th>Jurusan</th><th>Total</th></tr>
                        <?php 
                        if(mysqli_num_rows($top_users) > 0) {
                            while($row = mysqli_fetch_assoc($top_users)): 
                        ?>
                            <tr>
                                <td><?php echo $row['nama']; ?></td>
                                <td><?php echo $row['nim']; ?></td>
                                <td><?php echo $row['jurusan']; ?></td>
                                <td><?php echo $row['total']; ?></td>
                            </tr>
                        <?php 
                            endwhile;
                        } else {
                            echo '<tr><td colspan="4" style="text-align:center;">Tidak ada data</td></tr>';
                        }
                        ?>
                    </table>
                </div>
                <div class="card">
                    <h3>Respon Tercepat / Terlambat</h3>
                    <table>
                        <tr><th>Judul</th><th>Submit</th><th>First Response</th><th>Jam</th></tr>
                        <?php 
                        if(mysqli_num_rows($response_time) > 0) {
                            while($row = mysqli_fetch_assoc($response_time)): 
                        ?>
                            <tr>
                                <td><?php echo $row['judul']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['tanggal_submit'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['first_response'])); ?></td>
                                <td><?php echo $row['response_hours']; ?> jam</td>
                            </tr>
                        <?php 
                            endwhile;
                        } else {
                            echo '<tr><td colspan="4" style="text-align:center;">Tidak ada data</td></tr>';
                        }
                        ?>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3>Rata-rata Waktu Penyelesaian</h3>
                <p><strong><?php echo $avg_resolution ? round($avg_resolution,2) . ' hari' : '0 hari'; ?></strong></p>
            </div>

            <div class="card">
                <div style="margin-bottom: 1rem;">
                    <h3 style="margin-bottom: 10px;">📋 Pilih Komplain untuk Dicetak</h3>
                    <div class="info-guide">
                        <i class="fas fa-lightbulb"></i>
                        <strong>Panduan:</strong> Centang komplain yang ingin dicetak pada tabel di bawah, lalu klik tombol "Cetak Terpilih" untuk menghasilkan laporan PDF.
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; gap: 10px;">
                        <button type="button" onclick="selectAll()" class="btn btn-primary-form">
                            <i class="fas fa-check-double"></i> Pilih Semua
                        </button>
                        <button type="button" onclick="deselectAll()" class="btn btn-primary-form">
                            <i class="fas fa-times"></i> Batal Pilih
                        </button>
                    </div>
                    <button type="button" onclick="cetakTerpilih()" class="btn-cetak-terpilih" id="btnCetak" disabled>
                        <i class="fas fa-print"></i> Cetak Terpilih (<span id="countSelected">0</span>)
                    </button>
                </div>

                <div class="selection-info" id="selectionInfo">
                    <i class="fas fa-check-circle" style="color: #2196F3;"></i> 
                    <span id="infoText">Belum ada komplain yang dipilih</span>
                </div>

                <form id="formCetak" method="POST" action="cetak_laporan.php" target="_blank">
                    <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                    <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                    <input type="hidden" name="status_filter" value="<?php echo $status_filter; ?>">
                    
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th class="checkbox-col">
                                        <input type="checkbox" id="checkAll" onchange="toggleAll(this)" title="Pilih Semua">
                                    </th>
                                    <th>Tanggal</th>
                                    <th>Mahasiswa</th>
                                    <th>NIM</th>
                                    <th>Judul</th>
                                    <th>Kategori</th>
                                    <th>Prioritas</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($komplain_list) > 0): ?>
                                    <?php while($row = mysqli_fetch_assoc($komplain_list)): ?>
                                        <tr>
                                            <td class="checkbox-col">
                                                <input type="checkbox" name="komplain_ids[]" value="<?php echo $row['id']; ?>" class="komplain-checkbox" onchange="updateCount()">
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($row['tanggal_submit'])); ?></td>
                                            <td><?php echo $row['nama']; ?></td>
                                            <td><?php echo $row['nim']; ?></td>
                                            <td><?php echo $row['judul']; ?></td>
                                            <td><?php echo ucfirst($row['kategori']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $row['prioritas']; ?>">
                                                    <?php echo ucfirst($row['prioritas']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $row['status']; ?>">
                                                    <?php echo ucfirst($row['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 2rem;">
                                            <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                            <p style="margin-top: 1rem; color: #999;">Tidak ada data komplain sesuai filter yang dipilih</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </section>

<script>
    // Kategori Chart
    <?php mysqli_data_seek($kategori_result, 0); ?>
    const kategoriLabels = [<?php while($k = mysqli_fetch_assoc($kategori_result)) { echo "'".ucfirst($k['kategori'])."',"; } ?>];
    <?php mysqli_data_seek($kategori_result, 0); ?>
    const kategoriValues = [<?php while($k = mysqli_fetch_assoc($kategori_result)) { echo $k['total'].","; } ?>];
    
    if(kategoriLabels.length > 0) {
        const kategoriData = {
            labels: kategoriLabels,
            datasets: [{
                data: kategoriValues,
                backgroundColor: ['#4CAF50','#FFC107','#F44336','#2196F3','#9C27B0','#00BCD4','#FF9800']
            }]
        };
        new Chart(document.getElementById('kategoriChart'), { 
            type:'pie', 
            data:kategoriData,
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Prioritas Chart
    <?php mysqli_data_seek($prioritas_result, 0); ?>
    const prioritasLabels = [<?php while($p = mysqli_fetch_assoc($prioritas_result)) { echo "'".ucfirst($p['prioritas'])."',"; } ?>];
    <?php mysqli_data_seek($prioritas_result, 0); ?>
    const prioritasValues = [<?php while($p = mysqli_fetch_assoc($prioritas_result)) { echo $p['total'].","; } ?>];
    
    if(prioritasLabels.length > 0) {
        const prioritasData = {
            labels: prioritasLabels,
            datasets: [{
                data: prioritasValues,
                backgroundColor: ['#F44336','#FFC107','#4CAF50']
            }]
        };
        new Chart(document.getElementById('prioritasChart'), { 
            type:'doughnut', 
            data:prioritasData,
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Tren Harian Chart
    <?php mysqli_data_seek($daily_trend, 0); ?>
    const trendLabels = [<?php while($d = mysqli_fetch_assoc($daily_trend)) { echo "'".date('d/m', strtotime($d['tanggal']))."',"; } ?>];
    <?php mysqli_data_seek($daily_trend, 0); ?>
    const trendValues = [<?php while($d = mysqli_fetch_assoc($daily_trend)) { echo $d['total'].","; } ?>];
    
    if(trendLabels.length > 0) {
        const trendData = {
            labels: trendLabels,
            datasets: [{
                label: 'Jumlah Komplain',
                data: trendValues,
                borderColor: '#2196F3',
                backgroundColor: 'rgba(33, 150, 243, 0.1)',
                fill: true,
                tension: 0.4
            }]
        };
        new Chart(document.getElementById('trendChart'), { 
            type:'line', 
            data:trendData,
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Checkbox Functions
    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.komplain-checkbox');
        checkboxes.forEach(checkbox => checkbox.checked = source.checked);
        updateCount();
    }

    function selectAll() {
        const checkboxes = document.querySelectorAll('.komplain-checkbox');
        checkboxes.forEach(checkbox => checkbox.checked = true);
        document.getElementById('checkAll').checked = true;
        updateCount();
    }

    function deselectAll() {
        const checkboxes = document.querySelectorAll('.komplain-checkbox');
        checkboxes.forEach(checkbox => checkbox.checked = false);
        document.getElementById('checkAll').checked = false;
        updateCount();
    }

    function updateCount() {
        const checked = document.querySelectorAll('.komplain-checkbox:checked').length;
        const total = document.querySelectorAll('.komplain-checkbox').length;
        
        document.getElementById('countSelected').textContent = checked;
        document.getElementById('btnCetak').disabled = checked === 0;
        
        const info = document.getElementById('selectionInfo');
        const infoText = document.getElementById('infoText');
        
        if(checked > 0) {
            info.classList.add('active');
            infoText.textContent = `${checked} dari ${total} komplain dipilih untuk dicetak`;
        } else {
            info.classList.remove('active');
        }
        
        // Update checkAll status
        document.getElementById('checkAll').checked = (checked === total && total > 0);
    }

    function cetakTerpilih() {
        const checked = document.querySelectorAll('.komplain-checkbox:checked').length;
        if(checked === 0) {
            alert('⚠️ Silakan pilih minimal 1 komplain untuk dicetak!');
            return false;
        }
        
        // Konfirmasi sebelum cetak
        if(confirm(`📄 Anda akan mencetak ${checked} komplain.\n\nLanjutkan?`)) {
            document.getElementById('formCetak').submit();
        }
        return false;
    }

    // Initialize count on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateCount();
    });
</script>
</body>
</html>