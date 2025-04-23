<?php
require_once 'config.php';

// Handle Add User form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $device = $_POST['device'] ?? '';
    $name = $_POST['name'] ?? '';
    $rfid = $_POST['rfid'] ?? '';
    $pin = $_POST['pin'] ?? '';

    if ($device && $name && $rfid && $pin) {
        $stmt = $conn->prepare("INSERT INTO users (device, name, rfid, pin, role) VALUES (?, ?, ?, ?, 'user')");
        $stmt->bind_param("ssss", $device, $name, $rfid, $pin);
        $stmt->execute();
        header("Location: index.php");
        exit;
    }
}

// Handle Delete User request
if (isset($_GET['delete_user_id'])) {
    $delete_user_id = intval($_GET['delete_user_id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $delete_user_id);
    $stmt->execute();
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Akses User & Riwayat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .table-hover tbody tr:hover {
            background-color: #e9f5ff;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #0056b3, #003d80);
        }
        .header-title {
            font-weight: 700;
            color: #0d6efd;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .pagination .page-link {
            color: #0d6efd;
        }
        .pagination .page-link:hover {
            background-color: #e9f5ff;
            color: #004085;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php">
                <i class="fa-solid fa-door-open me-2"></i>Smart Door Access
            </a>
        </div>
    </nav>

    <div class="container">
        <h1 class="mb-4 header-title">📋 Akses User & Riwayat Akses</h1>

        <!-- Form Tambah User -->
        <div class="card mb-4 p-4">
            <h5 class="mb-3 text-success fw-semibold">Tambah User Baru</h5>
            <form method="POST" action="index.php" class="row g-3">
                <input type="hidden" name="add_user" value="1" />
                <div class="col-md-3">
                    <label for="device" class="form-label">Device</label>
                    <input type="text" class="form-control" id="device" name="device" placeholder="alat1/alat2" required />
                </div>
                <div class="col-md-3">
                    <label for="name" class="form-label">Nama</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="Nama User" required />
                </div>
                <div class="col-md-3">
                    <label for="rfid" class="form-label">RFID</label>
                    <input type="text" class="form-control" id="rfid" name="rfid" placeholder="RFID" required />
                </div>
                <div class="col-md-3">
                    <label for="pin" class="form-label">PIN</label>
                    <input type="text" class="form-control" id="pin" name="pin" placeholder="PIN" required />
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-user-plus me-2"></i>Tambah User
                    </button>
                </div>
            </form>
        </div>

        <!-- 🔍 Tabel User -->
        <div class="card mb-4 p-4">
            <h5 class="mb-3 text-primary fw-semibold">Daftar User</h5>
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
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
                                <a href='?user_id={$row['id']}' class='btn btn-info btn-sm me-1' title='Riwayat Akses'>
                                    <i class='fa-solid fa-clock-rotate-left'></i>
                                </a>
                                <a href='?delete_user_id={$row['id']}' class='btn btn-danger btn-sm' title='Hapus User' onclick='return confirm(\"Yakin ingin menghapus user ini?\");'>
                                    <i class='fa-solid fa-trash'></i>
                                </a>
                            </td>
                        </tr>";
                        $no++;
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- 📅 Tabel Riwayat Akses -->
        <?php if (isset($_GET['user_id'])): ?>
        <div class="card p-4">
            <h5 class="mb-3 text-success fw-semibold">
                Riwayat Akses untuk: 
                <?php
                $user_id = intval($_GET['user_id']);
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $limit = 10;
                $offset = ($page - 1) * $limit;

                $user_result = $conn->query("SELECT name FROM users WHERE id = $user_id");
                $user_name = $user_result->fetch_assoc()['name'];
                echo htmlspecialchars($user_name);
                ?>
            </h5>

            <?php
            // Get total count for pagination
            $count_result = $conn->query("SELECT COUNT(*) as total FROM access_logs WHERE user_id = $user_id");
            $total_logs = $count_result->fetch_assoc()['total'];
            $total_pages = ceil($total_logs / $limit);

            $log_result = $conn->query("SELECT access_time, method, status FROM access_logs WHERE user_id = $user_id ORDER BY access_time DESC LIMIT $limit OFFSET $offset");
            $no = $offset + 1;
            ?>

            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Waktu Akses</th>
                        <th>Metode</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($log = $log_result->fetch_assoc()) {
                        echo "<tr>
                            <td>{$no}</td>
                            <td>{$log['access_time']}</td>
                            <td>{$log['method']}</td>
                            <td>" . ($log['status'] == 'SUCCESS' ? '<span class=\"text-success\">✅ SUCCESS</span>' : '<span class=\"text-danger\">❌ FAILED</span>') . "</td>
                        </tr>";
                        $no++;
                    }
                    ?>
                </tbody>
            </table>

            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="index.php?user_id=<?php echo $user_id; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="index.php?user_id=<?php echo $user_id; ?>&page=<?php echo $page + 1; ?>">Next</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <a href="index.php" class="btn btn-secondary">Kembali</a>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
</body>
</html>
