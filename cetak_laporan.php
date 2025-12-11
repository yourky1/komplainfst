<?php
require_once 'config.php';

if(!isLoggedIn() || !isAdmin()) {
    redirect('dashboard.php');
}

// Get selected complaint IDs
$komplain_ids = isset($_POST['komplain_ids']) ? $_POST['komplain_ids'] : [];
$start_date = isset($_POST['start_date']) ? clean($_POST['start_date']) : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? clean($_POST['end_date']) : date('Y-m-d');
$status_filter = isset($_POST['status_filter']) ? clean($_POST['status_filter']) : 'semua';

// Validate
if(empty($komplain_ids)) {
    echo '<!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Tidak Ada Data</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background: #f5f5f5;
            }
            .error-box {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 400px;
            }
            .error-icon {
                font-size: 60px;
                margin-bottom: 20px;
            }
            h2 {
                color: #333;
                margin-bottom: 10px;
            }
            p {
                color: #666;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            .btn {
                background: #2196F3;
                color: white;
                border: none;
                padding: 12px 30px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                text-decoration: none;
                display: inline-block;
            }
            .btn:hover {
                background: #1976D2;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <div class="error-icon">⚠️</div>
            <h2>Tidak Ada Data</h2>
            <p>Tidak ada komplain yang dipilih untuk dicetak.<br>Silakan kembali dan pilih minimal 1 komplain.</p>
            <button onclick="window.close()" class="btn">← Kembali</button>
        </div>
    </body>
    </html>';
    exit;
}

// Sanitize IDs
$ids_clean = array_map('intval', $komplain_ids);
$ids_string = implode(',', $ids_clean);

// Get selected complaints
$komplain_result = mysqli_query($conn, "
    SELECT k.*, u.nama, u.nim, u.jurusan, u.email 
    FROM komplain k 
    JOIN users u ON k.user_id = u.id 
    WHERE k.id IN ($ids_string)
    ORDER BY k.tanggal_submit DESC
");

// Statistics for selected complaints
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status='diproses' THEN 1 ELSE 0 END) as diproses,
        SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN status='ditolak' THEN 1 ELSE 0 END) as ditolak
    FROM komplain 
    WHERE id IN ($ids_string)
"));

