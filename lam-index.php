<?php
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses User & Riwayat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">📋 Akses User & Riwayat Akses</h1>

        <!-- 🔍 Tabel User -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                Daftar User
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Device</th>
                            <th>Nama</th>
                            <th>RFID</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT id, device, name, rfid FROM users");
                        $no = 1;
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                <td>{$no}</td>
                                <td>{$row['device']}</td>
                                <td>{$row['name']}</td>
                                <td>{$row['rfid']}</td>
                                <td>
                                    <a href='?user_id={$row['id']}' class='btn btn-info btn-sm'>Riwayat Akses</a>
                                </td>
                            </tr>";
                            $no++;
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 📅 Tabel Riwayat Akses -->
        <?php if (isset($_GET['user_id'])): ?>
        <div class="card">
            <div class="card-header bg-success text-white">
                Riwayat Akses User
            </div>
            <div class="card-body">
                <?php
                $user_id = intval($_GET['user_id']);
                $user_result = $conn->query("SELECT name FROM users WHERE id = $user_id");
                $user_name = $user_result->fetch_assoc()['name'];
                echo "<h5>🔍 Riwayat Akses untuk: $user_name</h5>";
                ?>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Waktu Akses</th>
                            <th>Metode</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $log_result = $conn->query("SELECT access_time, method, status FROM access_logs WHERE user_id = $user_id ORDER BY access_time DESC");
                        $no = 1;
                        while ($log = $log_result->fetch_assoc()) {
                            echo "<tr>
                                <td>{$no}</td>
                                <td>{$log['access_time']}</td>
                                <td>{$log['method']}</td>
                                <td>" . ($log['status'] == 'SUCCESS' ? '✅ SUCCESS' : '❌ FAILED') . "</td>
                            </tr>";
                            $no++;
                        }
                        ?>
                    </tbody>
                </table>
                <a href="index.php" class="btn btn-secondary">Kembali</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
</body>
</html>