// Category distribution
$kategori_result = mysqli_query($conn, "
    SELECT kategori, COUNT(*) as total 
    FROM komplain 
    WHERE id IN ($ids_string)
    GROUP BY kategori 
    ORDER BY total DESC
");

// Priority distribution
$prioritas_result = mysqli_query($conn, "
    SELECT prioritas, COUNT(*) as total 
    FROM komplain 
    WHERE id IN ($ids_string)
    GROUP BY prioritas 
    ORDER BY FIELD(prioritas, 'tinggi', 'sedang', 'rendah')
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Komplain FST - <?php echo date('d/m/Y'); ?></title>
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 15mm;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-after: always;
            }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            background: #f5f5f5;
        }
        
        .container {
            max-width: 297mm;
            margin: 0 auto;
            padding: 15mm;
            background: white;
        }
        
        /* Header */
        .header {
            text-align: center;
            border-bottom: 3px solid #2196F3;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 18pt;
            color: #2196F3;
            margin-bottom: 5px;
        }
        
        .header h2 {
            font-size: 14pt;
            color: #555;
            font-weight: normal;
            margin-bottom: 10px;
        }
        
        .header .info {
            font-size: 9pt;
            color: #777;
            margin-top: 8px;
            line-height: 1.6;
        }
        
        /* Statistics Summary */
        .summary {
            display: flex;
            justify-content: space-around;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: #f5f5f5;
            border-left: 4px solid #2196F3;
            padding: 12px 20px;
            text-align: center;
            flex: 1;
            min-width: 100px;
        }
        
        .stat-box.pending { border-left-color: #FFC107; }
        .stat-box.diproses { border-left-color: #2196F3; }
        .stat-box.selesai { border-left-color: #4CAF50; }
        .stat-box.ditolak { border-left-color: #F44336; }
        
        .stat-box .label {
            font-size: 8pt;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .stat-box .value {
            font-size: 20pt;
            font-weight: bold;
            color: #333;
        }
        
        /* Distribution boxes */
        .distribution {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .dist-box {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 15px;
        }
        
        .dist-box h3 {
            font-size: 11pt;
            color: #2196F3;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .dist-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .dist-item:last-child {
            border-bottom: none;
        }
        
        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 8.5pt;
        }
        
        table th {
            background: #2196F3;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
        }
        
        table td {
            padding: 8px 6px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: top;
        }
        
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending { background: #FFF3CD; color: #856404; }
        .status-diproses { background: #CCE5FF; color: #004085; }
        .status-selesai { background: #D4EDDA; color: #155724; }
        .status-ditolak { background: #F8D7DA; color: #721C24; }
        
        /* Priority Badge */
        .priority-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .priority-tinggi { background: #FFEBEE; color: #C62828; }
        .priority-sedang { background: #FFF8E1; color: #F57C00; }
        .priority-rendah { background: #E8F5E9; color: #2E7D32; }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            font-size: 8pt;
            color: #777;
        }
        
        /* Buttons */
        .btn-container {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .btn {
            background: #2196F3;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11pt;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background: #1976D2;
        }
        
        .btn-secondary {
            background: #666;
        }
        
        .btn-secondary:hover {
            background: #555;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #2196F3;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #e0e0e0;
        }

        .deskripsi-text {
            max-width: 300px;
            white-space: normal;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="btn-container no-print">
        <button onclick="window.close()" class="btn btn-secondary">
            ← Tutup
        </button>
        <button onclick="window.print()" class="btn">
            🖨️ Cetak PDF
        </button>
    </div>
    
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>LAPORAN KOMPLAIN MAHASISWA</h1>
            <h2>Fakultas Sains dan Teknologi</h2>
            <div class="info">
                <strong>Periode Filter:</strong> <?php echo date('d F Y', strtotime($start_date)); ?> s/d <?php echo date('d F Y', strtotime($end_date)); ?>
                <?php if($status_filter != 'semua'): ?>
                    | <strong>Status:</strong> <?php echo ucfirst($status_filter); ?>
                <?php endif; ?>
            </div>
            <div class="info">
                <strong>Jumlah Komplain Terpilih:</strong> <?php echo count($komplain_ids); ?> komplain
            </div>
            <div class="info">
                Dicetak pada: <?php echo date('d F Y, H:i'); ?> WIB oleh <strong><?php echo $_SESSION['nama']; ?></strong>
            </div>
        </div>
        
        <!-- Statistics Summary -->
        <div class="summary">
            <div class="stat-box">
                <div class="label">Total</div>
                <div class="value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-box pending">
                <div class="label">Pending</div>
                <div class="value"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-box diproses">
                <div class="label">Diproses</div>
                <div class="value"><?php echo $stats['diproses']; ?></div>
            </div>
            <div class="stat-box selesai">
                <div class="label">Selesai</div>
                <div class="value"><?php echo $stats['selesai']; ?></div>
            </div>
            <div class="stat-box ditolak">
                <div class="label">Ditolak</div>
                <div class="value"><?php echo $stats['ditolak']; ?></div>
            </div>
        </div>
        
        <!-- Distribution -->
        <div class="distribution">
            <div class="dist-box">
                <h3>📊 Distribusi Kategori</h3>
                <?php if(mysqli_num_rows($kategori_result) > 0): ?>
                    <?php while($k = mysqli_fetch_assoc($kategori_result)): ?>
                        <div class="dist-item">
                            <span><?php echo ucfirst($k['kategori']); ?></span>
                            <strong><?php echo $k['total']; ?> komplain</strong>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="dist-item"><span>Tidak ada data</span></div>
                <?php endif; ?>
            </div>
            
            <div class="dist-box">
                <h3>⚡ Distribusi Prioritas</h3>
                <?php if(mysqli_num_rows($prioritas_result) > 0): ?>
                    <?php while($p = mysqli_fetch_assoc($prioritas_result)): ?>
                        <div class="dist-item">
                            <span><?php echo ucfirst($p['prioritas']); ?></span>
                            <strong><?php echo $p['total']; ?> komplain</strong>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="dist-item"><span>Tidak ada data</span></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Detailed Complaint List -->
        <div class="section-title">📋 Detail Komplain yang Dipilih</div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 3%;">No</th>
                    <th style="width: 10%;">Tanggal Submit</th>
                    <th style="width: 12%;">Mahasiswa</th>
                    <th style="width: 8%;">NIM</th>
                    <th style="width: 10%;">Jurusan</th>
                    <th style="width: 20%;">Judul Komplain</th>
                    <th style="width: 10%;">Kategori</th>
                    <th style="width: 8%;">Prioritas</th>
                    <th style="width: 8%;">Status</th>
                    <th style="width: 11%;">Tanggal Update</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                mysqli_data_seek($komplain_result, 0);
                while($row = mysqli_fetch_assoc($komplain_result)): 
                ?>
                    <tr>
                        <td style="text-align: center;"><?php echo $no++; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['tanggal_submit'])); ?></td>
                        <td><?php echo $row['nama']; ?></td>
                        <td><?php echo $row['nim']; ?></td>
                        <td><?php echo $row['jurusan']; ?></td>
                        <td class="deskripsi-text"><?php echo $row['judul']; ?></td>
                        <td><?php echo ucfirst($row['kategori']); ?></td>
                        <td>
                            <span class="priority-badge priority-<?php echo $row['prioritas']; ?>">
                                <?php echo ucfirst($row['prioritas']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $row['tanggal_update'] ? date('d/m/Y H:i', strtotime($row['tanggal_update'])) : '-'; ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>Sistem Komplain Mahasiswa FST</strong></p>
            <p>Dokumen ini dicetak secara otomatis oleh sistem | Data yang ditampilkan adalah komplain yang dipilih oleh admin</p>
            <p style="margin-top: 10px;">
                <strong>Catatan:</strong> Laporan ini bersifat rahasia dan hanya untuk keperluan internal Fakultas Sains dan Teknologi
            </p>
        </div>
    </div>

    <script>
        // Auto focus on print button when page loads
        window.onload = function() {
            // Optional: Auto open print dialog
            // setTimeout(function() { window.print(); }, 500);
        }
    </script>
</body>
</html>